<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\Consent;
use Illuminate\Database\Schema\Blueprint;
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

it( 'returns the configured categories and current consent map from GET /api/privacy/consent', function (): void {
	$response = $this->getJson( '/api/privacy/consent' );

	$response
		->assertOk()
		->assertJsonStructure( [ 'regulation', 'consents', 'categories' ] )
		->assertJsonPath( 'consents.necessary', true )
		->assertJsonPath( 'consents.analytics', false );
} );

it( 'returns the categories listing from GET /api/privacy/categories', function (): void {
	$response = $this->getJson( '/api/privacy/categories' );

	$response
		->assertOk()
		->assertJsonStructure( [ 'categories' => [ 'necessary', 'analytics' ] ] );
} );

it( 'grants a single category for an authenticated user via POST /api/privacy/consent', function (): void {
	$subject = TestSubject::create( [ 'name' => 'api-user' ] );

	$this->actingAs( $subject )
		->postJson( '/api/privacy/consent', [ 'category' => 'analytics', 'granted' => true ] )
		->assertOk()
		->assertJsonPath( 'consents.analytics', true );

	expect(
		Consent::query()
			->forSubject( $subject )
			->forCategory( 'analytics' )
			->active()
			->exists(),
	)->toBeTrue();
} );

it( 'accepts a bulk consent update', function (): void {
	$subject = TestSubject::create();

	$this->actingAs( $subject )
		->postJson( '/api/privacy/consent', [
			'consents' => [
				'analytics'  => true,
				'marketing'  => true,
				'functional' => false,
			],
		] )
		->assertOk()
		->assertJsonPath( 'consents.analytics', true )
		->assertJsonPath( 'consents.marketing', true )
		->assertJsonPath( 'consents.functional', false );

	expect(
		Consent::query()->forSubject( $subject )->forCategory( 'analytics' )->active()->exists(),
	)->toBeTrue();
	expect(
		Consent::query()->forSubject( $subject )->forCategory( 'marketing' )->active()->exists(),
	)->toBeTrue();
} );

it( 'forces required categories to stay granted even if the payload tries to deny them', function (): void {
	$subject = TestSubject::create();

	$this->actingAs( $subject )
		->postJson( '/api/privacy/consent', [
			'consents' => [
				'necessary' => false,
				'analytics' => false,
			],
		] )
		->assertOk()
		->assertJsonPath( 'consents.necessary', true );
} );

it( 'echoes the granted state in the POST response when no user is authenticated', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'cookie' );

	$this->postJson( '/api/privacy/consent', [
		'consents' => [ 'analytics' => true ],
	] )
		->assertOk()
		->assertJsonPath( 'consents.analytics', true );
} );

it( 'ignores unknown categories in the payload', function (): void {
	$subject = TestSubject::create();

	$response = $this->actingAs( $subject )
		->postJson( '/api/privacy/consent', [
			'consents' => [ 'totally-fake-category' => true ],
		] )
		->assertOk();

	expect( $response->json( 'consents' ) )->not->toHaveKey( 'totally-fake-category' );
	expect( Consent::query()->where( 'category', 'totally-fake-category' )->exists() )->toBeFalse();
} );

it( 'rejects a payload that is neither single-category nor bulk', function (): void {
	$this->postJson( '/api/privacy/consent', [
		'category' => 'analytics',
	] )->assertStatus( 422 );
} );
