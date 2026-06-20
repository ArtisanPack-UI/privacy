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

use ArtisanPackUI\Privacy\Console\Commands\PrivacyInstall;
use ArtisanPackUI\Privacy\Console\Commands\PrivacyScan;
use ArtisanPackUI\Privacy\Console\Commands\PurgeExpiredConsents;
use ArtisanPackUI\Privacy\Events\ConsentGiven;
use ArtisanPackUI\Privacy\Events\ConsentWithdrawn;
use ArtisanPackUI\Privacy\Events\DataAccessRequested;
use ArtisanPackUI\Privacy\Events\DataBreach;
use ArtisanPackUI\Privacy\Events\DataDeletionRequested;
use ArtisanPackUI\Privacy\Events\DataExportRequested;
use ArtisanPackUI\Privacy\Events\DataRectificationRequested;
use ArtisanPackUI\Privacy\Events\PolicyReconsentGiven;
use ArtisanPackUI\Privacy\Events\PolicyReconsentRequired;
use ArtisanPackUI\Privacy\Listeners\LogConsentActivity;
use ArtisanPackUI\Privacy\Listeners\LogPolicyReconsent;
use ArtisanPackUI\Privacy\Listeners\NotifyAdminOfRequest;
use ArtisanPackUI\Privacy\Listeners\NotifyDataBreach;
use ArtisanPackUI\Privacy\Listeners\ProcessDataAccessRequest;
use ArtisanPackUI\Privacy\Listeners\ProcessDataExportRequest;
use ArtisanPackUI\Privacy\Listeners\SyncConsentOnLogin;
use ArtisanPackUI\Privacy\Livewire\Admin\BreachDetail;
use ArtisanPackUI\Privacy\Livewire\Admin\BreachManager;
use ArtisanPackUI\Privacy\Livewire\Admin\BreachReportForm;
use ArtisanPackUI\Privacy\Livewire\Admin\ComplianceReport;
use ArtisanPackUI\Privacy\Livewire\Admin\ConsentManager;
use ArtisanPackUI\Privacy\Livewire\Admin\DataRequestManager;
use ArtisanPackUI\Privacy\Livewire\ConsentPreferences;
use ArtisanPackUI\Privacy\Livewire\CookieBanner;
use ArtisanPackUI\Privacy\Livewire\DataRequestForm;
use ArtisanPackUI\Privacy\Livewire\PolicyReconsentBanner;
use ArtisanPackUI\Privacy\Livewire\PrivacyDashboard;
use ArtisanPackUI\Privacy\Livewire\VerifyDataRequest;
use ArtisanPackUI\Privacy\Regulations\Ccpa;
use ArtisanPackUI\Privacy\Regulations\Gdpr;
use ArtisanPackUI\Privacy\Regulations\RegulationRegistry;
use ArtisanPackUI\Privacy\Services\AnonymizationService;
use ArtisanPackUI\Privacy\Services\BreachNotificationService;
use ArtisanPackUI\Privacy\Services\ComplianceReportService;
use ArtisanPackUI\Privacy\Services\ConsentService;
use ArtisanPackUI\Privacy\Services\DataDeletionService;
use ArtisanPackUI\Privacy\Services\DataExportService;
use ArtisanPackUI\Privacy\Services\DataRequestService;
use ArtisanPackUI\Privacy\Services\GeoLocationService;
use ArtisanPackUI\Privacy\Services\PersonalDataScanner;
use ArtisanPackUI\Privacy\Services\PrivacyPolicyGenerator;
use ArtisanPackUI\Privacy\Services\ReconsentService;
use ArtisanPackUI\Privacy\Services\VerificationService;
use ArtisanPackUI\Privacy\View\PrivacyDirectives;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
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
		DataRectificationRequested::class => [
			[ NotifyAdminOfRequest::class, 'handleRectification' ],
		],
		DataBreach::class => [
			[ NotifyDataBreach::class, 'handle' ],
		],
		PolicyReconsentRequired::class => [
			[ LogPolicyReconsent::class, 'handleRequired' ],
		],
		PolicyReconsentGiven::class => [
			[ LogPolicyReconsent::class, 'handleGiven' ],
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

		$this->app->singleton( BreachNotificationService::class, fn () => new BreachNotificationService() );
		$this->app->alias( BreachNotificationService::class, 'privacy.breach' );

		$this->app->singleton( ComplianceReportService::class, fn () => new ComplianceReportService() );
		$this->app->alias( ComplianceReportService::class, 'privacy.compliance' );

		$this->app->singleton( DataExportService::class, fn () => new DataExportService() );
		$this->app->alias( DataExportService::class, 'privacy.export' );

		$this->app->singleton(
			DataDeletionService::class,
			fn ( $app ) => new DataDeletionService( $app->make( AnonymizationService::class ) ),
		);
		$this->app->alias( DataDeletionService::class, 'privacy.deletion' );

		$this->app->singleton( VerificationService::class, fn () => new VerificationService() );
		$this->app->alias( VerificationService::class, 'privacy.verification' );

		$this->app->singleton( RegulationRegistry::class, function (): RegulationRegistry {
			$registry = new RegulationRegistry();
			$registry->registerClass( 'gdpr', Gdpr::class );
			$registry->registerClass( 'ccpa', Ccpa::class );

			return $registry;
		} );
		$this->app->alias( RegulationRegistry::class, 'privacy.regulations' );

		$this->app->singleton( PersonalDataScanner::class, fn () => new PersonalDataScanner() );
		$this->app->alias( PersonalDataScanner::class, 'privacy.scanner' );

		$this->app->singleton( GeoLocationService::class, fn () => new GeoLocationService() );
		$this->app->alias( GeoLocationService::class, 'privacy.geolocation' );

		$this->app->singleton( PrivacyPolicyGenerator::class, fn () => new PrivacyPolicyGenerator() );
		$this->app->alias( PrivacyPolicyGenerator::class, 'privacy.policy_generator' );

		$this->app->singleton(
			ReconsentService::class,
			fn ( $app ) => new ReconsentService( $app->make( ConsentService::class ) ),
		);
		$this->app->alias( ReconsentService::class, 'privacy.reconsent' );
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
		$this->registerWebRoutes();
		$this->registerAdminWebRoutes();
		$this->registerBreachTemplatePublishing();
		$this->registerBladeDirectives();
		$this->registerMiddlewareAliases();
		$this->registerRateLimiters();
		$this->registerViewComposers();
		$this->registerConsoleCommands();
		$this->registerScheduledTasks();
	}

	/**
	 * Registers the package's admin web routes (Livewire panel mount
	 * points) under the configured `admin.route_prefix` with the
	 * `admin.middleware` stack. Skips registration when the admin
	 * dashboard is disabled via `artisanpack.privacy.admin.enabled`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerAdminWebRoutes(): void
	{
		if ( true !== (bool) config( 'artisanpack.privacy.admin.enabled', true ) ) {
			return;
		}

		Route::group( [
			'prefix'     => (string) config( 'artisanpack.privacy.admin.route_prefix', 'admin/privacy' ),
			'middleware' => (array) config( 'artisanpack.privacy.admin.middleware', [ 'web', 'auth' ] ),
		], function (): void {
			$this->loadRoutesFrom( __DIR__ . '/../routes/admin.php' );
		} );
	}

	/**
	 * Registers the publishable breach-notification template stubs so
	 * applications can override the authority and user notification
	 * markdown via `php artisan vendor:publish --tag=privacy-breach-templates`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerBreachTemplatePublishing(): void
	{
		$templates = __DIR__ . '/../resources/views/breach-templates';

		if ( is_dir( $templates ) ) {
			$this->publishes( [
				$templates => resource_path( 'views/vendor/artisanpack-ui/privacy/breach-templates' ),
			], 'privacy-breach-templates' );
		}

		$adminLayout = __DIR__ . '/../resources/views/admin/layout.blade.php';

		if ( is_file( $adminLayout ) ) {
			$this->publishes( [
				$adminLayout => resource_path( 'views/vendor/artisanpack-ui/privacy/admin/layout.blade.php' ),
			], 'privacy-admin-layout' );
		}

		$policyTemplates = __DIR__ . '/../resources/templates/policies';

		if ( is_dir( $policyTemplates ) ) {
			$this->publishes( [
				$policyTemplates => resource_path( 'views/vendor/artisanpack-ui/privacy/templates/policies' ),
			], 'privacy-policy-templates' );
		}

		$cssFile = __DIR__ . '/../resources/css/privacy.css';

		if ( is_file( $cssFile ) ) {
			$this->publishes( [
				$cssFile => resource_path( 'css/vendor/artisanpack-ui/privacy.css' ),
			], 'privacy-css' );
		}
	}

	/**
	 * Registers the package's web routes (verification + export downloads).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerWebRoutes(): void
	{
		if ( true !== (bool) config( 'artisanpack.privacy.routes.enabled', true ) ) {
			return;
		}

		Route::group( [
			'prefix'     => (string) config( 'artisanpack.privacy.routes.prefix', 'privacy' ),
			'middleware' => (array) config( 'artisanpack.privacy.routes.middleware', [ 'web' ] ),
		], function (): void {
			$this->loadRoutesFrom( __DIR__ . '/../routes/web.php' );
		} );
	}

	/**
	 * Registers the named rate limiters used by the package's HTTP routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerRateLimiters(): void
	{
		$rule               = (string) config( 'artisanpack.privacy.verification.rate_limit', '6,1' );
		[ $hits, $minutes ] = array_pad( explode( ',', $rule ), 2, '1' );

		RateLimiter::for( 'privacy-verification', static function ( $request ) use ( $hits, $minutes ) {
			return Limit::perMinutes(
				(int) $minutes,
				(int) $hits,
			)->by( $request->ip() ?: 'anon' );
		} );

		$apiRule                  = (string) config( 'artisanpack.privacy.data_requests.api_rate_limit', '60,1' );
		[ $apiHits, $apiMinutes ] = array_pad( explode( ',', $apiRule ), 2, '1' );

		RateLimiter::for( 'privacy-data-requests-api', static function ( $request ) use ( $apiHits, $apiMinutes ) {
			$key = $request->user()?->getAuthIdentifier();

			return Limit::perMinutes(
				(int) $apiMinutes,
				(int) $apiHits,
			)->by( null !== $key ? "user:{$key}" : ( $request->ip() ?: 'anon' ) );
		} );

		$adminRule                    = (string) config( 'artisanpack.privacy.admin.api_rate_limit', '120,1' );
		[ $adminHits, $adminMinutes ] = array_pad( explode( ',', $adminRule ), 2, '1' );

		RateLimiter::for( 'privacy-admin-api', static function ( $request ) use ( $adminHits, $adminMinutes ) {
			$key = $request->user()?->getAuthIdentifier();

			return Limit::perMinutes(
				(int) $adminMinutes,
				(int) $adminHits,
			)->by( null !== $key ? "user:{$key}" : ( $request->ip() ?: 'anon' ) );
		} );
	}

	/**
	 * Registers a global view composer that exposes the current request's
	 * `privacy_consent` attribute (populated by {@see CheckCookieConsent}) to
	 * every view as `$privacyConsent`.
	 *
	 * Composers are evaluated per-render, so this is Octane-safe — there is
	 * no shared View::share state to leak between requests. Views requested
	 * outside an HTTP request (CLI, queue worker) receive an empty map.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerViewComposers(): void
	{
		View::composer( '*', static function ( $view ): void {
			$request = request();

			$map = $request instanceof \Illuminate\Http\Request
				? (array) $request->attributes->get( 'privacy_consent', [] )
				: [];

			$view->with( 'privacyConsent', $map );
		} );
	}

	/**
	 * Registers the package's Artisan commands when running in the console.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerConsoleCommands(): void
	{
		if ( ! $this->app->runningInConsole() ) {
			return;
		}

		$this->commands( [
			PurgeExpiredConsents::class,
			PrivacyScan::class,
			PrivacyInstall::class,
		] );
	}

	/**
	 * Registers the package's default schedule entries (cleanup, etc.) so
	 * applications that use Laravel's scheduler get them for free.
	 *
	 * Disable via `artisanpack.privacy.scheduling.purge_expired.enabled` or
	 * override the cron expression via `.cron`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerScheduledTasks(): void
	{
		if ( ! $this->app->runningInConsole() ) {
			return;
		}

		$purgeConfig = (array) config( 'artisanpack.privacy.scheduling.purge_expired', [] );

		if ( true !== ( $purgeConfig['enabled'] ?? true ) ) {
			return;
		}

		$this->app->booted( function ( Application $app ) use ( $purgeConfig ): void {
			if ( ! $app->bound( Schedule::class ) ) {
				return;
			}

			$schedule = $app->make( Schedule::class );
			$cron     = (string) ( $purgeConfig['cron'] ?? '0 3 * * *' );
			$prune    = (bool) ( $purgeConfig['prune'] ?? false );

			$event = $schedule->command(
				'privacy:purge-expired' . ( $prune ? ' --prune' : '' ),
			)->cron( $cron );

			if ( ! empty( $purgeConfig['timezone'] ) ) {
				$event->timezone( (string) $purgeConfig['timezone'] );
			}
		} );
	}

	/**
	 * Registers the package's Blade directives for consent checking inside
	 * views: `@hasConsent('category')` / `@endhasConsent`, and
	 * `@consentRequired('category')` / `@else` / `@endconsentRequired`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerBladeDirectives(): void
	{
		$class = PrivacyDirectives::class;

		Blade::directive( 'hasConsent', static function ( string $expression ) use ( $class ): string {
			return "<?php if ( \\{$class}::hasConsent( {$expression} ) ): ?>";
		} );
		Blade::directive( 'endhasConsent', static fn (): string => '<?php endif; ?>' );

		Blade::directive( 'consentRequired', static function ( string $expression ) use ( $class ): string {
			return "<?php if ( ! \\{$class}::hasConsent( {$expression} ) ): ?>";
		} );
		Blade::directive( 'endconsentRequired', static fn (): string => '<?php endif; ?>' );
	}

	/**
	 * Registers the package's HTTP middleware aliases so applications can
	 * reference them by short name in route definitions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerMiddlewareAliases(): void
	{
		$router = $this->app['router'] ?? null;

		if ( null === $router ) {
			return;
		}

		$router->aliasMiddleware( 'privacy.consent', Http\Middleware\EnsureConsentGiven::class );
		$router->aliasMiddleware( 'privacy.context', Http\Middleware\CheckCookieConsent::class );
		$router->aliasMiddleware( 'privacy.geolocate', Http\Middleware\GeolocateUser::class );
		$router->aliasMiddleware( 'privacy.reconsent', Http\Middleware\EnsureUpToDateConsent::class );
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
		\Livewire\Livewire::component( 'privacy-data-request-form', DataRequestForm::class );
		\Livewire\Livewire::component( 'privacy-verify-data-request', VerifyDataRequest::class );
		\Livewire\Livewire::component( 'privacy-dashboard', PrivacyDashboard::class );
		\Livewire\Livewire::component( 'privacy-policy-reconsent-banner', PolicyReconsentBanner::class );
		\Livewire\Livewire::component( 'privacy-admin-consent-manager', ConsentManager::class );
		\Livewire\Livewire::component( 'privacy-admin-data-request-manager', DataRequestManager::class );
		\Livewire\Livewire::component( 'privacy-admin-compliance-report', ComplianceReport::class );
		\Livewire\Livewire::component( 'privacy-admin-breach-manager', BreachManager::class );
		\Livewire\Livewire::component( 'privacy-admin-breach-detail', BreachDetail::class );
		\Livewire\Livewire::component( 'privacy-admin-breach-report-form', BreachReportForm::class );
	}
}
