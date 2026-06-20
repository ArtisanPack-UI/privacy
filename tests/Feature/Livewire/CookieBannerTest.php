<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Livewire\CookieBanner;
use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Services\ConsentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	if ( ! class_exists( Livewire::class ) ) {
		$this->markTestSkipped( 'Livewire is not installed.' );
	}

	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'name' )->nullable();
	} );

	config()->set( 'artisanpack.privacy.consent.storage', 'cookie' );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

it( 'mounts with the configured banner defaults', function (): void {
	Livewire::test( CookieBanner::class )
		->assertSet( 'position', config( 'artisanpack.privacy.ui.cookie_banner.position', 'bottom' ) )
		->assertSet( 'style', config( 'artisanpack.privacy.ui.cookie_banner.style', 'bar' ) )
		->assertSet( 'visible', true );
} );

it( 'accepts all categories and closes the banner', function (): void {
	Livewire::test( CookieBanner::class )
		->call( 'acceptAll' )
		->assertSet( 'visible', false )
		->assertDispatched( 'privacy:consent-updated' )
		->assertDispatched( 'privacy:banner-closed' );

	$queued = collect( Cookie::getQueuedCookies() )
		->first( fn ( $cookie ) => 'privacy_consent' === $cookie->getName() );

	expect( $queued )->not->toBeNull();
	$decoded = json_decode( (string) $queued->getValue(), true );
	expect( $decoded['analytics'] ?? false )->toBeTrue();
} );

it( 'rejects all but keeps the required categories on', function (): void {
	Livewire::test( CookieBanner::class )
		->call( 'rejectAll' )
		->assertSet( 'visible', false )
		->assertDispatched( 'privacy:consent-updated' );

	$queued = collect( Cookie::getQueuedCookies() )
		->first( fn ( $cookie ) => 'privacy_consent' === $cookie->getName() );

	$decoded = json_decode( (string) $queued->getValue(), true );
	expect( $decoded['necessary'] ?? false )->toBeTrue();
	expect( $decoded['analytics'] ?? true )->toBeFalse();
	expect( $decoded['marketing'] ?? true )->toBeFalse();
} );

it( 'accepts only the selected categories plus required ones', function (): void {
	Livewire::test( CookieBanner::class )
		->call( 'acceptSelected', [ 'analytics' ] )
		->assertSet( 'visible', false )
		->assertDispatched( 'privacy:consent-updated' );

	$queued = collect( Cookie::getQueuedCookies() )
		->first( fn ( $cookie ) => 'privacy_consent' === $cookie->getName() );
	$decoded = json_decode( (string) $queued->getValue(), true );

	expect( $decoded['necessary'] ?? false )->toBeTrue();
	expect( $decoded['analytics'] ?? false )->toBeTrue();
	expect( $decoded['marketing'] ?? true )->toBeFalse();
} );

it( 'opens the preferences panel without closing the banner', function (): void {
	Livewire::test( CookieBanner::class )
		->call( 'openPreferences' )
		->assertSet( 'showPreferences', true )
		->assertSet( 'visible', true );
} );

it( 'pre-populates selected with required categories on for the customise panel', function (): void {
	Livewire::test( CookieBanner::class )
		->assertSet( 'selected.necessary', true )
		->assertSet( 'selected.analytics', false )
		->assertSet( 'selected.marketing', false );
} );

it( 'saveSelected commits whatever is currently in selected', function (): void {
	Livewire::test( CookieBanner::class )
		->call( 'openPreferences' )
		->set( 'selected.analytics', true )
		->call( 'saveSelected' )
		->assertSet( 'visible', false )
		->assertDispatched( 'privacy:consent-updated' );

	$queued = collect( Cookie::getQueuedCookies() )
		->first( fn ( $cookie ) => 'privacy_consent' === $cookie->getName() );
	$decoded = json_decode( (string) $queued->getValue(), true );

	expect( $decoded['necessary'] ?? false )->toBeTrue();
	expect( $decoded['analytics'] ?? false )->toBeTrue();
	expect( $decoded['marketing'] ?? true )->toBeFalse();
} );

it( 'pins required categories on even when a client flips selected.required to false', function (): void {
	Livewire::test( CookieBanner::class )
		->set( 'selected.necessary', false )
		->assertSet( 'selected.necessary', true );
} );

it( 'persists grants to the database when storage allows it and an authenticated user is acting', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'both' );

	$subject = TestSubject::create();
	$this->actingAs( $subject );

	Livewire::test( CookieBanner::class )
		->call( 'acceptAll' );

	expect(
		Consent::query()
			->forSubject( $subject )
			->active()
			->count(),
	)->toBe( count( (array) config( 'artisanpack.privacy.cookie_categories' ) ) );
} );

it( 'flips visible to false after acceptAll', function (): void {
	Livewire::test( CookieBanner::class )
		->assertSet( 'visible', true )
		->call( 'acceptAll' )
		->assertSet( 'visible', false );
} );

it( 'openFromGlobal re-opens the banner with the preferences panel already expanded', function (): void {
	$component = Livewire::test( CookieBanner::class )
		->call( 'acceptAll' )
		->assertSet( 'visible', false )
		->assertSet( 'showPreferences', false )
		->call( 'openFromGlobal' );

	$component
		->assertSet( 'visible', true )
		->assertSet( 'showPreferences', true );
} );

it( 'pre-seeds selected from a prior cookie via previousSelections', function (): void {
	$service = Mockery::mock( ConsentService::class )->makePartial();
	$service->shouldReceive( 'getConsentCookie' )
		->andReturn( [ 'necessary' => true, 'analytics' => true, 'marketing' => false ] );
	app()->instance( ConsentService::class, $service );

	Livewire::test( CookieBanner::class )
		->assertSet( 'selected.necessary', true )
		->assertSet( 'selected.analytics', true )
		->assertSet( 'selected.marketing', false );
} );

it( 'previousSelections treats string "false" as false (no GDPR over-grant)', function (): void {
	$service = Mockery::mock( ConsentService::class )->makePartial();
	$service->shouldReceive( 'getConsentCookie' )
		->andReturn( [ 'necessary' => true, 'analytics' => 'false', 'marketing' => '0' ] );
	app()->instance( ConsentService::class, $service );

	Livewire::test( CookieBanner::class )
		->assertSet( 'selected.analytics', false )
		->assertSet( 'selected.marketing', false );
} );

it( 'previousSelections falls back to DB consents when the cookie is empty (database storage mode)', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'database' );

	$subject = TestSubject::create();
	app( ConsentService::class )->grantConsent( 'analytics', $subject );
	$this->actingAs( $subject );

	Livewire::test( CookieBanner::class )
		->assertSet( 'selected.analytics', true )
		->assertSet( 'selected.marketing', false );
} );

it( 'hasExpiredConsent triggers re-prompt when the visitor has an expired row', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'both' );

	$subject = TestSubject::create();
	$this->actingAs( $subject );

	Consent::query()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'category'         => 'analytics',
		'granted'          => true,
		'regulation'       => 'gdpr',
		'expires_at'       => now()->subDay(),
	] );

	// Simulate the visitor's cookie still being on disk via facade mock —
	// without it, hasExistingConsent() returns false and the banner shows
	// for that reason instead.
	Cookie::shouldReceive( 'get' )->andReturn( json_encode( [ 'analytics' => true ] ) );
	Cookie::shouldReceive( 'queue' )->andReturnNull();
	Cookie::shouldReceive( 'getQueuedCookies' )->andReturn( [] );
	Cookie::shouldReceive( 'has' )->andReturn( true );

	Livewire::test( CookieBanner::class )->assertSet( 'visible', true );
} );

it( 'hasExpiredConsent survives the purge command soft-withdrawal', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'both' );

	$subject = TestSubject::create();
	$this->actingAs( $subject );

	Consent::query()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'category'         => 'analytics',
		'granted'          => true,
		'regulation'       => 'gdpr',
		'expires_at'       => now()->subDay(),
	] );

	// Run the purge — this sets withdrawn_at on the expired row.
	$this->artisan( 'privacy:purge-expired' )->assertExitCode( 0 );

	Cookie::shouldReceive( 'get' )
		->andReturnUsing( fn ( string $name ) => 'privacy_consent' === $name
			? json_encode( [ 'analytics' => true ] )
			: null );
	Cookie::shouldReceive( 'queue' )->andReturnNull();
	Cookie::shouldReceive( 'getQueuedCookies' )->andReturn( [] );

	// Banner must still trigger re-prompt — the purge cancelling re-consent
	// was the worst bug in the review.
	Livewire::test( CookieBanner::class )->assertSet( 'visible', true );
} );

it( 'hasExpiredConsent stops re-prompting once the visitor has re-consented', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'both' );

	$subject = TestSubject::create();
	$this->actingAs( $subject );

	// Day 1 — visitor granted analytics with an expiry. Use the query
	// builder to backdate `created_at` because Eloquent's save() always
	// refreshes timestamps.
	$old = Consent::query()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'category'         => 'analytics',
		'granted'          => true,
		'regulation'       => 'gdpr',
		'expires_at'       => now()->subDay(),
	] );
	Consent::query()->where( 'id', $old->id )->update( [
		'created_at' => now()->subDays( 2 ),
		'updated_at' => now()->subDays( 2 ),
	] );

	// Day N — visitor re-consents (the service supersedes the old row).
	app( ConsentService::class )->grantConsent( 'analytics', $subject );

	// Call hasExpiredConsent directly via reflection — Livewire::test
	// adds enough lifecycle wrapping that mocking the Cookie facade for
	// hasExistingConsent fights with the grantConsent call above. The
	// query is what the review flagged; verify it.
	$component  = new CookieBanner();
	$reflection = new ReflectionMethod( $component, 'hasExpiredConsent' );
	$reflection->setAccessible( true );

	expect( $reflection->invoke( $component ) )->toBeFalse();
} );
