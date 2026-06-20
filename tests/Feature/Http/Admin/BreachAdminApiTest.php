<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\BreachNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'name' )->nullable();
	} );

	config()->set( 'artisanpack.privacy.admin.gate', 'manage-privacy' );
	Gate::define( 'manage-privacy', static fn () => true );

	$this->actingAs( TestSubject::create() );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

it( 'denies access when the gate refuses', function (): void {
	Gate::define( 'manage-privacy', static fn () => false );

	$this->getJson( '/api/privacy/admin/breaches' )->assertForbidden();
} );

it( 'lists breaches with deadline/overdue metadata', function (): void {
	BreachNotification::factory()->create( [
		'reference_number'      => 'BR-API-001',
		'discovered_at'         => now()->subDays( 10 ),
		'authority_notified_at' => null,
	] );

	$response = $this->getJson( '/api/privacy/admin/breaches' )
		->assertOk()
		->assertJsonStructure( [
			'data' => [ '*' => [ 'id', 'reference_number', 'authority_deadline', 'authority_overdue' ] ],
			'meta',
			'severities',
			'statuses',
		] );

	$row = $response->json( 'data.0' );
	expect( $row['authority_overdue'] )->toBeTrue();
	expect( $row['reference_number'] )->toBe( 'BR-API-001' );
} );

it( 'shows a breach with description, cause, and remediation', function (): void {
	$breach = BreachNotification::factory()->create( [
		'reference_number' => 'BR-API-SHOW',
		'cause'            => 'Misconfigured ACL',
		'remediation'      => 'Patched',
	] );

	$this->getJson( "/api/privacy/admin/breaches/{$breach->id}" )
		->assertOk()
		->assertJsonPath( 'data.reference_number', 'BR-API-SHOW' )
		->assertJsonPath( 'data.cause', 'Misconfigured ACL' )
		->assertJsonPath( 'data.remediation', 'Patched' );
} );

it( 'creates a breach via POST and returns the row', function (): void {
	$response = $this->postJson( '/api/privacy/admin/breaches', [
		'description'         => 'POSTed incident',
		'severity'            => 'high',
		'data_types_affected' => [ 'email', 'name' ],
		'records_affected'    => 12,
	] );

	$response->assertCreated()
		->assertJsonPath( 'data.severity', 'high' );

	expect( BreachNotification::query()->where( 'description', 'POSTed incident' )->exists() )->toBeTrue();
} );

it( 'rejects the create endpoint when required fields are missing', function (): void {
	$this->postJson( '/api/privacy/admin/breaches', [] )
		->assertUnprocessable()
		->assertJsonValidationErrors( [ 'description', 'severity', 'data_types_affected' ] );
} );

it( 'rejects affected_users entries that are not valid emails', function (): void {
	$this->postJson( '/api/privacy/admin/breaches', [
		'description'         => 'Test',
		'severity'            => 'high',
		'data_types_affected' => [ 'email' ],
		'affected_users'      => [ 'alice@example.test', 'not-an-email' ],
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( [ 'affected_users.1.email' ] );
} );

it( 'normalizes raw email strings in affected_users into the array shape', function (): void {
	$response = $this->postJson( '/api/privacy/admin/breaches', [
		'description'         => 'Normalized test',
		'severity'            => 'medium',
		'data_types_affected' => [ 'email' ],
		'affected_users'      => [ 'alice@example.test', [ 'email' => 'bob@example.test', 'name' => 'Bob' ] ],
	] );

	$response->assertCreated();

	$stored = BreachNotification::query()->where( 'description', 'Normalized test' )->firstOrFail();
	expect( $stored->affected_users )->toMatchArray( [
		[ 'email' => 'alice@example.test' ],
		[ 'email' => 'bob@example.test', 'name' => 'Bob' ],
	] );
} );

it( 'dispatches authority and user notifications via the actions endpoint', function (): void {
	Mail::spy();
	config()->set( 'artisanpack.privacy.breach.authority_email', 'dpa@example.test' );

	$breach = BreachNotification::factory()->create( [
		'affected_users' => [ 'alice@example.test' ],
	] );

	$this->postJson( "/api/privacy/admin/breaches/{$breach->id}/actions", [ 'action' => 'notify_authority' ] )
		->assertOk();

	$this->postJson( "/api/privacy/admin/breaches/{$breach->id}/actions", [ 'action' => 'notify_users' ] )
		->assertOk();

	expect( $breach->fresh()->authority_notified_at )->not->toBeNull();
	expect( $breach->fresh()->users_notified_at )->not->toBeNull();

	Mail::shouldHaveReceived( 'raw' )->twice();
} );

it( 'advances the status workflow via the actions endpoint', function (): void {
	$breach = BreachNotification::factory()->create( [ 'status' => BreachNotification::STATUS_INVESTIGATING ] );

	$this->postJson( "/api/privacy/admin/breaches/{$breach->id}/actions", [
		'action' => 'set_status',
		'status' => 'contained',
	] )->assertOk();

	expect( $breach->fresh()->status )->toBe( BreachNotification::STATUS_CONTAINED );
} );

it( 'appends remediation notes via the actions endpoint', function (): void {
	$breach = BreachNotification::factory()->create( [ 'remediation' => 'Initial' ] );

	$this->postJson( "/api/privacy/admin/breaches/{$breach->id}/actions", [
		'action' => 'add_remediation',
		'note'   => 'Second pass',
	] )->assertOk();

	expect( $breach->fresh()->remediation )->toContain( 'Initial' );
	expect( $breach->fresh()->remediation )->toContain( 'Second pass' );
} );

it( 'returns 422 when add_remediation has an empty note', function (): void {
	$breach = BreachNotification::factory()->create();

	$this->postJson( "/api/privacy/admin/breaches/{$breach->id}/actions", [
		'action' => 'add_remediation',
		'note'   => '   ',
	] )->assertStatus( 422 );
} );

it( 'serves the compliance report via the API', function (): void {
	BreachNotification::factory()->create();

	$response = $this->getJson( '/api/privacy/admin/compliance-report' )
		->assertOk()
		->assertJsonStructure( [
			'data' => [ 'meta', 'consents', 'requests', 'breaches' ],
			'regulations',
		] );

	expect( $response->json( 'data.breaches.total' ) )->toBeGreaterThanOrEqual( 1 );
} );
