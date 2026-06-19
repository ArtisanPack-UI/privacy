<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Database\Factories;

use ArtisanPackUI\Privacy\Models\DataRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for the {@see DataRequest} model.
 *
 * @extends Factory<DataRequest>
 *
 * @since 1.0.0
 */
class DataRequestFactory extends Factory
{
	/**
	 * The model the factory creates.
	 *
	 * @var class-string<DataRequest>
	 */
	protected $model = DataRequest::class;

	/**
	 * Default attributes.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		return [
			'requestable_type'   => 'test_subject',
			'requestable_id'     => fake()->numberBetween( 1, 10000 ),
			'type'               => fake()->randomElement( [
				DataRequest::TYPE_ACCESS,
				DataRequest::TYPE_DELETION,
				DataRequest::TYPE_EXPORT,
				DataRequest::TYPE_RECTIFICATION,
			] ),
			'status'             => DataRequest::STATUS_PENDING,
			'regulation'         => fake()->randomElement( [ 'gdpr', 'ccpa', null ] ),
			'reason'             => fake()->sentence(),
			'data'               => null,
			'verification_token' => Str::random( 40 ),
			'verified_at'        => null,
			'due_at'             => now()->addDays( 30 ),
			'completed_at'       => null,
			'processed_by'       => null,
			'admin_notes'        => null,
		];
	}

	/**
	 * State: a completed request.
	 *
	 * @return self
	 */
	public function completed(): self
	{
		return $this->state( fn () => [
			'status'       => DataRequest::STATUS_COMPLETED,
			'verified_at'  => now()->subDays( 2 ),
			'completed_at' => now()->subDay(),
		] );
	}

	/**
	 * State: an overdue request.
	 *
	 * @return self
	 */
	public function overdue(): self
	{
		return $this->state( fn () => [
			'status' => DataRequest::STATUS_PENDING,
			'due_at' => now()->subDay(),
		] );
	}
}
