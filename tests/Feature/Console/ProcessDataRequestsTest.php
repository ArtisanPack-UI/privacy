<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'name' )->nullable();
	} );

	config( [
		'artisanpack.privacy.data_requests.require_verification' => false,
		'artisanpack.privacy.data_requests.auto_process.access'  => true,
		'artisanpack.privacy.data_requests.auto_process.export'  => true,
	] );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

it( 'processes pending access requests and skips deletion', function (): void {
	Notification::fake();

	$subject = TestSubject::create();

	$access = DataRequest::factory()->create( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_ACCESS,
		'status'           => DataRequest::STATUS_PENDING,
	] );

	$deletion = DataRequest::factory()->create( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_DELETION,
		'status'           => DataRequest::STATUS_PENDING,
	] );

	$this->artisan( 'privacy:process-requests' )->assertExitCode( 0 );

	expect( $access->fresh()->status )->toBe( DataRequest::STATUS_COMPLETED );
	expect( $deletion->fresh()->status )->toBe( DataRequest::STATUS_PENDING );
	expect( DataRequestLog::query()->where( 'data_request_id', $access->id )->where( 'action', 'auto_processed' )->count() )->toBe( 1 );
} );

it( 'skips processing when --dry-run is set', function (): void {
	$subject = TestSubject::create();

	$access = DataRequest::factory()->create( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_ACCESS,
		'status'           => DataRequest::STATUS_PENDING,
	] );

	$this->artisan( 'privacy:process-requests', [ '--dry-run' => true ] )->assertExitCode( 0 );

	expect( $access->fresh()->status )->toBe( DataRequest::STATUS_PENDING );
} );

it( 'limits processing to the requested type', function (): void {
	$subject = TestSubject::create();

	$access = DataRequest::factory()->create( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_ACCESS,
		'status'           => DataRequest::STATUS_PENDING,
	] );

	$export = DataRequest::factory()->create( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_EXPORT,
		'status'           => DataRequest::STATUS_PENDING,
	] );

	$this->artisan( 'privacy:process-requests', [ '--type' => 'access' ] )->assertExitCode( 0 );

	expect( $access->fresh()->status )->toBe( DataRequest::STATUS_COMPLETED );
	expect( $export->fresh()->status )->toBe( DataRequest::STATUS_PENDING );
} );

it( 'rejects invalid --type values', function (): void {
	$this->artisan( 'privacy:process-requests', [ '--type' => 'deletion' ] )
		->assertExitCode( Illuminate\Console\Command::INVALID );
} );

it( 'skips unverified requests when verification is required', function (): void {
	config( [ 'artisanpack.privacy.data_requests.require_verification' => true ] );

	$subject = TestSubject::create();

	$access = DataRequest::factory()->create( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_ACCESS,
		'status'           => DataRequest::STATUS_PENDING,
		'verified_at'      => null,
	] );

	$this->artisan( 'privacy:process-requests' )->assertExitCode( 0 );

	expect( $access->fresh()->status )->toBe( DataRequest::STATUS_PENDING );
} );

it( 'rejects requests whose subject has been deleted instead of silently completing', function (): void {
	$subject = TestSubject::create();

	$request = DataRequest::factory()->create( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => 99999,
		'type'             => DataRequest::TYPE_ACCESS,
		'status'           => DataRequest::STATUS_PENDING,
	] );

	$this->artisan( 'privacy:process-requests' )->assertExitCode( 0 );

	expect( $request->fresh()->status )->toBe( DataRequest::STATUS_REJECTED );
	expect( DataRequestLog::query()->where( 'data_request_id', $request->id )->where( 'action', 'auto_rejected' )->exists() )->toBeTrue();
} );

it( 'reports a specific message when the requested --type is disabled by config', function (): void {
	config( [ 'artisanpack.privacy.data_requests.auto_process.access' => false ] );

	$this->artisan( 'privacy:process-requests', [ '--type' => 'access' ] )
		->assertExitCode( 0 )
		->expectsOutputToContain( 'Type access is not configured for auto-processing' );
} );

it( 'reports when no types are auto-processable', function (): void {
	config( [
		'artisanpack.privacy.data_requests.auto_process.access' => false,
		'artisanpack.privacy.data_requests.auto_process.export' => false,
	] );

	$this->artisan( 'privacy:process-requests' )
		->assertExitCode( 0 )
		->expectsOutputToContain( 'No request types are configured for auto-processing.' );
} );
