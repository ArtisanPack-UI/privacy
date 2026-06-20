<?php

/**
 * PrivacyAdminController — renders the package's admin panel pages.
 *
 * Each action mounts a Livewire admin component inside the package's
 * minimal admin shell. All actions check the configured `manage-privacy`
 * gate before rendering and abort with a 403 response when access is
 * denied.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Http\Controllers\Admin;

use ArtisanPackUI\Privacy\Models\BreachNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Admin shell controller.
 *
 * @since 1.0.0
 */
class PrivacyAdminController extends Controller
{
	/**
	 * Render the admin dashboard with links to every panel.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function dashboard(): View
	{
		$this->authorize();

		return view( 'privacy::admin.dashboard' );
	}

	/**
	 * Render the consent administration panel.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function consents(): View
	{
		$this->authorize();

		return view( 'privacy::admin.consents' );
	}

	/**
	 * Render the data subject request administration panel.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function dataRequests(): View
	{
		$this->authorize();

		return view( 'privacy::admin.data-requests' );
	}

	/**
	 * Render the compliance report panel.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function complianceReport(): View
	{
		$this->authorize();

		return view( 'privacy::admin.compliance-report' );
	}

	/**
	 * Render the breach list panel.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function breaches(): View
	{
		$this->authorize();

		return view( 'privacy::admin.breaches' );
	}

	/**
	 * Render the breach report form panel.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function breachReport(): View
	{
		$this->authorize();

		return view( 'privacy::admin.breach-report' );
	}

	/**
	 * Render the breach detail panel.
	 *
	 * @since 1.0.0
	 *
	 * @param  int  $id Breach primary key.
	 *
	 * @return View
	 */
	public function breachDetail( int $id ): View
	{
		$this->authorize();

		$breach = BreachNotification::query()->findOrFail( $id );

		return view( 'privacy::admin.breach-detail', [ 'breach' => $breach ] );
	}

	/**
	 * Authorize the request against the configured admin gate.
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
}
