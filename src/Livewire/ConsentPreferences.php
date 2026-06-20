<?php

/**
 * ConsentPreferences Livewire component — granular cookie-preference editor.
 *
 * Renders one toggle per configured category with optional descriptions and
 * cookie lists, tracks whether the visitor has made unsaved changes, and
 * persists them through {@see ConsentService} on save.
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

use ArtisanPackUI\Privacy\Services\ConsentService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Livewire consent preferences panel.
 *
 * Usable standalone or embedded inside a modal:
 *
 *   <livewire:privacy-consent-preferences />
 *
 *   <x-artisanpack-modal wire:model="showPreferences" title="Cookie Preferences">
 *       <livewire:privacy-consent-preferences />
 *   </x-artisanpack-modal>
 *
 * @since 1.0.0
 */
class ConsentPreferences extends Component
{
	/**
	 * Whether to render the per-category description text.
	 *
	 * @var bool
	 */
	public bool $showDescriptions = true;

	/**
	 * Whether to render the per-category cookie list.
	 *
	 * @var bool
	 */
	public bool $showCookieList = false;

	/**
	 * Visitor-toggleable consent map (category => granted).
	 *
	 * @var array<string, bool>
	 */
	public array $consents = [];

	/**
	 * Whether the visitor has made changes since the last save.
	 *
	 * @var bool
	 */
	public bool $dirty = false;

	/**
	 * Set on a successful save so the view can render a confirmation.
	 *
	 * @var bool
	 */
	public bool $saved = false;

	/**
	 * Cookie-category configuration keyed by category slug.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	#[Locked]
	public array $categories = [];

	/**
	 * Snapshot of the initial consent state used to detect unsaved changes.
	 *
	 * @var array<string, bool>
	 */
	#[Locked]
	public array $initialConsents = [];

	/**
	 * Mount the component.
	 *
	 * @since 1.0.0
	 *
	 * @param  bool|null  $showDescriptions Override the description-visibility default.
	 * @param  bool|null  $showCookieList   Override the cookie-list visibility default.
	 *
	 * @return void
	 */
	public function mount( ?bool $showDescriptions = null, ?bool $showCookieList = null ): void
	{
		if ( null !== $showDescriptions ) {
			$this->showDescriptions = $showDescriptions;
		}

		if ( null !== $showCookieList ) {
			$this->showCookieList = $showCookieList;
		}

		$this->categories      = (array) config( 'artisanpack.privacy.cookie_categories', [] );
		$this->consents        = $this->loadCurrentConsents();
		$this->initialConsents = $this->consents;
	}

	/**
	 * Toggles a single category. No-ops for required categories.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $category Category key.
	 *
	 * @return void
	 */
	public function toggleCategory( string $category ): void
	{
		if ( ! array_key_exists( $category, $this->categories ) ) {
			return;
		}

		if ( true === ( $this->categories[ $category ]['required'] ?? false ) ) {
			$this->consents[ $category ] = true;

			return;
		}

		$this->consents[ $category ] = ! ( $this->consents[ $category ] ?? false );
		$this->saved                 = false;
		$this->dirty                 = $this->consents !== $this->initialConsents;
	}

	/**
	 * Persists the visitor's preferences to the configured storage backend.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save(): void
	{
		$service = app( ConsentService::class );

		foreach ( $this->categories as $category => $config ) {
			$isRequired = true === ( $config['required'] ?? false );
			$wantsGrant = $isRequired || true === ( $this->consents[ $category ] ?? false );

			if ( $wantsGrant ) {
				$service->grantConsent( (string) $category );
				$this->consents[ $category ] = true;
				continue;
			}

			$service->revokeConsent( (string) $category );
			$this->consents[ $category ] = false;
		}

		$this->initialConsents = $this->consents;
		$this->dirty           = false;
		$this->saved           = true;

		$this->dispatch( 'privacy:consent-updated', categories: $this->consents );
	}

	/**
	 * Updated hook — keep the dirty flag in sync when Livewire writes back
	 * `consents.*` directly via `wire:model`.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed   $value Updated value (unused).
	 * @param  string  $key   Property path that changed.
	 *
	 * @return void
	 */
	public function updatedConsents( $value, string $key ): void
	{
		$category = $key;

		if ( array_key_exists( $category, $this->categories )
			&& true === ( $this->categories[ $category ]['required'] ?? false ) ) {
			$this->consents[ $category ] = true;
		}

		$this->saved = false;
		$this->dirty = $this->consents !== $this->initialConsents;
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
		return view( 'privacy::livewire.consent-preferences' );
	}

	/**
	 * Reads the visitor's existing consent map, preferring database state
	 * for authenticated users and falling back to the cookie for guests.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, bool>
	 */
	protected function loadCurrentConsents(): array
	{
		$service = app( ConsentService::class );
		$cookie  = $service->getConsentCookie() ?? [];
		$state   = [];

		foreach ( $this->categories as $category => $config ) {
			$state[ $category ] = true === ( $config['required'] ?? false )
				|| true === ( $cookie[ $category ] ?? $service->hasConsent( (string) $category ) );
		}

		return $state;
	}
}
