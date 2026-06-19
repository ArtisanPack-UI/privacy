<?php

/**
 * Privacy service provider.
 *
 * Bootstraps the Privacy package by registering services, merging
 * configuration, loading migrations, and exposing publishable assets.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy;

use ArtisanPackUI\Privacy\Services\ConsentService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Privacy package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */
class PrivacyServiceProvider extends ServiceProvider
{
	/**
	 * Registers any application services.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void
	{
		$this->mergeConfigFrom(
			__DIR__ . '/../config/artisanpack/privacy.php',
			'artisanpack.privacy',
		);

		$this->app->singleton( 'privacy', fn () => new Privacy() );

		$this->app->singleton( ConsentService::class, fn () => new ConsentService() );
		$this->app->alias( ConsentService::class, 'privacy.consent' );
	}

	/**
	 * Bootstraps any application services.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function boot(): void
	{
		$this->publishes( [
			__DIR__ . '/../config/artisanpack/privacy.php'
				 => config_path( 'artisanpack/privacy.php' ),
		], 'privacy-config' );

		if ( ! config( 'artisanpack.privacy.enabled', true ) ) {
			return;
		}

		$this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

		$this->publishes( [
			__DIR__ . '/../database/migrations' => database_path( 'migrations' ),
		], 'privacy-migrations' );
	}
}
