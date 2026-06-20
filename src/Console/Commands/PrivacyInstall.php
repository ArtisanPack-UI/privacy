<?php

/**
 * PrivacyInstall — `privacy:install` Artisan command.
 *
 * Publishes the privacy package's assets, optionally runs migrations,
 * seeds default consent categories, clears caches, and prints the
 * `manage-privacy` gate stub plus next steps for the developer.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Console\Commands;

use ArtisanPackUI\Privacy\Models\ConsentCategory;
use Illuminate\Console\Command;
use Throwable;

/**
 * Installs the privacy package — publishes assets, runs migrations,
 * seeds default consent categories, and prints next-step guidance.
 *
 * @since 1.0.0
 */
class PrivacyInstall extends Command
{
	/**
	 * The console command signature.
	 *
	 * @var string
	 */
	protected $signature = 'privacy:install
		{--force : Overwrite any existing published files.}
		{--skip-migrations : Skip publishing and running the database migrations.}
		{--skip-templates : Skip publishing the breach notification templates.}
		{--skip-admin-layout : Skip publishing the admin layout view.}
		{--skip-views : Skip publishing the package views.}
		{--skip-seed : Skip seeding the default consent categories.}
		{--skip-migrate : Skip running migrations after publishing.}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Install the privacy package: publish assets, run migrations, seed default categories, and print the admin gate stub.';

	/**
	 * Executes the command.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function handle(): int
	{
		$force = (bool) $this->option( 'force' );

		$this->info( __( 'Publishing privacy configuration…' ) );
		$this->call( 'vendor:publish', array_filter( [
			'--tag'   => 'privacy-config',
			'--force' => $force ?: null,
		] ) );

		if ( ! $this->option( 'skip-migrations' ) ) {
			$this->info( __( 'Publishing privacy migrations…' ) );
			$this->call( 'vendor:publish', array_filter( [
				'--tag'   => 'privacy-migrations',
				'--force' => $force ?: null,
			] ) );
		}

		if ( ! $this->option( 'skip-views' ) ) {
			$this->info( __( 'Publishing privacy views…' ) );
			$this->call( 'vendor:publish', array_filter( [
				'--tag'   => 'privacy-views',
				'--force' => $force ?: null,
			] ) );
		}

		if ( ! $this->option( 'skip-templates' ) ) {
			$this->info( __( 'Publishing breach notification templates…' ) );
			$this->call( 'vendor:publish', array_filter( [
				'--tag'   => 'privacy-breach-templates',
				'--force' => $force ?: null,
			] ) );
		}

		if ( ! $this->option( 'skip-admin-layout' ) ) {
			$this->info( __( 'Publishing admin layout view…' ) );
			$this->call( 'vendor:publish', array_filter( [
				'--tag'   => 'privacy-admin-layout',
				'--force' => $force ?: null,
			] ) );
		}

		if ( ! $this->option( 'skip-migrations' ) && ! $this->option( 'skip-migrate' ) ) {
			$this->runMigrations();
		}

		if ( ! $this->option( 'skip-seed' ) ) {
			$this->seedDefaultCategories();
		}

		$this->clearCaches();

		$this->printGateStub();
		$this->printNextSteps();

		return self::SUCCESS;
	}

	/**
	 * Runs database migrations, prompting for confirmation in interactive
	 * mode. Non-interactive runs (CI/CD via `--no-interaction`) skip the
	 * prompt and proceed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function runMigrations(): void
	{
		$shouldRun = ! $this->input->isInteractive()
			|| $this->confirm( __( 'Run database migrations now?' ), true );

		if ( ! $shouldRun ) {
			$this->comment( __( 'Skipped migrations. Run `php artisan migrate` when ready.' ) );

			return;
		}

		$this->info( __( 'Running migrations…' ) );
		$this->call( 'migrate', [ '--force' => true ] );
	}

	/**
	 * Seeds the default consent categories derived from
	 * `artisanpack.privacy.cookie_categories`. Existing rows (matched by
	 * key) are left untouched so re-runs are idempotent.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function seedDefaultCategories(): void
	{
		if ( ! class_exists( ConsentCategory::class ) ) {
			return;
		}

		try {
			if ( ConsentCategory::query()->count() > 0 ) {
				$this->comment( __( 'Consent categories already present; skipping seed.' ) );

				return;
			}
		} catch ( Throwable ) {
			$this->warn( __( 'Consent categories table not available; skipping seed.' ) );

			return;
		}

		$categories = (array) config( 'artisanpack.privacy.cookie_categories', [] );

		if ( empty( $categories ) ) {
			return;
		}

		$this->info( __( 'Seeding default consent categories…' ) );

		$sort = 0;

		try {
			foreach ( $categories as $key => $category ) {
				ConsentCategory::query()->updateOrCreate(
					[ 'key' => (string) $key ],
					[
						'name'        => (string) ( $category['name'] ?? ucfirst( (string) $key ) ),
						'description' => (string) ( $category['description'] ?? '' ),
						'required'    => (bool) ( $category['required'] ?? false ),
						'sort_order'  => $sort++,
						'active'      => true,
					],
				);
			}
		} catch ( Throwable $e ) {
			$this->warn( __( 'Could not seed consent categories: :error. Run `php artisan migrate` and try `privacy:install` again.', [ 'error' => $e->getMessage() ] ) );

			return;
		}

		$this->info( sprintf( '%s', __( 'Seeded :count consent category(ies).', [ 'count' => count( $categories ) ] ) ) );
	}

	/**
	 * Clears the application caches that the package's configuration and
	 * view registrations populate, so changes take effect immediately.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function clearCaches(): void
	{
		$this->info( __( 'Clearing caches…' ) );
		$this->call( 'config:clear' );
		$this->call( 'view:clear' );
	}

	/**
	 * Prints the admin gate stub for the developer to copy into their
	 * AuthServiceProvider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function printGateStub(): void
	{
		$gate = (string) config( 'artisanpack.privacy.admin.gate', 'manage-privacy' );

		if ( '' === $gate ) {
			$gate = 'manage-privacy';
		}

		$this->newLine();
		$this->info( __( 'Privacy package installed.' ) );
		$this->newLine();
		$this->comment( __( 'Add the following gate definition to your AuthServiceProvider::boot() method to authorize admin access:' ) );
		$this->newLine();
		$this->line( '    use Illuminate\\Support\\Facades\\Gate;' );
		$this->newLine();
		$this->line( "    Gate::define('{$gate}', function (\$user) {" );
		$this->line( '        // Customize this to match your authorization model.' );
		$this->line( "        return method_exists(\$user, 'hasRole') && \$user->hasRole('admin');" );
		$this->line( '    });' );
	}

	/**
	 * Prints the developer-facing next-steps guidance.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function printNextSteps(): void
	{
		$this->newLine();
		$this->comment( __( 'Next steps:' ) );
		$this->line( '  1. ' . __( 'Add the `HasPersonalData` trait to your User model.' ) );
		$this->line( '  2. ' . __( 'Review and adjust regulations under `config/artisanpack/privacy.php`.' ) );
		$this->line( '  3. ' . __( 'Add the `<livewire:privacy-cookie-banner />` component (or `<CookieBanner />` for React/Vue) to your main layout.' ) );
		$this->line( '  4. ' . __( 'Define the admin gate shown above in `AuthServiceProvider`.' ) );
		$this->line( '  5. ' . __( 'For React/Vue, import components from `@artisanpack-ui/privacy` and rebuild your bundle.' ) );
		$this->newLine();
		$this->comment( __( 'Override the gate name, route prefix, and middleware via the artisanpack.privacy.admin config block.' ) );
	}
}
