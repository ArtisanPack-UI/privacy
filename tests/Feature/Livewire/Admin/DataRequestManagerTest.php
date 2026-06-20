<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Livewire\Admin\DataRequestManager;
use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
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

	$this->admin = TestSubject::create();
	$this->actingAs( $this->admin );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

function makeAdminLivewireDataRequest( array $overrides = [] ): DataRequest
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

it( 'denies access when the manage-privacy gate refuses', function (): void {
	Gate::define( 'manage-privacy', static fn () => false );

	expect( Gate::allows( 'manage-privacy' ) )->toBeFalse();

	$component = Livewire::test( DataRequestManager::class );

	// Mount renders a denied response (Forbidden page) rather than the
	// configured view, so the table content is suppressed.
	$component->assertDontSee( 'data-request-' );
} );

it( 'shows pending requests in the table', function (): void {
	$request = makeAdminLivewireDataRequest();

	Livewire::test( DataRequestManager::class )
		->assertSee( ucfirst( $request->type ) )
		->assertSee( ucfirst( $request->status ) );
} );

it( 'filters by status', function (): void {
	$pending    = makeAdminLivewireDataRequest( [ 'status' => DataRequest::STATUS_PENDING, 'type' => 'access' ] );
	$processing = makeAdminLivewireDataRequest( [ 'status' => DataRequest::STATUS_PROCESSING, 'type' => 'deletion' ] );

	Livewire::test( DataRequestManager::class )
		->set( 'statusFilter', DataRequest::STATUS_PROCESSING )
		->assertSee( 'data-request-' . $processing->id )
		->assertDontSee( 'data-request-' . $pending->id );
} );

it( 'approves a pending request and writes an audit log entry', function (): void {
	$request = makeAdminLivewireDataRequest();

	Livewire::test( DataRequestManager::class )
		->call( 'view', $request->id )
		->set( 'note', 'Verified via support call.' )
		->call( 'approve', $request->id );

	$fresh = $request->fresh();

	expect( $fresh->status )->toBe( DataRequest::STATUS_PROCESSING );
	expect( $fresh->verified_at )->not->toBeNull();

	$log = DataRequestLog::query()->where( 'data_request_id', $request->id )->where( 'action', 'approved' )->first();

	expect( $log )->not->toBeNull();
	expect( $log->metadata['note'] ?? null )->toBe( 'Verified via support call.' );
} );

it( 'rejects a pending request', function (): void {
	$request = makeAdminLivewireDataRequest();

	Livewire::test( DataRequestManager::class )
		->set( 'note', 'Reason: duplicate request.' )
		->call( 'reject', $request->id );

	$fresh = $request->fresh();

	expect( $fresh->status )->toBe( DataRequest::STATUS_REJECTED );
	expect( $fresh->admin_notes )->toContain( 'duplicate request' );
} );

it( 'completes a request', function (): void {
	$request = makeAdminLivewireDataRequest( [ 'status' => DataRequest::STATUS_PROCESSING ] );

	Livewire::test( DataRequestManager::class )
		->call( 'complete', $request->id );

	$fresh = $request->fresh();

	expect( $fresh->status )->toBe( DataRequest::STATUS_COMPLETED );
	expect( $fresh->completed_at )->not->toBeNull();
} );

it( 'verifies a request manually without changing status', function (): void {
	$request = makeAdminLivewireDataRequest();

	Livewire::test( DataRequestManager::class )
		->call( 'verifyManually', $request->id );

	$fresh = $request->fresh();

	expect( $fresh->verified_at )->not->toBeNull();
	expect( $fresh->status )->toBe( DataRequest::STATUS_PROCESSING );
} );

it( 'sorts requests by overdue when requested', function (): void {
	makeAdminLivewireDataRequest( [ 'due_at' => now()->subDays( 5 ) ] );
	makeAdminLivewireDataRequest( [ 'due_at' => now()->addDays( 10 ) ] );

	Livewire::test( DataRequestManager::class )
		->set( 'sort', DataRequestManager::SORT_OVERDUE )
		->assertSeeText( 'Overdue' );
} );
