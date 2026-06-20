<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\BreachNotification;
use ArtisanPackUI\Privacy\Models\ConsentCategory;
use ArtisanPackUI\Privacy\Models\PersonalDataMap;
use ArtisanPackUI\Privacy\Models\PrivacyPolicy;

it( 'creates and scopes consent categories', function (): void {
	ConsentCategory::factory()->create( [ 'active' => true ] );
	ConsentCategory::factory()->create( [ 'active' => false ] );

	expect( ConsentCategory::query()->active()->count() )->toBe( 1 );
} );

it( 'casts privacy policy attributes', function (): void {
	$policy = PrivacyPolicy::factory()->active()->create( [
		'sections' => [ [ 'heading' => 'Data we collect' ] ],
	] );

	expect( $policy->active )->toBeTrue();
	expect( $policy->published_at )->not->toBeNull();
	expect( $policy->sections )->toBe( [ [ 'heading' => 'Data we collect' ] ] );
} );

it( 'scopes privacy policies by regulation', function (): void {
	PrivacyPolicy::factory()->create( [ 'regulation' => null ] );
	PrivacyPolicy::factory()->create( [ 'regulation' => 'gdpr' ] );

	expect( PrivacyPolicy::query()->forRegulation( null )->count() )->toBe( 1 );
	expect( PrivacyPolicy::query()->forRegulation( 'gdpr' )->count() )->toBe( 1 );
} );

it( 'flags sensitive personal data fields via scope', function (): void {
	PersonalDataMap::factory()->create( [ 'sensitivity' => PersonalDataMap::SENSITIVITY_NORMAL ] );
	PersonalDataMap::factory()->create( [ 'sensitivity' => PersonalDataMap::SENSITIVITY_SENSITIVE ] );
	PersonalDataMap::factory()->create( [ 'sensitivity' => PersonalDataMap::SENSITIVITY_SPECIAL_CATEGORY ] );

	expect( PersonalDataMap::query()->sensitive()->count() )->toBe( 2 );
} );

it( 'casts breach notification attributes', function (): void {
	$breach = BreachNotification::factory()->create( [
		'data_types_affected' => [ 'email', 'phone' ],
	] );

	expect( $breach->data_types_affected )->toBe( [ 'email', 'phone' ] );
	expect( $breach->discovered_at )->not->toBeNull();
} );
