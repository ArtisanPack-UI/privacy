<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Events\ConsentGiven;
use ArtisanPackUI\Privacy\Events\ConsentWithdrawn;
use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Services\ConsentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Event;
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

it( 'is resolvable from the container', function (): void {
	expect( app( ConsentService::class ) )->toBeInstanceOf( ConsentService::class );
	expect( app( 'privacy.consent' ) )->toBeInstanceOf( ConsentService::class );
} );

it( 'grants consent and fires the ConsentGiven event', function (): void {
	Event::fake( [ ConsentGiven::class ] );

	$subject = TestSubject::create( [ 'name' => 'Subject 1' ] );

	$consent = app( ConsentService::class )->grantConsent( 'analytics', $subject, [ 'source' => 'banner' ] );

	expect( $consent->granted )->toBeTrue();
	expect( $consent->metadata )->toBe( [ 'source' => 'banner' ] );
	expect( Consent::query()->count() )->toBe( 1 );
	Event::assertDispatched( ConsentGiven::class );
} );

it( 'considers an active consent as granted via hasConsent', function (): void {
	$subject = TestSubject::create();

	app( ConsentService::class )->grantConsent( 'analytics', $subject );

	expect( app( ConsentService::class )->hasConsent( 'analytics', $subject ) )->toBeTrue();
	expect( app( ConsentService::class )->hasConsent( 'marketing', $subject ) )->toBeFalse();
} );

it( 'withdraws old grants when granting again for the same category', function (): void {
	Event::fake( [ ConsentGiven::class, ConsentWithdrawn::class ] );

	$subject = TestSubject::create();
	$service = app( ConsentService::class );

	$service->grantConsent( 'analytics', $subject );
	$service->grantConsent( 'analytics', $subject );

	expect( Consent::query()->count() )->toBe( 2 );
	expect( Consent::query()->active()->count() )->toBe( 1 );
	Event::assertDispatchedTimes( ConsentGiven::class, 2 );
	Event::assertDispatchedTimes( ConsentWithdrawn::class, 1 );
} );

it( 'revokes consent and fires the ConsentWithdrawn event', function (): void {
	Event::fake( [ ConsentWithdrawn::class ] );

	$subject = TestSubject::create();
	$service = app( ConsentService::class );

	$service->grantConsent( 'analytics', $subject );
	$changed = $service->revokeConsent( 'analytics', $subject );

	expect( $changed )->toBeTrue();
	expect( $service->hasConsent( 'analytics', $subject ) )->toBeFalse();
	Event::assertDispatched( ConsentWithdrawn::class );
} );

it( 'grants every configured category via grantAllConsents', function (): void {
	$subject  = TestSubject::create();
	$consents = app( ConsentService::class )->grantAllConsents( $subject );

	$expected = array_keys( (array) config( 'artisanpack.privacy.cookie_categories' ) );

	expect( $consents )->toHaveCount( count( $expected ) );
	foreach ( $expected as $category ) {
		expect( app( ConsentService::class )->hasConsent( $category, $subject ) )->toBeTrue();
	}
} );

it( 'revokes only non-required categories via revokeAllConsents', function (): void {
	$subject = TestSubject::create();
	$service = app( ConsentService::class );

	$service->grantAllConsents( $subject );
	$service->revokeAllConsents( $subject );

	expect( $service->hasConsent( 'necessary', $subject ) )->toBeTrue();
	expect( $service->hasConsent( 'analytics', $subject ) )->toBeFalse();
	expect( $service->hasConsent( 'marketing', $subject ) )->toBeFalse();
} );

it( 'reads and writes the consent cookie', function (): void {
	$service = app( ConsentService::class );

	$service->setConsentCookie( [ 'analytics' => true, 'marketing' => false ] );

	$queued = collect( Cookie::getQueuedCookies() )
		->first( fn ( $cookie ) => 'privacy_consent' === $cookie->getName() );

	expect( $queued )->not->toBeNull();
	expect( json_decode( $queued->getValue(), true ) )
		->toBe( [ 'analytics' => true, 'marketing' => false ] );
} );

it( 'reflects the cookie when checking a guest subject without a model', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'cookie' );

	$service = app( ConsentService::class );
	$service->setConsentCookie( [ 'analytics' => true ] );

	request()->cookies->set( 'privacy_consent', json_encode( [ 'analytics' => true ] ) );

	expect( $service->hasConsent( 'analytics' ) )->toBeTrue();
	expect( $service->hasConsent( 'marketing' ) )->toBeFalse();
} );

it( 'returns the configured fallback regulation', function (): void {
	config()->set( 'artisanpack.privacy.regulations.gdpr.enabled', true );
	config()->set( 'artisanpack.privacy.regulations.ccpa.enabled', false );
	config()->set( 'artisanpack.privacy.geolocation.fallback_region', null );

	expect( app( ConsentService::class )->getApplicableRegulation() )->toBe( 'gdpr' );
} );

it( 'maps a fallback region to the matching regulation', function (): void {
	config()->set( 'artisanpack.privacy.geolocation.fallback_region', 'US-CA' );

	expect( app( ConsentService::class )->getApplicableRegulation() )->toBe( 'ccpa' );
} );

it( 'reports an expired consent as expired', function (): void {
	$consent = Consent::factory()->expired()->make();

	expect( app( ConsentService::class )->isConsentExpired( $consent ) )->toBeTrue();
} );
