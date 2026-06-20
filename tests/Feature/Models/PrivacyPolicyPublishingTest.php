<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\PrivacyPolicy;

it( 'replaces the previously active policy when publishing a new one', function (): void {
	$oldPolicy = PrivacyPolicy::factory()->active()->create( [
		'regulation' => 'gdpr',
		'locale'     => 'en',
		'version'    => '1.0.0',
	] );

	$newPolicy = PrivacyPolicy::factory()->create( [
		'regulation' => 'gdpr',
		'locale'     => 'en',
		'version'    => '1.1.0',
		'active'     => false,
	] );

	$newPolicy->publish();

	expect( $oldPolicy->fresh()->active )->toBeFalse();
	expect( $newPolicy->fresh()->active )->toBeTrue();
	expect( $newPolicy->fresh()->published_at )->not->toBeNull();
} );

it( 'leaves policies for other regulations alone when publishing', function (): void {
	$gdprPolicy = PrivacyPolicy::factory()->active()->create( [
		'regulation' => 'gdpr',
		'locale'     => 'en',
		'version'    => '1.0.0',
	] );

	$ccpa = PrivacyPolicy::factory()->create( [
		'regulation' => 'ccpa',
		'locale'     => 'en',
		'version'    => '1.0.0',
		'active'     => false,
	] );

	$ccpa->publish();

	expect( $gdprPolicy->fresh()->active )->toBeTrue();
	expect( $ccpa->fresh()->active )->toBeTrue();
} );

it( 'renders Markdown content to HTML', function (): void {
	$policy = PrivacyPolicy::factory()->create( [
		'content' => "# Title\n\nBody text.",
	] );

	expect( $policy->renderHtml() )->toContain( '<h1>Title</h1>' );
} );

it( 'returns a table of contents from the stored sections when present', function (): void {
	$policy = PrivacyPolicy::factory()->create( [
		'content'  => "# Hidden\n\nBody",
		'sections' => [
			[ 'heading' => 'Visible', 'slug' => 'visible', 'level' => 2 ],
		],
	] );

	expect( $policy->tableOfContents() )->toBe( [
		[ 'heading' => 'Visible', 'slug' => 'visible', 'level' => 2 ],
	] );
} );

it( 'falls back to extracting from content when sections are empty', function (): void {
	$policy = PrivacyPolicy::factory()->create( [
		'content'  => "# One\n\n## Two",
		'sections' => null,
	] );

	expect( $policy->tableOfContents() )->toHaveCount( 2 );
} );

it( 'produces a sortable version key', function (): void {
	$ten  = PrivacyPolicy::factory()->make( [ 'version' => '1.10.0' ] );
	$nine = PrivacyPolicy::factory()->make( [ 'version' => '1.9.0' ] );

	expect( $ten->versionSortKey() > $nine->versionSortKey() )->toBeTrue();
} );
