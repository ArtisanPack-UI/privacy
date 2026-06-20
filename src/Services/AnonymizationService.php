<?php

/**
 * AnonymizationService — applies field-level anonymization strategies to Eloquent models.
 *
 * Looks up the model's columns against configured field patterns
 * (`artisanpack.privacy.discovery.field_patterns`), maps each match to a
 * strategy (`artisanpack.privacy.anonymization.strategies.{type}`), and
 * writes the transformed value back to the model.
 *
 * Supports explicit field lists, batch processing, audit logging, and
 * reversible pseudonymization via Laravel's encrypter.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Pure-PHP anonymization service.
 *
 * @since 1.0.0
 */
class AnonymizationService
{
	public const STRATEGY_MASK         = 'mask';
	public const STRATEGY_REDACT       = 'redact';
	public const STRATEGY_HASH         = 'hash';
	public const STRATEGY_TRUNCATE     = 'truncate';
	public const STRATEGY_GENERALIZE   = 'generalize';
	public const STRATEGY_PSEUDONYMIZE = 'pseudonymize';

	public const STRATEGY_REVERSIBLE_PSEUDONYMIZE = 'reversible_pseudonymize';

	public const REVERSIBLE_PREFIX = 'rpsn:';

	/**
	 * Applies anonymization strategies to a model and persists the result.
	 *
	 * When `$fields` is null the service uses pattern-based field discovery
	 * via `artisanpack.privacy.discovery.field_patterns`. When `$fields` is
	 * provided as `['column' => 'strategy', ...]` each column is anonymized
	 * with the explicit strategy. As a shorthand, a numeric list of column
	 * names will fall back to the column's configured strategy resolved by
	 * the column's matched data-type.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model                            $model  Model to anonymize in place.
	 * @param  array<int|string, string>|null   $fields Explicit field map, list of columns, or null for discovery.
	 *
	 * @return bool True when at least one column was mutated.
	 */
	public function anonymize( Model $model, ?array $fields = null ): bool
	{
		if ( null === $fields ) {
			$mutated = $this->anonymizeByPatterns( $model );
			$touched = $this->columnsTouchedByPatterns( $model );
		} else {
			$map     = $this->resolveFieldMap( $fields );
			$mutated = $this->anonymizeFields( $model, $map );
			$touched = array_keys( $map );
		}

		if ( $mutated ) {
			$model->save();
			$this->logAudit( $model, $touched );
		}

		return $mutated;
	}

	/**
	 * Applies anonymization to every model in a collection or iterable.
	 *
	 * Returns the count of records that were actually mutated and saved;
	 * records with no matching fields are skipped silently.
	 *
	 * @since 1.0.0
	 *
	 * @param  iterable<int, Model>             $models  Models to anonymize.
	 * @param  array<int|string, string>|null   $fields  Explicit field map or null for discovery.
	 *
	 * @return int Count of records anonymized and saved.
	 */
	public function anonymizeBatch( iterable $models, ?array $fields = null ): int
	{
		$count = 0;

		foreach ( $models as $model ) {
			if ( $this->anonymize( $model, $fields ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Applies a strategy to a single value (without touching a model).
	 *
	 * Alias kept for the AC contract from issue #19.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $value    Original value.
	 * @param  string  $strategy Strategy key.
	 * @param  string  $type     Optional data-type hint.
	 *
	 * @return string
	 */
	public function anonymizeField( string $value, string $strategy, string $type = '' ): string
	{
		return $this->applyStrategy( $value, $strategy, $type );
	}

	/**
	 * Applies a single strategy to a single value.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $value    Original value.
	 * @param  string  $strategy Strategy key.
	 * @param  string  $type     Data-type hint (used by generalize/truncate).
	 *
	 * @return string
	 */
	public function applyStrategy( string $value, string $strategy, string $type = '' ): string
	{
		return match ( $strategy ) {
			self::STRATEGY_MASK                     => $this->mask( $value ),
			self::STRATEGY_REDACT                   => '[REDACTED]',
			self::STRATEGY_HASH                     => hash( $this->hashAlgorithm(), $value ),
			self::STRATEGY_TRUNCATE                 => $this->truncate( $value, $type ),
			self::STRATEGY_GENERALIZE               => $this->generalize( $value, $type ),
			self::STRATEGY_PSEUDONYMIZE             => $this->pseudonymize( $value ),
			self::STRATEGY_REVERSIBLE_PSEUDONYMIZE  => $this->reversiblePseudonymize( $value ),
			default                                 => '[REDACTED]',
		};
	}

	/**
	 * Returns the original value for a reversible pseudonym, or null if the
	 * value is not a recognized reversible pseudonym.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $value Pseudonymized value (must start with REVERSIBLE_PREFIX).
	 *
	 * @return string|null
	 */
	public function reverse( string $value ): ?string
	{
		if ( ! str_starts_with( $value, self::REVERSIBLE_PREFIX ) ) {
			return null;
		}

		$payload = substr( $value, strlen( self::REVERSIBLE_PREFIX ) );

		try {
			return Crypt::decryptString( $payload );
		} catch ( DecryptException ) {
			return null;
		}
	}

	/**
	 * Anonymizes a model using pattern-based discovery (legacy behaviour).
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $model Model to anonymize in place.
	 *
	 * @return bool
	 */
	protected function anonymizeByPatterns( Model $model ): bool
	{
		$strategies = (array) config( 'artisanpack.privacy.anonymization.strategies', [] );
		$mutated    = false;

		foreach ( $strategies as $type => $strategy ) {
			$column = $this->findColumnForType( $model, (string) $type );

			if ( null === $column ) {
				continue;
			}

			$value = $model->getAttribute( $column );

			if ( null === $value || '' === $value ) {
				continue;
			}

			$model->setAttribute( $column, $this->applyStrategy( (string) $value, (string) $strategy, (string) $type ) );
			$mutated = true;
		}

		return $mutated;
	}

	/**
	 * Returns the list of columns the pattern-driven path actually mutated
	 * (used for audit logging).
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $model Model that was just mutated.
	 *
	 * @return array<int, string>
	 */
	protected function columnsTouchedByPatterns( Model $model ): array
	{
		return array_values( array_keys( $model->getDirty() ) );
	}

	/**
	 * Applies the resolved column/strategy map to the model.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model                  $model Model to mutate.
	 * @param  array<string, string>  $map   Column => strategy map.
	 *
	 * @return bool
	 */
	protected function anonymizeFields( Model $model, array $map ): bool
	{
		$mutated = false;

		foreach ( $map as $column => $strategy ) {
			$value = $model->getAttribute( $column );

			if ( null === $value || '' === $value ) {
				continue;
			}

			$type = $this->guessTypeForColumn( $column );

			$model->setAttribute( $column, $this->applyStrategy( (string) $value, $strategy, $type ) );
			$mutated = true;
		}

		return $mutated;
	}

	/**
	 * Normalises the `$fields` argument into a `column => strategy` map.
	 *
	 * Numeric entries are resolved against the configured per-type strategy
	 * via the discovery patterns; associative entries are passed through.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int|string, string>  $fields Field argument from caller.
	 *
	 * @return array<string, string>
	 */
	protected function resolveFieldMap( array $fields ): array
	{
		$map = [];

		foreach ( $fields as $key => $value ) {
			if ( is_int( $key ) ) {
				$column   = (string) $value;
				$type     = $this->guessTypeForColumn( $column );
				$strategy = (string) config(
					"artisanpack.privacy.anonymization.strategies.{$type}",
					self::STRATEGY_REDACT,
				);

				$map[ $column ] = $strategy;
				continue;
			}

			$map[ (string) $key ] = (string) $value;
		}

		return $map;
	}

	/**
	 * Reverse-resolves a column name to a configured data-type key via the
	 * discovery patterns. Falls back to the column name itself when no
	 * pattern matches.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $column Column name.
	 *
	 * @return string
	 */
	protected function guessTypeForColumn( string $column ): string
	{
		$patterns = (array) config( 'artisanpack.privacy.discovery.field_patterns', [] );

		foreach ( $patterns as $type => $columns ) {
			if ( in_array( $column, (array) $columns, true ) ) {
				return (string) $type;
			}
		}

		return $column;
	}

	/**
	 * Masks a string preserving its first character of the local-part and
	 * domain (for email-shaped values) or first/last characters otherwise.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $value Original value.
	 *
	 * @return string
	 */
	protected function mask( string $value ): string
	{
		if ( str_contains( $value, '@' ) ) {
			[ $local, $domain ] = explode( '@', $value, 2 );
			$domainParts        = explode( '.', $domain );
			$domainHead         = array_shift( $domainParts );

			return sprintf(
				'%s***@%s***%s',
				mb_substr( $local, 0, 1 ),
				mb_substr( (string) $domainHead, 0, 1 ),
				[] === $domainParts ? '' : '.' . implode( '.', $domainParts ),
			);
		}

		$length = mb_strlen( $value );

		if ( $length <= 2 ) {
			return str_repeat( '*', $length );
		}

		return mb_substr( $value, 0, 1 ) . str_repeat( '*', $length - 2 ) . mb_substr( $value, -1 );
	}

	/**
	 * Truncates IP addresses (last octet/group) and falls back to keeping
	 * the first character of arbitrary strings.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $value Original value.
	 * @param  string  $type  Data-type hint.
	 *
	 * @return string
	 */
	protected function truncate( string $value, string $type ): string
	{
		if ( 'ip' === $type || 'ip_address' === $type ) {
			if ( str_contains( $value, '.' ) ) {
				$parts = explode( '.', $value );

				if ( 4 === count( $parts ) ) {
					$parts[3] = '0';

					return implode( '.', $parts );
				}
			}

			if ( str_contains( $value, ':' ) ) {
				$parts = explode( ':', $value );
				$keep  = array_slice( $parts, 0, max( 1, count( $parts ) - 1 ) );

				return implode( ':', $keep ) . '::';
			}
		}

		return mb_substr( $value, 0, 1 ) . '***';
	}

	/**
	 * Generalises a value (year-only for date_of_birth, otherwise the first token).
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $value Original value.
	 * @param  string  $type  Data-type hint.
	 *
	 * @return string
	 */
	protected function generalize( string $value, string $type ): string
	{
		if ( 'date_of_birth' === $type || 'date' === $type ) {
			$timestamp = strtotime( $value );

			return false === $timestamp ? '[REDACTED]' : (string) date( 'Y', $timestamp );
		}

		$parts = preg_split( '/[,\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY );

		return false === $parts || [] === $parts ? '[REDACTED]' : (string) $parts[0];
	}

	/**
	 * Replaces the value with a stable pseudonym derived from a hash.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $value Original value.
	 *
	 * @return string
	 */
	protected function pseudonymize( string $value ): string
	{
		$prefix = (string) config( 'artisanpack.privacy.anonymization.pseudonymization_prefix', 'Anon_' );

		return $prefix . mb_substr( hash( $this->hashAlgorithm(), $value ), 0, 12 );
	}

	/**
	 * Produces a reversible pseudonym using Laravel's encrypter. The original
	 * value can be recovered via {@see reverse()}.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $value Original value.
	 *
	 * @return string
	 */
	protected function reversiblePseudonymize( string $value ): string
	{
		return self::REVERSIBLE_PREFIX . Crypt::encryptString( $value );
	}

	/**
	 * Finds the first column on the model that matches one of the
	 * configured patterns for the given data type.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model   $model Model to inspect.
	 * @param  string  $type  Data type key (e.g. `email`, `name`, `phone`).
	 *
	 * @return string|null
	 */
	protected function findColumnForType( Model $model, string $type ): ?string
	{
		$patterns = (array) config( "artisanpack.privacy.discovery.field_patterns.{$type}", [ $type ] );
		$columns  = array_keys( $model->getAttributes() );

		foreach ( $patterns as $pattern ) {
			if ( in_array( $pattern, $columns, true ) ) {
				return (string) $pattern;
			}
		}

		return null;
	}

	/**
	 * Configured hashing algorithm for hash/pseudonymize strategies.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function hashAlgorithm(): string
	{
		return (string) config( 'artisanpack.privacy.anonymization.hash_algorithm', 'sha256' );
	}

	/**
	 * Emits an audit-log entry describing which model/columns were anonymized.
	 *
	 * Records column names only — never the original or transformed values —
	 * so audit logs remain safe to persist under regulatory retention rules.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model               $model    Model that was mutated.
	 * @param  array<int, string>  $columns  Columns that were touched.
	 *
	 * @return void
	 */
	protected function logAudit( Model $model, array $columns ): void
	{
		if ( true !== (bool) config( 'artisanpack.privacy.anonymization.audit', true ) ) {
			return;
		}

		Log::info( 'privacy.anonymization', [
			'model'   => $model::class,
			'key'     => $model->getKey(),
			'columns' => $columns,
		] );
	}
}
