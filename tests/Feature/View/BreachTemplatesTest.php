<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\BreachNotification;

it( 'renders the authority template with required GDPR Article 33 fields', function (): void {
	$breach = BreachNotification::factory()->create( [
		'reference_number'    => 'BR-AUTH-001',
		'description'         => 'Unauthorized read of accounts table',
		'data_types_affected' => [ 'email', 'name', 'hashed_password' ],
		'records_affected'    => 1500,
		'cause'               => 'Misconfigured access control on internal API',
		'remediation'         => 'Revoked offending key, rotated all admin tokens',
		'severity'            => BreachNotification::SEVERITY_HIGH,
		'status'              => BreachNotification::STATUS_CONTAINED,
	] );

	$rendered = view( 'privacy::breach-templates.authority', [
		'breach'       => $breach,
		'organization' => [
			'name'    => 'Acme, Inc.',
			'address' => '1 Privacy Way',
			'website' => 'https://acme.test',
			'contact' => 'privacy@acme.test',
		],
		'dpo' => [
			'name'  => 'Casey DPO',
			'email' => 'dpo@acme.test',
			'phone' => '+1-555-0100',
		],
	] )->render();

	expect( $rendered )
		->toContain( 'BR-AUTH-001' )
		->toContain( 'Acme, Inc.' )
		->toContain( 'Casey DPO' )
		->toContain( 'dpo@acme.test' )
		->toContain( 'Unauthorized read of accounts table' )
		->toContain( 'hashed_password' )
		->toContain( '1500' )
		->toContain( 'Revoked offending key' )
		->toContain( 'Personal Data Breach Notification' );
} );

it( 'renders the user template with required GDPR Article 34 fields', function (): void {
	$breach = BreachNotification::factory()->create( [
		'reference_number'    => 'BR-USER-002',
		'description'         => 'A subset of profile data was viewable by other users for 30 minutes.',
		'data_types_affected' => [ 'name', 'display_name' ],
		'remediation'         => 'Patched the visibility check and audited access logs.',
	] );

	$rendered = view( 'privacy::breach-templates.user', [
		'breach' => $breach,
		'user'   => [
			'email' => 'alice@example.test',
			'name'  => 'Alice',
		],
		'organization' => [
			'name'    => 'Acme, Inc.',
			'contact' => 'privacy@acme.test',
			'website' => 'https://acme.test',
		],
	] )->render();

	expect( $rendered )
		->toContain( 'Hello' )
		->toContain( 'Alice' )
		->toContain( 'A subset of profile data was viewable' )
		->toContain( 'display_name' )
		->toContain( 'Patched the visibility check' )
		->toContain( 'Acme, Inc.' )
		->toContain( 'Review your account for any unfamiliar activity.' );
} );

it( 'prefers per-user data scope over the breach-wide data scope', function (): void {
	$breach = BreachNotification::factory()->create( [
		'data_types_affected' => [ 'email', 'name', 'address' ],
	] );

	$rendered = view( 'privacy::breach-templates.user', [
		'breach' => $breach,
		'user'   => [
			'email' => 'bob@example.test',
			'data'  => [ 'email' ],
		],
		'organization' => [ 'name' => 'Acme' ],
	] )->render();

	expect( $rendered )->toContain( 'email' );
	expect( $rendered )->not->toContain( 'address' );
} );
