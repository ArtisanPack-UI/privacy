<?php

/**
 * Privacy service provider.
 *
 * Bootstraps the Privacy package by registering services, merging
 * configuration, loading migrations, wiring the event-listener map,
 * registering Livewire components (when Livewire is installed), and
 * exposing publishable assets.
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

use ArtisanPackUI\Privacy\Events\ConsentGiven;
use ArtisanPackUI\Privacy\Events\ConsentWithdrawn;
use ArtisanPackUI\Privacy\Events\DataAccessRequested;
use ArtisanPackUI\Privacy\Events\DataBreach;
use ArtisanPackUI\Privacy\Events\DataDeletionRequested;
use ArtisanPackUI\Privacy\Events\DataExportRequested;
use ArtisanPackUI\Privacy\Listeners\LogConsentActivity;
use ArtisanPackUI\Privacy\Listeners\NotifyAdminOfRequest;
use ArtisanPackUI\Privacy\Listeners\NotifyDataBreach;
use ArtisanPackUI\Privacy\Listeners\ProcessDataAccessRequest;
use ArtisanPackUI\Privacy\Listeners\ProcessDataExportRequest;
use ArtisanPackUI\Privacy\Listeners\SyncConsentOnLogin;
use ArtisanPackUI\Privacy\Livewire\ConsentPreferences;
use ArtisanPackUI\Privacy\Livewire\CookieBanner;
use ArtisanPackUI\Privacy\Services\AnonymizationService;
use ArtisanPackUI\Privacy\Services\ConsentService;
use ArtisanPackUI\Privacy\Services\DataRequestService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
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
	 * Event-to-listener map registered during {@see boot()}.
	 *
	 * @var array<class-string, array<int, array{0: class-string, 1: string}>>
	 */
	protected array $listen = [
		ConsentGiven::class => [
			[ LogConsentActivity::class, 'handleConsentGiven' ],
		],
		ConsentWithdrawn::class => [
			[ LogConsentActivity::class, 'handleConsentWithdrawn' ],
		],
		DataAccessRequested::class => [
			[ ProcessDataAccessRequest::class, 'handle' ],
			[ NotifyAdminOfRequest::class, 'handleAccess' ],
		],
		DataExportRequested::class => [
			[ ProcessDataExportRequest::class, 'handle' ],
			[ NotifyAdminOfRequest::class, 'handleExport' ],
		],
		DataDeletionRequested::class => [
			[ NotifyAdminOfRequest::class, 'handleDeletion' ],
		],
		DataBreach::class => [
			[ NotifyDataBreach::class, 'handle' ],
		],
		Login::class => [
			[ SyncConsentOnLogin::class, 'handle' ],
		],
	];

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

		$this->app->singleton(
			DataRequestService::class,
			fn ( $app ) => new DataRequestService( $app->make( ConsentService::class ) ),
		);
		$this->app->alias( DataRequestService::class, 'privacy.data_requests' );

		$this->app->singleton( AnonymizationService::class, fn () => new AnonymizationService() );
		$this->app->alias( AnonymizationService::class, 'privacy.anonymization' );
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

		$this->registerEventListeners();
		$this->loadPackageViews();
		$this->registerLivewireComponents();
		$this->registerApiRoutes();
	}

	/**
	 * Registers the package's JSON API routes under the configured prefix
	 * and middleware stack. Skips registration when route handling is
	 * disabled via `artisanpack.privacy.routes.enabled`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerApiRoutes(): void
	{
		if ( true !== (bool) config( 'artisanpack.privacy.routes.enabled', true ) ) {
			return;
		}

		Route::group( [
			'prefix'     => (string) config( 'artisanpack.privacy.routes.api_prefix', 'api/privacy' ),
			'middleware' => (array) config( 'artisanpack.privacy.routes.api_middleware', [ 'api' ] ),
		], function (): void {
			$this->loadRoutesFrom( __DIR__ . '/../routes/api.php' );
		} );
	}

	/**
	 * Wires the package's default events and listeners.
	 *
	 * Application code can override individual bindings by registering its
	 * own listeners in `AppServiceProvider` — Laravel composes listener
	 * lists rather than replacing them.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerEventListeners(): void
	{
		foreach ( $this->listen as $event => $listeners ) {
			foreach ( $listeners as $listener ) {
				Event::listen( $event, $listener );
			}
		}
	}

	/**
	 * Loads the package's view namespace and registers the publishable views.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function loadPackageViews(): void
	{
		$viewsPath = __DIR__ . '/../resources/views';

		if ( ! is_dir( $viewsPath ) ) {
			return;
		}

		$this->loadViewsFrom( $viewsPath, 'privacy' );

		$this->publishes( [
			$viewsPath => resource_path( 'views/vendor/artisanpack-ui/privacy' ),
		], 'privacy-views' );
	}

	/**
	 * Registers the package's Livewire components when Livewire is installed.
	 *
	 * Livewire is an optional peer dependency — the package gracefully
	 * degrades when it is not present so applications that only use the
	 * services and helpers do not need it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerLivewireComponents(): void
	{
		if ( ! class_exists( \Livewire\Livewire::class ) ) {
			return;
		}

		\Livewire\Livewire::component( 'privacy-cookie-banner', CookieBanner::class );
		\Livewire\Livewire::component( 'privacy-consent-preferences', ConsentPreferences::class );
	}
}
