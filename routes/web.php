<?php

/**
 * Privacy package — public web routes.
 *
 * Loaded by {@see \ArtisanPackUI\Privacy\PrivacyServiceProvider} under the
 * `artisanpack.privacy.routes.prefix` prefix with the configured web
 * middleware stack.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Http\Controllers\DataExportDownloadController;
use ArtisanPackUI\Privacy\Http\Controllers\DataRequestVerificationController;
use Illuminate\Support\Facades\Route;

Route::get(
	'/verify/{token}',
	[ DataRequestVerificationController::class, 'show' ],
)->name( 'privacy.verification.show' );

Route::post(
	'/verify/{token}',
	[ DataRequestVerificationController::class, 'verify' ],
)->middleware( 'throttle:privacy-verification' )->name( 'privacy.verification.verify' );

Route::get(
	'/exports/{path}',
	DataExportDownloadController::class,
)->where( 'path', '.*' )->name( 'privacy.exports.download' );
