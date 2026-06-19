<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Schema;

it( 'creates every privacy table when migrations run', function (): void {
	$tables = [
		'privacy_consents',
		'privacy_consent_categories',
		'privacy_data_requests',
		'privacy_data_request_logs',
		'privacy_personal_data_maps',
		'privacy_policies',
		'privacy_breach_notifications',
	];

	foreach ( $tables as $table ) {
		expect( Schema::hasTable( $table ) )->toBeTrue( "Missing table: {$table}" );
	}
} );

it( 'declares the expected columns on privacy_consents', function (): void {
	$columns = [
		'id',
		'consentable_type',
		'consentable_id',
		'category',
		'granted',
		'regulation',
		'ip_address',
		'user_agent',
		'metadata',
		'expires_at',
		'withdrawn_at',
		'created_at',
		'updated_at',
	];

	foreach ( $columns as $column ) {
		expect( Schema::hasColumn( 'privacy_consents', $column ) )->toBeTrue( "Missing column privacy_consents.{$column}" );
	}
} );

it( 'declares the expected columns on privacy_data_requests', function (): void {
	$columns = [
		'id',
		'requestable_type',
		'requestable_id',
		'type',
		'status',
		'regulation',
		'reason',
		'data',
		'verification_token',
		'verified_at',
		'due_at',
		'completed_at',
		'processed_by',
		'admin_notes',
	];

	foreach ( $columns as $column ) {
		expect( Schema::hasColumn( 'privacy_data_requests', $column ) )->toBeTrue( "Missing column privacy_data_requests.{$column}" );
	}
} );
