<?php

/**
 * NotifyAdminOfRequest listener — emails the configured admin when a new data request is filed.
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

use ArtisanPackUI\Privacy\Events\DataAccessRequested;
use ArtisanPackUI\Privacy\Events\DataDeletionRequested;
use ArtisanPackUI\Privacy\Events\DataExportRequested;
use ArtisanPackUI\Privacy\Models\DataRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a plain-text notification to `artisanpack.privacy.data_requests.admin_email`
 * for every new access/deletion/export request when
 * `artisanpack.privacy.data_requests.notify_admin` is true.
 *
 * Subscribed to all three request events via the service provider so a
 * single listener instance covers the full request surface area.
 *
 * @since 1.0.0
 */
class NotifyAdminOfRequest
{
	/**
	 * Handle a {@see DataAccessRequested} event.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataAccessRequested  $event Event payload.
	 *
	 * @return void
	 */
	public function handleAccess( DataAccessRequested $event ): void
	{
		$this->notify( $event->request );
	}

	/**
	 * Handle a {@see DataDeletionRequested} event.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataDeletionRequested  $event Event payload.
	 *
	 * @return void
	 */
	public function handleDeletion( DataDeletionRequested $event ): void
	{
		$this->notify( $event->request );
	}

	/**
	 * Handle a {@see DataExportRequested} event.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataExportRequested  $event Event payload.
	 *
	 * @return void
	 */
	public function handleExport( DataExportRequested $event ): void
	{
		$this->notify( $event->request );
	}

	/**
	 * Sends the email when configuration allows it.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataRequest  $request Data subject request.
	 *
	 * @return void
	 */
	protected function notify( DataRequest $request ): void
	{
		if ( true !== (bool) config( 'artisanpack.privacy.data_requests.notify_admin', false ) ) {
			return;
		}

		$adminEmail = config( 'artisanpack.privacy.data_requests.admin_email' );

		if ( ! is_string( $adminEmail ) || '' === trim( $adminEmail ) ) {
			Log::warning( 'privacy.data_request.admin_email_missing', [
				'request_id' => $request->getKey(),
			] );

			return;
		}

		$body = sprintf(
			"A new %s request has been filed.\n\nRequest ID: %s\nSubject: %s #%s\nRegulation: %s\nDue: %s",
			$request->type,
			(string) $request->getKey(),
			(string) $request->requestable_type,
			(string) $request->requestable_id,
			(string) ( $request->regulation ?? 'n/a' ),
			null !== $request->due_at ? $request->due_at->toIso8601String() : 'n/a',
		);

		Mail::raw( $body, function ( $message ) use ( $adminEmail, $request ): void {
			$message
				->to( $adminEmail )
				->subject( sprintf( '[Privacy] New %s request #%s', $request->type, (string) $request->getKey() ) );
		} );
	}
}
