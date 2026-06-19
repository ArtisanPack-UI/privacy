<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Database\Factories;

use ArtisanPackUI\Privacy\Models\ConsentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for the {@see ConsentCategory} model.
 *
 * @extends Factory<ConsentCategory>
 *
 * @since 1.0.0
 */
class ConsentCategoryFactory extends Factory
{
	/**
	 * The model the factory creates.
	 *
	 * @var class-string<ConsentCategory>
	 */
	protected $model = ConsentCategory::class;

	/**
	 * Default attributes.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		$name = fake()->unique()->words( 2, true );

		return [
			'key'         => Str::slug( $name ),
			'name'        => Str::title( $name ),
			'description' => fake()->sentence(),
			'required'    => false,
			'sort_order'  => fake()->numberBetween( 0, 100 ),
			'active'      => true,
			'regulations' => [ 'gdpr' ],
		];
	}

	/**
	 * State: marks the category as strictly necessary.
	 *
	 * @return self
	 */
	public function required(): self
	{
		return $this->state( fn () => [
			'required' => true,
		] );
	}
}
