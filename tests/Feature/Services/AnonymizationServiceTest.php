<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Services\AnonymizationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'anon_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'email' )->nullable();
		$table->string( 'name' )->nullable();
		$table->string( 'phone' )->nullable();
		$table->string( 'ip_address' )->nullable();
	} );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'anon_subjects' );
} );

class AnonSubject extends TestSubject
{
	protected $table = 'anon_subjects';
}

it( 'masks an email address', function (): void {
	$result = ( new AnonymizationService() )->applyStrategy( 'jacob@example.com', AnonymizationService::STRATEGY_MASK, 'email' );

	expect( $result )->toBe( 'j***@e***.com' );
} );

it( 'masks a non-email string preserving first and last characters', function (): void {
	$result = ( new AnonymizationService() )->applyStrategy( 'Hello', AnonymizationService::STRATEGY_MASK );

	expect( $result )->toBe( 'H***o' );
} );

it( 'redacts a value', function (): void {
	$result = ( new AnonymizationService() )->applyStrategy( 'secret', AnonymizationService::STRATEGY_REDACT );

	expect( $result )->toBe( '[REDACTED]' );
} );

it( 'truncates an IPv4 address', function (): void {
	$result = ( new AnonymizationService() )->applyStrategy( '203.0.113.42', AnonymizationService::STRATEGY_TRUNCATE, 'ip_address' );

	expect( $result )->toBe( '203.0.113.0' );
} );

it( 'pseudonymizes deterministically with the configured prefix', function (): void {
	config()->set( 'artisanpack.privacy.anonymization.pseudonymization_prefix', 'Anon_' );

	$service = new AnonymizationService();
	$first   = $service->applyStrategy( 'jacob@example.com', AnonymizationService::STRATEGY_PSEUDONYMIZE );
	$second  = $service->applyStrategy( 'jacob@example.com', AnonymizationService::STRATEGY_PSEUDONYMIZE );

	expect( $first )->toBe( $second );
	expect( $first )->toStartWith( 'Anon_' );
} );

it( 'anonymizes a model in place based on configured strategies', function (): void {
	$subject = AnonSubject::create( [
		'email' => 'jacob@example.com',
		'name'  => 'Jacob Martella',
		'phone' => '555-1234',
	] );

	$mutated = app( AnonymizationService::class )->anonymize( $subject );

	expect( $mutated )->toBeTrue();
	$subject->refresh();
	expect( $subject->email )->toBe( 'j***@e***.com' );
	expect( $subject->phone )->toBe( '[REDACTED]' );
	expect( $subject->name )->toStartWith( 'Anon_' );
} );

it( 'returns false when no personal-data columns match', function (): void {
	Schema::create( 'plain_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'untracked_column' )->nullable();
	} );

	$plain = new class extends Illuminate\Database\Eloquent\Model {
		public $timestamps = false;

		protected $table = 'plain_subjects';

		protected $guarded = [];
	};

	$plain->forceFill( [ 'untracked_column' => 'value' ] )->save();

	expect( app( AnonymizationService::class )->anonymize( $plain ) )->toBeFalse();

	Schema::dropIfExists( 'plain_subjects' );
} );
