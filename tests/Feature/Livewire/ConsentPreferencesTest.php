<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Livewire\ConsentPreferences;
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

it( 'mounts with required categories pre-toggled and others off', function (): void {
	Livewire::test( ConsentPreferences::class )
		->assertSet( 'consents.necessary', true )
		->assertSet( 'consents.analytics', false )
		->assertSet( 'consents.marketing', false )
		->assertSet( 'dirty', false );
} );

it( 'toggles a non-required category and marks the form dirty', function (): void {
	Livewire::test( ConsentPreferences::class )
		->call( 'toggleCategory', 'analytics' )
		->assertSet( 'consents.analytics', true )
		->assertSet( 'dirty', true );
} );

it( 'refuses to toggle a required category off', function (): void {
	Livewire::test( ConsentPreferences::class )
		->call( 'toggleCategory', 'necessary' )
		->assertSet( 'consents.necessary', true )
		->assertSet( 'dirty', false );
} );

it( 'persists preferences and dispatches an update event on save', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'both' );

	$subject = TestSubject::create();
	$this->actingAs( $subject );

	Livewire::test( ConsentPreferences::class )
		->call( 'toggleCategory', 'analytics' )
		->call( 'save' )
		->assertSet( 'saved', true )
		->assertSet( 'dirty', false )
		->assertDispatched( 'privacy:consent-updated' );

	expect(
		Consent::query()
			->forSubject( $subject )
			->forCategory( 'analytics' )
			->active()
			->exists(),
	)->toBeTrue();
} );

it( 'writes preferences to the cookie even in cookie-only mode', function (): void {
	Livewire::test( ConsentPreferences::class )
		->call( 'toggleCategory', 'marketing' )
		->call( 'save' );

	$queued = collect( Cookie::getQueuedCookies() )
		->first( fn ( $cookie ) => 'privacy_consent' === $cookie->getName() );

	expect( $queued )->not->toBeNull();
	$decoded = json_decode( (string) $queued->getValue(), true );
	expect( $decoded['marketing'] ?? false )->toBeTrue();
} );

it( 'respects the showDescriptions and showCookieList prop overrides', function (): void {
	Livewire::test( ConsentPreferences::class, [ 'showDescriptions' => false, 'showCookieList' => true ] )
		->assertSet( 'showDescriptions', false )
		->assertSet( 'showCookieList', true );
} );
