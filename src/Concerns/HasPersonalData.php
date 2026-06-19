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

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Apply this trait to any model whose columns should appear in data subject
 * access/export results and that the deletion service should consider when
 * cascading.
 *
 * Models can either declare `protected array $personalDataFields = []`
 * directly, or override {@see personalDataFields()} for dynamic discovery.
 *
 * @since 1.0.0
 */
trait HasPersonalData
{
	/**
	 * Columns whose values represent personal data for this model.
	 *
	 * Override on the consuming model — either as a property or by
	 * overriding {@see personalDataFields()}.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function personalDataFields(): array
	{
		return property_exists( $this, 'personalDataFields' ) ? (array) $this->personalDataFields : [];
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
	public function toPersonalDataArray(): array
	{
		$data = [];

		foreach ( $this->personalDataFields() as $field ) {
			$data[ $field ] = $this->getAttribute( $field );
		}

		return $data;
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
}
