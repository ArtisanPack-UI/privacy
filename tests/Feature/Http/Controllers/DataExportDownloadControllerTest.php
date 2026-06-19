<?php

declare( strict_types=1 );

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach( function (): void {
	Storage::fake( 'local' );
	config()->set( 'artisanpack.privacy.export.disk', 'local' );
	config()->set( 'artisanpack.privacy.export.directory', 'privacy-exports' );
} );

it( 'streams the export file for a valid signed URL', function (): void {
	$path = 'privacy-exports/test-export.json';
	Storage::disk( 'local' )->put( $path, '{"hello":"world"}' );

	$url = URL::temporarySignedRoute(
		'privacy.exports.download',
		Carbon::now()->addMinutes( 5 ),
		[ 'path' => $path ],
	);

	$response = $this->get( $url );

	$response->assertOk();
	expect( $response->streamedContent() )->toBe( '{"hello":"world"}' );
} );

it( 'rejects a tampered signature with 403', function (): void {
	$path = 'privacy-exports/test-export.json';
	Storage::disk( 'local' )->put( $path, 'payload' );

	$response = $this->get( route( 'privacy.exports.download', [ 'path' => $path ] ) );

	$response->assertForbidden();
} );

it( 'returns 410 when the sidecar expiry marker is in the past', function (): void {
	$path = 'privacy-exports/expired-export.json';
	Storage::disk( 'local' )->put( $path, 'expired' );
	Storage::disk( 'local' )->put( $path . '.expires', Carbon::now()->subHour()->toIso8601String() );

	$url = URL::temporarySignedRoute(
		'privacy.exports.download',
		Carbon::now()->addMinutes( 5 ),
		[ 'path' => $path ],
	);

	$this->get( $url )->assertStatus( 410 );
} );

it( 'refuses paths outside the configured export directory', function (): void {
	$path = '../app/config.php';

	$url = URL::temporarySignedRoute(
		'privacy.exports.download',
		Carbon::now()->addMinutes( 5 ),
		[ 'path' => $path ],
	);

	$this->get( $url )->assertForbidden();
} );

it( 'returns 404 when the file is missing', function (): void {
	$path = 'privacy-exports/missing.json';

	$url = URL::temporarySignedRoute(
		'privacy.exports.download',
		Carbon::now()->addMinutes( 5 ),
		[ 'path' => $path ],
	);

	$this->get( $url )->assertNotFound();
} );
