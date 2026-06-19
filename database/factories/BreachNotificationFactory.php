<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Database\Factories;

use ArtisanPackUI\Privacy\Models\BreachNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for the {@see BreachNotification} model.
 *
 * @extends Factory<BreachNotification>
 *
 * @since 1.0.0
 */
class BreachNotificationFactory extends Factory
{
	/**
	 * The model the factory creates.
	 *
	 * @var class-string<BreachNotification>
	 */
	protected $model = BreachNotification::class;

	/**
	 * Default attributes.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		return [
			'reference_number'    => 'BR-' . Str::upper( Str::random( 8 ) ),
			'discovered_at'       => now()->subHours( fake()->numberBetween( 1, 48 ) ),
			'occurred_at'         => now()->subDays( fake()->numberBetween( 1, 14 ) ),
			'severity'            => fake()->randomElement( [
				BreachNotification::SEVERITY_LOW,
				BreachNotification::SEVERITY_MEDIUM,
				BreachNotification::SEVERITY_HIGH,
				BreachNotification::SEVERITY_CRITICAL,
			] ),
			'description'         => fake()->paragraph(),
			'data_types_affected' => [ 'email', 'name' ],
			'records_affected'    => fake()->numberBetween( 1, 10000 ),
			'status'              => BreachNotification::STATUS_INVESTIGATING,
		];
	}
}
