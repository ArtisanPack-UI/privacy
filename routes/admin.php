<?php

/**
 * Privacy package — admin web routes.
 *
 * Loaded by {@see \ArtisanPackUI\Privacy\PrivacyServiceProvider} under the
 * `artisanpack.privacy.admin.route_prefix` prefix with the configured
 * admin middleware stack. Authorization is enforced via the configured
 * `admin.gate` (defaults to `manage-privacy`).
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Http\Controllers\Admin\PrivacyAdminController;
use Illuminate\Support\Facades\Route;

Route::get( '/', [ PrivacyAdminController::class, 'dashboard' ] )
	->name( 'privacy.admin.dashboard' );

Route::get( '/consents', [ PrivacyAdminController::class, 'consents' ] )
	->name( 'privacy.admin.consents' );

Route::get( '/data-requests', [ PrivacyAdminController::class, 'dataRequests' ] )
	->name( 'privacy.admin.data-requests' );

Route::get( '/compliance-report', [ PrivacyAdminController::class, 'complianceReport' ] )
	->name( 'privacy.admin.compliance-report' );

Route::get( '/breaches', [ PrivacyAdminController::class, 'breaches' ] )
	->name( 'privacy.admin.breaches' );

Route::get( '/breaches/report', [ PrivacyAdminController::class, 'breachReport' ] )
	->name( 'privacy.admin.breaches.report' );

Route::get( '/breaches/{id}', [ PrivacyAdminController::class, 'breachDetail' ] )
	->whereNumber( 'id' )
	->name( 'privacy.admin.breaches.show' );
