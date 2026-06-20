<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Livewire\PrivacyDashboard;
use ArtisanPackUI\Privacy\Models\DataRequest;
use Illuminate\Database\Schema\Blueprint;
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
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

it( 'renders a sign-in prompt for guests', function (): void {
	Livewire::test( PrivacyDashboard::class )
		->assertSee( __( 'Please sign in to view your privacy dashboard.' ) );
} );

it( 'lists the authenticated subjects request history', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	$request = DataRequest::query()->create( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_EXPORT,
		'status'           => DataRequest::STATUS_COMPLETED,
		'data'             => [ 'download_url' => 'https://example.test/export.json' ],
	] );

	Livewire::test( PrivacyDashboard::class )
		->assertSee( __( 'Request history' ) )
		->assertSee( ucfirst( DataRequest::TYPE_EXPORT ) )
		->assertSee( __( 'Download export' ) );

	expect( $request->fresh()->data['download_url'] )->toBe( 'https://example.test/export.json' );
} );

it( 'bumps the history version when a request is submitted by the embedded form', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	Livewire::test( PrivacyDashboard::class )
		->dispatch( 'privacy:data-request-submitted', id: 1, type: 'access' )
		->assertSet( 'requestsVersion', 1 );
} );

it( 'honours the showHistory toggle when set to false on mount', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	Livewire::test( PrivacyDashboard::class, [ 'showHistory' => false ] )
		->assertDontSee( __( 'Request history' ) );
} );

it( 'renders the policy link when a URL is supplied', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	Livewire::test( PrivacyDashboard::class, [ 'policyUrl' => 'https://example.test/policy' ] )
		->assertSee( 'https://example.test/policy' );
} );
