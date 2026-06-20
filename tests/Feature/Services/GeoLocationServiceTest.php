<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Services\GeoLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

it( 'looks up a country and region via ip-api', function (): void {
	Http::fake( [
		'ip-api.com/*' => Http::response( [
			'status'      => 'success',
			'countryCode' => 'US',
			'region'      => 'CA',
		] ),
	] );

	$service = new GeoLocationService();

	expect( $service->getCountryCode( '203.0.113.10' ) )->toBe( 'US' );
	expect( $service->getRegionCode( '203.0.113.10' ) )->toBe( 'US-CA' );
} );

it( 'returns null when the ip-api response is a failure', function (): void {
	Http::fake( [
		'ip-api.com/*' => Http::response( [ 'status' => 'fail' ] ),
	] );

	$service = new GeoLocationService();

	expect( $service->getCountryCode( '203.0.113.10' ) )->toBeNull();
	expect( $service->getRegionCode( '203.0.113.10' ) )->toBeNull();
} );

it( 'returns null when the ip-api endpoint errors', function (): void {
	Http::fake( [
		'ip-api.com/*' => Http::response( [], 500 ),
	] );

	$service = new GeoLocationService();

	expect( $service->getLocation( '203.0.113.10' ) )->toBeNull();
} );

it( 'falls back to the configured fallback region when geolocation is disabled', function (): void {
	config()->set( 'artisanpack.privacy.geolocation.enabled', false );
	config()->set( 'artisanpack.privacy.geolocation.fallback_region', 'EU' );

	$service = new GeoLocationService();

	expect( $service->getCountryCode( '203.0.113.10' ) )->toBe( 'EU' );
} );

it( 'falls back to the configured fallback when the lookup returns nothing', function (): void {
	config()->set( 'artisanpack.privacy.geolocation.fallback_region', 'US-CA' );

	Http::fake( [
		'ip-api.com/*' => Http::response( [ 'status' => 'fail' ] ),
	] );

	$service = new GeoLocationService();

	expect( $service->getCountryCode( '203.0.113.10' ) )->toBe( 'US' );
	expect( $service->getRegionCode( '203.0.113.10' ) )->toBe( 'US-CA' );
} );

it( 'caches the lookup result between calls', function (): void {
	Http::fake( [
		'ip-api.com/*' => Http::response( [
			'status'      => 'success',
			'countryCode' => 'DE',
			'region'      => null,
		] ),
	] );

	$service = new GeoLocationService();

	$service->getCountryCode( '203.0.113.20' );
	$service->getCountryCode( '203.0.113.20' );

	Http::assertSentCount( 1 );
} );

it( 'rejects invalid IP addresses without hitting the provider', function (): void {
	Http::fake( [
		'ip-api.com/*' => Http::response( [ 'status' => 'success', 'countryCode' => 'US' ] ),
	] );

	$service = new GeoLocationService();

	expect( $service->getCountryCode( 'not-an-ip' ) )->toBeNull();

	Http::assertNothingSent();
} );

it( 'resolves the region from the cloudflare CF-IPCountry header', function (): void {
	config()->set( 'artisanpack.privacy.geolocation.provider', 'cloudflare' );

	$service = new GeoLocationService();
	$request = Request::create( '/' );
	$request->headers->set( 'CF-IPCountry', 'de' );

	expect( $service->resolveRegion( $request ) )->toBe( 'DE' );
} );

it( 'ignores cloudflares XX placeholder', function (): void {
	config()->set( 'artisanpack.privacy.geolocation.provider', 'cloudflare' );

	$service = new GeoLocationService();
	$request = Request::create( '/' );
	$request->headers->set( 'CF-IPCountry', 'XX' );

	expect( $service->resolveRegion( $request ) )->toBeNull();
} );

it( 'returns the subdivision code from resolveRegion when available', function (): void {
	Http::fake( [
		'ip-api.com/*' => Http::response( [
			'status'      => 'success',
			'countryCode' => 'US',
			'region'      => 'CA',
		] ),
	] );

	$service = new GeoLocationService();
	$request = Request::create( '/', 'GET', [], [], [], [ 'REMOTE_ADDR' => '203.0.113.30' ] );

	expect( $service->resolveRegion( $request ) )->toBe( 'US-CA' );
} );

it( 'falls back to the country code in resolveRegion when no subdivision is available', function (): void {
	Http::fake( [
		'ip-api.com/*' => Http::response( [
			'status'      => 'success',
			'countryCode' => 'DE',
		] ),
	] );

	$service = new GeoLocationService();
	$request = Request::create( '/', 'GET', [], [], [], [ 'REMOTE_ADDR' => '203.0.113.31' ] );

	expect( $service->resolveRegion( $request ) )->toBe( 'DE' );
} );
