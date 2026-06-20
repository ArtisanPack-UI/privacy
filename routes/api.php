<?php

/**
 * Privacy package — JSON API routes.
 *
 * Loaded by {@see \ArtisanPackUI\Privacy\PrivacyServiceProvider} under the
 * `artisanpack.privacy.routes.api_prefix` prefix with the configured
 * middleware stack.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Http\Controllers\Api\Admin\BreachAdminApiController;
use ArtisanPackUI\Privacy\Http\Controllers\Api\Admin\ComplianceReportAdminApiController;
use ArtisanPackUI\Privacy\Http\Controllers\Api\Admin\ConsentAdminApiController;
use ArtisanPackUI\Privacy\Http\Controllers\Api\Admin\DataRequestAdminApiController;
use ArtisanPackUI\Privacy\Http\Controllers\Api\CategoriesApiController;
use ArtisanPackUI\Privacy\Http\Controllers\Api\ConsentApiController;
use ArtisanPackUI\Privacy\Http\Controllers\Api\DataRequestApiController;
use Illuminate\Support\Facades\Route;

Route::get( '/consent', [ ConsentApiController::class, 'show' ] )->name( 'privacy.api.consent.show' );
Route::post( '/consent', [ ConsentApiController::class, 'update' ] )->name( 'privacy.api.consent.update' );
Route::get( '/categories', CategoriesApiController::class )->name( 'privacy.api.categories' );
Route::get( '/data-requests', [ DataRequestApiController::class, 'index' ] )
	->middleware( 'throttle:privacy-data-requests-api' )
	->name( 'privacy.api.data-requests.index' );
Route::post( '/data-requests', [ DataRequestApiController::class, 'store' ] )
	->middleware( 'throttle:privacy-data-requests-api' )
	->name( 'privacy.api.data-requests.store' );

Route::prefix( 'admin' )->group( function (): void {
	Route::get( '/consents', [ ConsentAdminApiController::class, 'index' ] )
		->name( 'privacy.api.admin.consents.index' );

	Route::get( '/data-requests', [ DataRequestAdminApiController::class, 'index' ] )
		->name( 'privacy.api.admin.data-requests.index' );
	Route::get( '/data-requests/{id}', [ DataRequestAdminApiController::class, 'show' ] )
		->whereNumber( 'id' )
		->name( 'privacy.api.admin.data-requests.show' );
	Route::post( '/data-requests/{id}/actions', [ DataRequestAdminApiController::class, 'processAction' ] )
		->whereNumber( 'id' )
		->name( 'privacy.api.admin.data-requests.actions' );

	Route::get( '/compliance-report', [ ComplianceReportAdminApiController::class, 'show' ] )
		->middleware( 'throttle:privacy-admin-api' )
		->name( 'privacy.api.admin.compliance-report' );

	Route::get( '/breaches', [ BreachAdminApiController::class, 'index' ] )
		->middleware( 'throttle:privacy-admin-api' )
		->name( 'privacy.api.admin.breaches.index' );
	Route::post( '/breaches', [ BreachAdminApiController::class, 'store' ] )
		->middleware( 'throttle:privacy-admin-api' )
		->name( 'privacy.api.admin.breaches.store' );
	Route::get( '/breaches/{id}', [ BreachAdminApiController::class, 'show' ] )
		->middleware( 'throttle:privacy-admin-api' )
		->whereNumber( 'id' )
		->name( 'privacy.api.admin.breaches.show' );
	Route::post( '/breaches/{id}/actions', [ BreachAdminApiController::class, 'processAction' ] )
		->middleware( 'throttle:privacy-admin-api' )
		->whereNumber( 'id' )
		->name( 'privacy.api.admin.breaches.actions' );
} );
