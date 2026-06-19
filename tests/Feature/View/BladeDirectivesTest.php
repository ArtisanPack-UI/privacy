<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Services\ConsentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'name' )->nullable();
	} );

	config()->set( 'artisanpack.privacy.consent.storage', 'both' );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
} );

it( 'renders the @hasConsent block only when consent is granted', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	app( ConsentService::class )->grantConsent( 'analytics', $subject );

	$template = <<<'BLADE'
@hasConsent('analytics')
granted
@endhasConsent
BLADE;

	$rendered = Blade::render( $template );

	expect( trim( $rendered ) )->toBe( 'granted' );
} );

it( 'omits the @hasConsent block when consent is missing', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	$template = <<<'BLADE'
@hasConsent('analytics')
granted
@endhasConsent
BLADE;

	$rendered = Blade::render( $template );

	expect( trim( $rendered ) )->toBe( '' );
} );

it( 'renders the @consentRequired block when consent is missing and falls through to @else', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	$template = <<<'BLADE'
@consentRequired('marketing')
prompt
@else
hidden
@endconsentRequired
BLADE;

	expect( trim( Blade::render( $template ) ) )->toBe( 'prompt' );

	app( ConsentService::class )->grantConsent( 'marketing', $subject );

	expect( trim( Blade::render( $template ) ) )->toBe( 'hidden' );
} );

it( 'treats unknown categories as ungranted', function (): void {
	$subject = TestSubject::create();
	$this->actingAs( $subject );

	$template = <<<'BLADE'
@hasConsent('does-not-exist')
yes
@endhasConsent
BLADE;

	expect( trim( Blade::render( $template ) ) )->toBe( '' );
} );
