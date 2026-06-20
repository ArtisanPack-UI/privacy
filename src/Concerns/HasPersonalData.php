<?php

/**
 * HasPersonalData trait — marks an Eloquent model as carrying personal data
 * the privacy package can collect for export, anonymization, or deletion.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Concerns;

use ArtisanPackUI\Privacy\Services\AnonymizationService;
use ArtisanPackUI\Privacy\Services\DataDeletionService;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Apply this trait to any model whose columns should appear in data subject
 * access/export results and that the deletion service should consider when
 * cascading.
 *
 * Models can declare fields either as a plain list of column names —
 *
 *   protected array $personalDataFields = [ 'email', 'name' ];
 *
 * or as a metadata map keyed by column —
 *
 *   protected function personalDataFields(): array
 *   {
 *       return [
 *           'email' => [
 *               'type'              => 'email',
 *               'sensitivity'       => 'normal',
 *               'deletion_strategy' => 'anonymize',
 *           ],
 *       ];
 *   }
 *
 * @since 1.0.0
 */
trait HasPersonalData
{
	/**
	 * Field descriptors for the personal data on this model.
	 *
	 * Override on the consuming model — either as a property or by overriding
	 * this method.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int|string, mixed>
	 */
	public function personalDataFields(): array
	{
		return property_exists( $this, 'personalDataFields' ) ? (array) $this->personalDataFields : [];
	}

	/**
	 * Returns just the column names from {@see personalDataFields()},
	 * normalising both the plain-list and metadata-map shapes.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function personalDataFieldNames(): array
	{
		$names = [];

		foreach ( $this->personalDataFields() as $key => $value ) {
			$names[] = is_string( $key ) ? $key : (string) $value;
		}

		return $names;
	}

	/**
	 * Returns the metadata map for a single field, or an empty array when
	 * the field was declared as a plain name without metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $field Column name to look up.
	 *
	 * @return array<string, mixed>
	 */
	public function personalDataFieldMetadata( string $field ): array
	{
		$fields = $this->personalDataFields();

		if ( array_key_exists( $field, $fields ) && is_array( $fields[ $field ] ) ) {
			return $fields[ $field ];
		}

		return [];
	}

	/**
	 * Relations the privacy services should cascade across (export,
	 * deletion, anonymization). Override to opt in.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function personalDataRelations(): array
	{
		return property_exists( $this, 'personalDataRelations' ) ? (array) $this->personalDataRelations : [];
	}

	/**
	 * Returns the model's personal data as a column → value map.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getPersonalData(): array
	{
		$data = [];

		foreach ( $this->personalDataFieldNames() as $field ) {
			$data[ $field ] = $this->getAttribute( $field );
		}

		return $data;
	}

	/**
	 * Alias for {@see getPersonalData()} retained for backward compatibility
	 * with the original 1.0 API.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function toPersonalDataArray(): array
	{
		return $this->getPersonalData();
	}

	/**
	 * Returns the personal-data payload for each related record declared in
	 * {@see personalDataRelations()}.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function toPersonalDataRelationArray(): array
	{
		$out = [];

		foreach ( $this->personalDataRelations() as $relation ) {
			if ( ! method_exists( $this, $relation ) ) {
				continue;
			}

			$query = $this->{$relation}();

			if ( ! $query instanceof Relation ) {
				continue;
			}

			$out[ $relation ] = $query->get()
				->map( static function ( $record ) {
					return method_exists( $record, 'toPersonalDataArray' )
						? $record->toPersonalDataArray()
						: $record->toArray();
				} )
				->all();
		}

		return $out;
	}

	/**
	 * Anonymizes the model's declared personal-data fields using the
	 * per-field metadata when available, falling back to the
	 * {@see AnonymizationService} defaults otherwise.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when at least one field was mutated.
	 */
	public function anonymizePersonalData(): bool
	{
		$service = app( AnonymizationService::class );
		$map     = $this->buildAnonymizationMap();

		if ( [] === $map ) {
			return $service->anonymize( $this );
		}

		return $service->anonymize( $this, $map );
	}

	/**
	 * Applies the deletion strategy declared by the model's personal-data
	 * configuration.
	 *
	 * When no per-model strategy is declared, the package falls back to the
	 * configured `deletion.default_strategy`. Cascades across the relations
	 * returned by {@see personalDataRelations()}.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $options Override flags forwarded to
	 *                                         {@see DataDeletionService::delete()}.
	 *
	 * @return bool
	 */
	public function deletePersonalData( array $options = [] ): bool
	{
		$service = app( DataDeletionService::class );

		if ( ! array_key_exists( 'strategy', $options ) ) {
			$strategy = $this->resolvePreferredDeletionStrategy();

			if ( null !== $strategy ) {
				$options['strategy'] = $strategy;
			}
		}

		return $service->delete( $this, $options );
	}

	/**
	 * Builds the field → strategy map used by the anonymization service from
	 * the per-field metadata. Returns an empty array when no field declares
	 * an explicit strategy, in which case the service uses its defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function buildAnonymizationMap(): array
	{
		$map = [];

		foreach ( $this->personalDataFieldNames() as $field ) {
			$metadata = $this->personalDataFieldMetadata( $field );
			$strategy = $metadata['anonymization_strategy'] ?? $metadata['strategy'] ?? null;

			if ( null === $strategy && isset( $metadata['type'] ) ) {
				$strategy = config( "artisanpack.privacy.anonymization.strategies.{$metadata['type']}" );
			}

			if ( is_string( $strategy ) && '' !== $strategy ) {
				$map[ $field ] = $strategy;
			}
		}

		return $map;
	}

	/**
	 * Returns the dominant deletion strategy declared by the model's fields.
	 *
	 * When every field declaring a `deletion_strategy` agrees, that value is
	 * returned. When they disagree, the most common one wins (ties resolved
	 * by first occurrence). Returns null when no field declares a strategy.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	protected function resolvePreferredDeletionStrategy(): ?string
	{
		$tally = [];
		$order = [];

		foreach ( $this->personalDataFieldNames() as $field ) {
			$metadata = $this->personalDataFieldMetadata( $field );
			$strategy = $metadata['deletion_strategy'] ?? null;

			if ( ! is_string( $strategy ) || '' === $strategy ) {
				continue;
			}

			if ( ! array_key_exists( $strategy, $tally ) ) {
				$tally[ $strategy ] = 0;
				$order[]            = $strategy;
			}

			++$tally[ $strategy ];
		}

		if ( [] === $tally ) {
			return null;
		}

		$best  = null;
		$count = -1;

		foreach ( $order as $strategy ) {
			if ( $tally[ $strategy ] > $count ) {
				$best  = $strategy;
				$count = $tally[ $strategy ];
			}
		}

		return $best;
	}
}
