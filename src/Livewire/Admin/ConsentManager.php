<?php

/**
 * ConsentManager Livewire component — admin view of consent records.
 *
 * Surfaces a paginated, filterable table of {@see Consent} rows for
 * administrators with `manage-privacy` ability. Supports search, category
 * / status / date-range filters, bulk selection, and CSV / JSON export of
 * the audit log.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Livewire\Admin;

use ArtisanPackUI\Privacy\Models\Consent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Admin-facing consent administration panel.
 *
 * Usage:
 *
 *   <livewire:privacy-admin-consent-manager />
 *
 * @since 1.0.0
 */
class ConsentManager extends Component
{
	use WithPagination;

	public const STATUS_ALL       = 'all';
	public const STATUS_ACTIVE    = 'active';
	public const STATUS_WITHDRAWN = 'withdrawn';
	public const STATUS_EXPIRED   = 'expired';

	/**
	 * Free-text search over identifier columns.
	 *
	 * @var string
	 */
	#[Url( as: 'q', except: '' )]
	public string $search = '';

	/**
	 * Category filter (`''` matches all).
	 *
	 * @var string
	 */
	#[Url( as: 'category', except: '' )]
	public string $categoryFilter = '';

	/**
	 * Status filter (`active`, `withdrawn`, `expired`, `all`).
	 *
	 * @var string
	 */
	#[Url( as: 'status', except: 'all' )]
	public string $statusFilter = self::STATUS_ALL;

	/**
	 * From-date filter (`Y-m-d`) — applied against `created_at`.
	 *
	 * @var string
	 */
	#[Url( as: 'from', except: '' )]
	public string $dateFrom = '';

	/**
	 * To-date filter (`Y-m-d`) — applied against `created_at`.
	 *
	 * @var string
	 */
	#[Url( as: 'to', except: '' )]
	public string $dateTo = '';

	/**
	 * Per-page limit.
	 *
	 * @var int
	 */
	public int $perPage = 25;

	/**
	 * Selected consent ids (for bulk actions).
	 *
	 * @var array<int, int>
	 */
	public array $selected = [];

	/**
	 * "Select all" toggle.
	 *
	 * @var bool
	 */
	public bool $selectAll = false;

	/**
	 * Authorize the gate before the component mounts.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function booted(): void
	{
		$this->authorizeAdmin();
	}

	/**
	 * Returns the paginated consent rows for the current filter state.
	 *
	 * @since 1.0.0
	 *
	 * @return LengthAwarePaginator
	 */
	#[Computed]
	public function consents(): LengthAwarePaginator
	{
		return $this->query()->orderByDesc( 'created_at' )->paginate( $this->perPage );
	}

	/**
	 * Returns the list of configured category slugs (for the filter dropdown).
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	#[Computed]
	public function categories(): array
	{
		$categories = array_keys( (array) config( 'artisanpack.privacy.cookie_categories', [] ) );

		return array_values( array_unique( array_map( static fn ( $key ): string => (string) $key, $categories ) ) );
	}

	/**
	 * Reset pagination when search changes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function updatedSearch(): void
	{
		$this->resetPage();
	}

	/**
	 * Reset pagination when category filter changes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function updatedCategoryFilter(): void
	{
		$this->resetPage();
	}

	/**
	 * Reset pagination when status filter changes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function updatedStatusFilter(): void
	{
		$this->resetPage();
	}

	/**
	 * Reset pagination on date-range changes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function updatedDateFrom(): void
	{
		$this->resetPage();
	}

	/**
	 * Reset pagination on date-range changes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function updatedDateTo(): void
	{
		$this->resetPage();
	}

	/**
	 * Toggle every row currently visible (in the active page only) when
	 * `selectAll` flips. Livewire writes the new value back via wire:model,
	 * and this hook reconciles the selected[] state with it.
	 *
	 * @since 1.0.0
	 *
	 * @param  bool  $value New "select all" value.
	 *
	 * @return void
	 */
	public function updatedSelectAll( bool $value ): void
	{
		$this->selected = $value
			? $this->consents->pluck( 'id' )->all()
			: [];
	}

	/**
	 * Clear every active filter so the panel returns to the default view.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function clearFilters(): void
	{
		$this->search         = '';
		$this->categoryFilter = '';
		$this->statusFilter   = self::STATUS_ALL;
		$this->dateFrom       = '';
		$this->dateTo         = '';
		$this->selected       = [];
		$this->selectAll      = false;

		$this->resetPage();
	}

	/**
	 * Stream the filtered consent audit log as a CSV download.
	 *
	 * @since 1.0.0
	 *
	 * @return StreamedResponse
	 */
	public function exportCsv(): StreamedResponse
	{
		$filename = 'privacy-consents-' . now()->format( 'Ymd-His' ) . '.csv';
		$query    = $this->query()->orderByDesc( 'created_at' );

		return response()->streamDownload( static function () use ( $query ): void {
			$out = fopen( 'php://output', 'wb' );

			fputcsv( $out, [
				'id',
				'consentable_type',
				'consentable_id',
				'category',
				'granted',
				'regulation',
				'ip_address',
				'created_at',
				'expires_at',
				'withdrawn_at',
			] );

			$query->chunk( 500, static function ( $rows ) use ( $out ): void {
				foreach ( $rows as $row ) {
					fputcsv( $out, [
						$row->id,
						$row->consentable_type,
						$row->consentable_id,
						$row->category,
						$row->granted ? '1' : '0',
						$row->regulation,
						$row->ip_address,
						optional( $row->created_at )->toIso8601String(),
						optional( $row->expires_at )->toIso8601String(),
						optional( $row->withdrawn_at )->toIso8601String(),
					] );
				}
			} );

			fclose( $out );
		}, $filename, [ 'Content-Type' => 'text/csv' ] );
	}

	/**
	 * Stream the filtered consent audit log as a JSON download. Chunked at
	 * 500 rows like the CSV export so memory stays flat on large datasets;
	 * each row is encoded individually so a single malformed value cannot
	 * silently truncate the entire response.
	 *
	 * @since 1.0.0
	 *
	 * @return StreamedResponse
	 */
	public function exportJson(): StreamedResponse
	{
		$filename = 'privacy-consents-' . now()->format( 'Ymd-His' ) . '.json';
		$query    = $this->query()->orderByDesc( 'created_at' );

		return response()->streamDownload( static function () use ( $query ): void {
			$out = fopen( 'php://output', 'wb' );
			fwrite( $out, '[' );
			$first = true;

			$query->chunk( 500, static function ( $rows ) use ( $out, &$first ): void {
				foreach ( $rows as $row ) {
					$payload = [
						'id'                => $row->id,
						'consentable_type'  => $row->consentable_type,
						'consentable_id'    => $row->consentable_id,
						'category'          => $row->category,
						'granted'           => $row->granted,
						'regulation'        => $row->regulation,
						'ip_address'        => $row->ip_address,
						'metadata'          => $row->metadata,
						'created_at'        => optional( $row->created_at )->toIso8601String(),
						'expires_at'        => optional( $row->expires_at )->toIso8601String(),
						'withdrawn_at'      => optional( $row->withdrawn_at )->toIso8601String(),
					];

					$encoded = json_encode( $payload, JSON_UNESCAPED_SLASHES );

					if ( false === $encoded ) {
						throw new RuntimeException(
							'Failed to encode consent row #' . $row->id . ': ' . json_last_error_msg(),
						);
					}

					fwrite( $out, ( $first ? '' : ',' ) . $encoded );
					$first = false;
				}
			} );

			fwrite( $out, ']' );
			fclose( $out );
		}, $filename, [ 'Content-Type' => 'application/json' ] );
	}

	/**
	 * Render the component view.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'privacy::livewire.admin.consent-manager' );
	}

	/**
	 * Resolves the configured admin gate name (defaulting to `manage-privacy`)
	 * and runs `Gate::authorize` against it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function authorizeAdmin(): void
	{
		$gate = (string) config( 'artisanpack.privacy.admin.gate', 'manage-privacy' );

		if ( '' === $gate ) {
			$gate = 'manage-privacy';
		}

		Gate::authorize( $gate );
	}

	/**
	 * Build the base query for the current filter state.
	 *
	 * @since 1.0.0
	 *
	 * @return Builder<Consent>
	 */
	protected function query(): Builder
	{
		$query = Consent::query();

		if ( '' !== $this->categoryFilter ) {
			$query->forCategory( $this->categoryFilter );
		}

		switch ( $this->statusFilter ) {
			case self::STATUS_ACTIVE:
				$query->active();
				break;

			case self::STATUS_WITHDRAWN:
				$query->whereNotNull( 'withdrawn_at' );
				break;

			case self::STATUS_EXPIRED:
				$query->expired();
				break;

			case self::STATUS_ALL:
			default:
				// no-op
				break;
		}

		if ( '' !== $this->dateFrom ) {
			$from = $this->safeParse( $this->dateFrom );

			if ( null !== $from ) {
				$query->where( 'created_at', '>=', $from->startOfDay() );
			}
		}

		if ( '' !== $this->dateTo ) {
			$to = $this->safeParse( $this->dateTo );

			if ( null !== $to ) {
				$query->where( 'created_at', '<=', $to->endOfDay() );
			}
		}

		$term = trim( $this->search );

		// Skip the LIKE clause for short terms — a 1–2 character prefix
		// matches too broadly to be useful and a leading wildcard search
		// torpedoes any index. Three is short enough to match status codes
		// (e.g. `gdpr`) without scanning the table.
		if ( '' !== $term && strlen( $term ) >= 3 ) {
			$query->where( function ( Builder $inner ) use ( $term ): void {
				$inner->where( 'consentable_type', 'like', "{$term}%" )
					->orWhere( 'consentable_id', 'like', "{$term}%" )
					->orWhere( 'category', 'like', "{$term}%" )
					->orWhere( 'regulation', 'like', "{$term}%" )
					->orWhere( 'ip_address', 'like', "{$term}%" );
			} );
		}

		return $query;
	}

	/**
	 * Parses a `Y-m-d` date string without throwing.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $value Raw date string.
	 *
	 * @return Carbon|null
	 */
	protected function safeParse( string $value ): ?Carbon
	{
		try {
			return Carbon::parse( $value );
		} catch ( Throwable $e ) {
			return null;
		}
	}
}
