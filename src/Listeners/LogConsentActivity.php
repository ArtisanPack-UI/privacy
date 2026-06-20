<?php

/**
 * LogConsentActivity listener — writes consent grants and withdrawals to the application log.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Listeners;

use ArtisanPackUI\Privacy\Events\ConsentGiven;
use ArtisanPackUI\Privacy\Events\ConsentWithdrawn;
use Illuminate\Support\Facades\Log;

/**
 * Logs consent activity to the configured logging channel.
 *
 * Default listener for {@see ConsentGiven} and {@see ConsentWithdrawn};
 * subscribed via {@see \ArtisanPackUI\Privacy\PrivacyServiceProvider}.
 *
 * @since 1.0.0
 */
class LogConsentActivity
{
	/**
	 * Handle a {@see ConsentGiven} event.
	 *
	 * @since 1.0.0
	 *
	 * @param  ConsentGiven  $event Event payload.
	 *
	 * @return void
	 */
	public function handleConsentGiven( ConsentGiven $event ): void
	{
		Log::info( 'privacy.consent.granted', $this->context( $event->consent->toArray() ) );
	}

	/**
	 * Handle a {@see ConsentWithdrawn} event.
	 *
	 * @since 1.0.0
	 *
	 * @param  ConsentWithdrawn  $event Event payload.
	 *
	 * @return void
	 */
	public function handleConsentWithdrawn( ConsentWithdrawn $event ): void
	{
		Log::info( 'privacy.consent.withdrawn', $this->context( $event->consent->toArray() ) );
	}

	/**
	 * Reduces a consent attribute array to a stable context payload.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attributes Consent attributes.
	 *
	 * @return array<string, mixed>
	 */
	protected function context( array $attributes ): array
	{
		return [
			'id'               => $attributes['id'] ?? null,
			'consentable_type' => $attributes['consentable_type'] ?? null,
			'consentable_id'   => $attributes['consentable_id'] ?? null,
			'category'         => $attributes['category'] ?? null,
			'regulation'       => $attributes['regulation'] ?? null,
			'granted'          => $attributes['granted'] ?? null,
		];
	}
}
