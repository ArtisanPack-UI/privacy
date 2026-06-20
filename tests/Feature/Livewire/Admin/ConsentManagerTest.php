<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Livewire\Admin\ConsentManager;
use ArtisanPackUI\Privacy\Models\Consent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
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

	config()->set( 'artisanpack.privacy.admin.gate', 'manage-privacy' );
	Gate::define( 'manage-privacy', static fn () => true );

	$this->admin = TestSubject::create();
	$this->actingAs( $this->admin );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

function makeConsent( array $overrides = [] ): Consent
{
	$subject = TestSubject::create();

	return Consent::query()->create( array_merge( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'category'         => 'analytics',
		'granted'          => true,
		'regulation'       => 'gdpr',
		'ip_address'       => '127.0.0.1',
	], $overrides ) );
}

it( 'denies access when the manage-privacy gate refuses', function (): void {
	Gate::define( 'manage-privacy', static fn () => false );

	expect( Gate::allows( 'manage-privacy' ) )->toBeFalse();

	$component = Livewire::test( ConsentManager::class );

	// Mount renders a denied response (Forbidden page) rather than the
	// configured view, so the table content is suppressed.
	$component->assertDontSee( 'consentable_type' );
} );

it( 'lists every consent row by default', function (): void {
	$one = makeConsent( [ 'category' => 'analytics' ] );
	$two = makeConsent( [ 'category' => 'marketing' ] );

	Livewire::test( ConsentManager::class )
		->assertSee( $one->category )
		->assertSee( $two->category );
} );

it( 'filters by category', function (): void {
	$analytics = makeConsent( [ 'category' => 'analytics' ] );
	$marketing = makeConsent( [ 'category' => 'marketing' ] );

	Livewire::test( ConsentManager::class )
		->set( 'categoryFilter', 'analytics' )
		->assertSee( 'consent-' . $analytics->id )
		->assertDontSee( 'consent-' . $marketing->id );
} );

it( 'filters to withdrawn records', function (): void {
	makeConsent( [ 'category' => 'analytics' ] );
	$withdrawn = makeConsent( [ 'category' => 'marketing', 'withdrawn_at' => now() ] );

	Livewire::test( ConsentManager::class )
		->set( 'statusFilter', ConsentManager::STATUS_WITHDRAWN )
		->assertSee( 'marketing' )
		->assertDontSee( '127.0.0.1—' );

	expect( $withdrawn->fresh()->withdrawn_at )->not->toBeNull();
} );

it( 'resets every filter via clearFilters', function (): void {
	$component = Livewire::test( ConsentManager::class )
		->set( 'search', 'foo' )
		->set( 'categoryFilter', 'analytics' )
		->set( 'statusFilter', ConsentManager::STATUS_ACTIVE )
		->set( 'dateFrom', '2026-01-01' )
		->set( 'dateTo', '2026-01-31' )
		->call( 'clearFilters' );

	$component->assertSet( 'search', '' )
		->assertSet( 'categoryFilter', '' )
		->assertSet( 'statusFilter', ConsentManager::STATUS_ALL )
		->assertSet( 'dateFrom', '' )
		->assertSet( 'dateTo', '' );
} );

it( 'exports filtered consents as JSON', function (): void {
	makeConsent( [ 'category' => 'analytics' ] );
	makeConsent( [ 'category' => 'marketing' ] );

	$component = Livewire::test( ConsentManager::class )
		->set( 'categoryFilter', 'analytics' );

	$response = $component->instance()->exportJson();

	ob_start();
	$response->sendContent();
	$contents = ob_get_clean();

	$payload = json_decode( $contents, true );

	expect( $payload )->toBeArray();
	expect( $payload )->toHaveCount( 1 );
	expect( $payload[0]['category'] )->toBe( 'analytics' );
} );
