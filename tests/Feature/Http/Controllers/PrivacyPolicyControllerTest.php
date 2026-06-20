<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\PrivacyPolicy;

it( 'returns 404 when no active policy exists', function (): void {
	$this->get( '/privacy/policy' )->assertNotFound();
} );

it( 'renders the active policy at GET /privacy/policy', function (): void {
	$policy = PrivacyPolicy::factory()->active()->create( [
		'version' => '1.0.0',
		'content' => "# Acme Privacy Policy\n\nWelcome.",
	] );

	$this->get( '/privacy/policy' )
		->assertOk()
		->assertSee( 'Acme Privacy Policy' )
		->assertSee( '1.0.0' );
} );

it( 'renders a specific policy version at GET /privacy/policy/{version}', function (): void {
	PrivacyPolicy::factory()->create( [
		'version'      => '1.0.0',
		'content'      => '# Version One',
		'published_at' => now()->subWeek(),
	] );
	PrivacyPolicy::factory()->active()->create( [
		'version' => '1.1.0',
		'content' => '# Version Two',
	] );

	$this->get( '/privacy/policy/1.0.0' )
		->assertOk()
		->assertSee( 'Version One' );
} );

it( 'returns 404 for unknown policy versions', function (): void {
	PrivacyPolicy::factory()->active()->create( [ 'version' => '1.0.0' ] );

	$this->get( '/privacy/policy/9.9.9' )->assertNotFound();
} );

it( 'prefers the regulation-specific policy when requested', function (): void {
	PrivacyPolicy::factory()->active()->create( [
		'regulation' => null,
		'content'    => '# General',
	] );
	PrivacyPolicy::factory()->active()->create( [
		'regulation' => 'gdpr',
		'content'    => '# GDPR Specific',
	] );

	$this->get( '/privacy/policy?regulation=gdpr' )
		->assertOk()
		->assertSee( 'GDPR Specific' );
} );
