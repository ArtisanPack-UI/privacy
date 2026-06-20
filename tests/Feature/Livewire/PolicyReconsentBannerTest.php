<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Livewire\PolicyReconsentBanner;
use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Models\PrivacyPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
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

it( 'hides itself when no reconsent is required', function (): void {
	PrivacyPolicy::factory()->active()->create( [ 'requires_reconsent' => false ] );

	Livewire::test( PolicyReconsentBanner::class )
		->assertSet( 'visible', false );
} );

it( 'shows when the active policy requires reconsent and the user is out-of-date', function (): void {
	$subject = TestSubject::create();
	Consent::factory()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'policy_version'   => '0.9.0',
	] );

	PrivacyPolicy::factory()->active()->create( [
		'requires_reconsent' => true,
		'version'            => '1.0.0',
	] );

	Livewire::actingAs( $subject )
		->test( PolicyReconsentBanner::class )
		->assertSet( 'visible', true )
		->assertSet( 'policyVersion', '1.0.0' );
} );

it( 'accepts the new policy and updates consent rows', function (): void {
	$subject = TestSubject::create();
	Consent::factory()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'policy_version'   => '0.9.0',
	] );

	PrivacyPolicy::factory()->active()->create( [
		'requires_reconsent' => true,
		'version'            => '1.0.0',
	] );

	Livewire::actingAs( $subject )
		->test( PolicyReconsentBanner::class )
		->call( 'accept' )
		->assertSet( 'visible', false )
		->assertDispatched( 'privacy:reconsent-given' );

	$consent = Consent::query()
		->where( 'consentable_type', $subject->getMorphClass() )
		->where( 'consentable_id', $subject->getKey() )
		->firstOrFail();

	expect( $consent->policy_version )->toBe( '1.0.0' );
} );

it( 'forwards mount-time customisation props', function (): void {
	$subject = TestSubject::create();
	Consent::factory()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'policy_version'   => '0.9.0',
	] );
	PrivacyPolicy::factory()->active()->create( [
		'requires_reconsent' => true,
		'version'            => '1.0.0',
	] );

	Livewire::actingAs( $subject )
		->test( PolicyReconsentBanner::class, [
			'class'         => 'banner--xl',
			'buttonClasses' => 'btn-xl',
			'labels'        => [ 'accept' => 'Accept new version' ],
		] )
		->assertSet( 'class', 'banner--xl' )
		->assertSet( 'buttonClasses', 'btn-xl' )
		->assertSee( 'Accept new version' );
} );
