<?php

/**
 * PurgeExpiredConsents — Artisan command that clears expired consent rows.
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

use ArtisanPackUI\Privacy\Events\ConsentWithdrawn;
use ArtisanPackUI\Privacy\Models\Consent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;

/**
 * Marks expired consents as withdrawn (the soft path) or deletes them
 * outright (with `--prune`). The soft path preserves audit history and
 * dispatches {@see ConsentWithdrawn} per row so audit listeners stay in
 * sync.
 *
 * @since 1.0.0
 */
class PurgeExpiredConsents extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'privacy:purge-expired
		{--prune : Delete expired rows instead of marking them withdrawn.}
		{--dry-run : Report what would change without writing.}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Purge consent rows whose expiry has passed.';

	/**
	 * Execute the console command.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function handle(): int
	{
		$dryRun = (bool) $this->option( 'dry-run' );
		$prune  = (bool) $this->option( 'prune' );

		$expired = Consent::query()->expired();

		if ( $dryRun ) {
			$activeCount = $expired->clone()->whereNull( 'withdrawn_at' )->count();

			$this->line( sprintf( '[dry-run] %d expired consent(s) would be withdrawn.', $activeCount ) );

			if ( $prune ) {
				$total = $expired->count();
				$this->line( sprintf( '[dry-run] %d expired consent row(s) would be deleted.', $total ) );
			}

			return self::SUCCESS;
		}

		// When pruning we skip the soft path entirely — the rows are about
		// to vanish so an extra UPDATE pass is wasted work, and the
		// listeners that care about expiry-driven withdrawals fire below.
		if ( $prune ) {
			$deleted = 0;

			$expired->clone()->whereNull( 'withdrawn_at' )
				->each( function ( Consent $consent ) use ( &$deleted ): void {
					$consent->withdrawn_at = now();
					$consent->granted      = false;

					Event::dispatch( new ConsentWithdrawn( $consent ) );

					$consent->delete();
					$deleted++;
				} );

			$alreadyWithdrawn = $expired->clone()->whereNotNull( 'withdrawn_at' )->delete();
			$deleted += $alreadyWithdrawn;

			$this->info( sprintf( 'Deleted %d expired consent row(s).', $deleted ) );

			return self::SUCCESS;
		}

		$withdrawn = 0;

		$expired->clone()->whereNull( 'withdrawn_at' )
			->each( function ( Consent $consent ) use ( &$withdrawn ): void {
				$consent->update( [
					'granted'      => false,
					'withdrawn_at' => now(),
				] );

				Event::dispatch( new ConsentWithdrawn( $consent ) );
				$withdrawn++;
			} );

		$this->info( sprintf( 'Withdrew %d expired consent(s).', $withdrawn ) );

		return self::SUCCESS;
	}
}
