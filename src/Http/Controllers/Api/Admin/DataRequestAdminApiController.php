<?php

/**
 * DataRequestAdminApiController — JSON endpoints for the admin request panel.
 *
 * Powers the React and Vue versions of `Admin\DataRequestManager` with
 * listing, viewing, and processing operations on {@see DataRequest} rows.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Http\Controllers\Api\Admin;

use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;
use ArtisanPackUI\Privacy\Services\VerificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Admin data subject request endpoints.
 *
 * @since 1.0.0
 */
class DataRequestAdminApiController extends Controller
{
	public const ACTION_APPROVE  = 'approve';
	public const ACTION_REJECT   = 'reject';
	public const ACTION_COMPLETE = 'complete';
	public const ACTION_VERIFY   = 'verify';

	/**
	 * GET /api/privacy/admin/data-requests.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request Incoming request.
	 *
	 * @return JsonResponse
	 */
	public function index( Request $request ): JsonResponse
	{
		$this->authorize();

		$validated = $request->validate( [
			'type'     => 'sometimes|string|max:200',
			'status'   => 'sometimes|string|max:200',
			'sort'     => 'sometimes|in:newest,deadline,overdue',
			'per_page' => 'sometimes|integer|min:1|max:200',
			'page'     => 'sometimes|integer|min:1',
		] );

		$perPage = (int) ( $validated['per_page'] ?? 25 );

		$query = $this->applySort(
			$this->buildQuery( $validated ),
			(string) ( $validated['sort'] ?? 'deadline' ),
		);

		$paginator = $query->paginate( $perPage );
		$now       = now();

		return response()->json( [
			'data' => $paginator->getCollection()->map(
				static fn ( DataRequest $row ): array => self::serialize( $row, $now ),
			)->all(),
			'meta' => [
				'current_page' => $paginator->currentPage(),
				'per_page'     => $paginator->perPage(),
				'total'        => $paginator->total(),
				'last_page'    => $paginator->lastPage(),
			],
			'allowed_types' => self::allowedTypes(),
		] );
	}

	/**
	 * GET /api/privacy/admin/data-requests/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param  int  $id Request primary key.
	 *
	 * @return JsonResponse
	 */
	public function show( int $id ): JsonResponse
	{
		$this->authorize();

		$row = DataRequest::query()->with( 'logs' )->find( $id );

		if ( null === $row ) {
			return response()->json( [ 'message' => 'Not found' ], 404 );
		}

		return response()->json( [
			'data' => self::serialize( $row, now() ) + [
				'reason'      => $row->reason,
				'admin_notes' => $row->admin_notes,
				'data'        => $row->data,
				'logs'        => $row->logs->map( static fn ( DataRequestLog $log ): array => [
					'id'           => $log->id,
					'action'       => $log->action,
					'description'  => $log->description,
					'metadata'     => $log->metadata,
					'performed_by' => $log->performed_by,
					'created_at'   => optional( $log->created_at )->toIso8601String(),
				] )->all(),
			],
		] );
	}

	/**
	 * POST /api/privacy/admin/data-requests/{id}/actions.
	 *
	 * Body: `{ "action": "approve|reject|complete|verify", "note": "…" }`.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request Incoming request.
	 * @param  int      $id      Target request id.
	 *
	 * @return JsonResponse
	 */
	public function processAction( Request $request, int $id ): JsonResponse
	{
		$this->authorize();

		$validated = $request->validate( [
			'action' => 'required|in:approve,reject,complete,verify',
			'note'   => 'sometimes|nullable|string|max:2000',
		] );

		$note = trim( (string) ( $validated['note'] ?? '' ) );

		$result = DB::transaction( function () use ( $validated, $id, $note ) {
			$row = DataRequest::query()->lockForUpdate()->find( $id );

			if ( null === $row ) {
				return null;
			}

			$terminal = [ DataRequest::STATUS_COMPLETED, DataRequest::STATUS_REJECTED ];

			switch ( $validated['action'] ) {
				case self::ACTION_VERIFY:
					if ( null === $row->verified_at ) {
						$confirmed = app( VerificationService::class )
							->confirm( $row, 'manual', $this->actorId() );

						if ( ! $confirmed ) {
							return [ 'status' => 422, 'message' => 'Request could not be verified.' ];
						}

						$row->refresh();
					}
					$this->logAction( $row, 'verified', $note );
					break;

				case self::ACTION_APPROVE:
					if ( in_array( $row->status, $terminal, true ) ) {
						break;
					}

					if ( null === $row->verified_at ) {
						$confirmed = app( VerificationService::class )
							->confirm( $row, 'manual', $this->actorId() );

						if ( ! $confirmed ) {
							return [ 'status' => 422, 'message' => 'Request could not be verified.' ];
						}

						$row->refresh();
					}

					$row->forceFill( [
						'status'       => DataRequest::STATUS_PROCESSING,
						'processed_by' => $this->actorId(),
					] )->save();
					$this->logAction( $row, 'approved', $note );
					break;

				case self::ACTION_REJECT:
					if ( in_array( $row->status, $terminal, true ) ) {
						break;
					}

					$row->forceFill( [
						'status'       => DataRequest::STATUS_REJECTED,
						'processed_by' => $this->actorId(),
						'admin_notes'  => $this->mergeNote( $row, $note ),
					] )->save();
					$this->logAction( $row, 'rejected', $note );
					break;

				case self::ACTION_COMPLETE:
					if ( in_array( $row->status, $terminal, true ) ) {
						break;
					}

					$row->forceFill( [
						'status'       => DataRequest::STATUS_COMPLETED,
						'completed_at' => now(),
						'processed_by' => $this->actorId(),
						'admin_notes'  => $this->mergeNote( $row, $note ),
					] )->save();
					$this->logAction( $row, 'completed', $note );
					break;
			}

			return [ 'status' => 200 ];
		} );

		if ( null === $result ) {
			return response()->json( [ 'message' => 'Not found' ], 404 );
		}

		if ( 200 !== ( $result['status'] ?? 200 ) ) {
			return response()->json(
				[ 'message' => $result['message'] ?? 'Action failed.' ],
				(int) $result['status'],
			);
		}

		return $this->show( $id );
	}

	/**
	 * Run the gate check. Always authorizes — see
	 * {@see ConsentAdminApiController::authorize()} for the rationale.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function authorize(): void
	{
		$gate = (string) config( 'artisanpack.privacy.admin.gate', 'manage-privacy' );

		if ( '' === $gate ) {
			$gate = 'manage-privacy';
		}

		Gate::authorize( $gate );
	}

	/**
	 * Serialize a request row for JSON output.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataRequest                    $row Row to serialize.
	 * @param  \Illuminate\Support\Carbon     $now Reference time for overdue calc.
	 *
	 * @return array<string, mixed>
	 */
	protected static function serialize( DataRequest $row, $now ): array
	{
		$dueAt   = $row->due_at;
		$overdue = null !== $dueAt
			&& $dueAt->lt( $now )
			&& ! in_array( $row->status, [ DataRequest::STATUS_COMPLETED, DataRequest::STATUS_REJECTED ], true );

		return [
			'id'                => $row->id,
			'type'              => $row->type,
			'status'            => $row->status,
			'regulation'        => $row->regulation,
			'requestable_type'  => $row->requestable_type,
			'requestable_id'    => $row->requestable_id,
			'verified_at'       => optional( $row->verified_at )->toIso8601String(),
			'due_at'            => optional( $dueAt )->toIso8601String(),
			'completed_at'      => optional( $row->completed_at )->toIso8601String(),
			'created_at'        => optional( $row->created_at )->toIso8601String(),
			'overdue'           => $overdue,
		];
	}

	/**
	 * Returns the resolved admin user id for audit-log entries.
	 *
	 * @since 1.0.0
	 *
	 * @return int|null
	 */
	protected function actorId(): ?int
	{
		$user = Auth::user();

		if ( null === $user ) {
			return null;
		}

		$id = $user->getAuthIdentifier();

		return is_numeric( $id ) ? (int) $id : null;
	}

	/**
	 * Apply the sort key to the query.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder<DataRequest>  $query Base query.
	 * @param  string                $sort  Sort key.
	 *
	 * @return Builder<DataRequest>
	 */
	protected function applySort( Builder $query, string $sort ): Builder
	{
		switch ( $sort ) {
			case 'newest':
				return $query->orderByDesc( 'created_at' );

			case 'overdue':
				return $query
					->whereNotNull( 'due_at' )
					->where( 'due_at', '<', now() )
					->orderBy( 'due_at' );

			case 'deadline':
			default:
				return $query->orderByRaw( 'due_at IS NULL, due_at ASC' )->orderBy( 'created_at' );
		}
	}

	/**
	 * Build the base filter query.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $validated Validated request input.
	 *
	 * @return Builder<DataRequest>
	 */
	protected function buildQuery( array $validated ): Builder
	{
		$query = DataRequest::query();

		$type = (string) ( $validated['type'] ?? '' );

		if ( '' !== $type ) {
			$query->where( 'type', $type );
		}

		$status = (string) ( $validated['status'] ?? '' );

		if ( '' !== $status ) {
			$query->where( 'status', $status );
		}

		return $query;
	}

	/**
	 * Returns the allowed request types from configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected static function allowedTypes(): array
	{
		$allowed = (array) config( 'artisanpack.privacy.data_requests.allowed_types', [
			DataRequest::TYPE_ACCESS,
			DataRequest::TYPE_EXPORT,
			DataRequest::TYPE_DELETION,
			DataRequest::TYPE_RECTIFICATION,
		] );

		return array_values( array_map( static fn ( $value ): string => (string) $value, $allowed ) );
	}

	/**
	 * Append the supplied note onto the request's admin_notes.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataRequest  $request Target request.
	 * @param  string       $note    Note to append (trimmed).
	 *
	 * @return string|null
	 */
	protected function mergeNote( DataRequest $request, string $note ): ?string
	{
		if ( '' === $note ) {
			return $request->admin_notes;
		}

		$existing = (string) ( $request->admin_notes ?? '' );

		return '' === $existing ? $note : $existing . "\n" . $note;
	}

	/**
	 * Persist an admin-action audit log entry.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataRequest  $request Target request.
	 * @param  string       $action  Action identifier.
	 * @param  string       $note    Optional note.
	 *
	 * @return void
	 */
	protected function logAction( DataRequest $request, string $action, string $note ): void
	{
		DataRequestLog::query()->create( [
			'data_request_id' => $request->id,
			'action'          => $action,
			'performed_by'    => $this->actorId(),
			'metadata'        => '' === $note ? null : [ 'note' => $note ],
		] );
	}
}
