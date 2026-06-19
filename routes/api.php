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

use ArtisanPackUI\Privacy\Http\Controllers\Api\CategoriesApiController;
use ArtisanPackUI\Privacy\Http\Controllers\Api\ConsentApiController;
use ArtisanPackUI\Privacy\Http\Controllers\Api\DataRequestApiController;
use Illuminate\Support\Facades\Route;

Route::get( '/consent', [ ConsentApiController::class, 'show' ] )->name( 'privacy.api.consent.show' );
Route::post( '/consent', [ ConsentApiController::class, 'update' ] )->name( 'privacy.api.consent.update' );
Route::get( '/categories', CategoriesApiController::class )->name( 'privacy.api.categories' );
Route::post( '/data-requests', [ DataRequestApiController::class, 'store' ] )->name( 'privacy.api.data-requests.store' );
