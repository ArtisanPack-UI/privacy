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
