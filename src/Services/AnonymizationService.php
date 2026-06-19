<?php

/**
 * AnonymizationService — applies field-level anonymization strategies to Eloquent models.
 *
 * Looks up the model's columns against configured field patterns
 * (`artisanpack.privacy.discovery.field_patterns`), maps each match to a
 * strategy (`artisanpack.privacy.anonymization.strategies.{type}`), and
 * writes the transformed value back to the model.
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

use Illuminate\Database\Eloquent\Model;

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

	/**
	 * Applies the configured strategies to every recognised personal-data
	 * field on the model and persists the result.
	 *
	 * Returns true when at least one field was mutated and the model was
	 * saved, false when nothing matched (so callers can warn about a no-op
	 * anonymization).
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $model Model to anonymize in place.
	 *
	 * @return bool
	 */
	public function anonymize( Model $model ): bool
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

		if ( $mutated ) {
			$model->save();
		}

		return $mutated;
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
			self::STRATEGY_MASK         => $this->mask( $value ),
			self::STRATEGY_REDACT       => '[REDACTED]',
			self::STRATEGY_HASH         => hash( $this->hashAlgorithm(), $value ),
			self::STRATEGY_TRUNCATE     => $this->truncate( $value, $type ),
			self::STRATEGY_GENERALIZE   => $this->generalize( $value, $type ),
			self::STRATEGY_PSEUDONYMIZE => $this->pseudonymize( $value ),
			default                     => '[REDACTED]',
		};
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
}
