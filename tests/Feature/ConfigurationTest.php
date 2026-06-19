<?php

declare( strict_types=1 );

it( 'merges the privacy configuration namespace', function (): void {
	expect( config( 'artisanpack.privacy.regulations.gdpr.enabled' ) )->toBeTrue();
	expect( config( 'artisanpack.privacy.consent.storage' ) )->toBe( 'both' );
	expect( config( 'artisanpack.privacy.cookie_categories.necessary.required' ) )->toBeTrue();
	expect( config( 'artisanpack.privacy.ui.cookie_banner.position' ) )->toBe( 'bottom' );
	expect( config( 'artisanpack.privacy.routes.prefix' ) )->toBe( 'privacy' );
} );

it( 'declares every documented top-level configuration section', function (): void {
	$keys = [
		'enabled',
		'regulations',
		'consent',
		'cookie_categories',
		'data_requests',
		'deletion',
		'anonymization',
		'discovery',
		'geolocation',
		'ui',
		'routes',
		'admin',
	];

	foreach ( $keys as $key ) {
		expect( config()->has( "artisanpack.privacy.{$key}" ) )
			->toBeTrue( "Missing privacy config section: {$key}" );
	}
} );
