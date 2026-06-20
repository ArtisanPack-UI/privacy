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

it( 'returns 401 when an unauthenticated visitor requests their history', function (): void {
	$this->getJson( '/api/privacy/data-requests' )->assertUnauthorized();
} );

it( 'returns the authenticated subject history with download URLs for completed exports', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	$export = DataRequest::query()->create( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_EXPORT,
		'status'           => DataRequest::STATUS_COMPLETED,
		'data'             => [ 'download_url' => 'https://example.test/export.json' ],
	] );

	$pending = DataRequest::query()->create( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_ACCESS,
		'status'           => DataRequest::STATUS_PENDING,
	] );

	$response = $this->getJson( '/api/privacy/data-requests?limit=10' );

	$response->assertOk();
	$response->assertJsonCount( 2, 'requests' );

	$payload = $response->json();
	$ids     = array_column( $payload['requests'], 'id' );

	expect( $ids )->toContain( $export->id, $pending->id );

	$exportPayload = collect( $payload['requests'] )->firstWhere( 'id', $export->id );

	expect( $exportPayload['download_url'] )->toBe( 'https://example.test/export.json' );

	$pendingPayload = collect( $payload['requests'] )->firstWhere( 'id', $pending->id );

	expect( $pendingPayload['download_url'] )->toBeNull();
} );

it( 'isolates subjects so user A cannot see user Bs request history', function (): void {
	$alice = TestSubject::create();
	$bob   = TestSubject::create();

	DataRequest::query()->create( [
		'requestable_type' => $alice->getMorphClass(),
		'requestable_id'   => $alice->getKey(),
		'type'             => DataRequest::TYPE_EXPORT,
		'status'           => DataRequest::STATUS_COMPLETED,
		'data'             => [ 'download_url' => 'https://example.test/alice-export.json' ],
	] );

	$bobsRequest = DataRequest::query()->create( [
		'requestable_type' => $bob->getMorphClass(),
		'requestable_id'   => $bob->getKey(),
		'type'             => DataRequest::TYPE_ACCESS,
		'status'           => DataRequest::STATUS_PENDING,
	] );

	$this->actingAs( $alice );
	$response = $this->getJson( '/api/privacy/data-requests' );

	$response->assertOk();

	$ids = array_column( $response->json( 'requests' ), 'id' );

	expect( $ids )->not->toContain( $bobsRequest->id );
	expect( count( $ids ) )->toBe( 1 );
} );
