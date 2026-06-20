<?php

/**
 * DataRequestManager Livewire component — admin queue for data subject requests.
 *
 * Surfaces a filtered table of {@see DataRequest} rows with deadline
 * awareness, manual verification, and approval / rejection / completion
 * actions. Authorization is enforced through the configured
 * `manage-privacy` gate.
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

use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;
use ArtisanPackUI\Privacy\Services\VerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin panel for processing data subject requests.
 *
 * Usage:
 *
 *   <livewire:privacy-admin-data-request-manager />
 *
 * @since 1.0.0
 */
class DataRequestManager extends Component
{
	use WithPagination;

	public const SORT_NEWEST    = 'newest';
	public const SORT_DEADLINE  = 'deadline';
	public const SORT_OVERDUE   = 'overdue';

	/**
	 * Type filter (`''` matches all).
	 *
	 * @var string
	 */
	#[Url( as: 'type', except: '' )]
	public string $typeFilter = '';

	/**
	 * Status filter (`''` matches all).
	 *
	 * @var string
	 */
	#[Url( as: 'status', except: '' )]
	public string $statusFilter = '';

	/**
	 * Sort key.
	 *
	 * @var string
	 */
	#[Url( as: 'sort', except: 'deadline' )]
	public string $sort = self::SORT_DEADLINE;

	/**
	 * Per-page limit.
	 *
	 * @var int
	 */
	public int $perPage = 25;

	/**
	 * Currently active request being viewed in the details panel.
	 *
	 * @var int|null
	 */
	public ?int $activeRequestId = null;

	/**
	 * Free-text note appended on the next action.
	 *
	 * @var string
	 */
	public string $note = '';

	/**
	 * Authorize the gate before the component mounts.
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
	 * Returns the paginated request rows for the current filter state.
	 *
	 * @since 1.0.0
	 *
	 * @return LengthAwarePaginator
	 */
	#[Computed]
	public function requests(): LengthAwarePaginator
	{
		return $this->applySort( $this->query() )->paginate( $this->perPage );
	}

	/**
	 * Returns the request currently selected for the details panel.
	 *
	 * @since 1.0.0
	 *
	 * @return DataRequest|null
	 */
	#[Computed]
	public function activeRequest(): ?DataRequest
	{
		if ( null === $this->activeRequestId ) {
			return null;
		}

		return DataRequest::query()->with( 'logs' )->find( $this->activeRequestId );
	}

	/**
	 * Returns the allowed type filter options derived from configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	#[Computed]
	public function types(): array
	{
		$allowed = (array) config( 'artisanpack.privacy.data_requests.allowed_types', [
			DataRequest::TYPE_ACCESS,
			DataRequest::TYPE_EXPORT,
			DataRequest::TYPE_DELETION,
			DataRequest::TYPE_RECTIFICATION,
		] );

		return array_values( array_map( static fn ( $value ): string => (string) $value, $allowed ) );
	}

	/**
	 * Reset pagination when the type filter changes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function updatedTypeFilter(): void
	{
		$this->resetPage();
	}

	/**
	 * Reset pagination when the status filter changes.
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
	 * Reset pagination when the sort changes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function updatedSort(): void
	{
		$this->resetPage();
	}

	/**
	 * Show details for a specific request in the side panel.
	 *
	 * @since 1.0.0
	 *
	 * @param  int  $id Data-request primary key.
	 *
	 * @return void
	 */
	public function view( int $id ): void
	{
		$this->activeRequestId = $id;
		$this->note            = '';
	}

	/**
	 * Close the details panel.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function closeDetails(): void
	{
		$this->activeRequestId = null;
		$this->note            = '';
	}

	/**
	 * Approve a pending request — moves it into processing and verifies it
	 * manually when a verification token is still outstanding.
	 *
	 * @since 1.0.0
	 *
	 * @param  int  $id Data-request primary key.
	 *
	 * @return void
	 */
	public function approve( int $id ): void
	{
		$note    = $this->note;
		$result  = DB::transaction( function () use ( $id, $note ) {
			$request = DataRequest::query()->lockForUpdate()->find( $id );

			if ( null === $request ) {
				return null;
			}

			if ( DataRequest::STATUS_PENDING !== $request->status && DataRequest::STATUS_PROCESSING !== $request->status ) {
				return null;
			}

			if ( null === $request->verified_at ) {
				$confirmed = app( VerificationService::class )
					->confirm( $request, 'manual', $this->actorId() );

				if ( ! $confirmed ) {
					return null;
				}

				$request->refresh();
			}

			$request->forceFill( [
				'status'       => DataRequest::STATUS_PROCESSING,
				'processed_by' => $this->actorId(),
			] )->save();

			$this->logAction( $request, 'approved', $note );

			return $request;
		} );

		if ( $result instanceof DataRequest ) {
			$this->dispatch( 'privacy:data-request-processed', id: $result->id, status: $result->status );
			$this->note = '';
		}
	}

	/**
	 * Reject a pending or processing request.
	 *
	 * @since 1.0.0
	 *
	 * @param  int  $id Data-request primary key.
	 *
	 * @return void
	 */
	public function reject( int $id ): void
	{
		$note   = $this->note;
		$result = DB::transaction( function () use ( $id, $note ) {
			$request = DataRequest::query()->lockForUpdate()->find( $id );

			if ( null === $request ) {
				return null;
			}

			if ( DataRequest::STATUS_COMPLETED === $request->status || DataRequest::STATUS_REJECTED === $request->status ) {
				return null;
			}

			$request->forceFill( [
				'status'       => DataRequest::STATUS_REJECTED,
				'processed_by' => $this->actorId(),
				'admin_notes'  => $this->mergeNote( $request, $note ),
			] )->save();

			$this->logAction( $request, 'rejected', $note );

			return $request;
		} );

		if ( $result instanceof DataRequest ) {
			$this->dispatch( 'privacy:data-request-processed', id: $result->id, status: $result->status );
			$this->note = '';
		}
	}

	/**
	 * Complete a processing request.
	 *
	 * @since 1.0.0
	 *
	 * @param  int  $id Data-request primary key.
	 *
	 * @return void
	 */
	public function complete( int $id ): void
	{
		$note   = $this->note;
		$result = DB::transaction( function () use ( $id, $note ) {
			$request = DataRequest::query()->lockForUpdate()->find( $id );

			if ( null === $request ) {
				return null;
			}

			if ( DataRequest::STATUS_COMPLETED === $request->status || DataRequest::STATUS_REJECTED === $request->status ) {
				return null;
			}

			$request->forceFill( [
				'status'       => DataRequest::STATUS_COMPLETED,
				'completed_at' => now(),
				'processed_by' => $this->actorId(),
				'admin_notes'  => $this->mergeNote( $request, $note ),
			] )->save();

			$this->logAction( $request, 'completed', $note );

			return $request;
		} );

		if ( $result instanceof DataRequest ) {
			$this->dispatch( 'privacy:data-request-processed', id: $result->id, status: $result->status );
			$this->note = '';
		}
	}

	/**
	 * Manually verify a request without going through the email link.
	 *
	 * @since 1.0.0
	 *
	 * @param  int  $id Data-request primary key.
	 *
	 * @return void
	 */
	public function verifyManually( int $id ): void
	{
		$note      = $this->note;
		$verified  = DB::transaction( function () use ( $id, $note ) {
			$request = DataRequest::query()->lockForUpdate()->find( $id );

			if ( null === $request ) {
				return false;
			}

			if ( null !== $request->verified_at ) {
				return false;
			}

			$confirmed = app( VerificationService::class )
				->confirm( $request, 'manual', $this->actorId() );

			if ( ! $confirmed ) {
				return false;
			}

			$this->logAction( $request->refresh(), 'verified', $note );

			return true;
		} );

		if ( $verified ) {
			$this->note = '';
		}
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
		return view( 'privacy::livewire.admin.data-request-manager' );
	}

	/**
	 * Build the base query for the current filter state.
	 *
	 * @since 1.0.0
	 *
	 * @return Builder<DataRequest>
	 */
	protected function query(): Builder
	{
		$query = DataRequest::query();

		if ( '' !== $this->typeFilter ) {
			$query->where( 'type', $this->typeFilter );
		}

		if ( '' !== $this->statusFilter ) {
			$query->where( 'status', $this->statusFilter );
		}

		return $query;
	}

	/**
	 * Applies the active sort to the supplied query.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder<DataRequest>  $query Base query.
	 *
	 * @return Builder<DataRequest>
	 */
	protected function applySort( Builder $query ): Builder
	{
		switch ( $this->sort ) {
			case self::SORT_NEWEST:
				return $query->orderByDesc( 'created_at' );

			case self::SORT_OVERDUE:
				return $query
					->whereNotNull( 'due_at' )
					->where( 'due_at', '<', now() )
					->orderBy( 'due_at' );

			case self::SORT_DEADLINE:
			default:
				return $query->orderByRaw( 'due_at IS NULL, due_at ASC' )->orderBy( 'created_at' );
		}
	}

	/**
	 * Returns the resolved admin user id for audit-log entries.
	 *
	 * @since 1.0.0
	 *
	 * @return int|null
	 */
	protected function actorId(): ?int
	{
		$user = Auth::user();

		if ( null === $user ) {
			return null;
		}

		$id = $user->getAuthIdentifier();

		return is_numeric( $id ) ? (int) $id : null;
	}

	/**
	 * Append the supplied note onto the request's existing admin notes.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataRequest  $request Request being mutated.
	 * @param  string       $note    Note to append (trimmed).
	 *
	 * @return string|null
	 */
	protected function mergeNote( DataRequest $request, string $note ): ?string
	{
		$note = trim( $note );

		if ( '' === $note ) {
			return $request->admin_notes;
		}

		$existing = (string) ( $request->admin_notes ?? '' );

		return '' === $existing ? $note : $existing . "\n" . $note;
	}

	/**
	 * Log an admin action against the supplied request.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataRequest  $request Target request.
	 * @param  string       $action  Action identifier.
	 * @param  string       $note    Optional note.
	 *
	 * @return void
	 */
	protected function logAction( DataRequest $request, string $action, string $note ): void
	{
		if ( ! class_exists( DataRequestLog::class ) ) {
			return;
		}

		DataRequestLog::query()->create( [
			'data_request_id' => $request->id,
			'action'          => $action,
			'performed_by'    => $this->actorId(),
			'metadata'        => '' === trim( $note ) ? null : [ 'note' => trim( $note ) ],
		] );
	}
}
