<?php

/**
 * PrivacyDashboard Livewire component — user-facing privacy hub.
 *
 * Renders the authenticated visitor's consent settings, lets them file new
 * data subject requests, shows the history of past requests, and surfaces
 * download links for completed exports. Composes the existing consent /
 * data-request components so each section reuses tested behaviour.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Livewire;

use ArtisanPackUI\Privacy\Models\DataRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Privacy dashboard for authenticated users.
 *
 * Usage:
 *
 *   <livewire:privacy-dashboard />
 *
 * Optional mount params let host applications customize the layout — for
 * example, hide the history table when surfacing the dashboard inside a
 * compact sidebar.
 *
 * @since 1.0.0
 */
class PrivacyDashboard extends Component
{
	/**
	 * Whether to render the consent preferences section.
	 *
	 * @var bool
	 */
	#[Locked]
	public bool $showConsent = true;

	/**
	 * Whether to render the new-request form section.
	 *
	 * @var bool
	 */
	#[Locked]
	public bool $showRequestForm = true;

	/**
	 * Whether to render the request history section.
	 *
	 * @var bool
	 */
	#[Locked]
	public bool $showHistory = true;

	/**
	 * Optional URL to link the "view privacy policy" anchor at.
	 *
	 * @var string|null
	 */
	#[Locked]
	public ?string $policyUrl = null;

	/**
	 * Maximum number of historical requests to show inline.
	 *
	 * @var int
	 */
	#[Locked]
	public int $historyLimit = 25;

	/**
	 * Cache bumped after a request is submitted so the history table reloads.
	 *
	 * @var int
	 */
	public int $requestsVersion = 0;

	/**
	 * Mount the component.
	 *
	 * @since 1.0.0
	 *
	 * @param  bool|null     $showConsent     Override the consent section visibility.
	 * @param  bool|null     $showRequestForm Override the request-form visibility.
	 * @param  bool|null     $showHistory     Override the history section visibility.
	 * @param  string|null   $policyUrl       URL to the privacy policy.
	 * @param  int|null      $historyLimit    Max historical requests to show.
	 *
	 * @return void
	 */
	public function mount(
		?bool $showConsent = null,
		?bool $showRequestForm = null,
		?bool $showHistory = null,
		?string $policyUrl = null,
		?int $historyLimit = null,
	): void {
		if ( null !== $showConsent ) {
			$this->showConsent = $showConsent;
		}

		if ( null !== $showRequestForm ) {
			$this->showRequestForm = $showRequestForm;
		}

		if ( null !== $showHistory ) {
			$this->showHistory = $showHistory;
		}

		$this->policyUrl = $policyUrl;

		if ( null !== $historyLimit && $historyLimit > 0 ) {
			$this->historyLimit = $historyLimit;
		}
	}

	/**
	 * Refresh the history table whenever the request form fires a submitted
	 * event. Keeps the dashboard's snapshot in sync without forcing the user
	 * to refresh the page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	#[On( 'privacy:data-request-submitted' )]
	public function onRequestSubmitted(): void
	{
		$this->requestsVersion++;
	}

	/**
	 * Bumps the cache key so the history reload happens on the next render.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	#[On( 'privacy:consent-updated' )]
	public function onConsentUpdated(): void
	{
		// Touching the dashboard is enough for Livewire to re-render the
		// applicable-regulation badge — no extra state needs flipping.
	}

	/**
	 * Returns the authenticated subject the dashboard is built for, or null
	 * when no user is signed in (the view degrades to a sign-in prompt).
	 *
	 * @since 1.0.0
	 *
	 * @return Model|null
	 */
	#[Computed]
	public function subject(): ?Model
	{
		$user = Auth::user();

		return $user instanceof Model ? $user : null;
	}

	/**
	 * Returns the historical requests filed by the subject, newest first.
	 *
	 * Bumping `requestsVersion` forces a re-render (via the table's
	 * `wire:key`), at which point Livewire's per-request computed cache is
	 * re-evaluated against the freshly-bumped state.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection<int, DataRequest>
	 */
	#[Computed]
	public function requests(): Collection
	{
		$subject = $this->subject();

		if ( ! $subject instanceof Model ) {
			return new Collection();
		}

		return DataRequest::query()
			->where( 'requestable_type', $subject->getMorphClass() )
			->where( 'requestable_id', $subject->getKey() )
			->orderByDesc( 'created_at' )
			->limit( $this->historyLimit )
			->get();
	}

	/**
	 * Returns the regulation key applicable to the current request.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	#[Computed]
	public function regulation(): ?string
	{
		$resolver   = app( \ArtisanPackUI\Privacy\Regulations\RegulationRegistry::class );
		$regulation = $resolver->applicableFor();

		return $regulation?->key();
	}

	/**
	 * Returns the URL the user should be sent to for a completed export, or
	 * null when no download URL is stored.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataRequest  $request Persisted request row.
	 *
	 * @return string|null
	 */
	public function downloadUrlFor( DataRequest $request ): ?string
	{
		if ( DataRequest::TYPE_EXPORT !== $request->type ) {
			return null;
		}

		if ( DataRequest::STATUS_COMPLETED !== $request->status ) {
			return null;
		}

		$payload = $request->data;

		if ( ! is_array( $payload ) ) {
			return null;
		}

		$url = $payload['download_url'] ?? null;

		return is_string( $url ) && '' !== $url ? $url : null;
	}

	/**
	 * Render the dashboard view.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'privacy::livewire.privacy-dashboard' );
	}
}
