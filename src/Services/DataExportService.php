<?php

/**
 * DataExportService — produces portable copies of a data subject's personal data.
 *
 * Collects column-level personal data from models tagged with the
 * {@see HasPersonalData} trait, the subject's consent history, and its
 * data-request activity log, then serializes the result to JSON, CSV, or
 * XML for delivery to the subject.
 *
 * Other packages can append sections to the payload by listening for the
 * {@see DataExportCollecting} event, or — when the `artisanpack-ui/hooks`
 * package is installed — by registering an `addFilter('ap.privacy.exportData', …)`
 * filter that receives `($data, $subject)` and returns the mutated array.
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
use ArtisanPackUI\Privacy\Events\DataExportCollecting;
use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Data subject export service.
 *
 * @since 1.0.0
 */
class DataExportService
{
	public const FORMAT_JSON = 'json';
	public const FORMAT_CSV  = 'csv';
	public const FORMAT_XML  = 'xml';

	/**
	 * Returns the serialized export string for the supplied subject.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model   $subject Data subject the export belongs to.
	 * @param  string  $format  One of FORMAT_JSON, FORMAT_CSV, FORMAT_XML.
	 *
	 * @throws InvalidArgumentException When the format is unsupported.
	 *
	 * @return string
	 */
	public function export( Model $subject, string $format = self::FORMAT_JSON ): string
	{
		$data = $this->collect( $subject );

		return $this->serialize( $data, $format );
	}

	/**
	 * Writes the export to the configured disk and returns a signed
	 * download URL valid for `artisanpack.privacy.export.url_ttl` minutes.
	 *
	 * The stored file expires automatically — its `expires_at` is recorded
	 * in a sidecar `<file>.expires` marker so the cleanup command can prune
	 * stale exports without depending on a queue job.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model   $subject Data subject the export belongs to.
	 * @param  string  $format  Export format.
	 *
	 * @return array{path: string, url: string, expires_at: string}
	 */
	public function exportToFile( Model $subject, string $format = self::FORMAT_JSON ): array
	{
		$payload   = $this->export( $subject, $format );
		$disk      = $this->disk();
		$ttl       = (int) config( 'artisanpack.privacy.export.url_ttl', 60 );
		$retention = (int) config( 'artisanpack.privacy.export.file_retention_minutes', 1440 );

		$filename = sprintf(
			'%s/%s-%s.%s',
			$this->directory(),
			Str::slug( class_basename( $subject ) . '-' . $subject->getKey() ),
			Str::lower( Str::random( 8 ) ),
			$format,
		);

		Storage::disk( $disk )->put( $filename, $payload );

		$expiresAt = Carbon::now()->addMinutes( $retention );
		Storage::disk( $disk )->put( $filename . '.expires', $expiresAt->toIso8601String() );

		return [
			'path'       => $filename,
			'url'        => URL::temporarySignedRoute(
				'privacy.exports.download',
				Carbon::now()->addMinutes( $ttl ),
				[ 'path' => $filename ],
			),
			'expires_at' => $expiresAt->toIso8601String(),
		];
	}

	/**
	 * Collects the personal-data payload for a subject — column data from
	 * `HasPersonalData` models, consent history, activity log, plus any
	 * extensions contributed via event listeners or hooks.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $subject Data subject the export belongs to.
	 *
	 * @return array<string, mixed>
	 */
	public function getPersonalData( Model $subject ): array
	{
		return $this->collect( $subject );
	}

	/**
	 * Returns the subject's consent history (grants and withdrawals) as a
	 * plain array.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $subject Data subject.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getConsentHistory( Model $subject ): array
	{
		return Consent::query()
			->where( 'consentable_type', $subject->getMorphClass() )
			->where( 'consentable_id', $subject->getKey() )
			->orderBy( 'created_at' )
			->limit( $this->rowCap() )
			->get()
			->map( static fn ( Consent $c ): array => [
				'category'     => $c->category,
				'granted'      => (bool) $c->granted,
				'regulation'   => $c->regulation ?? null,
				'recorded_at'  => $c->created_at?->toIso8601String(),
				'withdrawn_at' => $c->withdrawn_at?->toIso8601String(),
				'expires_at'   => $c->expires_at?->toIso8601String(),
			] )
			->all();
	}

	/**
	 * Returns the subject's data-request activity log entries.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $subject Data subject.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getActivityLog( Model $subject ): array
	{
		$requestIds = DataRequest::query()
			->where( 'requestable_type', $subject->getMorphClass() )
			->where( 'requestable_id', $subject->getKey() )
			->pluck( 'id' );

		if ( $requestIds->isEmpty() ) {
			return [];
		}

		return DataRequestLog::query()
			->whereIn( 'data_request_id', $requestIds )
			->orderBy( 'created_at' )
			->limit( $this->rowCap() )
			->get()
			->map( static fn ( DataRequestLog $log ): array => [
				'data_request_id' => $log->data_request_id,
				'action'          => $log->action,
				'description'     => $log->description,
				'performed_by'    => $log->performed_by,
				'created_at'      => $log->created_at?->toIso8601String(),
			] )
			->all();
	}

	/**
	 * Returns the model-by-model personal data for any related models tagged
	 * with {@see HasPersonalData} (via its `personalDataRelations()`).
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $subject Data subject.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function getRelatedData( Model $subject ): array
	{
		if ( ! $this->usesPersonalData( $subject ) ) {
			return [];
		}

		return $subject->toPersonalDataRelationArray();
	}

	/**
	 * Assembles the canonical payload before format-specific serialization.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $subject Data subject.
	 *
	 * @return array<string, mixed>
	 */
	protected function collect( Model $subject ): array
	{
		$data = [
			'subject'         => [
				'type' => $subject->getMorphClass(),
				'id'   => $subject->getKey(),
			],
			'personal_data'   => $this->usesPersonalData( $subject ) ? $subject->toPersonalDataArray() : [],
			'related'         => $this->getRelatedData( $subject ),
			'consent_history' => $this->getConsentHistory( $subject ),
			'data_requests'   => $this->getDataRequestSummary( $subject ),
			'activity_log'    => $this->getActivityLog( $subject ),
			'generated_at'    => Carbon::now()->toIso8601String(),
		];

		$event = new DataExportCollecting( $subject, $data );
		Event::dispatch( $event );
		$data = $event->data;

		if ( function_exists( 'applyFilters' ) ) {
			$data = (array) applyFilters( 'ap.privacy.exportData', $data, $subject );
		}

		return $data;
	}

	/**
	 * Returns true when the model uses the {@see HasPersonalData} trait.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $subject Subject to inspect.
	 *
	 * @return bool
	 */
	protected function usesPersonalData( Model $subject ): bool
	{
		return in_array( HasPersonalData::class, class_uses_recursive( $subject ), true );
	}

	/**
	 * Returns a flat list of the subject's data-requests for inclusion in
	 * the export.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $subject Data subject.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function getDataRequestSummary( Model $subject ): array
	{
		return DataRequest::query()
			->where( 'requestable_type', $subject->getMorphClass() )
			->where( 'requestable_id', $subject->getKey() )
			->orderBy( 'created_at' )
			->limit( $this->rowCap() )
			->get()
			->map( static fn ( DataRequest $r ): array => [
				'id'           => $r->id,
				'type'         => $r->type,
				'status'       => $r->status,
				'regulation'   => $r->regulation,
				'created_at'   => $r->created_at?->toIso8601String(),
				'completed_at' => $r->completed_at?->toIso8601String(),
			] )
			->all();
	}

	/**
	 * Serializes the payload to the requested format.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data   Export payload.
	 * @param  string                $format Format key.
	 *
	 * @throws InvalidArgumentException
	 *
	 * @return string
	 */
	protected function serialize( array $data, string $format ): string
	{
		return match ( $format ) {
			self::FORMAT_JSON => (string) json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			self::FORMAT_CSV  => $this->toCsv( $data ),
			self::FORMAT_XML  => $this->toXml( $data ),
			default           => throw new InvalidArgumentException( "Unsupported export format: {$format}" ),
		};
	}

	/**
	 * Flattens the payload into a CSV with `section,key,value` rows so that
	 * nested arrays survive a row-based format.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data Export payload.
	 *
	 * @return string
	 */
	protected function toCsv( array $data ): string
	{
		$handle = fopen( 'php://temp', 'r+' );

		fputcsv( $handle, [ 'section', 'key', 'value' ], ',', '"', '\\' );

		foreach ( $this->flatten( $data ) as [ $section, $key, $value ] ) {
			fputcsv( $handle, [ $section, $key, $value ], ',', '"', '\\' );
		}

		rewind( $handle );
		$out = (string) stream_get_contents( $handle );
		fclose( $handle );

		return $out;
	}

	/**
	 * Renders the payload as XML.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data Export payload.
	 *
	 * @return string
	 */
	protected function toXml( array $data ): string
	{
		$root = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><export/>' );
		$this->arrayToXml( $data, $root );

		return (string) $root->asXML();
	}

	/**
	 * Recursive XML node builder.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int|string, mixed>  $data   Branch payload.
	 * @param  SimpleXMLElement          $parent Parent node.
	 *
	 * @return void
	 */
	protected function arrayToXml( array $data, SimpleXMLElement $parent ): void
	{
		foreach ( $data as $key => $value ) {
			$tag = is_int( $key ) ? 'item' : preg_replace( '/[^A-Za-z0-9_\-]/', '_', (string) $key );
			$tag = '' === $tag ? 'item' : $tag;

			if ( is_array( $value ) ) {
				$child = $parent->addChild( $tag );
				$this->arrayToXml( $value, $child );
				continue;
			}

			$parent->addChild( $tag, htmlspecialchars( (string) ( $value ?? '' ), ENT_XML1 ) );
		}
	}

	/**
	 * Flattens a nested payload to `[section, key, value]` rows.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int|string, mixed>  $data   Payload to flatten.
	 * @param  string                    $prefix Section prefix carried from parent.
	 *
	 * @return array<int, array{0: string, 1: string, 2: string}>
	 */
	protected function flatten( array $data, string $prefix = '' ): array
	{
		$rows = [];

		foreach ( $data as $key => $value ) {
			$section = '' === $prefix ? (string) $key : $prefix;
			$label   = '' === $prefix ? '' : (string) $key;

			if ( is_array( $value ) ) {
				$childPrefix = '' === $prefix ? (string) $key : "{$prefix}.{$key}";
				$rows        = array_merge( $rows, $this->flatten( $value, $childPrefix ) );
				continue;
			}

			$rows[] = [ $section, $label, (string) ( $value ?? '' ) ];
		}

		return $rows;
	}

	/**
	 * Configured filesystem disk for export storage.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function disk(): string
	{
		return (string) config( 'artisanpack.privacy.export.disk', 'local' );
	}

	/**
	 * Configured directory inside the disk for export files.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function directory(): string
	{
		return trim( (string) config( 'artisanpack.privacy.export.directory', 'privacy-exports' ), '/' );
	}

	/**
	 * Maximum number of rows per collected section. A safety net against an
	 * accidental OOM when a high-volume subject has hundreds of thousands of
	 * consent/log entries. Override via `artisanpack.privacy.export.row_cap`.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function rowCap(): int
	{
		return (int) config( 'artisanpack.privacy.export.row_cap', 10000 );
	}
}
