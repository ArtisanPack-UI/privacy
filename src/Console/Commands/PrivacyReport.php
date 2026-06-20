<?php

/**
 * PrivacyReport — `privacy:report` Artisan command.
 *
 * Generates a compliance report (consent stats, request metrics, breach
 * stats, deadline compliance) for a given period and writes it to disk
 * and/or emails it to the configured DPO.
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

use ArtisanPackUI\Privacy\Services\ComplianceReportService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Generates compliance reports on demand or on a schedule.
 *
 * Examples:
 *
 *     php artisan privacy:report --period=month --format=json
 *     php artisan privacy:report --start=2026-01-01 --end=2026-01-31 --output=storage/reports/jan.json
 *     php artisan privacy:report --period=month --email=dpo@example.com
 *
 * @since 1.0.0
 */
class PrivacyReport extends Command
{

	/**
	 * Allowed `--period` values.
	 *
	 * @var array<int, string>
	 */
	protected const PERIODS = [ 'day', 'week', 'month', 'quarter', 'year', 'custom' ];

	/**
	 * Allowed `--format` values.
	 *
	 * @var array<int, string>
	 */
	protected const FORMATS = [ 'json', 'csv' ];

	/**
	 * The console command signature.
	 *
	 * @var string
	 */
	protected $signature = 'privacy:report
		{--period=month : Reporting window — day|week|month|quarter|year|custom.}
		{--start= : Custom window start date (Y-m-d). Required for --period=custom.}
		{--end= : Custom window end date (Y-m-d). Required for --period=custom.}
		{--regulation= : Optional regulation filter (gdpr, ccpa, …).}
		{--format=json : Output format — json|csv.}
		{--output= : File path to write the report to. Defaults to stdout.}
		{--email= : Email address to send the report to.}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Generate a privacy compliance report for a given period.';

	/**
	 * Executes the command.
	 *
	 * @since 1.0.0
	 *
	 * @param  ComplianceReportService  $service Report-data builder.
	 * @param  Mailer                   $mailer  Mailer used when `--email` is set.
	 *
	 * @return int
	 */
	public function handle( ComplianceReportService $service, Mailer $mailer ): int
	{
		$period = (string) $this->option( 'period' );
		$format = (string) $this->option( 'format' );

		if ( ! in_array( $period, self::PERIODS, true ) ) {
			$this->error( sprintf( 'Invalid --period "%s". Allowed: %s.', $period, implode( ', ', self::PERIODS ) ) );

			return self::INVALID;
		}

		if ( ! in_array( $format, self::FORMATS, true ) ) {
			$this->error( sprintf( 'Invalid --format "%s". Allowed: %s.', $format, implode( ', ', self::FORMATS ) ) );

			return self::INVALID;
		}

		$range = $this->resolveRange( $period );

		if ( null === $range ) {
			return self::INVALID;
		}

		[ $from, $to ] = $range;

		$regulation = $this->option( 'regulation' );
		$regulation = null === $regulation || '' === $regulation ? null : (string) $regulation;

		$report = $service->generate( $from, $to, $regulation );

		$payload = 'csv' === $format
			? $this->toCsv( $report )
			: (string) json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		$output = $this->option( 'output' );

		if ( null !== $output && '' !== $output ) {
			$path = (string) $output;
			$dir  = dirname( $path );

			if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
				$this->error( sprintf( 'Could not create output directory: %s', $dir ) );

				return self::FAILURE;
			}

			file_put_contents( $path, $payload );
			$this->info( sprintf( 'Report written to %s', $path ) );
		} else {
			$this->line( $payload );
		}

		$email = $this->option( 'email' );

		if ( null !== $email && '' !== $email ) {
			try {
				$this->emailReport( $mailer, (string) $email, $payload, $format, $from, $to );
				$this->info( sprintf( 'Report emailed to %s', $email ) );
			} catch ( Throwable $e ) {
				$this->error( sprintf( 'Failed to email report: %s', $e->getMessage() ) );

				return self::FAILURE;
			}
		}

		return self::SUCCESS;
	}

	/**
	 * Resolves the requested period (or custom start/end) to a concrete
	 * [from, to] Carbon range.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $period Period identifier — one of {@see PERIODS}.
	 *
	 * @return array{0: Carbon, 1: Carbon}|null Null on invalid custom range.
	 */
	protected function resolveRange( string $period ): ?array
	{
		if ( 'custom' === $period ) {
			$start = $this->option( 'start' );
			$end   = $this->option( 'end' );

			if ( null === $start || '' === $start || null === $end || '' === $end ) {
				$this->error( '--period=custom requires both --start and --end (Y-m-d).' );

				return null;
			}

			try {
				$from = Carbon::parse( (string) $start )->startOfDay();
				$to   = Carbon::parse( (string) $end )->endOfDay();
			} catch ( Throwable $e ) {
				$this->error( sprintf( 'Invalid custom date range: %s', $e->getMessage() ) );

				return null;
			}

			if ( $from->greaterThan( $to ) ) {
				$this->error( '--start must be on or before --end.' );

				return null;
			}

			return [ $from, $to ];
		}

		$to   = Carbon::now()->endOfDay();
		$from = match ( $period ) {
			'day'     => Carbon::now()->startOfDay(),
			'week'    => Carbon::now()->subWeek()->startOfDay(),
			'month'   => Carbon::now()->subMonth()->startOfDay(),
			'quarter' => Carbon::now()->subQuarter()->startOfDay(),
			'year'    => Carbon::now()->subYear()->startOfDay(),
			default   => null,
		};

		if ( null === $from ) {
			$this->error( sprintf( 'Unsupported period "%s".', $period ) );

			return null;
		}

		return [ $from, $to ];
	}

	/**
	 * Flattens the report payload to a two-column key/value CSV. Nested
	 * arrays are dot-flattened so spreadsheets can ingest them directly.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $report Report payload.
	 *
	 * @return string CSV content.
	 */
	protected function toCsv( array $report ): string
	{
		$rows = [ [ 'metric', 'value' ] ];

		foreach ( $this->flatten( $report ) as $key => $value ) {
			$rows[] = [ $key, is_scalar( $value ) ? (string) $value : (string) json_encode( $value ) ];
		}

		$handle = fopen( 'php://temp', 'r+' );

		foreach ( $rows as $row ) {
			fputcsv( $handle, $row );
		}

		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle );

		return $csv;
	}

	/**
	 * Recursively flattens a nested array into dot-keyed scalars.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data   Source data.
	 * @param  string                $prefix Current dot prefix.
	 *
	 * @return array<string, mixed>
	 */
	protected function flatten( array $data, string $prefix = '' ): array
	{
		$out = [];

		foreach ( $data as $key => $value ) {
			$path = '' === $prefix ? (string) $key : $prefix . '.' . $key;

			if ( is_array( $value ) && ! empty( $value ) && ! array_is_list( $value ) ) {
				$out = array_merge( $out, $this->flatten( $value, $path ) );

				continue;
			}

			$out[ $path ] = $value;
		}

		return $out;
	}

	/**
	 * Sends the rendered report as an email attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param  Mailer  $mailer  Mailer instance.
	 * @param  string  $to      Recipient address.
	 * @param  string  $payload Rendered report body.
	 * @param  string  $format  Report format (json|csv).
	 * @param  Carbon  $from    Window start (for subject).
	 * @param  Carbon  $to_     Window end (for subject).
	 *
	 * @return void
	 */
	protected function emailReport( Mailer $mailer, string $to, string $payload, string $format, Carbon $from, Carbon $to_ ): void
	{
		$subject  = sprintf( 'Privacy Compliance Report — %s to %s', $from->toDateString(), $to_->toDateString() );
		$filename = sprintf( 'privacy-report-%s-to-%s.%s', $from->toDateString(), $to_->toDateString(), $format );
		$mime     = 'csv' === $format ? 'text/csv' : 'application/json';
		$body     = sprintf( "Privacy compliance report for %s to %s attached.\n", $from->toDateString(), $to_->toDateString() );

		$mailer->raw( $body, function ( Message $message ) use ( $to, $subject, $payload, $filename, $mime ): void {
			$message->to( $to )
				->subject( $subject )
				->attachData( $payload, $filename, [ 'mime' => $mime ] );
		} );
	}
}
