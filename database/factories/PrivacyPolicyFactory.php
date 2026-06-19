<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Database\Factories;

use ArtisanPackUI\Privacy\Models\PrivacyPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for the {@see PrivacyPolicy} model.
 *
 * @extends Factory<PrivacyPolicy>
 *
 * @since 1.0.0
 */
class PrivacyPolicyFactory extends Factory
{
	/**
	 * The model the factory creates.
	 *
	 * @var class-string<PrivacyPolicy>
	 */
	protected $model = PrivacyPolicy::class;

	/**
	 * Default attributes.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		return [
			'version'            => '1.0.' . fake()->numberBetween( 0, 100 ),
			'regulation'         => null,
			'locale'             => 'en',
			'content'            => fake()->paragraphs( 3, true ),
			'sections'           => null,
			'active'             => false,
			'requires_reconsent' => false,
			'published_at'       => null,
			'created_by'         => null,
		];
	}

	/**
	 * State: a published, active policy.
	 *
	 * @return self
	 */
	public function active(): self
	{
		return $this->state( fn () => [
			'active'       => true,
			'published_at' => now(),
		] );
	}
}
