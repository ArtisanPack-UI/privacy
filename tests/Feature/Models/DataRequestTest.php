<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;

it( 'hydrates with the expected status by default', function (): void {
	$request = DataRequest::factory()->create();

	expect( $request->status )->toBe( DataRequest::STATUS_PENDING );
	expect( $request->due_at )->not->toBeNull();
} );

it( 'scopes pending, processing, completed, rejected', function (): void {
	DataRequest::factory()->create();
	DataRequest::factory()->create( [ 'status' => DataRequest::STATUS_PROCESSING ] );
	DataRequest::factory()->completed()->create();
	DataRequest::factory()->create( [ 'status' => DataRequest::STATUS_REJECTED ] );

	expect( DataRequest::query()->pending()->count() )->toBe( 1 );
	expect( DataRequest::query()->processing()->count() )->toBe( 1 );
	expect( DataRequest::query()->completed()->count() )->toBe( 1 );
	expect( DataRequest::query()->rejected()->count() )->toBe( 1 );
} );

it( 'flags overdue requests via the scope', function (): void {
	DataRequest::factory()->overdue()->create();
	DataRequest::factory()->create();

	expect( DataRequest::query()->overdue()->count() )->toBe( 1 );
} );

it( 'cascades log deletion when the parent request is deleted', function (): void {
	$request = DataRequest::factory()->create();
	DataRequestLog::factory()->count( 3 )->create( [ 'data_request_id' => $request->id ] );

	expect( $request->logs )->toHaveCount( 3 );

	$request->delete();

	expect( DataRequestLog::query()->count() )->toBe( 0 );
} );

it( 'casts data and timestamps correctly', function (): void {
	$request = DataRequest::factory()->create( [
		'data'        => [ 'fields' => [ 'email' ] ],
		'verified_at' => now(),
	] );

	expect( $request->data )->toBe( [ 'fields' => [ 'email' ] ] );
	expect( $request->verified_at )->not->toBeNull();
} );
