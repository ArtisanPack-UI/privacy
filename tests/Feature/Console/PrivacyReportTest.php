<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\Consent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'name' )->nullable();
	} );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );

	if ( isset( $this->tmpReport ) && is_file( $this->tmpReport ) ) {
		unlink( $this->tmpReport );
	}
} );

it( 'prints a JSON report to stdout for a period', function (): void {
	$subject = TestSubject::create();

	Consent::query()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'category'         => 'analytics',
		'granted'          => true,
		'regulation'       => 'gdpr',
	] );

	$this->artisan( 'privacy:report', [ '--period' => 'month', '--format' => 'json' ] )
		->assertExitCode( 0 )
		->expectsOutputToContain( '"consents"' );
} );

it( 'writes the report to the --output path', function (): void {
	$this->tmpReport = sys_get_temp_dir() . '/privacy-report-' . uniqid() . '.json';

	$this->artisan( 'privacy:report', [
		'--period' => 'day',
		'--format' => 'json',
		'--output' => $this->tmpReport,
	] )->assertExitCode( 0 );

	expect( file_exists( $this->tmpReport ) )->toBeTrue();

	$decoded = json_decode( (string) file_get_contents( $this->tmpReport ), true );
	expect( $decoded )->toHaveKeys( [ 'meta', 'consents', 'requests', 'breaches' ] );
} );

it( 'supports CSV output', function (): void {
	$this->tmpReport = sys_get_temp_dir() . '/privacy-report-' . uniqid() . '.csv';

	$this->artisan( 'privacy:report', [
		'--period' => 'day',
		'--format' => 'csv',
		'--output' => $this->tmpReport,
	] )->assertExitCode( 0 );

	$contents = (string) file_get_contents( $this->tmpReport );
	expect( $contents )->toContain( 'metric,value' );
	expect( $contents )->toContain( 'meta.from' );
} );

it( 'supports a custom start/end window', function (): void {
	$this->artisan( 'privacy:report', [
		'--period' => 'custom',
		'--start'  => now()->subDays( 7 )->toDateString(),
		'--end'    => now()->toDateString(),
		'--format' => 'json',
	] )->assertExitCode( 0 );
} );

it( 'rejects custom period without start/end', function (): void {
	$this->artisan( 'privacy:report', [ '--period' => 'custom' ] )
		->assertExitCode( Illuminate\Console\Command::INVALID );
} );

it( 'rejects custom period when start is after end', function (): void {
	$this->artisan( 'privacy:report', [
		'--period' => 'custom',
		'--start'  => '2026-02-01',
		'--end'    => '2026-01-01',
	] )->assertExitCode( Illuminate\Console\Command::INVALID );
} );

it( 'rejects invalid period values', function (): void {
	$this->artisan( 'privacy:report', [ '--period' => 'eternity' ] )
		->assertExitCode( Illuminate\Console\Command::INVALID );
} );

it( 'rejects invalid format values', function (): void {
	$this->artisan( 'privacy:report', [ '--format' => 'pdf' ] )
		->assertExitCode( Illuminate\Console\Command::INVALID );
} );

it( 'emails the report when --email is set', function (): void {
	config( [ 'mail.default' => 'array' ] );

	$this->artisan( 'privacy:report', [
		'--period' => 'day',
		'--email'  => 'dpo@example.com',
	] )
		->assertExitCode( 0 )
		->expectsOutputToContain( 'Report emailed to dpo@example.com' );

	$sent = app( 'mailer' )->getSymfonyTransport()->messages();
	expect( $sent )->not->toBeEmpty();
} );
