<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Database\Factories;

use ArtisanPackUI\Privacy\Models\PersonalDataMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for the {@see PersonalDataMap} model.
 *
 * @extends Factory<PersonalDataMap>
 *
 * @since 1.0.0
 */
class PersonalDataMapFactory extends Factory
{
	/**
	 * The model the factory creates.
	 *
	 * @var class-string<PersonalDataMap>
	 */
	protected $model = PersonalDataMap::class;

	/**
	 * Default attributes.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		$dataType = fake()->randomElement( [ 'email', 'name', 'phone', 'address', 'ip' ] );

		return [
			'model'             => 'App\\Models\\User',
			'field'             => fake()->unique()->word(),
			'data_type'         => $dataType,
			'sensitivity'       => PersonalDataMap::SENSITIVITY_NORMAL,
			'retention_period'  => '1 year',
			'deletion_strategy' => PersonalDataMap::STRATEGY_ANONYMIZE,
			'export_format'     => null,
			'auto_discovered'   => false,
		];
	}
}
