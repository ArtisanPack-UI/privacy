<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Contracts\PrivacyRegulation;
use ArtisanPackUI\Privacy\Regulations\BaseRegulation;
use ArtisanPackUI\Privacy\Regulations\GenericRegulation;
use ArtisanPackUI\Privacy\Regulations\RegulationRegistry;
use Illuminate\Http\Request;

beforeEach( function (): void {
	config()->set( 'artisanpack.privacy.regulations', [
		'gdpr' => [
			'enabled'                   => true,
			'applies_to'                => [ 'EU', 'UK' ],
			'consent_expiry_days'       => 365,
			'breach_notification_hours' => 72,
		],
		'ccpa' => [
			'enabled'    => true,
			'applies_to' => [ 'US-CA' ],
		],
		'lgpd' => [
			'enabled'    => false,
			'applies_to' => [ 'BR' ],
		],
	] );
	config()->set( 'artisanpack.privacy.geolocation.fallback_region', null );
	config()->set( 'artisanpack.privacy.data_requests.response_days', [
		'gdpr'    => 30,
		'ccpa'    => 45,
		'default' => 30,
	] );

	app( RegulationRegistry::class )->flush();
} );

it( 'returns a config-driven generic regulation for known keys', function (): void {
	$registry = app( RegulationRegistry::class );

	$regulation = $registry->get( 'gdpr' );

	expect( $regulation )->toBeInstanceOf( GenericRegulation::class );
	expect( $regulation->key() )->toBe( 'gdpr' );
	expect( $regulation->getBreachNotificationHours() )->toBe( 72 );
	expect( $regulation->getResponseDays( 'access' ) )->toBe( 30 );
} );

it( 'returns null for keys that are not configured', function (): void {
	expect( app( RegulationRegistry::class )->get( 'pdpa' ) )->toBeNull();
} );

it( 'lets applications register a custom regulation class', function (): void {
	$registry = app( RegulationRegistry::class );

	$registry->register( new class extends BaseRegulation {
		protected string $key  = 'gdpr';

		protected string $name = 'GDPR (custom)';

		protected function consentRequirements(): array
		{
			return [ 'explicit_opt_in' => true ];
		}

		protected function dataRights(): array
		{
			return [ 'access', 'erasure', 'portability' ];
		}

		protected function retentionRules(): array
		{
			return [ 'consent' => 365 ];
		}

		protected function defaultResponseDays(): array
		{
			return [ 'default' => 30 ];
		}
	} );

	$regulation = $registry->get( 'gdpr' );

	expect( $regulation )->toBeInstanceOf( PrivacyRegulation::class );
	expect( $regulation->name() )->toBe( 'GDPR (custom)' );
	expect( $regulation->getDataRights() )->toContain( 'erasure' );
} );

it( 'returns every enabled regulation in all()', function (): void {
	$registry = app( RegulationRegistry::class );

	$all = $registry->all();

	expect( $all )->toHaveKey( 'gdpr' );
	expect( $all )->toHaveKey( 'ccpa' );
	expect( $all )->not->toHaveKey( 'lgpd' );
} );

it( 'identifies the applicable regulation from a request header', function (): void {
	$registry = app( RegulationRegistry::class );

	$request = Request::create( '/' );
	$request->headers->set( 'X-Region', 'EU' );

	$regulation = $registry->applicableFor( $request );

	expect( $regulation?->key() )->toBe( 'gdpr' );
} );

it( 'returns multiple matches when the visitor is subject to several regulations', function (): void {
	$registry = app( RegulationRegistry::class );

	$request = Request::create( '/' );
	$request->attributes->set( 'privacy_region', 'EU' );

	$matches = $registry->applicable( $request );

	$keys = array_map( static fn ( PrivacyRegulation $r ): string => $r->key(), $matches );

	expect( $keys )->toContain( 'gdpr' );
	expect( $keys )->not->toContain( 'ccpa' );
} );
