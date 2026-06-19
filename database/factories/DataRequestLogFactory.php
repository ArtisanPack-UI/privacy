<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Database\Factories;

use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for the {@see DataRequestLog} model.
 *
 * @extends Factory<DataRequestLog>
 *
 * @since 1.0.0
 */
class DataRequestLogFactory extends Factory
{
	/**
	 * The model the factory creates.
	 *
	 * @var class-string<DataRequestLog>
	 */
	protected $model = DataRequestLog::class;

	/**
	 * Default attributes.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		return [
			'data_request_id' => DataRequest::factory(),
			'action'          => fake()->randomElement( [ 'created', 'verified', 'processing', 'completed' ] ),
			'description'     => fake()->sentence(),
			'metadata'        => null,
			'performed_by'    => null,
		];
	}
}
