<?php

/**
 * PrivacyInstall — `privacy:install` Artisan command.
 *
 * Publishes the privacy package's config, migrations, and breach
 * notification templates, and prints the `manage-privacy` gate stub
 * for the developer to register in their `AuthServiceProvider`.
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

use Illuminate\Console\Command;

/**
 * Installs the privacy package — publishes assets and prints the gate stub.
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
		{--skip-migrations : Skip publishing the database migrations.}
		{--skip-templates : Skip publishing the breach notification templates.}
		{--skip-admin-layout : Skip publishing the admin layout view.}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Publish the privacy package assets and print the admin gate stub.';

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
		$this->newLine();
		$this->comment( __( 'Override the gate name, route prefix, and middleware via the artisanpack.privacy.admin config block.' ) );

		return self::SUCCESS;
	}
}
