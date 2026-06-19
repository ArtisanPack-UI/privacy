<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Livewire\CookieBanner;
use ArtisanPackUI\Privacy\Models\Consent;
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
