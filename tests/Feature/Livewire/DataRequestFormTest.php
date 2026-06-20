<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Events\DataAccessRequested;
use ArtisanPackUI\Privacy\Events\DataExportRequested;
use ArtisanPackUI\Privacy\Events\DataRectificationRequested;
use ArtisanPackUI\Privacy\Livewire\DataRequestForm;
use ArtisanPackUI\Privacy\Models\DataRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
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

it( 'mounts with the full default request type list', function (): void {
	$this->actingAs( TestSubject::create() );

	Livewire::test( DataRequestForm::class )
		->assertSet( 'requestTypes', [ 'access', 'export', 'deletion', 'rectification' ] )
		->assertSet( 'requireReason', false );
} );

it( 'limits the request types to the requestTypes prop', function (): void {
	$this->actingAs( TestSubject::create() );

	Livewire::test( DataRequestForm::class, [ 'requestTypes' => [ 'access', 'export' ] ] )
		->assertSet( 'requestTypes', [ 'access', 'export' ] );
} );

it( 'persists an access request and dispatches the success event', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	Event::fake( [ DataAccessRequested::class ] );

	Livewire::test( DataRequestForm::class )
		->set( 'type', DataRequest::TYPE_ACCESS )
		->set( 'reason', 'Auditing my data' )
		->call( 'submit' )
		->assertSet( 'submitted', true )
		->assertDispatched( 'privacy:data-request-submitted' );

	Event::assertDispatched( DataAccessRequested::class );

	expect( DataRequest::query()->ofType( DataRequest::TYPE_ACCESS )->count() )->toBe( 1 );
} );

it( 'persists an export request', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	Event::fake( [ DataExportRequested::class ] );

	Livewire::test( DataRequestForm::class )
		->set( 'type', DataRequest::TYPE_EXPORT )
		->call( 'submit' )
		->assertSet( 'submitted', true );

	Event::assertDispatched( DataExportRequested::class );
} );

it( 'persists a rectification request and fires the new event', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	Event::fake( [ DataRectificationRequested::class ] );

	Livewire::test( DataRequestForm::class )
		->set( 'type', DataRequest::TYPE_RECTIFICATION )
		->call( 'submit' )
		->assertSet( 'submitted', true );

	Event::assertDispatched( DataRectificationRequested::class );

	expect( DataRequest::query()->ofType( DataRequest::TYPE_RECTIFICATION )->count() )->toBe( 1 );
} );

it( 'requires the reason when requireReason is true', function (): void {
	$this->actingAs( TestSubject::create() );

	Livewire::test( DataRequestForm::class, [ 'requireReason' => true ] )
		->set( 'type', DataRequest::TYPE_ACCESS )
		->set( 'reason', '' )
		->call( 'submit' )
		->assertHasErrors( [ 'reason' ] )
		->assertSet( 'submitted', false );
} );

it( 'rejects unknown request types', function (): void {
	$this->actingAs( TestSubject::create() );

	Livewire::test( DataRequestForm::class, [ 'requestTypes' => [ 'access' ] ] )
		->set( 'type', DataRequest::TYPE_DELETION )
		->call( 'submit' )
		->assertHasErrors( [ 'type' ] )
		->assertSet( 'submitted', false );
} );

it( 'errors with the sign-in-required message when not authenticated', function (): void {
	$tester = Livewire::test( DataRequestForm::class )
		->set( 'type', DataRequest::TYPE_ACCESS )
		->call( 'submit' )
		->assertSet( 'submitted', false );

	$errors = $tester->errors();

	expect( $errors->get( 'type' ) )->toContain( 'You must be signed in to submit a privacy request.' );
} );

it( 'honours allowed_types config — disabled types are not selectable', function (): void {
	config()->set( 'artisanpack.privacy.data_requests.allowed_types', [ 'access', 'export' ] );

	$this->actingAs( TestSubject::create() );

	Livewire::test( DataRequestForm::class )
		->assertSet( 'requestTypes', [ 'access', 'export' ] );
} );

it( 'logs the swallowed exception via report() when submit blows up', function (): void {
	$this->actingAs( TestSubject::create() );

	Illuminate\Support\Facades\Exceptions::fake();

	// Swap the service for one that throws so submit() falls into the catch.
	$this->app->bind( ArtisanPackUI\Privacy\Services\DataRequestService::class, fn () => new class ( app( ArtisanPackUI\Privacy\Services\ConsentService::class ) ) extends ArtisanPackUI\Privacy\Services\DataRequestService {
		public function createAccessRequest( $subject, $reason = null ): DataRequest
		{
			throw new RuntimeException( 'simulated failure' );
		}
	} );

	Livewire::test( DataRequestForm::class )
		->set( 'type', DataRequest::TYPE_ACCESS )
		->call( 'submit' )
		->assertSet( 'submitted', false );

	Illuminate\Support\Facades\Exceptions::assertReported(
		fn ( RuntimeException $e ) => 'simulated failure' === $e->getMessage(),
	);
} );

it( 'startNewRequest resets the success state', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	Livewire::test( DataRequestForm::class )
		->set( 'type', DataRequest::TYPE_ACCESS )
		->call( 'submit' )
		->assertSet( 'submitted', true )
		->call( 'startNewRequest' )
		->assertSet( 'submitted', false )
		->assertSet( 'type', null )
		->assertSet( 'reason', '' );
} );

it( 'reports verificationSent=false when require_verification is disabled', function (): void {
	config()->set( 'artisanpack.privacy.data_requests.require_verification', false );

	$subject = TestSubject::create();
	$this->actingAs( $subject );

	Livewire::test( DataRequestForm::class )
		->set( 'type', DataRequest::TYPE_ACCESS )
		->call( 'submit' )
		->assertSet( 'verificationSent', false );
} );

it( 'reports verificationSent=true by default (matches the package default config)', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	Livewire::test( DataRequestForm::class )
		->set( 'type', DataRequest::TYPE_ACCESS )
		->call( 'submit' )
		->assertSet( 'verificationSent', true );
} );
