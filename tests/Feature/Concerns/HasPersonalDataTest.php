<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Concerns\HasPersonalData;
use ArtisanPackUI\Privacy\Services\AnonymizationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach( function (): void {
	Schema::create( 'has_pd_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'email' )->nullable();
		$table->string( 'name' )->nullable();
	} );

	if ( ! class_exists( HasPersonalDataModel::class ) ) {
		eval( <<<'PHP'
			class HasPersonalDataModel extends \Illuminate\Database\Eloquent\Model
			{
				use \ArtisanPackUI\Privacy\Concerns\HasPersonalData;

				public $timestamps = false;
				protected $table = 'has_pd_subjects';
				protected $guarded = [];

				public function personalDataFields(): array
				{
					return [
						'email' => [
							'type'              => 'email',
							'sensitivity'       => 'normal',
							'deletion_strategy' => 'anonymize',
						],
						'name' => [
							'type'              => 'name',
							'sensitivity'       => 'normal',
							'deletion_strategy' => 'anonymize',
						],
					];
				}
			}
		PHP );
	}

	if ( ! class_exists( HasPersonalDataAliasModel::class ) ) {
		eval( <<<'PHP'
			class HasPersonalDataAliasModel extends \Illuminate\Database\Eloquent\Model
			{
				use \ArtisanPackUI\Privacy\Traits\HasPersonalData;

				public $timestamps = false;
				protected $table = 'has_pd_subjects';
				protected $guarded = [];

				protected $personalDataFields = [ 'email', 'name' ];
			}
		PHP );
	}
} );

afterEach( function (): void {
	Schema::dropIfExists( 'has_pd_subjects' );
} );

it( 'returns a flat list of column names regardless of declaration shape', function (): void {
	$model = new HasPersonalDataModel( [ 'email' => 'a@example.com', 'name' => 'A' ] );

	expect( $model->personalDataFieldNames() )->toBe( [ 'email', 'name' ] );
} );

it( 'returns per-field metadata when declared as a map', function (): void {
	$model = new HasPersonalDataModel();

	expect( $model->personalDataFieldMetadata( 'email' )['type'] )->toBe( 'email' );
	expect( $model->personalDataFieldMetadata( 'email' )['deletion_strategy'] )->toBe( 'anonymize' );
	expect( $model->personalDataFieldMetadata( 'missing' ) )->toBe( [] );
} );

it( 'getPersonalData returns a column → value map', function (): void {
	$model = new HasPersonalDataModel( [ 'email' => 'a@example.com', 'name' => 'A' ] );

	expect( $model->getPersonalData() )->toBe( [
		'email' => 'a@example.com',
		'name'  => 'A',
	] );
} );

it( 'works through the Traits alias and exposes the same API', function (): void {
	$model = new HasPersonalDataAliasModel( [ 'email' => 'b@example.com', 'name' => 'B' ] );

	expect( in_array(
		HasPersonalData::class,
		class_uses_recursive( $model ),
		true,
	) )->toBeTrue();

	expect( $model->getPersonalData() )->toBe( [
		'email' => 'b@example.com',
		'name'  => 'B',
	] );
} );

it( 'anonymizePersonalData uses the per-field strategy from metadata', function (): void {
	$model = HasPersonalDataModel::create( [ 'email' => 'pre@example.com', 'name' => 'Pre' ] );

	expect( $model->anonymizePersonalData() )->toBeTrue();

	$model->refresh();

	expect( $model->email )->not->toBe( 'pre@example.com' );
	expect( $model->name )->not->toBe( 'Pre' );
} );

it( 'deletePersonalData delegates to the DataDeletionService', function (): void {
	$model = HasPersonalDataModel::create( [ 'email' => 'pre@example.com', 'name' => 'Pre' ] );

	expect( $model->deletePersonalData() )->toBeTrue();

	$model->refresh();

	expect( $model->email )->not->toBe( 'pre@example.com' );
} );

it( 'falls back to the anonymization service defaults when no metadata is declared', function (): void {
	$model = HasPersonalDataAliasModel::create( [ 'email' => 'plain@example.com', 'name' => 'Plain' ] );

	expect( app( AnonymizationService::class ) )->toBeInstanceOf( AnonymizationService::class );

	// The Traits alias model declares fields as a plain list — anonymization
	// should still work using config defaults from the service.
	expect( $model->anonymizePersonalData() )->toBeTrue();
} );

it( 'anonymizes declared fields that do not match any discovery pattern', function (): void {
	Schema::table( 'has_pd_subjects', function ( Blueprint $table ): void {
		$table->string( 'arbitrary_handle' )->nullable();
	} );

	if ( ! class_exists( HasPersonalDataNonPatternModel::class ) ) {
		eval( <<<'PHP'
			class HasPersonalDataNonPatternModel extends \Illuminate\Database\Eloquent\Model
			{
				use \ArtisanPackUI\Privacy\Concerns\HasPersonalData;

				public $timestamps = false;
				protected $table = 'has_pd_subjects';
				protected $guarded = [];

				protected $personalDataFields = [ 'arbitrary_handle' ];
			}
		PHP );
	}

	$model = HasPersonalDataNonPatternModel::create( [
		'arbitrary_handle' => 'original-handle-value',
	] );

	expect( $model->anonymizePersonalData() )->toBeTrue();

	$model->refresh();

	expect( $model->arbitrary_handle )->not->toBe( 'original-handle-value' );
	expect( $model->arbitrary_handle )->not->toBeNull();
} );
