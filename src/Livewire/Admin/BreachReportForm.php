<?php

/**
 * BreachReportForm Livewire component — admin breach report form.
 *
 * Captures the minimum set of fields required to file a new breach and
 * delegates persistence to {@see BreachNotificationService}. Affected
 * users may be supplied as a newline-delimited list of email addresses.
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Admin breach report form.
 *
 * Usage:
 *
 *   <livewire:privacy-admin-breach-report-form />
 *
 * @since 1.0.0
 */
class BreachReportForm extends Component
{
	public string $description = '';

	public string $severity = BreachNotification::SEVERITY_MEDIUM;

	public string $dataTypes = '';

	public string $affectedUsers = '';

	public ?int $recordsAffected = null;

	public string $cause = '';

	public string $remediation = '';

	public string $occurredAt = '';

	public ?int $createdBreachId = null;

	/**
	 * Authorize the gate before mount.
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
	 * Persist the breach via the service.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function submit(): void
	{
		$this->validate( [
			'description' => 'required|string|max:5000',
			'severity'    => 'required|in:low,medium,high,critical',
			'dataTypes'   => 'required|string|max:2000',
		] );

		$dataTypes = collect( explode( ',', $this->dataTypes ) )
			->map( static fn ( string $value ): string => trim( $value ) )
			->filter( static fn ( string $value ): bool => '' !== $value )
			->values()
			->all();

		$candidateEmails = collect( preg_split( "/[\r\n]+/", $this->affectedUsers ) ?: [] )
			->map( static fn ( string $value ): string => trim( $value ) )
			->filter( static fn ( string $value ): bool => '' !== $value )
			->values()
			->all();

		$invalid = array_values( array_filter(
			$candidateEmails,
			static fn ( string $value ): bool => false === filter_var( $value, FILTER_VALIDATE_EMAIL ),
		) );

		if ( [] !== $invalid ) {
			$this->addError( 'affectedUsers', __(
				'These entries are not valid email addresses: :list',
				[ 'list' => implode( ', ', $invalid ) ],
			) );

			return;
		}

		$affected = $candidateEmails;

		$breach = app( BreachNotificationService::class )->reportBreach( [
			'description'         => $this->description,
			'severity'            => $this->severity,
			'data_types_affected' => $dataTypes,
			'records_affected'    => $this->recordsAffected,
			'affected_users'      => [] === $affected ? null : $affected,
			'cause'               => '' === $this->cause ? null : $this->cause,
			'remediation'         => '' === $this->remediation ? null : $this->remediation,
			'occurred_at'         => '' === $this->occurredAt ? null : $this->occurredAt,
			'reported_by'         => $this->actorId(),
		] );

		$this->createdBreachId = $breach->id;

		$this->dispatch( 'privacy:breach-reported', id: $breach->id, reference: $breach->reference_number );
		$this->reset( [
			'description',
			'dataTypes',
			'affectedUsers',
			'recordsAffected',
			'cause',
			'remediation',
			'occurredAt',
		] );
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
		return view( 'privacy::livewire.admin.breach-report-form' );
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
	 * Resolve the actor id for `reported_by`.
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
}
