<?php

/**
 * ConsentAdminApiController — JSON endpoint for the admin consent panel.
 *
 * Powers the React and Vue versions of `Admin\ConsentManager`. Authorizes
 * through the configured `manage-privacy` gate before returning anything.
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

use ArtisanPackUI\Privacy\Models\Consent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Admin consent listing endpoint.
 *
 * @since 1.0.0
 */
class ConsentAdminApiController extends Controller
{
	public const STATUS_ACTIVE    = 'active';
	public const STATUS_WITHDRAWN = 'withdrawn';
	public const STATUS_EXPIRED   = 'expired';

	/**
	 * GET /api/privacy/admin/consents.
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
			'q'        => 'sometimes|string|max:200',
			'category' => 'sometimes|string|max:200',
			'status'   => 'sometimes|in:active,withdrawn,expired,all',
			'from'     => 'sometimes|date',
			'to'       => 'sometimes|date',
			'per_page' => 'sometimes|integer|min:1|max:200',
			'page'     => 'sometimes|integer|min:1',
		] );

		$perPage = (int) ( $validated['per_page'] ?? 25 );

		$query = $this->buildQuery( $validated )->orderByDesc( 'created_at' );

		$paginator = $query->paginate( $perPage );

		return response()->json( [
			'data'  => $paginator->getCollection()->map( static fn ( Consent $row ): array => [
				'id'                => $row->id,
				'consentable_type'  => $row->consentable_type,
				'consentable_id'    => $row->consentable_id,
				'category'          => $row->category,
				'granted'           => (bool) $row->granted,
				'regulation'        => $row->regulation,
				'ip_address'        => $row->ip_address,
				'created_at'        => optional( $row->created_at )->toIso8601String(),
				'expires_at'        => optional( $row->expires_at )->toIso8601String(),
				'withdrawn_at'      => optional( $row->withdrawn_at )->toIso8601String(),
			] )->all(),
			'meta'  => [
				'current_page' => $paginator->currentPage(),
				'per_page'     => $paginator->perPage(),
				'total'        => $paginator->total(),
				'last_page'    => $paginator->lastPage(),
			],
			'categories' => array_values( array_map(
				static fn ( $key ): string => (string) $key,
				array_keys( (array) config( 'artisanpack.privacy.cookie_categories', [] ) ),
			) ),
		] );
	}

	/**
	 * Run the gate check (extracted so it can be reused / mocked).
	 *
	 * Always authorizes — an empty `admin.gate` config falls back to the
	 * `manage-privacy` default rather than bypassing auth, so a missing or
	 * blanked-out config cannot inadvertently expose the admin endpoints.
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
	 * Build a filtered query against the consent table.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $validated Validated query input.
	 *
	 * @return Builder<Consent>
	 */
	protected function buildQuery( array $validated ): Builder
	{
		$query = Consent::query();

		$category = (string) ( $validated['category'] ?? '' );

		if ( '' !== $category ) {
			$query->forCategory( $category );
		}

		switch ( $validated['status'] ?? 'all' ) {
			case self::STATUS_ACTIVE:
				$query->active();
				break;

			case self::STATUS_WITHDRAWN:
				$query->whereNotNull( 'withdrawn_at' );
				break;

			case self::STATUS_EXPIRED:
				$query->expired();
				break;
		}

		$from = $this->safeParse( $validated['from'] ?? null );

		if ( null !== $from ) {
			$query->where( 'created_at', '>=', $from->startOfDay() );
		}

		$to = $this->safeParse( $validated['to'] ?? null );

		if ( null !== $to ) {
			$query->where( 'created_at', '<=', $to->endOfDay() );
		}

		$term = trim( (string) ( $validated['q'] ?? '' ) );

		if ( '' !== $term ) {
			$query->where( function ( Builder $inner ) use ( $term ): void {
				$inner->where( 'consentable_type', 'like', "%{$term}%" )
					->orWhere( 'consentable_id', 'like', "%{$term}%" )
					->orWhere( 'category', 'like', "%{$term}%" )
					->orWhere( 'regulation', 'like', "%{$term}%" )
					->orWhere( 'ip_address', 'like', "%{$term}%" );
			} );
		}

		return $query;
	}

	/**
	 * Safely parses an optional date input.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed  $value Raw value.
	 *
	 * @return Carbon|null
	 */
	protected function safeParse( $value ): ?Carbon
	{
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		try {
			return Carbon::parse( $value );
		} catch ( Throwable $e ) {
			return null;
		}
	}
}
