<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Events\ConsentWithdrawn;
use ArtisanPackUI\Privacy\Models\Consent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
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

/**
 * Helper to create a consent row with an explicit expiry. Wrapped in a
 * conditional declare to keep it Pest-file-scoped without leaking into the
 * global namespace if other test files also need a similar fixture.
 */
if ( ! function_exists( 'privacyTestConsentExpiringAt' ) ) {
	function privacyTestConsentExpiringAt(
		TestSubject $subject,
		string $category,
		Illuminate\Support\Carbon $expiresAt,
		?Illuminate\Support\Carbon $withdrawnAt = null,
	): Consent {
		return Consent::query()->create( [
			'consentable_type' => $subject->getMorphClass(),
			'consentable_id'   => $subject->getKey(),
			'category'         => $category,
			'granted'          => null === $withdrawnAt,
			'regulation'       => 'gdpr',
			'expires_at'       => $expiresAt,
			'withdrawn_at'     => $withdrawnAt,
		] );
	}
}

it( 'withdraws expired consents but keeps the rows for audit', function (): void {
	$subject = TestSubject::create();

	$expired = privacyTestConsentExpiringAt( $subject, 'analytics', now()->subDay() );
	$future  = privacyTestConsentExpiringAt( $subject, 'marketing', now()->addDay() );

	$this->artisan( 'privacy:purge-expired' )->assertExitCode( 0 );

	$expired->refresh();
	$future->refresh();

	expect( $expired->granted )->toBeFalse();
	expect( $expired->withdrawn_at )->not->toBeNull();
	expect( $future->granted )->toBeTrue();
	expect( $future->withdrawn_at )->toBeNull();
} );

it( 'dispatches ConsentWithdrawn per row so audit listeners stay in sync', function (): void {
	Event::fake( [ ConsentWithdrawn::class ] );

	$subject = TestSubject::create();
	privacyTestConsentExpiringAt( $subject, 'analytics', now()->subDay() );
	privacyTestConsentExpiringAt( $subject, 'marketing', now()->subDay() );

	$this->artisan( 'privacy:purge-expired' )->assertExitCode( 0 );

	Event::assertDispatchedTimes( ConsentWithdrawn::class, 2 );
} );

it( 'deletes expired rows with --prune including ones already withdrawn', function (): void {
	$subject = TestSubject::create();

	$active    = privacyTestConsentExpiringAt( $subject, 'analytics', now()->subDay() );
	$withdrawn = privacyTestConsentExpiringAt( $subject, 'marketing', now()->subDay(), now()->subHour() );

	$this->artisan( 'privacy:purge-expired', [ '--prune' => true ] )->assertExitCode( 0 );

	expect( Consent::query()->whereKey( $active->id )->exists() )->toBeFalse();
	expect( Consent::query()->whereKey( $withdrawn->id )->exists() )->toBeFalse();
} );

it( 'dispatches ConsentWithdrawn before deleting active rows with --prune', function (): void {
	Event::fake( [ ConsentWithdrawn::class ] );

	$subject = TestSubject::create();
	privacyTestConsentExpiringAt( $subject, 'analytics', now()->subDay() );

	$this->artisan( 'privacy:purge-expired', [ '--prune' => true ] )->assertExitCode( 0 );

	Event::assertDispatched( ConsentWithdrawn::class );
} );

it( 'leaves rows untouched on --dry-run', function (): void {
	$subject = TestSubject::create();

	$expired = privacyTestConsentExpiringAt( $subject, 'analytics', now()->subDay() );

	$this->artisan( 'privacy:purge-expired', [ '--dry-run' => true ] )->assertExitCode( 0 );

	$expired->refresh();

	expect( $expired->granted )->toBeTrue();
	expect( $expired->withdrawn_at )->toBeNull();
} );
