<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\ConsentCategory;

it( 'runs the privacy:install command non-interactively to completion', function (): void {
	$this->artisan( 'privacy:install', [ '--no-interaction' => true, '--force' => true ] )
		->assertExitCode( 0 )
		->expectsOutputToContain( 'Privacy package installed.' );
} );

it( 'seeds default consent categories from config', function (): void {
	expect( ConsentCategory::query()->count() )->toBe( 0 );

	$this->artisan( 'privacy:install', [
		'--no-interaction'   => true,
		'--force'            => true,
		'--skip-migrate'     => true,
	] )->assertExitCode( 0 );

	expect( ConsentCategory::query()->count() )->toBeGreaterThan( 0 );
	expect( ConsentCategory::query()->where( 'key', 'necessary' )->exists() )->toBeTrue();
} );

it( 'is idempotent when categories already exist', function (): void {
	ConsentCategory::query()->create( [
		'key'         => 'necessary',
		'name'        => 'Existing',
		'description' => 'pre-seeded',
		'required'    => true,
		'sort_order'  => 0,
		'active'      => true,
	] );

	$this->artisan( 'privacy:install', [
		'--no-interaction' => true,
		'--force'          => true,
		'--skip-migrate'   => true,
	] )
		->assertExitCode( 0 )
		->expectsOutputToContain( 'Consent categories already present' );

	expect( ConsentCategory::query()->where( 'key', 'necessary' )->first()->name )->toBe( 'Existing' );
} );

it( 'skips seeding when --skip-seed is set', function (): void {
	$this->artisan( 'privacy:install', [
		'--no-interaction' => true,
		'--force'          => true,
		'--skip-migrate'   => true,
		'--skip-seed'      => true,
	] )->assertExitCode( 0 );

	expect( ConsentCategory::query()->count() )->toBe( 0 );
} );

it( 'prints the admin gate stub', function (): void {
	$this->artisan( 'privacy:install', [
		'--no-interaction' => true,
		'--force'          => true,
		'--skip-migrate'   => true,
	] )
		->assertExitCode( 0 )
		->expectsOutputToContain( "Gate::define('manage-privacy'" );
} );
