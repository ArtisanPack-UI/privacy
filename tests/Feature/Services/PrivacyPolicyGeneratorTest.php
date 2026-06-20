<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\PrivacyPolicy;
use ArtisanPackUI\Privacy\Services\PrivacyPolicyGenerator;

it( 'generates a draft policy using the GDPR template', function (): void {
	config()->set( 'artisanpack.privacy.breach.organization.name', 'Acme Co.' );
	config()->set( 'artisanpack.privacy.breach.organization.contact', 'privacy@acme.test' );
	config()->set( 'artisanpack.privacy.breach.dpo.email', 'dpo@acme.test' );

	$policy = ( new PrivacyPolicyGenerator() )->generate( [
		'regulation' => 'gdpr',
		'version'    => '1.0.0',
		'locale'     => 'en',
	] );

	expect( $policy )->toBeInstanceOf( PrivacyPolicy::class );
	expect( $policy->regulation )->toBe( 'gdpr' );
	expect( $policy->active )->toBeFalse();
	expect( $policy->published_at )->toBeNull();
	expect( $policy->content )->toContain( 'Acme Co.' );
	expect( $policy->content )->toContain( 'dpo@acme.test' );
	expect( $policy->content )->not->toContain( '{{company_name}}' );
	expect( $policy->sections )->not->toBeEmpty();
} );

it( 'strips conditional blocks when their placeholder is empty', function (): void {
	$generator = new PrivacyPolicyGenerator();

	$rendered = $generator->renderTemplate(
		'Hello {{name}}!{{#extra}} Extra: {{extra}}{{/extra}}',
		[ 'name' => 'World', 'extra' => '' ],
	);

	expect( $rendered )->toBe( 'Hello World!' );

	$renderedWithExtra = $generator->renderTemplate(
		'Hello {{name}}!{{#extra}} Extra: {{extra}}{{/extra}}',
		[ 'name' => 'World', 'extra' => 'yes' ],
	);

	expect( $renderedWithExtra )->toBe( 'Hello World! Extra: yes' );
} );

it( 'extracts the section outline used by the table of contents', function (): void {
	$sections = ( new PrivacyPolicyGenerator() )->extractSections(
		"# Title\n\n## Section A\n\nSome text.\n\n## Section B",
	);

	expect( $sections )->toHaveCount( 3 );
	expect( $sections[1] )->toMatchArray( [
		'heading' => 'Section A',
		'slug'    => 'section-a',
		'level'   => 2,
	] );
} );

it( 'activates the policy when activate=true is passed', function (): void {
	$policy = ( new PrivacyPolicyGenerator() )->generate( [
		'regulation' => 'ccpa',
		'activate'   => true,
	] );

	expect( $policy->active )->toBeTrue();
	expect( $policy->published_at )->not->toBeNull();
} );

it( 'falls back to the general template for unknown regulations', function (): void {
	$policy = ( new PrivacyPolicyGenerator() )->generate( [
		'regulation' => 'unknown_regulation',
	] );

	expect( $policy->regulation )->toBe( 'general' );
	expect( $policy->content )->toContain( 'Privacy Policy' );
} );
