<?php

/**
 * ComplianceReportAdminApiController — JSON endpoint for the compliance report panel.
 *
 * Backs the React and Vue versions of `Admin\ComplianceReport` with the
 * same payload the Livewire component renders. Authorization is enforced
 * through the configured `manage-privacy` gate.
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

use ArtisanPackUI\Privacy\Services\ComplianceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Admin compliance report endpoint.
 *
 * @since 1.0.0
 */
class ComplianceReportAdminApiController extends Controller
{
	/**
	 * GET /api/privacy/admin/compliance-report.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request                  $request Incoming request.
	 * @param  ComplianceReportService  $service Reporting service.
	 *
	 * @return JsonResponse
	 */
	public function show( Request $request, ComplianceReportService $service ): JsonResponse
	{
		$this->authorize();

		$validated = $request->validate( [
			'from'       => 'sometimes|date',
			'to'         => 'sometimes|date',
			'regulation' => 'sometimes|nullable|string|max:50',
		] );

		$from = isset( $validated['from'] )
			? Carbon::parse( (string) $validated['from'] )->startOfDay()
			: now()->subDays( 30 )->startOfDay();

		$to = isset( $validated['to'] )
			? Carbon::parse( (string) $validated['to'] )->endOfDay()
			: now()->endOfDay();

		$regulation = isset( $validated['regulation'] ) && '' !== (string) $validated['regulation']
			? (string) $validated['regulation']
			: null;

		return response()->json( [
			'data'        => $service->generate( $from, $to, $regulation ),
			'regulations' => $this->enabledRegulations(),
		] );
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
	 * Returns the enabled regulation keys.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function enabledRegulations(): array
	{
		$configured = (array) config( 'artisanpack.privacy.regulations', [] );

		$enabled = [];
		foreach ( $configured as $key => $value ) {
			if ( is_array( $value ) && true === ( $value['enabled'] ?? false ) ) {
				$enabled[] = (string) $key;
			}
		}

		return $enabled;
	}
}
