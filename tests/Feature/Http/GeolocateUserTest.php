<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach( function (): void {
	config()->set( 'artisanpack.privacy.geolocation', [
		'enabled'         => true,
		'provider'        => 'ip-api',
		'cache_duration'  => 60,
		'fallback_region' => null,
		'maxmind'         => [ 'database' => null ],
	] );

	Cache::flush();
} );

it( 'sets the privacy_region request attribute from the geolocation provider', function (): void {
	Http::fake( [
		'ip-api.com/*' => Http::response( [
			'status'      => 'success',
			'countryCode' => 'US',
			'region'      => 'CA',
		] ),
	] );

	Route::middleware( 'privacy.geolocate' )
		->get( '/__test/geolocated', static fn () => request()->attributes->get( 'privacy_region' ) );

	$this->get( '/__test/geolocated' )
		->assertOk()
		->assertSee( 'US-CA' );
} );

it( 'does not overwrite an existing privacy_region attribute', function (): void {
	Http::fake( [
		'ip-api.com/*' => Http::response( [
			'status'      => 'success',
			'countryCode' => 'US',
			'region'      => 'CA',
		] ),
	] );

	$service = app( ArtisanPackUI\Privacy\Services\GeoLocationService::class );
	$request = Illuminate\Http\Request::create( '/' );
	$request->attributes->set( 'privacy_region', 'EU' );

	$middleware = new ArtisanPackUI\Privacy\Http\Middleware\GeolocateUser( $service );
	$response   = $middleware->handle( $request, static fn ( $req ) => response( (string) $req->attributes->get( 'privacy_region' ) ) );

	expect( $response->getContent() )->toBe( 'EU' );
} );

it( 'falls back to the configured region when the provider returns nothing', function (): void {
	config()->set( 'artisanpack.privacy.geolocation.fallback_region', 'EU' );

	Http::fake( [
		'ip-api.com/*' => Http::response( [ 'status' => 'fail' ] ),
	] );

	Route::middleware( 'privacy.geolocate' )
		->get( '/__test/geolocated-fallback', static fn () => request()->attributes->get( 'privacy_region' ) );

	$this->get( '/__test/geolocated-fallback' )
		->assertOk()
		->assertSee( 'EU' );
} );
