<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Regulations\Ccpa;
use Illuminate\Http\Request;

beforeEach( function (): void {
	config()->set( 'artisanpack.privacy.regulations.ccpa', [
		'enabled'              => true,
		'applies_to'           => [ 'US-CA' ],
		'opt_out_sale'         => true,
		'financial_threshold'  => 25000000,
	] );
	config()->set( 'artisanpack.privacy.data_requests.response_days', [
		'ccpa'    => 45,
		'default' => 30,
	] );
} );

it( 'identifies itself with the ccpa key', function (): void {
	$ccpa = new Ccpa();

	expect( $ccpa->key() )->toBe( 'ccpa' );
	expect( $ccpa->name() )->toBe( 'CCPA/CPRA' );
} );

it( 'returns the expected consent requirements', function (): void {
	$requirements = ( new Ccpa() )->getConsentRequirements();

	expect( $requirements )->toMatchArray( [
		'opt_out_model'        => true,
		'opt_in_for_under_16'  => true,
		'opt_in_for_sensitive' => true,
		'do_not_sell_link'     => true,
		'non_discrimination'   => true,
	] );
} );

it( 'lists every CCPA/CPRA data subject right', function (): void {
	$rights = ( new Ccpa() )->getDataRights();

	expect( $rights )->toContain( Ccpa::RIGHT_TO_KNOW );
	expect( $rights )->toContain( Ccpa::RIGHT_TO_DELETE );
	expect( $rights )->toContain( Ccpa::RIGHT_TO_CORRECT );
	expect( $rights )->toContain( Ccpa::RIGHT_TO_OPT_OUT_OF_SALE );
	expect( $rights )->toContain( Ccpa::RIGHT_TO_LIMIT_SENSITIVE_USE );
	expect( $rights )->toContain( Ccpa::RIGHT_TO_NON_DISCRIMINATION );
	expect( $rights )->toContain( Ccpa::RIGHT_TO_DATA_PORTABILITY );
} );

it( 'returns the 45 day response deadline', function (): void {
	expect( ( new Ccpa() )->getResponseDays( 'access' ) )->toBe( 45 );
} );

it( 'returns the shorter opt-out-of-sale deadline from the default map', function (): void {
	config()->set( 'artisanpack.privacy.data_requests.response_days', [
		'ccpa' => [ 'opt_out_of_sale' => 15, 'default' => 45 ],
	] );

	expect( ( new Ccpa() )->getResponseDays( 'opt_out_of_sale' ) )->toBe( 15 );
} );

it( 'requires the do-not-sell link by default', function (): void {
	expect( ( new Ccpa() )->requiresDoNotSellLink() )->toBeTrue();
} );

it( 'lets the host opt out of the do-not-sell link', function (): void {
	config()->set( 'artisanpack.privacy.regulations.ccpa.opt_out_sale', false );

	expect( ( new Ccpa() )->requiresDoNotSellLink() )->toBeFalse();
} );

it( 'evaluates the business threshold against configured revenue', function (): void {
	$ccpa = new Ccpa();

	expect( $ccpa->meetsBusinessThreshold() )->toBeFalse();

	config()->set( 'artisanpack.privacy.regulations.ccpa.annual_revenue', 26000000 );

	expect( $ccpa->meetsBusinessThreshold() )->toBeTrue();
} );

it( 'evaluates the business threshold against consumer counts', function (): void {
	config()->set( 'artisanpack.privacy.regulations.ccpa.annual_consumers', 250000 );

	expect( ( new Ccpa() )->meetsBusinessThreshold() )->toBeTrue();
} );

it( 'evaluates the business threshold against sale revenue share', function (): void {
	config()->set( 'artisanpack.privacy.regulations.ccpa.actual_sale_revenue_share', 0.6 );

	expect( ( new Ccpa() )->meetsBusinessThreshold() )->toBeTrue();
} );

it( 'applies for a California request', function (): void {
	$ccpa    = new Ccpa();
	$request = Request::create( '/' );
	$request->attributes->set( 'privacy_region', 'US-CA' );

	expect( $ccpa->applies( $request ) )->toBeTrue();
} );

it( 'does not apply for non-California regions', function (): void {
	$ccpa    = new Ccpa();
	$request = Request::create( '/' );
	$request->attributes->set( 'privacy_region', 'US-NY' );

	expect( $ccpa->applies( $request ) )->toBeFalse();
} );

it( 'does not apply when the regulation is disabled in config', function (): void {
	config()->set( 'artisanpack.privacy.regulations.ccpa.enabled', false );

	$ccpa    = new Ccpa();
	$request = Request::create( '/' );
	$request->attributes->set( 'privacy_region', 'US-CA' );

	expect( $ccpa->applies( $request ) )->toBeFalse();
} );

it( 'exposes the configured financial incentives map', function (): void {
	config()->set( 'artisanpack.privacy.regulations.ccpa.financial_incentives', [
		'loyalty' => [ 'description' => 'Loyalty program' ],
	] );

	$incentives = ( new Ccpa() )->financialIncentives();

	expect( $incentives )->toHaveKey( 'loyalty' );
} );
