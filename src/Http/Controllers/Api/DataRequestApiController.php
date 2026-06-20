<?php

/**
 * DataRequestApiController — JSON endpoint backing the React/Vue request forms.
 *
 * Accepts an authenticated submit, files the request through
 * {@see DataRequestService}, and returns a small JSON payload describing
 * what happened so the client can flip into its success state.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Http\Controllers\Api;

use ArtisanPackUI\Privacy\Http\Requests\StoreDataRequestRequest;
use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Regulations\RegulationRegistry;
use ArtisanPackUI\Privacy\Services\DataRequestService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Files data subject requests on behalf of authenticated visitors.
 *
 * @since 1.0.0
 */
class DataRequestApiController extends Controller
{
	/**
	 * @param DataRequestService $requests Request service.
	 */
	public function __construct( protected DataRequestService $requests )
	{
	}

	/**
	 * POST /api/privacy/data-requests
	 *
	 * @since 1.0.0
	 *
	 * @param  StoreDataRequestRequest  $request Validated request.
	 *
	 * @return JsonResponse
	 */
	public function store( StoreDataRequestRequest $request ): JsonResponse
	{
		// StoreDataRequestRequest::authorize() short-circuits with 403 for
		// unauthenticated callers, so $subject is guaranteed non-null here.
		/** @var Model $subject */
		$subject = Auth::user();

		$type   = (string) $request->input( 'type' );
		$reason = $request->input( 'reason' );
		$reason = is_string( $reason ) && '' !== $reason ? $reason : null;

		$result = match ( $type ) {
			DataRequest::TYPE_ACCESS        => $this->requests->createAccessRequest( $subject, $reason ),
			DataRequest::TYPE_EXPORT        => $this->requests->createExportRequest( $subject, $reason ),
			DataRequest::TYPE_DELETION      => $this->requests->createDeletionRequest( $subject, $reason ),
			DataRequest::TYPE_RECTIFICATION => $this->requests->createRectificationRequest( $subject, $reason ),
		};

		return response()->json( [
			'id'                => $result->id,
			'type'              => $result->type,
			'status'            => $result->status,
			'verification_sent' => (bool) config( 'artisanpack.privacy.data_requests.require_verification', true ),
		], 201 );
	}

	/**
	 * GET /api/privacy/data-requests
	 *
	 * Returns the authenticated subject's own request history plus the
	 * currently-applicable regulation so the dashboard can show both in a
	 * single round trip.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request             $request HTTP request.
	 * @param  RegulationRegistry  $registry Regulation registry.
	 *
	 * @return JsonResponse
	 */
	public function index( Request $request, RegulationRegistry $registry ): JsonResponse
	{
		$subject = Auth::user();

		if ( ! $subject instanceof Model ) {
			return response()->json( [ 'message' => __( 'Authentication required.' ) ], 401 );
		}

		$limit = max( 1, min( 100, (int) $request->query( 'limit', '25' ) ) );

		$rows = DataRequest::query()
			->where( 'requestable_type', $subject->getMorphClass() )
			->where( 'requestable_id', $subject->getKey() )
			->orderByDesc( 'created_at' )
			->limit( $limit )
			->get();

		$applicable = $registry->applicableFor( $request );

		return response()->json( [
			'regulation' => $applicable?->key(),
			'requests'   => $rows->map( static function ( DataRequest $row ): array {
				$payload     = is_array( $row->data ) ? $row->data : [];
				$downloadUrl = is_string( $payload['download_url'] ?? null ) ? (string) $payload['download_url'] : null;

				return [
					'id'           => $row->id,
					'type'         => $row->type,
					'status'       => $row->status,
					'regulation'   => $row->regulation,
					'created_at'   => $row->created_at?->toIso8601String(),
					'due_at'       => $row->due_at?->toIso8601String(),
					'completed_at' => $row->completed_at?->toIso8601String(),
					'download_url' => DataRequest::TYPE_EXPORT === $row->type
						&& DataRequest::STATUS_COMPLETED === $row->status
							? $downloadUrl
							: null,
				];
			} )->all(),
		] );
	}
}
