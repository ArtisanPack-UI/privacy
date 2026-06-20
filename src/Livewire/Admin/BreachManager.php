<?php

/**
 * BreachManager Livewire component — admin breach list panel.
 *
 * Paginated, filterable list of {@see BreachNotification} rows with
 * severity badges, deadline-aware "overdue" markers, and links into the
 * detail panel and the report form.
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

use ArtisanPackUI\Privacy\Models\BreachNotification;
use ArtisanPackUI\Privacy\Services\BreachNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin breach list panel.
 *
 * Usage:
 *
 *   <livewire:privacy-admin-breach-manager />
 *
 * @since 1.0.0
 */
class BreachManager extends Component
{
	use WithPagination;

	/**
	 * Severity filter (`''` matches all).
	 *
	 * @var string
	 */
	#[Url( as: 'severity', except: '' )]
	public string $severityFilter = '';

	/**
	 * Status filter (`''` matches all).
	 *
	 * @var string
	 */
	#[Url( as: 'status', except: '' )]
	public string $statusFilter = '';

	/**
	 * Per-page limit.
	 *
	 * @var int
	 */
	public int $perPage = 25;

	/**
	 * Authorize the gate before mount.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function mount(): void
	{
		$gate = (string) config( 'artisanpack.privacy.admin.gate', 'manage-privacy' );

		if ( '' === $gate ) {
			$gate = 'manage-privacy';
		}

		Gate::authorize( $gate );
	}

	/**
	 * Reset pagination when filters change.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function updatedSeverityFilter(): void
	{
		$this->resetPage();
	}

	/**
	 * Reset pagination when filters change.
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
	 * Paginated breaches for the current filter state.
	 *
	 * @since 1.0.0
	 *
	 * @return LengthAwarePaginator
	 */
	#[Computed]
	public function breaches(): LengthAwarePaginator
	{
		return $this->query()->orderByDesc( 'discovered_at' )->paginate( $this->perPage );
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
		return view( 'privacy::livewire.admin.breach-manager', [
			'service' => app( BreachNotificationService::class ),
		] );
	}

	/**
	 * Build the base query for the current filter state.
	 *
	 * @since 1.0.0
	 *
	 * @return Builder<BreachNotification>
	 */
	protected function query(): Builder
	{
		$query = BreachNotification::query();

		if ( '' !== $this->severityFilter ) {
			$query->where( 'severity', $this->severityFilter );
		}

		if ( '' !== $this->statusFilter ) {
			$query->where( 'status', $this->statusFilter );
		}

		return $query;
	}
}
