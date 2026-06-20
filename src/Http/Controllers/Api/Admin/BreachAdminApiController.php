<?php

/**
 * BreachAdminApiController — JSON endpoints for the admin breach panel.
 *
 * Backs the React and Vue versions of `Admin\BreachManager`,
 * `Admin\BreachReportForm`, and `Admin\BreachDetail` with listing,
 * reporting, viewing, notification dispatch, status updates, and
 * remediation logging.
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

use ArtisanPackUI\Privacy\Models\BreachNotification;
use ArtisanPackUI\Privacy\Services\BreachNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Admin breach API controller.
 *
 * @since 1.0.0
 */
class BreachAdminApiController extends Controller
{
	public const ACTION_NOTIFY_AUTHORITY = 'notify_authority';
	public const ACTION_NOTIFY_USERS     = 'notify_users';
	public const ACTION_SET_STATUS       = 'set_status';
	public const ACTION_ADD_REMEDIATION  = 'add_remediation';

	/**
	 * GET /api/privacy/admin/breaches.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request                    $request Incoming request.
	 * @param  BreachNotificationService  $service Notification service.
	 *
	 * @return JsonResponse
	 */
	public function index( Request $request, BreachNotificationService $service ): JsonResponse
	{
		$this->authorize();

		$validated = $request->validate( [
			'severity' => 'sometimes|string|max:50',
			'status'   => 'sometimes|string|max:50',
			'per_page' => 'sometimes|integer|min:1|max:200',
			'page'     => 'sometimes|integer|min:1',
		] );

		$perPage = (int) ( $validated['per_page'] ?? 25 );

		$paginator = $this->buildQuery( $validated )
			->orderByDesc( 'discovered_at' )
			->paginate( $perPage );

		return response()->json( [
			'data' => $paginator->getCollection()->map(
				static fn ( BreachNotification $row ): array => self::serialize( $row, $service ),
			)->all(),
			'meta' => [
				'current_page' => $paginator->currentPage(),
				'per_page'     => $paginator->perPage(),
				'total'        => $paginator->total(),
				'last_page'    => $paginator->lastPage(),
			],
			'severities' => [
				BreachNotification::SEVERITY_LOW,
				BreachNotification::SEVERITY_MEDIUM,
				BreachNotification::SEVERITY_HIGH,
				BreachNotification::SEVERITY_CRITICAL,
			],
			'statuses' => [
				BreachNotification::STATUS_INVESTIGATING,
				BreachNotification::STATUS_CONTAINED,
				BreachNotification::STATUS_RESOLVED,
			],
		] );
	}

	/**
	 * GET /api/privacy/admin/breaches/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param  int                        $id      Breach primary key.
	 * @param  BreachNotificationService  $service Notification service.
	 *
	 * @return JsonResponse
	 */
	public function show( int $id, BreachNotificationService $service ): JsonResponse
	{
		$this->authorize();

		$row = BreachNotification::query()->find( $id );

		if ( null === $row ) {
			return response()->json( [ 'message' => 'Not found' ], 404 );
		}

		return response()->json( [
			'data' => self::serialize( $row, $service ) + [
				'description'        => $row->description,
				'cause'              => $row->cause,
				'remediation'        => $row->remediation,
				'notifications_sent' => (array) ( $row->notifications_sent ?? [] ),
				'affected_users'     => (array) ( $row->affected_users ?? [] ),
			],
		] );
	}

	/**
	 * POST /api/privacy/admin/breaches.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request                    $request Incoming request.
	 * @param  BreachNotificationService  $service Notification service.
	 *
	 * @return JsonResponse
	 */
	public function store( Request $request, BreachNotificationService $service ): JsonResponse
	{
		$this->authorize();

		if ( $request->has( 'affected_users' ) && is_array( $request->input( 'affected_users' ) ) ) {
			$request->merge( [
				'affected_users' => self::normalizeAffectedUsers( (array) $request->input( 'affected_users' ) ),
			] );
		}

		$validated = $request->validate( [
			'description'             => 'required|string|max:5000',
			'severity'                => 'required|in:low,medium,high,critical',
			'data_types_affected'     => 'required|array|min:1|max:200',
			'data_types_affected.*'   => 'string|max:200',
			'records_affected'        => 'sometimes|nullable|integer|min:0',
			'affected_users'          => 'sometimes|nullable|array|max:10000',
			'affected_users.*'        => 'array',
			'affected_users.*.email'  => 'required|email:rfc',
			'affected_users.*.name'   => 'sometimes|nullable|string|max:200',
			'affected_users.*.data'   => 'sometimes|nullable|array|max:200',
			'affected_users.*.locale' => 'sometimes|nullable|string|max:20',
			'cause'                   => 'sometimes|nullable|string|max:5000',
			'remediation'             => 'sometimes|nullable|string|max:5000',
			'occurred_at'             => 'sometimes|nullable|date',
		] );

		$breach = $service->reportBreach( $validated + [ 'reported_by' => $this->actorId() ] );

		return $this->show( $breach->id, $service )->setStatusCode( 201 );
	}

	/**
	 * POST /api/privacy/admin/breaches/{id}/actions.
	 *
	 * Body: `{ "action": "notify_authority|notify_users|set_status|add_remediation", … }`.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request                    $request Incoming request.
	 * @param  int                        $id      Breach primary key.
	 * @param  BreachNotificationService  $service Notification service.
	 *
	 * @return JsonResponse
	 */
	public function processAction( Request $request, int $id, BreachNotificationService $service ): JsonResponse
	{
		$this->authorize();

		$validated = $request->validate( [
			'action' => 'required|in:notify_authority,notify_users,set_status,add_remediation',
			'status' => 'sometimes|in:investigating,contained,resolved',
			'note'   => 'sometimes|nullable|string|max:5000',
		] );

		$breach = BreachNotification::query()->find( $id );

		if ( null === $breach ) {
			return response()->json( [ 'message' => 'Not found' ], 404 );
		}

		switch ( $validated['action'] ) {
			case self::ACTION_NOTIFY_AUTHORITY:
				$service->notifyAuthority( $breach );
				break;

			case self::ACTION_NOTIFY_USERS:
				$service->notifyAffectedUsers( $breach );
				break;

			case self::ACTION_SET_STATUS:
				if ( ! isset( $validated['status'] ) ) {
					return response()->json( [ 'message' => 'Missing status.' ], 422 );
				}
				$breach->forceFill( [ 'status' => (string) $validated['status'] ] )->save();
				break;

			case self::ACTION_ADD_REMEDIATION:
				$note = trim( (string) ( $validated['note'] ?? '' ) );
				if ( '' === $note ) {
					return response()->json( [ 'message' => 'Note is required.' ], 422 );
				}
				$existing = (string) ( $breach->remediation ?? '' );
				$breach->forceFill( [
					'remediation' => '' === $existing ? $note : $existing . "\n" . $note,
				] )->save();
				break;
		}

		return $this->show( $id, $service );
	}

	/**
	 * Authorize against the configured admin gate.
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
	 * Returns the resolved admin user id for `reported_by`.
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
	 * Normalize the affected-users payload so every entry has the same
	 * `array{email: string, ...}` shape before validation runs. Accepts
	 * raw email strings and assoc arrays interchangeably.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, mixed>  $entries Raw affected-users payload.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected static function normalizeAffectedUsers( array $entries ): array
	{
		$normalized = [];

		foreach ( $entries as $entry ) {
			if ( is_string( $entry ) ) {
				$normalized[] = [ 'email' => trim( $entry ) ];
				continue;
			}

			if ( is_array( $entry ) ) {
				$normalized[] = $entry;
			}
		}

		return $normalized;
	}

	/**
	 * Build the listing query from validated input.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $validated Validated input.
	 *
	 * @return Builder<BreachNotification>
	 */
	protected function buildQuery( array $validated ): Builder
	{
		$query = BreachNotification::query();

		$severity = (string) ( $validated['severity'] ?? '' );

		if ( '' !== $severity ) {
			$query->where( 'severity', $severity );
		}

		$status = (string) ( $validated['status'] ?? '' );

		if ( '' !== $status ) {
			$query->where( 'status', $status );
		}

		return $query;
	}

	/**
	 * Serialize a breach row for JSON output.
	 *
	 * @since 1.0.0
	 *
	 * @param  BreachNotification         $row     Row to serialize.
	 * @param  BreachNotificationService  $service Service used to compute deadlines.
	 *
	 * @return array<string, mixed>
	 */
	protected static function serialize( BreachNotification $row, BreachNotificationService $service ): array
	{
		$deadline = $service->getNotificationDeadline( $row );
		$overdue  = null === $row->authority_notified_at && $deadline->isPast();

		return [
			'id'                     => $row->id,
			'reference_number'       => $row->reference_number,
			'severity'               => $row->severity,
			'status'                 => $row->status,
			'discovered_at'          => optional( $row->discovered_at )->toIso8601String(),
			'occurred_at'            => optional( $row->occurred_at )->toIso8601String(),
			'authority_notified_at'  => optional( $row->authority_notified_at )->toIso8601String(),
			'users_notified_at'      => optional( $row->users_notified_at )->toIso8601String(),
			'authority_deadline'     => $deadline->toIso8601String(),
			'authority_overdue'      => $overdue,
			'records_affected'       => $row->records_affected,
			'data_types_affected'    => (array) ( $row->data_types_affected ?? [] ),
		];
	}
}
