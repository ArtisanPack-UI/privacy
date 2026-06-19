<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Events\ConsentGiven;
use ArtisanPackUI\Privacy\Events\ConsentWithdrawn;
use ArtisanPackUI\Privacy\Events\DataAccessRequested;
use ArtisanPackUI\Privacy\Events\DataBreach;
use ArtisanPackUI\Privacy\Events\DataDeletionRequested;
use ArtisanPackUI\Privacy\Events\DataExportRequested;
use ArtisanPackUI\Privacy\Events\DataRequestCompleted;
use ArtisanPackUI\Privacy\Events\PrivacyPolicyUpdated;
use ArtisanPackUI\Privacy\Models\BreachNotification;
use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\PrivacyPolicy;
use Illuminate\Support\Facades\Event;

it( 'dispatches the ConsentGiven event with the consent payload', function (): void {
	Event::fake();

	$consent = Consent::factory()->make( [ 'id' => 1 ] );

	ConsentGiven::dispatch( $consent );

	Event::assertDispatched(
		ConsentGiven::class,
		fn ( ConsentGiven $event ): bool => $event->consent === $consent,
	);
} );

it( 'dispatches the ConsentWithdrawn event with the consent payload', function (): void {
	Event::fake();

	$consent = Consent::factory()->withdrawn()->make( [ 'id' => 2 ] );

	ConsentWithdrawn::dispatch( $consent );

	Event::assertDispatched(
		ConsentWithdrawn::class,
		fn ( ConsentWithdrawn $event ): bool => $event->consent === $consent,
	);
} );

it( 'dispatches DataAccessRequested with the request payload', function (): void {
	Event::fake();

	$request = DataRequest::factory()->make( [ 'type' => DataRequest::TYPE_ACCESS, 'id' => 3 ] );

	DataAccessRequested::dispatch( $request );

	Event::assertDispatched(
		DataAccessRequested::class,
		fn ( DataAccessRequested $event ): bool => $event->request === $request,
	);
} );

it( 'dispatches DataDeletionRequested with the request payload', function (): void {
	Event::fake();

	$request = DataRequest::factory()->make( [ 'type' => DataRequest::TYPE_DELETION, 'id' => 4 ] );

	DataDeletionRequested::dispatch( $request );

	Event::assertDispatched(
		DataDeletionRequested::class,
		fn ( DataDeletionRequested $event ): bool => $event->request === $request,
	);
} );

it( 'dispatches DataExportRequested with the request payload', function (): void {
	Event::fake();

	$request = DataRequest::factory()->make( [ 'type' => DataRequest::TYPE_EXPORT, 'id' => 5 ] );

	DataExportRequested::dispatch( $request );

	Event::assertDispatched(
		DataExportRequested::class,
		fn ( DataExportRequested $event ): bool => $event->request === $request,
	);
} );

it( 'dispatches DataRequestCompleted with the request payload', function (): void {
	Event::fake();

	$request = DataRequest::factory()->completed()->make( [ 'id' => 6 ] );

	DataRequestCompleted::dispatch( $request );

	Event::assertDispatched(
		DataRequestCompleted::class,
		fn ( DataRequestCompleted $event ): bool => $event->request === $request,
	);
} );

it( 'dispatches DataBreach with the breach payload', function (): void {
	Event::fake();

	$breach = BreachNotification::factory()->make( [ 'id' => 7 ] );

	DataBreach::dispatch( $breach );

	Event::assertDispatched(
		DataBreach::class,
		fn ( DataBreach $event ): bool => $event->breach === $breach,
	);
} );

it( 'dispatches PrivacyPolicyUpdated with the policy payload', function (): void {
	Event::fake();

	$policy = PrivacyPolicy::factory()->active()->make( [ 'id' => 8 ] );

	PrivacyPolicyUpdated::dispatch( $policy );

	Event::assertDispatched(
		PrivacyPolicyUpdated::class,
		fn ( PrivacyPolicyUpdated $event ): bool => $event->policy === $policy,
	);
} );
