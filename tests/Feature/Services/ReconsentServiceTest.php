<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Events\PolicyReconsentGiven;
use ArtisanPackUI\Privacy\Events\PolicyReconsentRequired;
use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Models\PrivacyPolicy;
use ArtisanPackUI\Privacy\Services\ReconsentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
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

it( 'resolves a regulation-tagged active policy even when the caller passes no regulation', function (): void {
	PrivacyPolicy::factory()->active()->create( [
		'regulation'         => 'gdpr',
		'version'            => '1.0.0',
		'requires_reconsent' => true,
	] );

	$service = app( ReconsentService::class );

	expect( $service->currentPolicy()?->version )->toBe( '1.0.0' );
	expect( $service->currentPolicy( 'gdpr' )?->version )->toBe( '1.0.0' );
} );

it( 'treats users with no consent rows as up-to-date so brand-new users don\'t see a reconsent prompt', function (): void {
	PrivacyPolicy::factory()->active()->create( [
		'requires_reconsent' => true,
		'version'            => '1.0.0',
	] );

	$subject = TestSubject::create();

	expect( app( ReconsentService::class )->isUpToDate( $subject ) )->toBeTrue();
} );

it( 'scopes grant() to the policy\'s regulation and skips withdrawn consents', function (): void {
	$subject = TestSubject::create();

	Consent::factory()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'regulation'       => 'gdpr',
		'category'         => 'analytics',
		'policy_version'   => '0.9.0',
	] );
	Consent::factory()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'regulation'       => 'ccpa',
		'category'         => 'sale',
		'policy_version'   => '0.5.0',
	] );
	Consent::factory()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'regulation'       => 'gdpr',
		'category'         => 'marketing',
		'policy_version'   => '0.9.0',
		'withdrawn_at'     => now()->subDay(),
	] );

	$policy = PrivacyPolicy::factory()->active()->create( [
		'regulation'         => 'gdpr',
		'requires_reconsent' => true,
		'version'            => '1.0.0',
	] );

	$updated = app( ReconsentService::class )->grant( $policy, $subject );

	expect( $updated )->toBe( 1 );
	expect( Consent::query()->where( 'regulation', 'ccpa' )->first()->policy_version )->toBe( '0.5.0' );
	expect( Consent::query()->whereNotNull( 'withdrawn_at' )->first()->policy_version )->toBe( '0.9.0' );
} );

it( 'returns up-to-date when no active policy requires reconsent', function (): void {
	$subject = TestSubject::create();
	$service = app( ReconsentService::class );

	expect( $service->isUpToDate( $subject ) )->toBeTrue();

	PrivacyPolicy::factory()->active()->create( [
		'requires_reconsent' => false,
		'version'            => '1.0.0',
	] );

	expect( $service->isUpToDate( $subject ) )->toBeTrue();
} );

it( 'flags a user with stale consent as out-of-date', function (): void {
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

	$service = app( ReconsentService::class );

	expect( $service->isUpToDate( $subject ) )->toBeFalse();
} );

it( 'records re-consent by updating policy_version on consent rows', function (): void {
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

	Event::fake();

	$service = app( ReconsentService::class );
	$updated = $service->grant( $policy, $subject );

	expect( $updated )->toBe( 1 );
	expect( $service->isUpToDate( $subject ) )->toBeTrue();
	Event::assertDispatched( PolicyReconsentGiven::class );
} );

it( 'respects the grace period before blocking', function (): void {
	config()->set( 'artisanpack.privacy.policy.block_on_no_reconsent', true );
	config()->set( 'artisanpack.privacy.policy.reconsent_grace_period_days', 30 );

	$subject = TestSubject::create();
	Consent::factory()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'policy_version'   => '0.9.0',
	] );

	PrivacyPolicy::factory()->active()->create( [
		'requires_reconsent' => true,
		'version'            => '1.0.0',
		'published_at'       => now()->subDays( 10 ),
	] );

	$service = app( ReconsentService::class );

	expect( $service->isBlocked( $subject ) )->toBeFalse();
} );

it( 'blocks once the grace period elapses', function (): void {
	config()->set( 'artisanpack.privacy.policy.block_on_no_reconsent', true );
	config()->set( 'artisanpack.privacy.policy.reconsent_grace_period_days', 5 );

	$subject = TestSubject::create();
	Consent::factory()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'policy_version'   => '0.9.0',
	] );

	PrivacyPolicy::factory()->active()->create( [
		'requires_reconsent' => true,
		'version'            => '1.0.0',
		'published_at'       => now()->subDays( 30 ),
	] );

	$service = app( ReconsentService::class );

	expect( $service->isBlocked( $subject ) )->toBeTrue();
} );

it( 'fires PolicyReconsentRequired when notifyRequired is called', function (): void {
	Event::fake();

	$policy = PrivacyPolicy::factory()->active()->create( [
		'requires_reconsent' => true,
		'version'            => '1.0.0',
	] );

	app( ReconsentService::class )->notifyRequired( $policy );

	Event::assertDispatched( PolicyReconsentRequired::class );
} );
