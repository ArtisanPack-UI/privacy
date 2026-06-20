<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Livewire\Admin\ComplianceReport;
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

	$this->actingAs( TestSubject::create() );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

it( 'denies access when the gate refuses', function (): void {
	Gate::define( 'manage-privacy', static fn () => false );

	Livewire::test( ComplianceReport::class )
		->assertDontSee( 'Compliance report' );
} );

it( 'renders the default 30-day range and shows aggregate metrics', function (): void {
	$subject = TestSubject::create();
	Consent::query()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'category'         => 'analytics',
		'granted'          => true,
		'regulation'       => 'gdpr',
	] );

	Livewire::test( ComplianceReport::class )
		->assertSee( __( 'Compliance report' ) )
		->assertSet( 'from', now()->subDays( 30 )->toDateString() )
		->assertSet( 'to', now()->toDateString() )
		->assertSee( 'analytics' );
} );

it( 'exposes the enabled regulations to the filter dropdown', function (): void {
	config()->set( 'artisanpack.privacy.regulations', [
		'gdpr' => [ 'enabled' => true ],
		'ccpa' => [ 'enabled' => true ],
		'lgpd' => [ 'enabled' => false ],
	] );

	$component = Livewire::test( ComplianceReport::class );

	expect( $component->instance()->regulations )->toBe( [ 'gdpr', 'ccpa' ] );
} );

it( 'streams a CSV export with section/metric/value rows', function (): void {
	$component = Livewire::test( ComplianceReport::class );
	$response  = $component->instance()->exportCsv();

	expect( $response->headers->get( 'Content-Type' ) )->toContain( 'text/csv' );

	ob_start();
	$response->sendContent();
	$body = (string) ob_get_clean();

	expect( $body )->toContain( 'section,metric,value' );
	expect( $body )->toContain( 'consents,total' );
	expect( $body )->toContain( 'requests,total' );
	expect( $body )->toContain( 'breaches,total' );
} );
