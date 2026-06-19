<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Database\Factories;

use ArtisanPackUI\Privacy\Models\Consent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for the {@see Consent} model.
 *
 * @extends Factory<Consent>
 *
 * @since 1.0.0
 */
class ConsentFactory extends Factory
{
	/**
	 * The model the factory creates.
	 *
	 * @var class-string<Consent>
	 */
	protected $model = Consent::class;

	/**
	 * Default attributes.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		return [
			'consentable_type' => 'test_subject',
			'consentable_id'   => fake()->numberBetween( 1, 10000 ),
			'category'         => fake()->randomElement( [ 'necessary', 'functional', 'analytics', 'marketing' ] ),
			'granted'          => true,
			'regulation'       => fake()->randomElement( [ 'gdpr', 'ccpa', null ] ),
			'ip_address'       => fake()->ipv4(),
			'user_agent'       => fake()->userAgent(),
			'metadata'         => null,
			'expires_at'       => null,
			'withdrawn_at'     => null,
		];
	}

	/**
	 * State: a withdrawn consent.
	 *
	 * @return self
	 */
	public function withdrawn(): self
	{
		return $this->state( fn () => [
			'granted'      => false,
			'withdrawn_at' => now()->subDay(),
		] );
	}

	/**
	 * State: a consent that has expired.
	 *
	 * @return self
	 */
	public function expired(): self
	{
		return $this->state( fn () => [
			'expires_at' => now()->subDay(),
		] );
	}
}
