<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\PersonalDataMap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach( function (): void {
	Schema::create( 'cmd_scan_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'email' )->nullable();
		$table->string( 'phone' )->nullable();
	} );

	if ( ! class_exists( CmdScanSubject::class ) ) {
		eval( <<<'PHP'
			class CmdScanSubject extends \Illuminate\Database\Eloquent\Model
			{
				public $timestamps = false;
				protected $table = 'cmd_scan_subjects';
				protected $guarded = [];
			}
		PHP );
	}
} );

afterEach( function (): void {
	Schema::dropIfExists( 'cmd_scan_subjects' );
} );

it( 'runs the scanner against a specific model and prints a summary', function (): void {
	$this->artisan( 'privacy:scan', [ '--model' => CmdScanSubject::class ] )
		->expectsOutputToContain( CmdScanSubject::class )
		->expectsOutputToContain( 'email' )
		->expectsOutputToContain( 'Found 2 personal-data field(s)' )
		->assertSuccessful();
} );

it( 'persists discovered mappings when --save is supplied', function (): void {
	$this->artisan( 'privacy:scan', [ '--model' => CmdScanSubject::class, '--save' => true ] )
		->assertSuccessful();

	expect( PersonalDataMap::query()->where( 'model', CmdScanSubject::class )->count() )
		->toBe( 2 );
} );

it( 'emits valid JSON when --format=json', function (): void {
	$exitCode = $this->artisan( 'privacy:scan', [
		'--model'  => CmdScanSubject::class,
		'--format' => 'json',
	] )->run();

	expect( $exitCode )->toBe( 0 );
} );

it( 'rejects unknown formats', function (): void {
	$this->artisan( 'privacy:scan', [ '--format' => 'yaml' ] )
		->expectsOutputToContain( 'Unsupported format' )
		->assertExitCode( 2 );
} );
