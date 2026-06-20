<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Livewire\Admin\BreachDetail;
use ArtisanPackUI\Privacy\Livewire\Admin\BreachManager;
use ArtisanPackUI\Privacy\Livewire\Admin\BreachReportForm;
use ArtisanPackUI\Privacy\Models\BreachNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	if ( ! class_exists( Livewire::class ) ) {
		$this->markTestSkipped( 'Livewire is not installed.' );
	}

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

	Livewire::test( BreachManager::class )->assertDontSee( 'Breach incidents' );
} );

it( 'lists breaches with severity and status badges', function (): void {
	$breach = BreachNotification::factory()->create( [
		'reference_number' => 'BR-LIST-001',
		'severity'         => BreachNotification::SEVERITY_HIGH,
		'status'           => BreachNotification::STATUS_INVESTIGATING,
	] );

	Livewire::test( BreachManager::class )
		->assertSee( 'BR-LIST-001' )
		->assertSee( 'High' )
		->assertSee( 'Investigating' );
} );

it( 'filters by severity and status', function (): void {
	$high = BreachNotification::factory()->create( [
		'reference_number' => 'BR-FILT-HIGH',
		'severity'         => BreachNotification::SEVERITY_HIGH,
		'status'           => BreachNotification::STATUS_INVESTIGATING,
	] );

	$low = BreachNotification::factory()->create( [
		'reference_number' => 'BR-FILT-LOW',
		'severity'         => BreachNotification::SEVERITY_LOW,
		'status'           => BreachNotification::STATUS_RESOLVED,
	] );

	Livewire::test( BreachManager::class )
		->set( 'severityFilter', BreachNotification::SEVERITY_HIGH )
		->assertSee( 'BR-FILT-HIGH' )
		->assertDontSee( 'BR-FILT-LOW' );
} );

it( 'records a new breach via the report form', function (): void {
	Mail::fake();

	$component = Livewire::test( BreachReportForm::class )
		->set( 'description', 'Test incident' )
		->set( 'severity', BreachNotification::SEVERITY_HIGH )
		->set( 'dataTypes', 'email, name' )
		->set( 'recordsAffected', 42 )
		->set( 'affectedUsers', "alice@example.test\nbob@example.test" )
		->call( 'submit' );

	$component->assertHasNoErrors();

	$breach = BreachNotification::query()->latest( 'id' )->first();

	expect( $breach )->not->toBeNull();
	expect( $breach->description )->toBe( 'Test incident' );
	expect( $breach->severity )->toBe( BreachNotification::SEVERITY_HIGH );
	expect( $breach->data_types_affected )->toBe( [ 'email', 'name' ] );
	expect( $breach->affected_users )->toBe( [ 'alice@example.test', 'bob@example.test' ] );
	expect( $breach->records_affected )->toBe( 42 );
} );

it( 'validates required fields on the report form', function (): void {
	Livewire::test( BreachReportForm::class )
		->set( 'description', '' )
		->set( 'severity', BreachNotification::SEVERITY_MEDIUM )
		->set( 'dataTypes', '' )
		->call( 'submit' )
		->assertHasErrors( [ 'description', 'dataTypes' ] );
} );

it( 'rejects affected_users entries that are not valid email addresses', function (): void {
	Livewire::test( BreachReportForm::class )
		->set( 'description', 'Test' )
		->set( 'severity', BreachNotification::SEVERITY_HIGH )
		->set( 'dataTypes', 'email' )
		->set( 'affectedUsers', "alice@example.test\nnot-an-email\nbob@example.test" )
		->call( 'submit' )
		->assertHasErrors( [ 'affectedUsers' ] );

	expect( BreachNotification::query()->where( 'description', 'Test' )->exists() )->toBeFalse();
} );

it( 'displays breach detail with timeline and dispatches notifications', function (): void {
	Mail::spy();
	config()->set( 'artisanpack.privacy.breach.authority_email', 'dpa@example.test' );

	$breach = BreachNotification::factory()->create( [
		'reference_number' => 'BR-DETAIL-001',
		'affected_users'   => [ 'alice@example.test' ],
	] );

	$component = Livewire::test( BreachDetail::class, [ 'breachId' => $breach->id ] )
		->assertSee( 'BR-DETAIL-001' )
		->call( 'notifyAuthority' );

	expect( $breach->fresh()->authority_notified_at )->not->toBeNull();

	$component->call( 'notifyUsers' );
	expect( $breach->fresh()->users_notified_at )->not->toBeNull();

	Mail::shouldHaveReceived( 'raw' )->twice();
} );

it( 'appends a remediation note from the detail view', function (): void {
	$breach = BreachNotification::factory()->create( [ 'remediation' => 'Initial note' ] );

	Livewire::test( BreachDetail::class, [ 'breachId' => $breach->id ] )
		->set( 'remediationNote', 'Follow-up patch deployed' )
		->call( 'addRemediation' )
		->assertSet( 'remediationNote', '' );

	expect( $breach->fresh()->remediation )->toContain( 'Follow-up patch deployed' );
	expect( $breach->fresh()->remediation )->toContain( 'Initial note' );
} );

it( 'advances the status workflow through investigating → contained → resolved', function (): void {
	$breach = BreachNotification::factory()->create( [
		'status' => BreachNotification::STATUS_INVESTIGATING,
	] );

	$component = Livewire::test( BreachDetail::class, [ 'breachId' => $breach->id ] );

	$component->call( 'setStatus', BreachNotification::STATUS_CONTAINED );
	expect( $breach->fresh()->status )->toBe( BreachNotification::STATUS_CONTAINED );

	$component->call( 'setStatus', BreachNotification::STATUS_RESOLVED );
	expect( $breach->fresh()->status )->toBe( BreachNotification::STATUS_RESOLVED );

	$component->call( 'setStatus', 'invalid' );
	expect( $breach->fresh()->status )->toBe( BreachNotification::STATUS_RESOLVED );
} );

it( 'exports breach documentation as CSV', function (): void {
	$breach = BreachNotification::factory()->create( [
		'reference_number' => 'BR-CSV-001',
		'description'      => 'CSV export incident',
	] );

	$component = Livewire::test( BreachDetail::class, [ 'breachId' => $breach->id ] );
	$response  = $component->instance()->exportDocumentation();

	expect( $response )->not->toBeNull();
	expect( $response->headers->get( 'Content-Type' ) )->toContain( 'text/csv' );

	ob_start();
	$response->sendContent();
	$body = (string) ob_get_clean();

	expect( $body )->toContain( 'field,value' );
	expect( $body )->toContain( 'BR-CSV-001' );
	expect( $body )->toContain( 'CSV export incident' );
} );
