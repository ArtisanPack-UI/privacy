<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Models\PrivacyPolicy;
use ArtisanPackUI\Privacy\Services\ReconsentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'name' )->nullable();
		$table->string( 'email' )->nullable();
	} );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

it( 'accepts a valid reconsent submission as JSON', function (): void {
	$subject = TestSubject::create();

	Consent::factory()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'policy_version'   => '0.9.0',
	] );

	$policy = PrivacyPolicy::factory()->active()->create( [
		'requires_reconsent' => true,
		'version'            => '1.0.0',
	] );

	$this->actingAs( $subject )
		->postJson( '/privacy/reconsent', [ 'version' => '1.0.0' ] )
		->assertOk()
		->assertJson( [ 'ok' => true, 'version' => '1.0.0' ] );

	expect( app( ReconsentService::class )->isUpToDate( $subject ) )->toBeTrue();
} );

it( 'returns 409 when the supplied version is no longer current', function (): void {
	$subject = TestSubject::create();

	PrivacyPolicy::factory()->active()->create( [
		'requires_reconsent' => true,
		'version'            => '2.0.0',
	] );

	$this->actingAs( $subject )
		->postJson( '/privacy/reconsent', [ 'version' => '1.0.0' ] )
		->assertStatus( 409 )
		->assertJsonPath( 'ok', false );
} );

it( 'returns 401 for guests', function (): void {
	PrivacyPolicy::factory()->active()->create( [
		'requires_reconsent' => true,
		'version'            => '1.0.0',
	] );

	$this->postJson( '/privacy/reconsent', [ 'version' => '1.0.0' ] )
		->assertStatus( 401 );
} );
