<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Services\ConsentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'name' )->nullable();
	} );

	config()->set( 'artisanpack.privacy.consent.storage', 'both' );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

it( 'EnsureConsentGiven aborts with 403 when consent is missing', function (): void {
	Route::middleware( 'privacy.consent:analytics' )
		->get( '/__test/analytics-only', fn () => 'ok' );

	$response = $this->get( '/__test/analytics-only' );

	$response->assertForbidden();
} );

it( 'EnsureConsentGiven lets the request through once consent is granted', function (): void {
	$subject = TestSubject::create();

	Route::middleware( 'privacy.consent:analytics' )
		->get( '/__test/analytics-pass', fn () => 'ok' );

	app( ConsentService::class )->grantConsent( 'analytics', $subject );

	$response = $this->actingAs( $subject )->get( '/__test/analytics-pass' );

	$response->assertOk();
	expect( $response->getContent() )->toBe( 'ok' );
} );

it( 'EnsureConsentGiven accepts multiple required categories', function (): void {
	$subject = TestSubject::create();

	Route::middleware( 'privacy.consent:analytics,marketing' )
		->get( '/__test/both-categories', fn () => 'ok' );

	app( ConsentService::class )->grantConsent( 'analytics', $subject );

	$this->actingAs( $subject )
		->get( '/__test/both-categories' )
		->assertForbidden();

	app( ConsentService::class )->grantConsent( 'marketing', $subject );

	$this->actingAs( $subject )
		->get( '/__test/both-categories' )
		->assertOk();
} );

it( 'EnsureConsentGiven redirects when configured to do so', function (): void {
	config()->set( 'artisanpack.privacy.middleware.ensure_consent', [
		'action'         => 'redirect',
		'redirect_route' => null,
		'redirect_url'   => '/__test/login',
	] );

	Route::middleware( 'privacy.consent:analytics' )
		->get( '/__test/redirected', fn () => 'ok' );

	$this->get( '/__test/redirected' )
		->assertRedirect( '/__test/login' );
} );

it( 'EnsureConsentGiven falls back to redirect_url when redirect_route is missing', function (): void {
	config()->set( 'artisanpack.privacy.middleware.ensure_consent', [
		'action'         => 'redirect',
		'redirect_route' => 'definitely.does.not.exist',
		'redirect_url'   => '/__test/login-fallback',
	] );

	Route::middleware( 'privacy.consent:analytics' )
		->get( '/__test/bad-redirect-route', fn () => 'ok' );

	// Without the Route::has() guard this would throw RouteNotFoundException
	// and surface as a 500.
	$this->get( '/__test/bad-redirect-route' )
		->assertRedirect( '/__test/login-fallback' );
} );

it( 'EnsureConsentGiven flashes the missing categories under a non-dotted session key', function (): void {
	config()->set( 'artisanpack.privacy.middleware.ensure_consent', [
		'action'         => 'redirect',
		'redirect_route' => null,
		'redirect_url'   => '/__test/login',
	] );

	Route::middleware( 'privacy.consent:analytics,marketing' )
		->get( '/__test/flash', fn () => 'ok' );

	$response = $this->get( '/__test/flash' );

	$response->assertSessionHas( 'privacyConsentRequired', [ 'analytics', 'marketing' ] );
} );

it( 'CheckCookieConsent attaches the consent map to the request and exposes it to views via a per-request composer', function (): void {
	Route::middleware( 'privacy.context' )
		->get( '/__test/view-context', function ( Request $request ) {
			expect( $request->attributes->get( 'privacy_consent' ) )->toBeArray();

			$rendered = view()->file( __DIR__ . '/../../fixtures/privacy-consent-view.blade.php' )->render();

			expect( $rendered )->toContain( 'necessary:true' );

			return 'ok';
		} );

	$this->get( '/__test/view-context' )->assertOk();
} );

it( 'CheckCookieConsent forces required categories on even when the cookie says otherwise', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'cookie' );

	app( ConsentService::class )->setConsentCookie( [
		'necessary' => false,
		'analytics' => false,
	] );

	$captured = null;

	Route::middleware( 'privacy.context' )
		->get( '/__test/required-forced', function ( Request $request ) use ( &$captured ) {
			$captured = $request->attributes->get( 'privacy_consent' );

			return 'ok';
		} );

	$this->get( '/__test/required-forced' );

	expect( $captured )->toBeArray();
	expect( $captured['necessary'] )->toBeTrue(); // forced on by required flag
	expect( $captured['analytics'] )->toBeFalse(); // cookie's false is honoured
} );

it( 'CheckCookieConsent does not leak privacyConsent into views when the middleware is not mounted', function (): void {
	// First request mounts the middleware and sets a non-trivial map.
	Route::middleware( 'privacy.context' )
		->get( '/__test/leak-first', fn () => 'ok' );

	// Second request does NOT mount the middleware — the composer must see
	// an empty map, not whatever was stashed during the first request.
	Route::get( '/__test/leak-second', function () {
		return view()->file( __DIR__ . '/../../fixtures/privacy-consent-view.blade.php' )->render();
	} );

	$this->get( '/__test/leak-first' );

	$response = $this->get( '/__test/leak-second' );

	$response->assertOk();
	$response->assertDontSee( 'necessary:true' );
} );
