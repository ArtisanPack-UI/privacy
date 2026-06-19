<?php

/**
 * SyncConsentOnLogin listener — copies cookie consent into the database when a user logs in.
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

use ArtisanPackUI\Privacy\Services\ConsentService;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;

/**
 * On {@see Login}, hands the just-authenticated user to
 * {@see ConsentService::syncCookieToDatabase()} so guest-era consent
 * recorded in the cookie is materialised as database rows.
 *
 * @since 1.0.0
 */
class SyncConsentOnLogin
{
	/**
	 * @param ConsentService $consents Consent service used for the sync.
	 */
	public function __construct( protected ConsentService $consents )
	{
	}

	/**
	 * Handle the event.
	 *
	 * @since 1.0.0
	 *
	 * @param  Login  $event Event payload.
	 *
	 * @return void
	 */
	public function handle( Login $event ): void
	{
		$user = $event->user;

		if ( ! $user instanceof Model ) {
			return;
		}

		$this->consents->syncCookieToDatabase( $user );
	}
}
