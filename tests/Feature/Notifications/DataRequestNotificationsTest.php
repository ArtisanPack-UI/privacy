<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Notifications\AdminDataRequestNotification;
use ArtisanPackUI\Privacy\Notifications\DataRequestCompleted;
use ArtisanPackUI\Privacy\Notifications\DataRequestReceived;
use ArtisanPackUI\Privacy\Notifications\DataRequestRejected;
use ArtisanPackUI\Privacy\Notifications\DataRequestVerificationRequired;
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

function makeDataRequest(): DataRequest
{
	$subject = TestSubject::create();

	return DataRequest::query()->create( [
		'requestable_type'   => $subject->getMorphClass(),
		'requestable_id'     => $subject->getKey(),
		'type'               => DataRequest::TYPE_EXPORT,
		'status'             => DataRequest::STATUS_PENDING,
		'verification_token' => 'tok-notification',
	] );
}

it( 'resolves channels via config including per-notification overrides', function (): void {
	config()->set( 'artisanpack.privacy.notifications.channels', [ 'mail' ] );
	config()->set( 'artisanpack.privacy.notifications.received.channels', [ 'mail', 'database' ] );

	$notification = new DataRequestReceived( makeDataRequest() );

	expect( $notification->via( new stdClass() ) )->toBe( [ 'mail', 'database' ] );
} );

it( 'builds a mail message for DataRequestReceived', function (): void {
	$mail = ( new DataRequestReceived( makeDataRequest() ) )->toMail( new stdClass() );

	expect( $mail->subject )->toContain( 'received your data request' );
} );

it( 'builds a verification mail with an action URL', function (): void {
	$mail = ( new DataRequestVerificationRequired( makeDataRequest() ) )->toMail( new stdClass() );

	expect( $mail->actionText )->toContain( 'Verify' );
	expect( $mail->actionUrl )->toContain( 'tok-notification' );
} );

it( 'includes the download URL in DataRequestCompleted when provided', function (): void {
	$notification = new DataRequestCompleted( makeDataRequest(), 'https://example.com/download' );
	$mail         = $notification->toMail( new stdClass() );
	$array        = $notification->toArray( new stdClass() );

	expect( $mail->actionUrl )->toBe( 'https://example.com/download' );
	expect( $array['download_url'] )->toBe( 'https://example.com/download' );
} );

it( 'includes the rejection reason in DataRequestRejected', function (): void {
	$notification = new DataRequestRejected( makeDataRequest(), 'Insufficient identity proof' );
	$array        = $notification->toArray( new stdClass() );

	expect( $array['reason'] )->toBe( 'Insufficient identity proof' );
} );

it( 'builds an admin notification mail', function (): void {
	$mail = ( new AdminDataRequestNotification( makeDataRequest() ) )->toMail( new stdClass() );

	expect( $mail->subject )->toContain( 'New data request received' );
} );
