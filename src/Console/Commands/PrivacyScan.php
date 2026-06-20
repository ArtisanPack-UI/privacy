<?php

/**
 * PrivacyScan — `privacy:scan` Artisan command.
 *
 * Runs the {@see PersonalDataScanner} against the configured model paths and
 * optionally persists the result. Output is human-readable by default but
 * can be switched to `json` or `csv` for integration with downstream tools.
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

use ArtisanPackUI\Privacy\Models\PersonalDataMap;
use ArtisanPackUI\Privacy\Services\PersonalDataScanner;
use Illuminate\Console\Command;

/**
 * Scans the configured models for personal-data fields.
 *
 * @since 1.0.0
 */
class PrivacyScan extends Command
{
	/**
	 * The console command signature.
	 *
	 * @var string
	 */
	protected $signature = 'privacy:scan
		{--model= : Limit the scan to the given fully-qualified model class.}
		{--save : Persist the discovered mappings to the privacy_personal_data_maps table.}
		{--format=table : Output format. Supported: table, json, csv.}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Discover personal-data fields across Eloquent models.';

	/**
	 * Executes the command.
	 *
	 * @since 1.0.0
	 *
	 * @param  PersonalDataScanner  $scanner Scanner service.
	 *
	 * @return int
	 */
	public function handle( PersonalDataScanner $scanner ): int
	{
		$format = strtolower( (string) $this->option( 'format' ) );

		if ( ! in_array( $format, [ 'table', 'json', 'csv' ], true ) ) {
			$this->error( "Unsupported format: {$format}. Use table, json, or csv." );

			return self::INVALID;
		}

		$results = $this->collect( $scanner );

		if ( [] === $results ) {
			$this->warn( 'No personal-data fields were discovered.' );

			return self::SUCCESS;
		}

		if ( $this->option( 'save' ) ) {
			$written = $scanner->persist( $results );
			$this->info( sprintf( 'Persisted %d field mapping(s).', $written ) );
		}

		match ( $format ) {
			'json'  => $this->renderJson( $results ),
			'csv'   => $this->renderCsv( $results ),
			default => $this->renderTable( $results, $scanner ),
		};

		return self::SUCCESS;
	}

	/**
	 * Runs the scanner against either the supplied model or every configured
	 * model path.
	 *
	 * @since 1.0.0
	 *
	 * @param  PersonalDataScanner  $scanner Scanner service.
	 *
	 * @return array<class-string, array<string, array<string, mixed>>>
	 */
	protected function collect( PersonalDataScanner $scanner ): array
	{
		$model = $this->option( 'model' );

		if ( null !== $model ) {
			$fields = $scanner->scanModel( (string) $model );

			return [] === $fields ? [] : [ (string) $model => $fields ];
		}

		return $scanner->scan();
	}

	/**
	 * Renders the discovered fields as a console table grouped by model.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<class-string, array<string, array<string, mixed>>>  $results Scanner results.
	 * @param  PersonalDataScanner                                       $scanner Scanner service.
	 *
	 * @return void
	 */
	protected function renderTable( array $results, PersonalDataScanner $scanner ): void
	{
		$totalFields = 0;

		foreach ( $results as $modelClass => $fields ) {
			$this->line( '' );
			$this->line( '<info>' . $modelClass . '</info>' );

			$existing = $scanner->getFieldMappings( $modelClass );
			$rows     = [];

			foreach ( $fields as $field => $config ) {
				$totalFields++;
				$rows[] = [
					$field,
					$config['data_type'] ?? 'unknown',
					$this->formatSensitivity( (string) ( $config['sensitivity'] ?? PersonalDataMap::SENSITIVITY_NORMAL ) ),
					$config['deletion_strategy'] ?? PersonalDataMap::STRATEGY_ANONYMIZE,
					$this->formatSource( $config, $existing[ $field ] ?? null ),
				];
			}

			$this->table(
				[ 'Field', 'Type', 'Sensitivity', 'Strategy', 'Source' ],
				$rows,
			);
		}

		$this->line( '' );
		$this->info( sprintf(
			'Found %d personal-data field(s) across %d model(s).',
			$totalFields,
			count( $results ),
		) );
	}

	/**
	 * Renders the discovered fields as JSON to stdout.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<class-string, array<string, array<string, mixed>>>  $results Scanner results.
	 *
	 * @return void
	 */
	protected function renderJson( array $results ): void
	{
		$this->line(
			(string) json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		);
	}

	/**
	 * Renders the discovered fields as CSV to stdout.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<class-string, array<string, array<string, mixed>>>  $results Scanner results.
	 *
	 * @return void
	 */
	protected function renderCsv( array $results ): void
	{
		$handle = fopen( 'php://temp', 'r+' );

		fputcsv(
			$handle,
			[ 'model', 'field', 'data_type', 'sensitivity', 'deletion_strategy', 'auto_discovered' ],
			',',
			'"',
			'\\',
		);

		foreach ( $results as $modelClass => $fields ) {
			foreach ( $fields as $field => $config ) {
				fputcsv( $handle, [
					$modelClass,
					$field,
					$config['data_type'] ?? 'unknown',
					$config['sensitivity'] ?? PersonalDataMap::SENSITIVITY_NORMAL,
					$config['deletion_strategy'] ?? PersonalDataMap::STRATEGY_ANONYMIZE,
					( $config['auto_discovered'] ?? false ) ? 'true' : 'false',
				], ',', '"', '\\' );
			}
		}

		rewind( $handle );
		$this->line( (string) stream_get_contents( $handle ) );
		fclose( $handle );
	}

	/**
	 * Returns a human-friendly sensitivity label with a marker on
	 * sensitive/special-category fields so they stand out in the table.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $sensitivity Sensitivity classification.
	 *
	 * @return string
	 */
	protected function formatSensitivity( string $sensitivity ): string
	{
		return match ( $sensitivity ) {
			PersonalDataMap::SENSITIVITY_SENSITIVE,
			PersonalDataMap::SENSITIVITY_SPECIAL_CATEGORY => strtoupper( $sensitivity ) . ' !',
			default                                       => $sensitivity,
		};
	}

	/**
	 * Returns the "Source" column for a row — combines auto-detection origin
	 * with a marker that flags drift between this scan and the persisted map.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>       $config    Discovered field config.
	 * @param  array<string, mixed>|null  $persisted Persisted mapping if any.
	 *
	 * @return string
	 */
	protected function formatSource( array $config, ?array $persisted ): string
	{
		$source = (string) ( $config['source'] ?? ( ( $config['auto_discovered'] ?? false ) ? PersonalDataScanner::SOURCE_PATTERN : PersonalDataScanner::SOURCE_TRAIT ) );

		if ( null === $persisted ) {
			return $source . ' (new)';
		}

		$drift = ( $persisted['data_type'] ?? null ) !== ( $config['data_type'] ?? null )
			|| ( $persisted['sensitivity'] ?? null ) !== ( $config['sensitivity'] ?? null )
			|| ( $persisted['deletion_strategy'] ?? null ) !== ( $config['deletion_strategy'] ?? null );

		return $drift ? $source . ' (changed)' : $source;
	}
}
