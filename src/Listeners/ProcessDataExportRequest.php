<?php

/**
 * ProcessDataExportRequest listener — auto-processes export requests when enabled.
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

use ArtisanPackUI\Privacy\Events\DataExportRequested;
use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;
use Illuminate\Support\Facades\Log;

/**
 * Marks the request as `processing` when the
 * `artisanpack.privacy.data_requests.auto_process.export` flag is true and
 * records an audit-log entry. Actual export-file generation is handed off
 * to a dedicated service in a later phase; this listener is the trigger.
 *
 * @since 1.0.0
 */
class ProcessDataExportRequest
{
	/**
	 * Handle the event.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataExportRequested  $event Event payload.
	 *
	 * @return void
	 */
	public function handle( DataExportRequested $event ): void
	{
		if ( true !== (bool) config( 'artisanpack.privacy.data_requests.auto_process.export', false ) ) {
			return;
		}

		$request = $event->request;

		if ( DataRequest::STATUS_PENDING !== $request->status ) {
			return;
		}

		if ( true === (bool) config( 'artisanpack.privacy.data_requests.require_verification', true ) && null === $request->verified_at ) {
			return;
		}

		$request->update( [ 'status' => DataRequest::STATUS_PROCESSING ] );

		DataRequestLog::query()->create( [
			'data_request_id' => $request->getKey(),
			'action'          => 'auto_processing_started',
			'description'     => 'Export request marked as processing by ProcessDataExportRequest listener.',
		] );

		Log::info( 'privacy.data_request.export.processing', [
			'request_id' => $request->getKey(),
		] );
	}
}
