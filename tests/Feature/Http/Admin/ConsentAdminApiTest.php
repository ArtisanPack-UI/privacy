<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\Consent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'name' )->nullable();
	} );

	config()->set( 'artisanpack.privacy.admin.gate', 'manage-privacy' );
	Gate::define( 'manage-privacy', static fn () => true );

	$this->admin = TestSubject::create();
	$this->actingAs( $this->admin );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

function makeConsentApi( array $overrides = [] ): Consent
{
	$subject = TestSubject::create();

	return Consent::query()->create( array_merge( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'category'         => 'analytics',
		'granted'          => true,
		'regulation'       => 'gdpr',
	], $overrides ) );
}

it( 'returns a paginated consent list', function (): void {
	makeConsentApi();
	makeConsentApi( [ 'category' => 'marketing' ] );

	$this->getJson( '/api/privacy/admin/consents' )
		->assertOk()
		->assertJsonStructure( [
			'data' => [ '*' => [ 'id', 'category', 'granted' ] ],
			'meta' => [ 'current_page', 'per_page', 'total', 'last_page' ],
			'categories',
		] )
		->assertJsonCount( 2, 'data' );
} );

it( 'filters by category', function (): void {
	makeConsentApi();
	makeConsentApi( [ 'category' => 'marketing' ] );

	$this->getJson( '/api/privacy/admin/consents?category=marketing' )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.category', 'marketing' );
} );

it( 'rejects unauthorized requests', function (): void {
	Gate::define( 'manage-privacy', static fn () => false );

	$this->getJson( '/api/privacy/admin/consents' )
		->assertForbidden();
} );
