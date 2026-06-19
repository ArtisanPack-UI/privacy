<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Events\DataDeletionRequested;
use ArtisanPackUI\Privacy\Events\DataExportRequested;
use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Models\DataRequest;
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

it( 'returns true from privacyHasConsent when an active consent exists', function (): void {
	$subject = TestSubject::create();
	Consent::factory()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'category'         => 'analytics',
		'granted'          => true,
	] );

	expect( privacyHasConsent( 'analytics', $subject ) )->toBeTrue();
	expect( privacyHasConsent( 'marketing', $subject ) )->toBeFalse();
} );

it( 'returns the applicable regulation from privacyGetRegulation', function (): void {
	config()->set( 'artisanpack.privacy.regulations.gdpr.enabled', true );
	config()->set( 'artisanpack.privacy.regulations.ccpa.enabled', false );
	config()->set( 'artisanpack.privacy.geolocation.fallback_region', null );

	expect( privacyGetRegulation() )->toBe( 'gdpr' );
} );

it( 'creates and dispatches an export request through privacyRequestDataExport', function (): void {
	Event::fake( [ DataExportRequested::class ] );

	$subject = TestSubject::create();
	$request = privacyRequestDataExport( $subject );

	expect( $request )->toBeInstanceOf( DataRequest::class );
	expect( $request->type )->toBe( DataRequest::TYPE_EXPORT );
	Event::assertDispatched( DataExportRequested::class );
} );

it( 'creates and dispatches a deletion request through privacyRequestDataDeletion', function (): void {
	Event::fake( [ DataDeletionRequested::class ] );

	$subject = TestSubject::create();
	$request = privacyRequestDataDeletion( $subject, 'Account closed' );

	expect( $request->type )->toBe( DataRequest::TYPE_DELETION );
	expect( $request->reason )->toBe( 'Account closed' );
	Event::assertDispatched( DataDeletionRequested::class );
} );

it( 'anonymizes a model through privacyAnonymize', function (): void {
	$subject = TestSubject::create( [ 'name' => 'Jacob', 'email' => 'jacob@example.com' ] );

	$result = privacyAnonymize( $subject );

	expect( $result )->toBeTrue();
	$subject->refresh();
	expect( $subject->email )->toBe( 'j***@e***.com' );
} );

it( 'returns true from privacyCanDelete when no in-flight deletion exists', function (): void {
	$subject = TestSubject::create();

	expect( privacyCanDelete( $subject ) )->toBeTrue();
} );

it( 'returns false from privacyCanDelete while a pending deletion request exists', function (): void {
	$subject = TestSubject::create();
	DataRequest::factory()->create( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_DELETION,
		'status'           => DataRequest::STATUS_PENDING,
	] );

	expect( privacyCanDelete( $subject ) )->toBeFalse();
} );
