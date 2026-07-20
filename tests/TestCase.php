<?php

declare( strict_types=1 );

namespace Tests;

use ArtisanPackUI\Hooks\Providers\HooksServiceProvider;
use ArtisanPackUI\Privacy\PrivacyServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Tests\Support\TestSubject;

/**
 * Base Test Case
 *
 * Provides base functionality for all package tests.
 *
 * @since   1.0.0
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

        Relation::enforceMorphMap( [
            'test_subject' => TestSubject::class,
        ] );
    }

    /**
     * Gets package providers.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     *
     * @return array<int, class-string> Array of service provider class names.
     */
    protected function getPackageProviders( $app ): array
    {
        $providers = [
            HooksServiceProvider::class,
            PrivacyServiceProvider::class,
        ];

        if ( class_exists( \Livewire\LivewireServiceProvider::class ) ) {
            array_unshift( $providers, \Livewire\LivewireServiceProvider::class );
        }

        return $providers;
    }

    /**
     * Defines environment setup.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     */
    protected function defineEnvironment( $app ): void
    {
        // Setup app key for encryption
        $app['config']->set( 'app.key', 'base64:' . base64_encode( random_bytes( 32 ) ) );

        // Setup default database to use sqlite :memory:
        $app['config']->set( 'database.default', 'testbench' );
        $app['config']->set( 'database.connections.testbench', [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ] );
    }
}
