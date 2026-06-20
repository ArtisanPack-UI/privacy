<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Services\ConsentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'name' )->nullable();
	} );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

it( 'persists to the database only when storage = database', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'database' );

	$subject = TestSubject::create();
	app( ConsentService::class )->grantConsent( 'analytics', $subject );

	expect( Consent::query()->count() )->toBe( 1 );
} );

it( 'writes to both backends when storage = both', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'both' );

	$subject = TestSubject::create();
	app( ConsentService::class )->grantConsent( 'analytics', $subject );

	expect( Consent::query()->count() )->toBe( 1 );
	$queued = collect( Cookie::getQueuedCookies() )
		->first( fn ( $cookie ) => 'privacy_consent' === $cookie->getName() );
	expect( $queued )->not->toBeNull();
} );

it( 'syncs cookie state to database rows for the just-authenticated user', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'both' );

	$subject = TestSubject::create();
	$service = app( ConsentService::class );

	request()->cookies->set( 'privacy_consent', json_encode( [
		'analytics' => true,
		'marketing' => false,
	] ) );

	$written = $service->syncCookieToDatabase( $subject );

	expect( $written )->toBe( 1 );
	expect(
		Consent::query()
			->forSubject( $subject )
			->forCategory( 'analytics' )
			->active()
			->exists(),
	)->toBeTrue();
} );

it( 'is idempotent — a second sync with the same cookie does not duplicate rows', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'both' );

	$subject = TestSubject::create();
	$service = app( ConsentService::class );

	request()->cookies->set( 'privacy_consent', json_encode( [ 'analytics' => true ] ) );

	$service->syncCookieToDatabase( $subject );
	$service->syncCookieToDatabase( $subject );

	expect(
		Consent::query()
			->forSubject( $subject )
			->forCategory( 'analytics' )
			->active()
			->count(),
	)->toBe( 1 );
} );

it( 'returns 0 from syncCookieToDatabase when storage = cookie only', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'cookie' );

	$subject = TestSubject::create();
	request()->cookies->set( 'privacy_consent', json_encode( [ 'analytics' => true ] ) );

	expect( app( ConsentService::class )->syncCookieToDatabase( $subject ) )->toBe( 0 );
	expect( Consent::query()->count() )->toBe( 0 );
} );

it( 'resolves a fingerprint guest identifier from request signals', function (): void {
	config()->set( 'artisanpack.privacy.consent.guest_identifier', 'fingerprint' );

	request()->server->set( 'HTTP_USER_AGENT', 'Mozilla/5.0' );
	request()->server->set( 'REMOTE_ADDR', '203.0.113.10' );

	$identifier = app( ConsentService::class )->resolveGuestIdentifier();

	expect( $identifier )->not->toBeNull();
	expect( $identifier )->toMatch( '/^[a-f0-9]{64}$/' );
} );

it( 'returns null guest identifier when the ip strategy has no client address', function (): void {
	config()->set( 'artisanpack.privacy.consent.guest_identifier', 'ip' );

	request()->server->remove( 'REMOTE_ADDR' );
	request()->server->remove( 'HTTP_CLIENT_IP' );
	request()->server->remove( 'HTTP_X_FORWARDED_FOR' );

	expect( app( ConsentService::class )->resolveGuestIdentifier() )->toBeNull();
} );
