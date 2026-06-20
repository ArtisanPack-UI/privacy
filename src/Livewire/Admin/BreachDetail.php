<?php

/**
 * BreachDetail Livewire component — admin breach detail view.
 *
 * Shows the full incident record, the notification timeline, and exposes
 * actions for dispatching the authority/user notifications, appending
 * remediation notes, advancing the status workflow, and exporting the
 * breach documentation.
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
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin breach detail panel.
 *
 * Usage:
 *
 *   <livewire:privacy-admin-breach-detail :breach-id="$breach->id" />
 *
 * @since 1.0.0
 */
class BreachDetail extends Component
{
	/**
	 * Target breach primary key (passed in by the parent route).
	 *
	 * @var int|null
	 */
	public ?int $breachId = null;

	/**
	 * Free-text remediation note appended on the next save.
	 *
	 * @var string
	 */
	public string $remediationNote = '';

	/**
	 * Authorize the gate before mount.
	 *
	 * @since 1.0.0
	 *
	 * @param  int|null  $breachId Breach id from the parent.
	 *
	 * @return void
	 */
	public function mount( ?int $breachId = null ): void
	{
		$gate = (string) config( 'artisanpack.privacy.admin.gate', 'manage-privacy' );

		if ( '' === $gate ) {
			$gate = 'manage-privacy';
		}

		Gate::authorize( $gate );

		if ( null !== $breachId ) {
			$this->breachId = $breachId;
		}
	}

	/**
	 * Resolve the breach record.
	 *
	 * @since 1.0.0
	 *
	 * @return BreachNotification|null
	 */
	#[Computed]
	public function breach(): ?BreachNotification
	{
		return null === $this->breachId
			? null
			: BreachNotification::query()->find( $this->breachId );
	}

	/**
	 * Dispatch the authority notification.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function notifyAuthority(): void
	{
		$breach = $this->breach;

		if ( null === $breach ) {
			return;
		}

		app( BreachNotificationService::class )->notifyAuthority( $breach );

		$this->dispatch( 'privacy:breach-notified', id: $breach->id, channel: 'authority' );
	}

	/**
	 * Dispatch the affected user notifications.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function notifyUsers(): void
	{
		$breach = $this->breach;

		if ( null === $breach ) {
			return;
		}

		app( BreachNotificationService::class )->notifyAffectedUsers( $breach );

		$this->dispatch( 'privacy:breach-notified', id: $breach->id, channel: 'users' );
	}

	/**
	 * Append the current remediation note to the breach.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function addRemediation(): void
	{
		$breach = $this->breach;
		$note   = trim( $this->remediationNote );

		if ( null === $breach || '' === $note ) {
			return;
		}

		$existing = (string) ( $breach->remediation ?? '' );

		$breach->forceFill( [
			'remediation' => '' === $existing ? $note : $existing . "\n" . $note,
		] )->save();

		$this->remediationNote = '';
		unset( $this->breach );
	}

	/**
	 * Advance the breach status workflow.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $status Target status (`investigating`, `contained`, `resolved`).
	 *
	 * @return void
	 */
	public function setStatus( string $status ): void
	{
		$breach = $this->breach;

		if ( null === $breach ) {
			return;
		}

		if ( ! in_array( $status, [
			BreachNotification::STATUS_INVESTIGATING,
			BreachNotification::STATUS_CONTAINED,
			BreachNotification::STATUS_RESOLVED,
		], true ) ) {
			return;
		}

		$breach->forceFill( [ 'status' => $status ] )->save();
		unset( $this->breach );

		$this->dispatch( 'privacy:breach-status-changed', id: $breach->id, status: $status );
	}

	/**
	 * Stream the breach documentation as a CSV download.
	 *
	 * @since 1.0.0
	 *
	 * @return StreamedResponse|null
	 */
	public function exportDocumentation(): ?StreamedResponse
	{
		$breach = $this->breach;

		if ( null === $breach ) {
			return null;
		}

		$filename = sprintf( 'privacy-breach-%s.csv', $breach->reference_number );

		$rows = [
			[ 'reference_number', (string) $breach->reference_number ],
			[ 'severity', (string) $breach->severity ],
			[ 'status', (string) $breach->status ],
			[ 'discovered_at', optional( $breach->discovered_at )->toIso8601String() ?? '' ],
			[ 'occurred_at', optional( $breach->occurred_at )->toIso8601String() ?? '' ],
			[ 'authority_notified_at', optional( $breach->authority_notified_at )->toIso8601String() ?? '' ],
			[ 'users_notified_at', optional( $breach->users_notified_at )->toIso8601String() ?? '' ],
			[ 'records_affected', (string) ( $breach->records_affected ?? '' ) ],
			[ 'description', (string) $breach->description ],
			[ 'cause', (string) ( $breach->cause ?? '' ) ],
			[ 'remediation', (string) ( $breach->remediation ?? '' ) ],
			[ 'data_types_affected', (string) json_encode( (array) ( $breach->data_types_affected ?? [] ) ) ],
			[ 'notifications_sent', (string) json_encode( (array) ( $breach->notifications_sent ?? [] ) ) ],
		];

		return response()->streamDownload( static function () use ( $rows ): void {
			$out = fopen( 'php://output', 'wb' );
			fputcsv( $out, [ 'field', 'value' ] );
			foreach ( $rows as $row ) {
				fputcsv( $out, $row );
			}
			fclose( $out );
		}, $filename, [ 'Content-Type' => 'text/csv' ] );
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
		return view( 'privacy::livewire.admin.breach-detail', [
			'service' => app( BreachNotificationService::class ),
		] );
	}
}
