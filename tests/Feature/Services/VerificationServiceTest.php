<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;
use ArtisanPackUI\Privacy\Services\VerificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'email' )->nullable();
	} );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

function makeRequest( string $token = 'tok-12345', ?Illuminate\Support\Carbon $createdAt = null ): DataRequest
{
	$subject = TestSubject::create( [ 'email' => 'jacob@example.com' ] );

	$request = DataRequest::query()->create( [
		'requestable_type'   => $subject->getMorphClass(),
		'requestable_id'     => $subject->getKey(),
		'type'               => DataRequest::TYPE_ACCESS,
		'status'             => DataRequest::STATUS_PENDING,
		'verification_token' => $token,
	] );

	if ( null !== $createdAt ) {
		$request->forceFill( [ 'created_at' => $createdAt ] )->save();
		$request->setRawAttributes( $request->getAttributes(), true );
	}

	return $request;
}

it( 'finds a request by token', function (): void {
	$request = makeRequest( 'abc-def' );

	expect( app( VerificationService::class )->findByToken( 'abc-def' )?->id )->toBe( $request->id );
} );

it( 'returns null for a missing token', function (): void {
	makeRequest( 'abc-def' );

	expect( app( VerificationService::class )->findByToken( 'no-match' ) )->toBeNull();
} );

it( 'confirms a pending request and writes an audit-log entry', function (): void {
	$request = makeRequest();

	$result = app( VerificationService::class )->confirm( $request, 'email' );

	expect( $result )->toBeTrue();
	$request->refresh();
	expect( $request->verified_at )->not->toBeNull();
	expect( $request->status )->toBe( DataRequest::STATUS_PROCESSING );

	$log = DataRequestLog::query()->where( 'data_request_id', $request->id )->first();
	expect( $log?->action )->toBe( 'verification.confirmed' );
} );

it( 'refuses to confirm an already-verified request', function (): void {
	$request = makeRequest();
	$request->update( [ 'verified_at' => now() ] );

	expect( app( VerificationService::class )->confirm( $request ) )->toBeFalse();
} );

it( 'refuses to confirm an expired request', function (): void {
	config()->set( 'artisanpack.privacy.verification.token_ttl_minutes', 1 );

	$request = makeRequest( 'tok-old', now()->subMinutes( 60 ) );

	expect( app( VerificationService::class )->isExpired( $request ) )->toBeTrue();
	expect( app( VerificationService::class )->confirm( $request ) )->toBeFalse();
} );

it( 'manually verifies a request with the admin id recorded on the audit log', function (): void {
	$request = makeRequest();

	app( VerificationService::class )->manuallyVerify( $request, 99 );

	$log = DataRequestLog::query()->where( 'data_request_id', $request->id )->first();
	expect( $log?->performed_by )->toBe( 99 );
} );

it( 'refreshes a token and clears verified_at', function (): void {
	$request = makeRequest( 'tok-original' );
	$request->update( [ 'verified_at' => now() ] );

	$new = app( VerificationService::class )->refreshToken( $request );

	$request->refresh();
	expect( $new )->not->toBe( 'tok-original' );
	expect( $request->verification_token )->toBe( $new );
	expect( $request->verified_at )->toBeNull();
} );

it( 'builds a verification URL when a token is set', function (): void {
	$request = makeRequest( 'tok-link' );

	$url = app( VerificationService::class )->verificationUrl( $request );

	expect( $url )->toContain( 'tok-link' );
} );
