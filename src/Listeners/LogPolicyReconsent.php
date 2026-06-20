<?php

/**
 * LogPolicyReconsent listener — logs re-consent prompts and acceptances.
 *
 * Default listener for {@see PolicyReconsentRequired} and
 * {@see PolicyReconsentGiven}; subscribed via
 * {@see \ArtisanPackUI\Privacy\PrivacyServiceProvider}.
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

use ArtisanPackUI\Privacy\Events\PolicyReconsentGiven;
use ArtisanPackUI\Privacy\Events\PolicyReconsentRequired;
use Illuminate\Support\Facades\Log;

/**
 * Writes re-consent lifecycle events to the configured logging channel.
 *
 * @since 1.0.0
 */
class LogPolicyReconsent
{
	/**
	 * Handle a {@see PolicyReconsentRequired} event.
	 *
	 * @since 1.0.0
	 *
	 * @param  PolicyReconsentRequired  $event Event payload.
	 *
	 * @return void
	 */
	public function handleRequired( PolicyReconsentRequired $event ): void
	{
		Log::info( 'privacy.policy.reconsent.required', [
			'policy_version' => $event->policy->version,
			'regulation'     => $event->policy->regulation,
			'subject'        => $this->subjectReference( $event->subject ),
		] );
	}

	/**
	 * Handle a {@see PolicyReconsentGiven} event.
	 *
	 * @since 1.0.0
	 *
	 * @param  PolicyReconsentGiven  $event Event payload.
	 *
	 * @return void
	 */
	public function handleGiven( PolicyReconsentGiven $event ): void
	{
		Log::info( 'privacy.policy.reconsent.given', [
			'policy_version' => $event->policy->version,
			'regulation'     => $event->policy->regulation,
			'subject'        => $this->subjectReference( $event->subject ),
		] );
	}

	/**
	 * Builds the loggable subject reference. Returns null for guest events.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed  $subject Subject seed.
	 *
	 * @return array{type: string, id: int|string}|null
	 */
	protected function subjectReference( mixed $subject ): ?array
	{
		if ( ! $subject instanceof \Illuminate\Database\Eloquent\Model ) {
			return null;
		}

		return [
			'type' => $subject->getMorphClass(),
			'id'   => $subject->getKey(),
		];
	}
}
