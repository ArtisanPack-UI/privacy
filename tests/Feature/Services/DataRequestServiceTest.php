<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Events\DataAccessRequested;
use ArtisanPackUI\Privacy\Events\DataDeletionRequested;
use ArtisanPackUI\Privacy\Events\DataExportRequested;
use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Services\DataRequestService;
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

it( 'creates an access request with a verification token and fires DataAccessRequested', function (): void {
	Event::fake( [ DataAccessRequested::class ] );

	$subject = TestSubject::create();
	$request = app( DataRequestService::class )->createAccessRequest( $subject, 'I want my data' );

	expect( $request->exists )->toBeTrue();
	expect( $request->type )->toBe( DataRequest::TYPE_ACCESS );
	expect( $request->status )->toBe( DataRequest::STATUS_PENDING );
	expect( $request->reason )->toBe( 'I want my data' );
	expect( $request->verification_token )->not->toBeNull();
	Event::assertDispatched(
		DataAccessRequested::class,
		fn ( DataAccessRequested $event ): bool => $event->request->is( $request ),
	);
} );

it( 'creates an export request and fires DataExportRequested', function (): void {
	Event::fake( [ DataExportRequested::class ] );

	$subject = TestSubject::create();
	$request = app( DataRequestService::class )->createExportRequest( $subject );

	expect( $request->type )->toBe( DataRequest::TYPE_EXPORT );
	Event::assertDispatched( DataExportRequested::class );
} );

it( 'creates a deletion request and fires DataDeletionRequested', function (): void {
	Event::fake( [ DataDeletionRequested::class ] );

	$subject = TestSubject::create();
	$request = app( DataRequestService::class )->createDeletionRequest( $subject, 'Closing account' );

	expect( $request->type )->toBe( DataRequest::TYPE_DELETION );
	expect( $request->reason )->toBe( 'Closing account' );
	Event::assertDispatched( DataDeletionRequested::class );
} );

it( 'computes the due date from the resolved regulation', function (): void {
	config()->set( 'artisanpack.privacy.regulations.gdpr.enabled', true );
	config()->set( 'artisanpack.privacy.regulations.ccpa.enabled', false );
	config()->set( 'artisanpack.privacy.geolocation.fallback_region', null );
	config()->set( 'artisanpack.privacy.data_requests.response_days.gdpr', 30 );

	$subject = TestSubject::create();
	$request = app( DataRequestService::class )->createAccessRequest( $subject );

	expect( $request->regulation )->toBe( 'gdpr' );
	expect( $request->due_at )->not->toBeNull();
	expect( $request->due_at->isAfter( now()->addDays( 28 ) ) )->toBeTrue();
	expect( $request->due_at->isBefore( now()->addDays( 32 ) ) )->toBeTrue();
} );

it( 'reports canDelete as true when no in-flight deletion request exists', function (): void {
	$subject = TestSubject::create();

	expect( app( DataRequestService::class )->canDelete( $subject ) )->toBeTrue();
} );

it( 'reports canDelete as false while a pending deletion request exists', function (): void {
	$subject = TestSubject::create();

	DataRequest::factory()->create( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_DELETION,
		'status'           => DataRequest::STATUS_PENDING,
	] );

	expect( app( DataRequestService::class )->canDelete( $subject ) )->toBeFalse();
} );

it( 'reports canDelete as true again once the deletion request is completed', function (): void {
	$subject = TestSubject::create();

	DataRequest::factory()->completed()->create( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_DELETION,
	] );

	expect( app( DataRequestService::class )->canDelete( $subject ) )->toBeTrue();
} );
