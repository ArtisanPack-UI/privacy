<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'name' )->nullable();
	} );

	config()->set( 'artisanpack.privacy.admin.gate', 'manage-privacy' );
	Gate::define( 'manage-privacy', static fn () => true );

	$this->admin = TestSubject::create();
	$this->actingAs( $this->admin );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

function makeAdminDataRequest( array $overrides = [] ): DataRequest
{
	$subject = TestSubject::create();

	return DataRequest::query()->create( array_merge( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_ACCESS,
		'status'           => DataRequest::STATUS_PENDING,
		'due_at'           => now()->addDays( 14 ),
	], $overrides ) );
}

it( 'returns paginated requests with overdue flags', function (): void {
	$overdue = makeAdminDataRequest( [ 'due_at' => now()->subDays( 2 ) ] );
	$future  = makeAdminDataRequest( [ 'due_at' => now()->addDays( 5 ) ] );

	$response = $this->getJson( '/api/privacy/admin/data-requests' )
		->assertOk()
		->assertJsonStructure( [
			'data' => [ '*' => [ 'id', 'type', 'status', 'overdue' ] ],
			'meta',
			'allowed_types',
		] );

	$ids     = collect( $response->json( 'data' ) )->pluck( 'id' )->all();
	$flags   = collect( $response->json( 'data' ) )->keyBy( 'id' );

	expect( $flags[ $overdue->id ]['overdue'] )->toBeTrue();
	expect( $flags[ $future->id ]['overdue'] )->toBeFalse();
	expect( $ids )->toContain( $overdue->id );
} );

it( 'filters by type and status', function (): void {
	makeAdminDataRequest( [ 'type' => DataRequest::TYPE_ACCESS ] );
	makeAdminDataRequest( [ 'type' => DataRequest::TYPE_DELETION, 'status' => DataRequest::STATUS_PROCESSING ] );

	$this->getJson( '/api/privacy/admin/data-requests?type=deletion&status=processing' )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.type', DataRequest::TYPE_DELETION );
} );

it( 'returns the request with logs via show', function (): void {
	$request = makeAdminDataRequest();

	DataRequestLog::query()->create( [
		'data_request_id' => $request->id,
		'action'          => 'received',
	] );

	$this->getJson( "/api/privacy/admin/data-requests/{$request->id}" )
		->assertOk()
		->assertJsonPath( 'data.id', $request->id )
		->assertJsonCount( 1, 'data.logs' );
} );

it( 'returns 404 when showing a request that does not exist', function (): void {
	$this->getJson( '/api/privacy/admin/data-requests/9999' )
		->assertNotFound();
} );

it( 'approves a request through the actions endpoint', function (): void {
	$request = makeAdminDataRequest();

	$this->postJson( "/api/privacy/admin/data-requests/{$request->id}/actions", [
		'action' => 'approve',
		'note'   => 'Confirmed identity in support ticket.',
	] )
		->assertOk()
		->assertJsonPath( 'data.status', DataRequest::STATUS_PROCESSING );

	$fresh = $request->fresh();

	expect( $fresh->status )->toBe( DataRequest::STATUS_PROCESSING );
	expect( $fresh->verified_at )->not->toBeNull();
} );

it( 'rejects a request through the actions endpoint', function (): void {
	$request = makeAdminDataRequest();

	$this->postJson( "/api/privacy/admin/data-requests/{$request->id}/actions", [
		'action' => 'reject',
		'note'   => 'Duplicate.',
	] )->assertOk();

	$fresh = $request->fresh();

	expect( $fresh->status )->toBe( DataRequest::STATUS_REJECTED );
	expect( $fresh->admin_notes )->toBe( 'Duplicate.' );
} );

it( 'rejects unauthorized requests', function (): void {
	Gate::define( 'manage-privacy', static fn () => false );

	$this->getJson( '/api/privacy/admin/data-requests' )
		->assertForbidden();
} );

it( 'validates the action body', function (): void {
	$request = makeAdminDataRequest();

	$this->postJson( "/api/privacy/admin/data-requests/{$request->id}/actions", [
		'action' => 'not-valid',
	] )->assertUnprocessable();
} );
