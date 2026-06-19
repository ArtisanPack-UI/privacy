<?php

/**
 * NotifyDataBreach listener — emails the configured admin when a breach is recorded.
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

use ArtisanPackUI\Privacy\Events\DataBreach;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Starts the breach-notification workflow by logging the breach and
 * surfacing it to the configured admin mailbox. Full authority/user
 * notifications are handed off to {@see \ArtisanPackUI\Privacy\Services\BreachNotificationService}
 * in a later phase; this listener is the entry point.
 *
 * @since 1.0.0
 */
class NotifyDataBreach
{
	/**
	 * Handle the event.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataBreach  $event Event payload.
	 *
	 * @return void
	 */
	public function handle( DataBreach $event ): void
	{
		$breach = $event->breach;

		Log::critical( 'privacy.data_breach.reported', [
			'breach_id'        => $breach->getKey(),
			'reference_number' => $breach->reference_number,
			'severity'         => $breach->severity,
			'records_affected' => $breach->records_affected,
		] );

		$adminEmail = config( 'artisanpack.privacy.data_requests.admin_email' );

		if ( ! is_string( $adminEmail ) || '' === trim( $adminEmail ) ) {
			return;
		}

		$body = sprintf(
			"A data breach has been recorded.\n\nReference: %s\nSeverity: %s\nDiscovered: %s\nRecords affected: %s\nDescription:\n%s",
			(string) $breach->reference_number,
			(string) $breach->severity,
			$breach->discovered_at->toIso8601String(),
			null === $breach->records_affected ? 'unknown' : (string) $breach->records_affected,
			(string) $breach->description,
		);

		Mail::raw( $body, function ( $message ) use ( $adminEmail, $breach ): void {
			$message
				->to( $adminEmail )
				->subject( sprintf( '[Privacy] Data breach reported (%s)', $breach->reference_number ) );
		} );
	}
}
