<?php

/**
 * PersonalDataScanner — discovers personal-data fields across Eloquent models.
 *
 * Walks the application's models, matches column names against configured
 * patterns, and produces a per-model inventory the package can persist for
 * audits and downstream services.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Services;

use ArtisanPackUI\Privacy\Concerns\HasPersonalData;
use ArtisanPackUI\Privacy\Models\PersonalDataMap;
use FilesystemIterator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Throwable;

/**
 * Personal data discovery service.
 *
 * @since 1.0.0
 */
class PersonalDataScanner
{
	public const SOURCE_TRAIT   = 'trait';
	public const SOURCE_PATTERN = 'pattern';

	/**
	 * High-sensitivity field types. Any match against one of these types is
	 * promoted to {@see PersonalDataMap::SENSITIVITY_SENSITIVE}.
	 *
	 * @var array<int, string>
	 */
	protected array $sensitiveTypes = [ 'ssn', 'tax_id', 'health', 'financial' ];

	/**
	 * Scans every model the configured paths expose and returns the merged
	 * field map keyed by fully-qualified model class.
	 *
	 * @since 1.0.0
	 *
	 * @return array<class-string<Model>, array<string, array<string, mixed>>>
	 */
	public function scan(): array
	{
		$results = [];

		foreach ( $this->discoverModelClasses() as $class ) {
			$fields = $this->scanModel( $class );

			if ( [] !== $fields ) {
				$results[ $class ] = $fields;
			}
		}

		return $results;
	}

	/**
	 * Scans a single model and returns its field map.
	 *
	 * @since 1.0.0
	 *
	 * @param  class-string<Model>  $modelClass Fully-qualified model class.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function scanModel( string $modelClass ): array
	{
		if ( ! $this->isScannableModel( $modelClass ) ) {
			return [];
		}

		try {
			/** @var Model $model */
			$model = new $modelClass();
		} catch ( Throwable ) {
			return [];
		}

		$traitFields   = $this->collectTraitDeclaredFields( $model );
		$patternFields = $this->collectPatternMatchedFields( $model, array_keys( $traitFields ) );

		return $traitFields + $patternFields;
	}

	/**
	 * Persists a single field mapping. Updates existing rows in place so the
	 * scanner can be re-run without producing duplicates.
	 *
	 * @since 1.0.0
	 *
	 * @param  string                $model  Fully-qualified model class.
	 * @param  string                $field  Column name.
	 * @param  array<string, mixed>  $config Field configuration (type, sensitivity, …).
	 *
	 * @return PersonalDataMap
	 */
	public function registerField( string $model, string $field, array $config = [] ): PersonalDataMap
	{
		$payload = [
			'data_type'         => (string) ( $config['data_type'] ?? $config['type'] ?? 'unknown' ),
			'sensitivity'       => (string) ( $config['sensitivity'] ?? PersonalDataMap::SENSITIVITY_NORMAL ),
			'retention_period'  => $config['retention_period'] ?? null,
			'deletion_strategy' => (string) ( $config['deletion_strategy'] ?? PersonalDataMap::STRATEGY_ANONYMIZE ),
			'export_format'     => $config['export_format'] ?? null,
			'auto_discovered'   => (bool) ( $config['auto_discovered'] ?? false ),
		];

		return PersonalDataMap::query()->updateOrCreate(
			[ 'model' => $model, 'field' => $field ],
			$payload,
		);
	}

	/**
	 * Returns the persisted field mappings for a single model, keyed by
	 * column name.
	 *
	 * @since 1.0.0
	 *
	 * @param  class-string<Model>  $modelClass Fully-qualified model class.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getFieldMappings( string $modelClass ): array
	{
		return PersonalDataMap::query()
			->forModel( $modelClass )
			->get()
			->keyBy( 'field' )
			->map( static fn ( PersonalDataMap $map ): array => [
				'data_type'         => $map->data_type,
				'sensitivity'       => $map->sensitivity,
				'retention_period'  => $map->retention_period,
				'deletion_strategy' => $map->deletion_strategy,
				'export_format'     => $map->export_format,
				'auto_discovered'   => (bool) $map->auto_discovered,
			] )
			->all();
	}

	/**
	 * Returns a flattened data inventory suitable for serialization (DPO
	 * reports, audit handoffs, …).
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function generateDataInventory(): array
	{
		$discovered = $this->scan();
		$inventory  = [];

		foreach ( $discovered as $modelClass => $fields ) {
			foreach ( $fields as $field => $config ) {
				$inventory[] = array_merge(
					[
						'model' => $modelClass,
						'field' => $field,
					],
					$config,
				);
			}
		}

		foreach ( PersonalDataMap::query()->get() as $row ) {
			$key = $row->model . '::' . $row->field;

			foreach ( $inventory as $entry ) {
				if ( $entry['model'] . '::' . $entry['field'] === $key ) {
					continue 2;
				}
			}

			$inventory[] = [
				'model'             => $row->model,
				'field'             => $row->field,
				'data_type'         => $row->data_type,
				'sensitivity'       => $row->sensitivity,
				'retention_period'  => $row->retention_period,
				'deletion_strategy' => $row->deletion_strategy,
				'export_format'     => $row->export_format,
				'auto_discovered'   => (bool) $row->auto_discovered,
				'source'            => 'persisted',
			];
		}

		return $inventory;
	}

	/**
	 * Merges the supplied scan results into the persisted map table. Returns
	 * the number of rows written.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<class-string<Model>, array<string, array<string, mixed>>>  $scanResults Map produced by {@see scan()}.
	 *
	 * @return int
	 */
	public function persist( array $scanResults ): int
	{
		$written = 0;

		foreach ( $scanResults as $modelClass => $fields ) {
			foreach ( $fields as $field => $config ) {
				$this->registerField( $modelClass, $field, $config );
				$written++;
			}
		}

		return $written;
	}

	/**
	 * Returns the configured field-pattern map keyed by data type.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<int, string>>
	 */
	protected function fieldPatterns(): array
	{
		return (array) config( 'artisanpack.privacy.discovery.field_patterns', [] );
	}

	/**
	 * Returns the list of configured scan paths.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function scanPaths(): array
	{
		return (array) config( 'artisanpack.privacy.discovery.scan_paths', [] );
	}

	/**
	 * Returns the list of excluded model class names.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, class-string>
	 */
	protected function excludedModels(): array
	{
		return (array) config( 'artisanpack.privacy.discovery.exclude_models', [] );
	}

	/**
	 * Discovers candidate model classes by reflecting over the configured
	 * scan paths.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, class-string<Model>>
	 */
	protected function discoverModelClasses(): array
	{
		$found = [];

		foreach ( $this->scanPaths() as $path ) {
			if ( ! is_dir( (string) $path ) ) {
				continue;
			}

			foreach ( $this->iterateDirectory( (string) $path ) as $file ) {
				$class = $this->classFromFile( $file );

				if ( null === $class ) {
					continue;
				}

				if ( ! $this->isScannableModel( $class ) ) {
					continue;
				}

				$found[ $class ] = $class;
			}
		}

		return array_values( $found );
	}

	/**
	 * Yields the `*.php` files inside the supplied directory recursively.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $path Directory to walk.
	 *
	 * @return iterable<int, string>
	 */
	protected function iterateDirectory( string $path ): iterable
	{
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		);

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof SplFileInfo || 'php' !== strtolower( $file->getExtension() ) ) {
				continue;
			}

			yield $file->getPathname();
		}
	}

	/**
	 * Attempts to resolve a fully-qualified class name from a PHP file by
	 * parsing its namespace declaration.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $file Absolute path to a PHP file.
	 *
	 * @return class-string|null
	 */
	protected function classFromFile( string $file ): ?string
	{
		$contents = @file_get_contents( $file );

		if ( false === $contents ) {
			return null;
		}

		if ( ! preg_match( '/^namespace\s+([^;]+);/m', $contents, $matches ) ) {
			return null;
		}

		$namespace = trim( $matches[1] );
		$class     = pathinfo( $file, PATHINFO_FILENAME );

		return $namespace . '\\' . $class;
	}

	/**
	 * Returns true when the supplied class is a concrete Eloquent model that
	 * the scanner should inspect.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $class Fully-qualified class name.
	 *
	 * @return bool
	 */
	protected function isScannableModel( string $class ): bool
	{
		if ( in_array( $class, $this->excludedModels(), true ) ) {
			return false;
		}

		if ( ! class_exists( $class ) ) {
			return false;
		}

		try {
			$reflection = new ReflectionClass( $class );
		} catch ( Throwable ) {
			return false;
		}

		if ( $reflection->isAbstract() ) {
			return false;
		}

		return $reflection->isSubclassOf( Model::class );
	}

	/**
	 * Collects the fields a model declares through the
	 * {@see HasPersonalData} trait.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $model Model instance to inspect.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function collectTraitDeclaredFields( Model $model ): array
	{
		if ( ! in_array( HasPersonalData::class, class_uses_recursive( $model ), true ) ) {
			return [];
		}

		$declared = [];

		foreach ( $model->personalDataFields() as $key => $value ) {
			$field    = is_string( $key ) ? $key : (string) $value;
			$metadata = is_array( $value ) ? $value : [];

			$type        = (string) ( $metadata['type'] ?? $this->guessTypeFromName( $field ) ?? 'unknown' );
			$sensitivity = (string) ( $metadata['sensitivity'] ?? $this->sensitivityFor( $type ) );
			$strategy    = (string) ( $metadata['deletion_strategy'] ?? PersonalDataMap::STRATEGY_ANONYMIZE );

			$declared[ $field ] = [
				'data_type'         => $type,
				'sensitivity'       => $sensitivity,
				'deletion_strategy' => $strategy,
				'retention_period'  => $metadata['retention_period'] ?? null,
				'export_format'     => $metadata['export_format'] ?? null,
				'auto_discovered'   => false,
				'source'            => self::SOURCE_TRAIT,
			];
		}

		return $declared;
	}

	/**
	 * Matches the model's columns against the configured patterns to
	 * discover personal-data fields that were not declared explicitly.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model              $model    Model instance to inspect.
	 * @param  array<int, string> $declared Field names already covered by the trait scan.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function collectPatternMatchedFields( Model $model, array $declared ): array
	{
		$columns = $this->modelColumns( $model );
		$matches = [];

		foreach ( $columns as $column ) {
			if ( in_array( $column, $declared, true ) ) {
				continue;
			}

			$type = $this->guessTypeFromName( $column );

			if ( null === $type ) {
				continue;
			}

			$matches[ $column ] = [
				'data_type'         => $type,
				'sensitivity'       => $this->sensitivityFor( $type ),
				'deletion_strategy' => PersonalDataMap::STRATEGY_ANONYMIZE,
				'retention_period'  => null,
				'export_format'     => null,
				'auto_discovered'   => true,
				'source'            => self::SOURCE_PATTERN,
			];
		}

		return $matches;
	}

	/**
	 * Returns the column list for a model — uses the schema builder when a
	 * database connection is available, otherwise falls back to the model's
	 * `fillable` attribute.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $model Model instance to inspect.
	 *
	 * @return array<int, string>
	 */
	protected function modelColumns( Model $model ): array
	{
		try {
			return Schema::connection( $model->getConnectionName() )
				->getColumnListing( $model->getTable() );
		} catch ( Throwable ) {
			return $model->getFillable();
		}
	}

	/**
	 * Best-effort type guess from a column name using the configured pattern
	 * map.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $field Column name.
	 *
	 * @return string|null
	 */
	protected function guessTypeFromName( string $field ): ?string
	{
		$lower = Str::lower( $field );

		foreach ( $this->fieldPatterns() as $type => $patterns ) {
			foreach ( (array) $patterns as $pattern ) {
				if ( $lower === Str::lower( (string) $pattern ) ) {
					return (string) $type;
				}
			}
		}

		foreach ( $this->fieldPatterns() as $type => $patterns ) {
			foreach ( (array) $patterns as $pattern ) {
				$needle = Str::lower( (string) $pattern );

				if ( '' !== $needle && Str::contains( $lower, $needle ) ) {
					return (string) $type;
				}
			}
		}

		return null;
	}

	/**
	 * Returns the canonical sensitivity classification for a data type.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $type Data type.
	 *
	 * @return string
	 */
	protected function sensitivityFor( string $type ): string
	{
		return in_array( $type, $this->sensitiveTypes, true )
			? PersonalDataMap::SENSITIVITY_SENSITIVE
			: PersonalDataMap::SENSITIVITY_NORMAL;
	}
}
