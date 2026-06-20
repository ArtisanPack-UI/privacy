<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Regulations\Gdpr;
use Illuminate\Http\Request;

beforeEach( function (): void {
	config()->set( 'artisanpack.privacy.regulations.gdpr', [
		'enabled'                   => true,
		'applies_to'                => [ 'EU', 'EEA', 'UK' ],
		'consent_expiry_days'       => 365,
		'breach_notification_hours' => 72,
	] );
	config()->set( 'artisanpack.privacy.data_requests.response_days', [
		'gdpr'    => 30,
		'default' => 30,
	] );
} );

it( 'identifies itself with the gdpr key', function (): void {
	$gdpr = new Gdpr();

	expect( $gdpr->key() )->toBe( 'gdpr' );
	expect( $gdpr->name() )->toBe( 'GDPR' );
} );

it( 'returns the expected consent requirements', function (): void {
	$requirements = ( new Gdpr() )->getConsentRequirements();

	expect( $requirements )->toMatchArray( [
		'explicit_opt_in'     => true,
		'granular'            => true,
		'withdrawable'        => true,
		'unbundled'           => true,
		'no_pre_ticked_boxes' => true,
		'records_required'    => true,
	] );
	expect( $requirements['expiry_days'] )->toBe( 365 );
} );

it( 'lists every GDPR data subject right', function (): void {
	$rights = ( new Gdpr() )->getDataRights();

	expect( $rights )->toContain( 'access' );
	expect( $rights )->toContain( 'rectification' );
	expect( $rights )->toContain( 'erasure' );
	expect( $rights )->toContain( 'portability' );
	expect( $rights )->toContain( 'restriction' );
	expect( $rights )->toContain( 'objection' );
	expect( $rights )->toContain( 'automated_decision_making' );
	expect( $rights )->toContain( 'be_informed' );
} );

it( 'returns the 72 hour breach notification window', function (): void {
	expect( ( new Gdpr() )->getBreachNotificationHours() )->toBe( 72 );
} );

it( 'returns a 30 day response deadline', function (): void {
	$gdpr = new Gdpr();

	expect( $gdpr->getResponseDays( 'access' ) )->toBe( 30 );
	expect( $gdpr->getResponseDays( 'deletion' ) )->toBe( 30 );
} );

it( 'returns the configurable consent expiry window', function (): void {
	config()->set( 'artisanpack.privacy.regulations.gdpr.consent_expiry_days', 180 );

	expect( ( new Gdpr() )->consentExpiryDays() )->toBe( 180 );
} );

it( 'applies for a request flagged as EU', function (): void {
	$gdpr    = new Gdpr();
	$request = Request::create( '/' );
	$request->attributes->set( 'privacy_region', 'EU' );

	expect( $gdpr->applies( $request ) )->toBeTrue();
} );

it( 'applies for an EU member country via translation', function (): void {
	$gdpr    = new Gdpr();
	$request = Request::create( '/' );
	$request->attributes->set( 'privacy_region', 'DE' );

	expect( $gdpr->applies( $request ) )->toBeTrue();
} );

it( 'applies for a UK request via the GB alias', function (): void {
	$gdpr    = new Gdpr();
	$request = Request::create( '/' );
	$request->attributes->set( 'privacy_region', 'GB' );

	expect( $gdpr->applies( $request ) )->toBeTrue();
} );

it( 'applies for an EEA country that is not in the EU', function (): void {
	$gdpr    = new Gdpr();
	$request = Request::create( '/' );
	$request->attributes->set( 'privacy_region', 'NO' );

	expect( $gdpr->applies( $request ) )->toBeTrue();
} );

it( 'does not apply for a request from outside the EU/EEA/UK', function (): void {
	$gdpr    = new Gdpr();
	$request = Request::create( '/' );
	$request->attributes->set( 'privacy_region', 'US-CA' );

	expect( $gdpr->applies( $request ) )->toBeFalse();
} );

it( 'does not apply when the regulation is disabled in config', function (): void {
	config()->set( 'artisanpack.privacy.regulations.gdpr.enabled', false );

	$gdpr    = new Gdpr();
	$request = Request::create( '/' );
	$request->attributes->set( 'privacy_region', 'EU' );

	expect( $gdpr->applies( $request ) )->toBeFalse();
} );

it( 'exposes data protection officer contact information when configured', function (): void {
	config()->set( 'artisanpack.privacy.regulations.gdpr.dpo', [
		'name'  => 'Jane Smith',
		'email' => 'dpo@example.test',
	] );

	$dpo = ( new Gdpr() )->dataProtectionOfficer();

	expect( $dpo )->toMatchArray( [ 'name' => 'Jane Smith', 'email' => 'dpo@example.test' ] );
} );

it( 'lists the six legal bases for processing', function (): void {
	$bases = ( new Gdpr() )->legalBases();

	expect( $bases )->toContain( Gdpr::LEGAL_BASIS_CONSENT );
	expect( $bases )->toContain( Gdpr::LEGAL_BASIS_LEGITIMATE_INTEREST );
	expect( $bases )->toHaveCount( 6 );
} );
