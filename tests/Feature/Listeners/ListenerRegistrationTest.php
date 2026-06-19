<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Events\ConsentGiven;
use ArtisanPackUI\Privacy\Events\ConsentWithdrawn;
use ArtisanPackUI\Privacy\Events\DataAccessRequested;
use ArtisanPackUI\Privacy\Events\DataBreach;
use ArtisanPackUI\Privacy\Events\DataDeletionRequested;
use ArtisanPackUI\Privacy\Events\DataExportRequested;
use ArtisanPackUI\Privacy\Models\BreachNotification;
use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;
use ArtisanPackUI\Privacy\Notifications\AdminDataRequestNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Mockery as M;
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

it( 'logs an info entry when LogConsentActivity handles ConsentGiven', function (): void {
	Log::spy();

	$consent = Consent::factory()->create( [ 'consentable_type' => 'test_subject', 'consentable_id' => 1 ] );

	ConsentGiven::dispatch( $consent );

	Log::shouldHaveReceived( 'info' )
		->with( 'privacy.consent.granted', M::on( fn ( array $context ): bool => $context['id'] === $consent->id ) )
		->once();
} );

it( 'logs an info entry when LogConsentActivity handles ConsentWithdrawn', function (): void {
	Log::spy();

	$consent = Consent::factory()->withdrawn()->create( [ 'consentable_type' => 'test_subject', 'consentable_id' => 1 ] );

	ConsentWithdrawn::dispatch( $consent );

	Log::shouldHaveReceived( 'info' )
		->with( 'privacy.consent.withdrawn', M::on( fn ( array $context ): bool => $context['id'] === $consent->id ) )
		->once();
} );

it( 'marks an access request as processing when auto-process is enabled', function (): void {
	config()->set( 'artisanpack.privacy.data_requests.auto_process.access', true );
	config()->set( 'artisanpack.privacy.data_requests.notify_admin', false );
	config()->set( 'artisanpack.privacy.data_requests.require_verification', false );

	$request = DataRequest::factory()->create( [
		'type'             => DataRequest::TYPE_ACCESS,
		'status'           => DataRequest::STATUS_PENDING,
		'requestable_type' => 'test_subject',
		'requestable_id'   => 1,
	] );

	DataAccessRequested::dispatch( $request->fresh() );

	expect( $request->fresh()->status )->toBe( DataRequest::STATUS_PROCESSING );
	expect( DataRequestLog::query()->where( 'data_request_id', $request->id )->exists() )->toBeTrue();
} );

it( 'leaves an access request untouched when auto-process is disabled', function (): void {
	config()->set( 'artisanpack.privacy.data_requests.auto_process.access', false );
	config()->set( 'artisanpack.privacy.data_requests.notify_admin', false );

	$request = DataRequest::factory()->create( [
		'type'             => DataRequest::TYPE_ACCESS,
		'status'           => DataRequest::STATUS_PENDING,
		'requestable_type' => 'test_subject',
		'requestable_id'   => 1,
	] );

	DataAccessRequested::dispatch( $request->fresh() );

	expect( $request->fresh()->status )->toBe( DataRequest::STATUS_PENDING );
} );

it( 'marks an export request as processing when auto-process is enabled', function (): void {
	config()->set( 'artisanpack.privacy.data_requests.auto_process.export', true );
	config()->set( 'artisanpack.privacy.data_requests.notify_admin', false );
	config()->set( 'artisanpack.privacy.data_requests.require_verification', false );

	$request = DataRequest::factory()->create( [
		'type'             => DataRequest::TYPE_EXPORT,
		'status'           => DataRequest::STATUS_PENDING,
		'requestable_type' => 'test_subject',
		'requestable_id'   => 1,
	] );

	DataExportRequested::dispatch( $request->fresh() );

	expect( $request->fresh()->status )->toBe( DataRequest::STATUS_PROCESSING );
} );

it( 'emails the admin when notify_admin is enabled and an access request is dispatched', function (): void {
	config()->set( 'artisanpack.privacy.data_requests.notify_admin', true );
	config()->set( 'artisanpack.privacy.data_requests.admin_email', 'admin@example.com' );
	config()->set( 'artisanpack.privacy.data_requests.auto_process.access', false );

	Notification::fake();

	$request = DataRequest::factory()->create( [
		'type'             => DataRequest::TYPE_ACCESS,
		'status'           => DataRequest::STATUS_PENDING,
		'requestable_type' => 'test_subject',
		'requestable_id'   => 1,
	] );

	DataAccessRequested::dispatch( $request->fresh() );

	Notification::assertSentOnDemand( AdminDataRequestNotification::class );
} );

it( 'emails the admin for a deletion request when notify_admin is enabled', function (): void {
	config()->set( 'artisanpack.privacy.data_requests.notify_admin', true );
	config()->set( 'artisanpack.privacy.data_requests.admin_email', 'admin@example.com' );

	Notification::fake();

	$request = DataRequest::factory()->create( [
		'type'             => DataRequest::TYPE_DELETION,
		'status'           => DataRequest::STATUS_PENDING,
		'requestable_type' => 'test_subject',
		'requestable_id'   => 1,
	] );

	DataDeletionRequested::dispatch( $request->fresh() );

	Notification::assertSentOnDemand( AdminDataRequestNotification::class );
} );

it( 'does not email the admin when notify_admin is disabled', function (): void {
	config()->set( 'artisanpack.privacy.data_requests.notify_admin', false );

	Notification::fake();

	$request = DataRequest::factory()->create( [
		'type'             => DataRequest::TYPE_DELETION,
		'status'           => DataRequest::STATUS_PENDING,
		'requestable_type' => 'test_subject',
		'requestable_id'   => 1,
	] );

	DataDeletionRequested::dispatch( $request->fresh() );

	Notification::assertNothingSent();
} );

it( 'starts the breach workflow when NotifyDataBreach handles a DataBreach', function (): void {
	config()->set( 'artisanpack.privacy.data_requests.admin_email', 'admin@example.com' );

	Illuminate\Support\Facades\Mail::spy();
	Log::spy();

	$breach = BreachNotification::factory()->create();

	DataBreach::dispatch( $breach->fresh() );

	Log::shouldHaveReceived( 'critical' )
		->with( 'privacy.data_breach.reported', M::on( fn ( array $context ): bool => $context['breach_id'] === $breach->id ) )
		->once();
	Illuminate\Support\Facades\Mail::shouldHaveReceived( 'raw' )->once();
} );

it( 'syncs cookie consents to the database on Login via SyncConsentOnLogin', function (): void {
	config()->set( 'artisanpack.privacy.consent.storage', 'both' );

	$subject = TestSubject::create( [ 'name' => 'Logged In' ] );

	request()->cookies->set( 'privacy_consent', json_encode( [ 'analytics' => true, 'marketing' => false ] ) );

	event( new Login( 'web', $subject, false ) );

	expect(
		Consent::query()
			->where( 'consentable_type', $subject->getMorphClass() )
			->where( 'consentable_id', $subject->getKey() )
			->where( 'category', 'analytics' )
			->active()
			->exists(),
	)->toBeTrue();
} );
