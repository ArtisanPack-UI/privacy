<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Concerns\HasPersonalData;
use ArtisanPackUI\Privacy\Events\DataSubjectDeleted;
use ArtisanPackUI\Privacy\Events\DataSubjectDeletionScheduled;
use ArtisanPackUI\Privacy\Models\ScheduledDeletion;
use ArtisanPackUI\Privacy\Services\DataDeletionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

beforeEach( function (): void {
	Schema::create( 'deletion_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'email' )->nullable();
		$table->string( 'name' )->nullable();
	} );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'deletion_subjects' );
} );

class DeletionSubject extends Model
{
	use HasPersonalData;

	public $timestamps = false;

	protected $table = 'deletion_subjects';

	protected $guarded = [];

	protected array $personalDataFields = [ 'email', 'name' ];

	public function getMorphClass(): string
	{
		return 'deletion_subject';
	}
}

it( 'anonymizes a subject when the default strategy is anonymize', function (): void {
	config()->set( 'artisanpack.privacy.deletion.default_strategy', DataDeletionService::STRATEGY_ANONYMIZE );

	$subject = DeletionSubject::create( [ 'email' => 'jacob@example.com', 'name' => 'Jacob' ] );

	Event::fake( [ DataSubjectDeleted::class ] );

	$result = app( DataDeletionService::class )->delete( $subject );

	expect( $result )->toBeTrue();
	$subject->refresh();
	expect( $subject->email )->toBe( 'j***@e***.com' );
	Event::assertDispatched( DataSubjectDeleted::class );
} );

it( 'hard-deletes a subject when the strategy is delete', function (): void {
	$subject = DeletionSubject::create( [ 'email' => 'jacob@example.com' ] );

	app( DataDeletionService::class )->delete( $subject, [ 'strategy' => DataDeletionService::STRATEGY_DELETE ] );

	expect( DeletionSubject::query()->find( $subject->id ) )->toBeNull();
} );

it( 'pseudonymizes a subject with the reversible strategy', function (): void {
	$subject = DeletionSubject::create( [ 'email' => 'jacob@example.com' ] );

	$result = app( DataDeletionService::class )->pseudonymize( $subject );

	expect( $result )->toBeTrue();
	$subject->refresh();
	expect( $subject->email )->not->toBe( 'jacob@example.com' );
	expect( $subject->email )->toStartWith( 'Anon_' );
} );

it( 'throws for an unknown strategy', function (): void {
	$subject = DeletionSubject::create( [ 'email' => 'jacob@example.com' ] );

	app( DataDeletionService::class )->delete( $subject, [ 'strategy' => 'shred' ] );
} )->throws( InvalidArgumentException::class );

it( 'schedules a deletion with the configured grace period', function (): void {
	Event::fake( [ DataSubjectDeletionScheduled::class ] );

	$subject = DeletionSubject::create( [ 'email' => 'jacob@example.com' ] );

	$scheduled = app( DataDeletionService::class )->scheduleForDeletion( $subject, 7 );

	expect( $scheduled )->toBeInstanceOf( ScheduledDeletion::class );
	expect( $scheduled->isPending() )->toBeTrue();
	expect( $scheduled->scheduled_for->isFuture() )->toBeTrue();
	Event::assertDispatched( DataSubjectDeletionScheduled::class );
} );

it( 'cancels a pending scheduled deletion', function (): void {
	$subject   = DeletionSubject::create( [ 'email' => 'jacob@example.com' ] );
	$scheduled = app( DataDeletionService::class )->scheduleForDeletion( $subject, 7 );

	$cancelled = app( DataDeletionService::class )->cancelScheduledDeletion( $subject, 'user changed their mind' );

	expect( $cancelled )->toBeTrue();
	$scheduled->refresh();
	expect( $scheduled->cancelled_at )->not->toBeNull();
	expect( $scheduled->cancelled_reason )->toBe( 'user changed their mind' );
} );

it( 'returns false when there is no scheduled deletion to cancel', function (): void {
	$subject = DeletionSubject::create( [ 'email' => 'jacob@example.com' ] );

	expect( app( DataDeletionService::class )->cancelScheduledDeletion( $subject ) )->toBeFalse();
} );
