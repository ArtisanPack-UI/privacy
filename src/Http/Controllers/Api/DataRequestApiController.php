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
use ArtisanPackUI\Privacy\Services\DataRequestService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
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
}
