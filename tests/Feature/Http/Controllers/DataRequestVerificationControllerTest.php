<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\DataRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
	} );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

function makeVerificationRequest( string $token = 'verify-tok-1', bool $verified = false ): DataRequest
{
	$subject = TestSubject::create();

	$request = DataRequest::query()->create( [
		'requestable_type'   => $subject->getMorphClass(),
		'requestable_id'     => $subject->getKey(),
		'type'               => DataRequest::TYPE_ACCESS,
		'status'             => DataRequest::STATUS_PENDING,
		'verification_token' => $token,
	] );

	if ( $verified ) {
		$request->update( [ 'verified_at' => now() ] );
	}

	return $request;
}

it( 'renders the verification page for a valid token', function (): void {
	$request = makeVerificationRequest( 'show-token-1' );

	$response = $this->get( route( 'privacy.verification.show', [ 'token' => 'show-token-1' ] ) );

	$response->assertOk();
	$response->assertSee( 'Verify Your Data Request' );
	$response->assertSee( '#' . $request->id );
} );

it( 'returns 404 for an unknown token', function (): void {
	$this->get( route( 'privacy.verification.show', [ 'token' => 'no-such-token' ] ) )
		->assertNotFound();
} );

it( 'flips the request to processing on POST', function (): void {
	$request = makeVerificationRequest( 'post-token-1' );

	$response = $this->post( route( 'privacy.verification.verify', [ 'token' => 'post-token-1' ] ) );

	$response->assertRedirect();
	$request->refresh();
	expect( $request->verified_at )->not->toBeNull();
	expect( $request->status )->toBe( DataRequest::STATUS_PROCESSING );
} );

it( 'redirects with an expired errors bag for a stale token', function (): void {
	config()->set( 'artisanpack.privacy.verification.token_ttl_minutes', 1 );

	$request = makeVerificationRequest( 'stale-token-1' );
	$request->forceFill( [ 'created_at' => now()->subHours( 2 ) ] )->save();

	$response = $this->post( route( 'privacy.verification.verify', [ 'token' => 'stale-token-1' ] ) );

	$response->assertRedirect();
	$response->assertSessionHasErrors( [ 'token' ] );
	$request->refresh();
	expect( $request->verified_at )->toBeNull();
} );

it( 'returns 404 when verifying an unknown token', function (): void {
	$this->post( route( 'privacy.verification.verify', [ 'token' => 'unknown' ] ) )
		->assertNotFound();
} );
