<?php

/**
 * ProcessDataAccessRequest listener — auto-processes access requests when enabled.
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
use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;
use Illuminate\Support\Facades\Log;

/**
 * Marks the request as `processing` when the
 * `artisanpack.privacy.data_requests.auto_process.access` flag is true and
 * records an audit-log entry. Real data assembly happens elsewhere (the
 * future DataExportService); this listener is intentionally idempotent.
 *
 * @since 1.0.0
 */
class ProcessDataAccessRequest
{
	/**
	 * Handle the event.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataAccessRequested  $event Event payload.
	 *
	 * @return void
	 */
	public function handle( DataAccessRequested $event ): void
	{
		if ( true !== (bool) config( 'artisanpack.privacy.data_requests.auto_process.access', false ) ) {
			return;
		}

		$request = $event->request;

		if ( DataRequest::STATUS_PENDING !== $request->status ) {
			return;
		}

		$request->update( [ 'status' => DataRequest::STATUS_PROCESSING ] );

		DataRequestLog::query()->create( [
			'data_request_id' => $request->getKey(),
			'action'          => 'auto_processing_started',
			'description'     => 'Access request marked as processing by ProcessDataAccessRequest listener.',
		] );

		Log::info( 'privacy.data_request.access.processing', [
			'request_id' => $request->getKey(),
		] );
	}
}
