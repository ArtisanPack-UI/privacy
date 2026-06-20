<?php

/**
 * ProcessDataExportJob — queues the data-export-to-file flow.
 *
 * Extracts the inline export processing previously embedded in
 * `ProcessDataRequests::processRequest()` so very large exports do not
 * block the scheduler loop and can fail / retry independently.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Jobs;

use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;
use ArtisanPackUI\Privacy\Notifications\DataRequestCompleted;
use ArtisanPackUI\Privacy\Services\DataExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Notifications\Dispatcher as NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Processes a single data subject request end-to-end on the queue.
 *
 * @since 1.0.0
 */
class ProcessDataExportJob implements ShouldQueue
{
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	/**
	 * @param int $requestId Data-request primary key to process.
	 */
	public function __construct( public int $requestId )
	{
	}

	/**
	 * Run the export, mark the request completed, write the audit log, and
	 * notify the subject.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataExportService       $exporter      Export service used for export requests.
	 * @param  NotificationDispatcher  $notifications Notification dispatcher for completion emails.
	 *
	 * @return void
	 */
	public function handle( DataExportService $exporter, NotificationDispatcher $notifications ): void
	{
		$request = DataRequest::query()->find( $this->requestId );

		if ( null === $request ) {
			return;
		}

		$subject = $request->requestable;

		// Refuse to silently complete a request whose subject is gone — the
		// audit log would say "fulfilled" while no data was actually
		// delivered. Reject it explicitly so an admin sees the dangling row.
		if ( ! $subject instanceof Model ) {
			$request->update( [
				'status'      => DataRequest::STATUS_REJECTED,
				'admin_notes' => __( 'Subject record could not be resolved (deleted or stale morph reference); request rejected without processing.' ),
			] );

			DataRequestLog::query()->create( [
				'data_request_id' => $request->id,
				'action'          => 'auto_rejected',
				'description'     => sprintf( 'Auto-rejected %s request — subject record missing', $request->type ),
				'metadata'        => [
					'requestable_type' => $request->requestable_type,
					'requestable_id'   => $request->requestable_id,
				],
			] );

			return;
		}

		$request->update( [ 'status' => DataRequest::STATUS_PROCESSING ] );

		$downloadUrl = null;
		$metadata    = [];

		if ( DataRequest::TYPE_EXPORT === $request->type ) {
			$format = (string) config( 'artisanpack.privacy.data_requests.export_format', 'json' );
			$result = $exporter->exportToFile( $subject, $format );

			$downloadUrl = $result['url'] ?? null;
			$metadata    = [
				'export_path' => $result['path'] ?? null,
				'expires_at'  => $result['expires_at'] ?? null,
			];
		}

		$request->update( [
			'status'       => DataRequest::STATUS_COMPLETED,
			'completed_at' => Carbon::now(),
		] );

		DataRequestLog::query()->create( [
			'data_request_id' => $request->id,
			'action'          => 'auto_processed',
			'description'     => sprintf( 'Auto-processed %s request', $request->type ),
			'metadata'        => $metadata,
		] );

		if ( method_exists( $subject, 'notify' ) ) {
			$notifications->send( $subject, new DataRequestCompleted( $request, $downloadUrl ) );
		}
	}
}
