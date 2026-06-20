<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\PersonalDataMap;
use ArtisanPackUI\Privacy\Services\PersonalDataScanner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach( function (): void {
	Schema::create( 'scan_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'email' )->nullable();
		$table->string( 'first_name' )->nullable();
		$table->string( 'phone' )->nullable();
		$table->string( 'random_column' )->nullable();
	} );

	if ( ! class_exists( ScannerSubject::class ) ) {
		eval( <<<'PHP'
			class ScannerSubject extends \Illuminate\Database\Eloquent\Model
			{
				public $timestamps = false;
				protected $table = 'scan_subjects';
				protected $guarded = [];
			}
		PHP );
	}

	if ( ! class_exists( ScannerSubjectWithTrait::class ) ) {
		eval( <<<'PHP'
			class ScannerSubjectWithTrait extends \Illuminate\Database\Eloquent\Model
			{
				use \ArtisanPackUI\Privacy\Concerns\HasPersonalData;

				public $timestamps = false;
				protected $table = 'scan_subjects';
				protected $guarded = [];

				public function personalDataFields(): array
				{
					return [
						'email' => [
							'type'              => 'email',
							'sensitivity'       => 'normal',
							'deletion_strategy' => 'anonymize',
						],
					];
				}
			}
		PHP );
	}
} );

afterEach( function (): void {
	Schema::dropIfExists( 'scan_subjects' );
} );

it( 'detects pattern-matched personal data fields on a plain Eloquent model', function (): void {
	$scanner = new PersonalDataScanner();

	$fields = $scanner->scanModel( ScannerSubject::class );

	expect( $fields )->toHaveKey( 'email' );
	expect( $fields['email']['data_type'] )->toBe( 'email' );
	expect( $fields['email']['auto_discovered'] )->toBeTrue();
	expect( $fields )->toHaveKey( 'first_name' );
	expect( $fields['first_name']['data_type'] )->toBe( 'name' );
	expect( $fields )->toHaveKey( 'phone' );
	expect( $fields )->not->toHaveKey( 'random_column' );
} );

it( 'includes trait-declared metadata in the scan result', function (): void {
	$scanner = new PersonalDataScanner();

	$fields = $scanner->scanModel( ScannerSubjectWithTrait::class );

	expect( $fields['email']['source'] )->toBe( PersonalDataScanner::SOURCE_TRAIT );
	expect( $fields['email']['auto_discovered'] )->toBeFalse();
} );

it( 'persists discovered mappings without duplicating rows on re-scan', function (): void {
	$scanner = new PersonalDataScanner();
	$fields  = $scanner->scanModel( ScannerSubject::class );

	$scanner->persist( [ ScannerSubject::class => $fields ] );
	$scanner->persist( [ ScannerSubject::class => $fields ] );

	expect( PersonalDataMap::query()->where( 'model', ScannerSubject::class )->count() )
		->toBe( count( $fields ) );
} );

it( 'getFieldMappings returns persisted rows keyed by field', function (): void {
	$scanner = new PersonalDataScanner();

	$scanner->registerField( ScannerSubject::class, 'email', [
		'type'              => 'email',
		'sensitivity'       => 'sensitive',
		'deletion_strategy' => 'delete',
	] );

	$map = $scanner->getFieldMappings( ScannerSubject::class );

	expect( $map['email']['sensitivity'] )->toBe( 'sensitive' );
	expect( $map['email']['deletion_strategy'] )->toBe( 'delete' );
} );

it( 'respects the excluded models list', function (): void {
	config()->set( 'artisanpack.privacy.discovery.exclude_models', [ ScannerSubject::class ] );

	$scanner = new PersonalDataScanner();

	expect( $scanner->scanModel( ScannerSubject::class ) )->toBe( [] );
} );

it( 'classifies SSN-style columns as sensitive', function (): void {
	Schema::table( 'scan_subjects', function ( Blueprint $table ): void {
		$table->string( 'ssn' )->nullable();
	} );

	$scanner = new PersonalDataScanner();
	$fields  = $scanner->scanModel( ScannerSubject::class );

	expect( $fields['ssn']['sensitivity'] )->toBe( PersonalDataMap::SENSITIVITY_SENSITIVE );
} );
