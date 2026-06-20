<?php

declare( strict_types=1 );

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'name' )->nullable();
	} );

	config()->set( 'artisanpack.privacy.admin.enabled', true );
	config()->set( 'artisanpack.privacy.admin.route_prefix', 'admin/privacy' );
	config()->set( 'artisanpack.privacy.admin.middleware', [ 'web' ] );
	config()->set( 'artisanpack.privacy.admin.gate', 'manage-privacy' );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

it( 'returns 403 when the manage-privacy gate denies access', function (): void {
	Gate::define( 'manage-privacy', static fn () => false );

	$this->actingAs( TestSubject::create() );

	$this->get( '/admin/privacy' )->assertForbidden();
	$this->get( '/admin/privacy/consents' )->assertForbidden();
	$this->get( '/admin/privacy/data-requests' )->assertForbidden();
	$this->get( '/admin/privacy/compliance-report' )->assertForbidden();
	$this->get( '/admin/privacy/breaches' )->assertForbidden();
	$this->get( '/admin/privacy/breaches/report' )->assertForbidden();
} );

it( 'renders the dashboard when the gate allows access', function (): void {
	Gate::define( 'manage-privacy', static fn () => true );

	$this->actingAs( TestSubject::create() );

	$this->get( '/admin/privacy' )
		->assertOk()
		->assertSee( __( 'Privacy admin' ) );
} );

it( 'registers admin routes under the configured prefix', function (): void {
	Gate::define( 'manage-privacy', static fn () => true );
	config()->set( 'artisanpack.privacy.admin.route_prefix', 'admin/privacy' );

	$this->actingAs( TestSubject::create() );

	expect( route( 'privacy.admin.dashboard' ) )->toContain( 'admin/privacy' );
	expect( route( 'privacy.admin.consents' ) )->toContain( 'admin/privacy/consents' );
	expect( route( 'privacy.admin.breaches' ) )->toContain( 'admin/privacy/breaches' );
} );

it( 'falls back to manage-privacy when admin.gate is empty', function (): void {
	config()->set( 'artisanpack.privacy.admin.gate', '' );
	Gate::define( 'manage-privacy', static fn () => true );

	$this->actingAs( TestSubject::create() );

	$this->get( '/admin/privacy' )->assertOk();
} );
