<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Events\DataBreach;
use ArtisanPackUI\Privacy\Models\BreachNotification;
use ArtisanPackUI\Privacy\Services\BreachNotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

beforeEach( function (): void {
	$this->service = app( BreachNotificationService::class );
	config()->set( 'artisanpack.privacy.regulations.gdpr.breach_notification_hours', 72 );
	config()->set( 'artisanpack.privacy.regulations.ccpa.breach_notification_hours', 96 );
} );

it( 'requires description, severity, and data_types_affected', function (): void {
	$this->service->reportBreach( [
		'severity'            => BreachNotification::SEVERITY_HIGH,
		'data_types_affected' => [ 'email' ],
	] );
} )->throws( InvalidArgumentException::class );

it( 'persists a breach and fires the DataBreach event', function (): void {
	Event::fake( [ DataBreach::class ] );

	$breach = $this->service->reportBreach( [
		'description'         => 'Unauthorized access to user table',
		'severity'            => BreachNotification::SEVERITY_HIGH,
		'data_types_affected' => [ 'email', 'name' ],
		'records_affected'    => 250,
	] );

	expect( $breach->exists )->toBeTrue();
	expect( $breach->reference_number )->toStartWith( 'BR-' );
	expect( $breach->status )->toBe( BreachNotification::STATUS_INVESTIGATING );
	expect( $breach->records_affected )->toBe( 250 );

	Event::assertDispatched( DataBreach::class, fn ( DataBreach $e ): bool => $e->breach->is( $breach ) );
} );

it( 'preserves a caller-supplied reference number', function (): void {
	$breach = $this->service->reportBreach( [
		'reference_number'    => 'BR-CUSTOM-001',
		'description'         => 'Custom incident',
		'severity'            => BreachNotification::SEVERITY_MEDIUM,
		'data_types_affected' => [ 'email' ],
	] );

	expect( $breach->reference_number )->toBe( 'BR-CUSTOM-001' );
} );

it( 'computes the notification deadline using the regulation window', function (): void {
	$breach = BreachNotification::factory()->create( [
		'discovered_at' => Carbon::parse( '2026-03-01 08:00:00' ),
	] );

	$gdpr = $this->service->getNotificationDeadline( $breach, BreachNotificationService::REGULATION_GDPR );
	$ccpa = $this->service->getNotificationDeadline( $breach, BreachNotificationService::REGULATION_CCPA );

	expect( $gdpr->toIso8601String() )->toBe( Carbon::parse( '2026-03-04 08:00:00' )->toIso8601String() );
	expect( $ccpa->toIso8601String() )->toBe( Carbon::parse( '2026-03-05 08:00:00' )->toIso8601String() );
} );

it( 'falls back to the default 72h window for unknown regulations', function (): void {
	$breach = BreachNotification::factory()->create( [
		'discovered_at' => Carbon::parse( '2026-03-01 08:00:00' ),
	] );

	$deadline = $this->service->getNotificationDeadline( $breach, 'default' );

	expect( $deadline->toIso8601String() )->toBe( Carbon::parse( '2026-03-04 08:00:00' )->toIso8601String() );
} );

it( 'isWithinNotificationWindow flips after the deadline passes', function (): void {
	Carbon::setTestNow( Carbon::parse( '2026-03-04 09:00:00' ) );

	$breach = BreachNotification::factory()->create( [
		'discovered_at' => Carbon::parse( '2026-03-01 08:00:00' ),
	] );

	expect( $this->service->isWithinNotificationWindow( $breach ) )->toBeFalse();

	Carbon::setTestNow( Carbon::parse( '2026-03-04 07:59:00' ) );
	expect( $this->service->isWithinNotificationWindow( $breach ) )->toBeTrue();

	Carbon::setTestNow();
} );

it( 'sends the authority email and stamps authority_notified_at when a recipient is configured', function (): void {
	Mail::spy();
	config()->set( 'artisanpack.privacy.breach.authority_email', 'dpa@example.test' );

	$breach = BreachNotification::factory()->create();

	$sent = $this->service->notifyAuthority( $breach );

	expect( $sent )->toBeTrue();
	expect( $breach->fresh()->authority_notified_at )->not->toBeNull();
	expect( $breach->fresh()->notifications_sent )->toBeArray()->toHaveCount( 1 );

	Mail::shouldHaveReceived( 'raw' )->once();
} );

it( 'records the authority notification attempt even without a configured recipient', function (): void {
	Mail::spy();
	config()->set( 'artisanpack.privacy.breach.authority_email', '' );

	$breach = BreachNotification::factory()->create();

	$sent = $this->service->notifyAuthority( $breach );

	expect( $sent )->toBeFalse();
	expect( $breach->fresh()->notifications_sent )->toBeArray()->toHaveCount( 1 );
	expect( $breach->fresh()->notifications_sent[0]['dispatched'] )->toBeFalse();

	Mail::shouldNotHaveReceived( 'raw' );
} );

it( 'notifies each affected user and returns the count', function (): void {
	Mail::spy();

	$breach = BreachNotification::factory()->create( [
		'affected_users' => [
			'alice@example.test',
			[ 'email' => 'bob@example.test', 'name' => 'Bob' ],
			[ 'name'  => 'Missing email' ],
			'',
		],
	] );

	$count = $this->service->notifyAffectedUsers( $breach );

	expect( $count )->toBe( 2 );
	expect( $breach->fresh()->users_notified_at )->not->toBeNull();

	Mail::shouldHaveReceived( 'raw' )->twice();
} );
