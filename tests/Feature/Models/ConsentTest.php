<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\Consent;

it( 'is active when granted, not withdrawn, and not expired', function (): void {
	$consent = Consent::factory()->create();

	expect( $consent->isActive() )->toBeTrue();
	expect( $consent->isExpired() )->toBeFalse();
	expect( $consent->isWithdrawn() )->toBeFalse();
} );

it( 'flags expired consents', function (): void {
	$consent = Consent::factory()->expired()->create();

	expect( $consent->isExpired() )->toBeTrue();
	expect( $consent->isActive() )->toBeFalse();
} );

it( 'flags withdrawn consents', function (): void {
	$consent = Consent::factory()->withdrawn()->create();

	expect( $consent->isWithdrawn() )->toBeTrue();
	expect( $consent->isActive() )->toBeFalse();
} );

it( 'casts metadata to an array and granted to a boolean', function (): void {
	$consent = Consent::factory()->create( [
		'metadata' => [ 'source' => 'banner' ],
		'granted'  => 1,
	] );

	expect( $consent->metadata )->toBe( [ 'source' => 'banner' ] );
	expect( $consent->granted )->toBeTrue();
} );

it( 'scopes active to exclude expired and withdrawn rows', function (): void {
	Consent::factory()->count( 2 )->create();
	Consent::factory()->expired()->create();
	Consent::factory()->withdrawn()->create();

	expect( Consent::query()->active()->count() )->toBe( 2 );
} );

it( 'scopes forCategory to a single category', function (): void {
	Consent::factory()->create( [ 'category' => 'analytics' ] );
	Consent::factory()->create( [ 'category' => 'marketing' ] );

	expect( Consent::query()->forCategory( 'analytics' )->count() )->toBe( 1 );
} );
