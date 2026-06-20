<?php

/**
 * ProcessDataRequests — `privacy:process-requests` Artisan command.
 *
 * Processes pending, verified, auto-processable data subject requests
 * (access and export) on a schedule. Deletion requests are intentionally
 * skipped — they require manual review by a privacy admin.
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

use ArtisanPackUI\Privacy\Jobs\ProcessDataExportJob;
use ArtisanPackUI\Privacy\Models\DataRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes pending data subject requests that are configured for
 * auto-processing. Designed to be invoked from the Laravel scheduler:
 *
 *     Schedule::command('privacy:process-requests')->daily();
 *
 * @since 1.0.0
 */
class ProcessDataRequests extends Command
{

	/**
	 * Types this command will ever consider. Deletion is excluded by
	 * design — it requires manual admin review.
	 *
	 * @var array<int, string>
	 */
	protected const PROCESSABLE_TYPES = [
		DataRequest::TYPE_ACCESS,
		DataRequest::TYPE_EXPORT,
	];

	/**
	 * The console command signature.
	 *
	 * @var string
	 */
	protected $signature = 'privacy:process-requests
		{--type= : Limit processing to a single request type (access|export).}
		{--dry-run : Report what would be processed without writing.}
		{--limit=100 : Maximum number of requests to process in one run.}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Process pending auto-processable data subject requests (access + export).';

	/**
	 * Executes the command.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function handle(): int
	{
		$dryRun = (bool) $this->option( 'dry-run' );
		$type   = $this->resolveTypeFilter();

		if ( false === $type ) {
			return self::INVALID;
		}

		$requested = null !== $type ? [ $type ] : self::PROCESSABLE_TYPES;
		$types     = array_values( array_filter( $requested, fn ( string $t ): bool => $this->isAutoProcessable( $t ) ) );

		if ( empty( $types ) ) {
			$message = null !== $type
				? __( 'Type :type is not configured for auto-processing (config: artisanpack.privacy.data_requests.auto_process.:type).', [ 'type' => $type ] )
				: __( 'No request types are configured for auto-processing.' );

			$this->info( $message );

			return self::SUCCESS;
		}

		$limit = max( 1, (int) $this->option( 'limit' ) );

		$query = DataRequest::query()
			->pending()
			->whereIn( 'type', $types )
			->orderBy( 'id' )
			->limit( $limit );

		if ( true === (bool) config( 'artisanpack.privacy.data_requests.require_verification', true ) ) {
			$query->whereNotNull( 'verified_at' );
		}

		$requests = $query->get();

		if ( $requests->isEmpty() ) {
			$this->info( __( 'No pending requests to process.' ) );

			return self::SUCCESS;
		}

		$processed = 0;
		$failed    = 0;

		foreach ( $requests as $request ) {
			if ( $dryRun ) {
				$this->line( sprintf(
					'[dry-run] #%d (%s) for %s#%d',
					$request->id,
					$request->type,
					$request->requestable_type,
					$request->requestable_id,
				) );
				$processed++;

				continue;
			}

			try {
				$this->dispatchRequest( $request );
				$processed++;
			} catch ( Throwable $e ) {
				$failed++;

				Log::error( 'privacy:process-requests failed', [
					'request_id' => $request->id,
					'type'       => $request->type,
					'error'      => $e->getMessage(),
				] );

				$this->warn( sprintf( 'Request #%d failed: %s', $request->id, $e->getMessage() ) );
			}
		}

		$this->info( sprintf(
			'%s %d / %d requests%s',
			$dryRun ? __( '[dry-run] Would process' ) : __( 'Processed' ),
			$processed,
			$requests->count(),
			$failed > 0 ? sprintf( ' (%d failed)', $failed ) : '',
		) );

		return $failed > 0 ? self::FAILURE : self::SUCCESS;
	}

	/**
	 * Dispatches the heavy export-to-file work to the queue so very large
	 * exports do not block the scheduler tick. Falls back to a synchronous
	 * dispatch when the application is configured for the `sync` queue
	 * driver, preserving the pre-queue behaviour for installs that haven't
	 * wired up a worker yet.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataRequest  $request The request to dispatch.
	 *
	 * @return void
	 */
	protected function dispatchRequest( DataRequest $request ): void
	{
		if ( 'sync' === (string) config( 'queue.default' ) ) {
			ProcessDataExportJob::dispatchSync( $request->id );

			return;
		}

		ProcessDataExportJob::dispatch( $request->id );
	}

	/**
	 * Returns true when the request type is enabled for auto-processing
	 * via `artisanpack.privacy.data_requests.auto_process.<type>`.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $type Request type to check.
	 *
	 * @return bool
	 */
	protected function isAutoProcessable( string $type ): bool
	{
		return true === (bool) config( "artisanpack.privacy.data_requests.auto_process.{$type}", false );
	}

	/**
	 * Resolves the `--type` option to a valid processable type, or false
	 * when invalid.
	 *
	 * @since 1.0.0
	 *
	 * @return false|string|null A type string, null for no filter, or false on invalid input.
	 */
	protected function resolveTypeFilter(): string|null|false
	{
		$type = $this->option( 'type' );

		if ( null === $type || '' === $type ) {
			return null;
		}

		$type = (string) $type;

		if ( ! in_array( $type, self::PROCESSABLE_TYPES, true ) ) {
			$this->error( sprintf(
				'Invalid --type value "%s". Allowed: %s.',
				$type,
				implode( ', ', self::PROCESSABLE_TYPES ),
			) );

			return false;
		}

		return $type;
	}
}
