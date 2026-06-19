<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Events\DataAccessRequested;
use ArtisanPackUI\Privacy\Models\DataRequest;
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

it( 'rejects unauthenticated submissions with 403', function (): void {
	$this->postJson( '/api/privacy/data-requests', [
		'type' => DataRequest::TYPE_ACCESS,
	] )->assertForbidden();
} );

it( 'creates an access request and fires the event', function (): void {
	Event::fake( [ DataAccessRequested::class ] );

	$subject = TestSubject::create();

	$response = $this->actingAs( $subject )->postJson( '/api/privacy/data-requests', [
		'type'   => DataRequest::TYPE_ACCESS,
		'reason' => 'Audit',
	] );

	$response->assertCreated();
	$response->assertJson( [
		'type'              => DataRequest::TYPE_ACCESS,
		'status'            => DataRequest::STATUS_PENDING,
		'verification_sent' => true,
	] );

	Event::assertDispatched( DataAccessRequested::class );

	expect( DataRequest::query()->count() )->toBe( 1 );
} );

it( 'rejects a request type that is disabled via config (no UI bypass via curl)', function (): void {
	config()->set( 'artisanpack.privacy.data_requests.allowed_types', [ 'access', 'export' ] );

	$subject = TestSubject::create();

	$this->actingAs( $subject )->postJson( '/api/privacy/data-requests', [
		'type' => DataRequest::TYPE_DELETION,
	] )->assertUnprocessable()
		->assertJsonValidationErrors( [ 'type' ] );

	expect( DataRequest::query()->count() )->toBe( 0 );
} );

it( 'reflects require_verification=false in the response payload', function (): void {
	config()->set( 'artisanpack.privacy.data_requests.require_verification', false );

	$subject = TestSubject::create();

	$this->actingAs( $subject )->postJson( '/api/privacy/data-requests', [
		'type' => DataRequest::TYPE_ACCESS,
	] )->assertCreated()
		->assertJson( [ 'verification_sent' => false ] );
} );

it( 'validates the type field', function (): void {
	$subject = TestSubject::create();

	$this->actingAs( $subject )->postJson( '/api/privacy/data-requests', [
		'type' => 'invalid-type',
	] )->assertUnprocessable()
		->assertJsonValidationErrors( [ 'type' ] );
} );

it( 'accepts a missing reason', function (): void {
	$subject = TestSubject::create();

	$this->actingAs( $subject )->postJson( '/api/privacy/data-requests', [
		'type' => DataRequest::TYPE_EXPORT,
	] )->assertCreated();
} );
