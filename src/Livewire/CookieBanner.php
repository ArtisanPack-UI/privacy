<?php

/**
 * CookieBanner Livewire component — surfaces the consent banner to visitors.
 *
 * Renders the package's first-touch consent UI with three primary actions
 * (accept all, reject all, customise), dispatches browser events that the
 * Privacy JavaScript API listens for, and self-hides once the visitor has
 * recorded a choice.
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
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Livewire cookie consent banner.
 *
 * Mount it with reasonable defaults:
 *
 *   <livewire:privacy-cookie-banner />
 *
 * Or pass props to override layout choices:
 *
 *   <livewire:privacy-cookie-banner position="bottom" style="bar" />
 *
 * @since 1.0.0
 */
class CookieBanner extends Component
{
	/**
	 * Banner position keyword (e.g. `bottom`, `top`, `bottom-right`).
	 *
	 * @var string
	 */
	public string $position = 'bottom';

	/**
	 * Banner visual style (`bar`, `modal`, `floating`).
	 *
	 * @var string
	 */
	public string $style = 'bar';

	/**
	 * Whether the banner is currently visible.
	 *
	 * @var bool
	 */
	public bool $visible = true;

	/**
	 * Whether the inline preferences panel is open.
	 *
	 * @var bool
	 */
	public bool $showPreferences = false;

	/**
	 * Cookie-category configuration keyed by category slug.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	#[Locked]
	public array $categories = [];

	/**
	 * In-progress selection map for the inline preferences panel
	 * (category => granted). Required categories are pre-set to true.
	 *
	 * @var array<string, bool>
	 */
	public array $selected = [];

	/**
	 * Mount the component.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null  $position   Override the configured banner position.
	 * @param  string|null  $style      Override the configured banner style.
	 * @param  array|null   $categories Explicit cookie-category map override.
	 *
	 * @return void
	 */
	public function mount( ?string $position = null, ?string $style = null, ?array $categories = null ): void
	{
		$ui = (array) config( 'artisanpack.privacy.ui.cookie_banner', [] );

		$this->position   = $position ?? (string) ( $ui['position'] ?? 'bottom' );
		$this->style      = $style ?? (string) ( $ui['style'] ?? 'bar' );
		$this->categories = $categories ?? $this->resolveCategories();
		$this->selected   = $this->defaultSelection();
		$this->visible    = ! $this->hasExistingConsent();
	}

	/**
	 * Grants consent for every configured category.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function acceptAll(): void
	{
		app( ConsentService::class )->grantAllConsents();

		$this->dispatchConsentUpdated( array_fill_keys( array_keys( $this->categories ), true ) );
		$this->close();
	}

	/**
	 * Revokes consent for every non-required category and grants the required ones.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function rejectAll(): void
	{
		$service = app( ConsentService::class );
		$payload = [];

		foreach ( $this->categories as $category => $config ) {
			if ( true === ( $config['required'] ?? false ) ) {
				$service->grantConsent( (string) $category );
				$payload[ $category ] = true;
				continue;
			}

			$service->revokeConsent( (string) $category );
			$payload[ $category ] = false;
		}

		$this->dispatchConsentUpdated( $payload );
		$this->close();
	}

	/**
	 * Grants consent only for the supplied set of categories (plus the
	 * required ones, which are always granted regardless of the request).
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, string>  $categories Categories the visitor accepted.
	 *
	 * @return void
	 */
	public function acceptSelected( array $categories ): void
	{
		$service  = app( ConsentService::class );
		$selected = array_map( 'strval', $categories );
		$payload  = [];

		foreach ( $this->categories as $category => $config ) {
			$isRequired = true === ( $config['required'] ?? false );

			if ( $isRequired || in_array( (string) $category, $selected, true ) ) {
				$service->grantConsent( (string) $category );
				$payload[ $category ] = true;
				continue;
			}

			$service->revokeConsent( (string) $category );
			$payload[ $category ] = false;
		}

		$this->dispatchConsentUpdated( $payload );
		$this->close();
	}

	/**
	 * Opens the inline preferences panel for the visitor to customise consent.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function openPreferences(): void
	{
		$this->showPreferences = true;
	}

	/**
	 * Persists the visitor's inline-preference selections via {@see acceptSelected()}.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function saveSelected(): void
	{
		$picked = array_keys( array_filter( $this->selected, static fn ( $value ): bool => true === $value ) );

		$this->acceptSelected( $picked );
	}

	/**
	 * Updated hook — keep required categories pinned to true even if a
	 * client-side handler attempts to flip them off.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed   $value Updated value (unused).
	 * @param  string  $key   Property path that changed (the category key).
	 *
	 * @return void
	 */
	public function updatedSelected( $value, string $key ): void
	{
		if ( array_key_exists( $key, $this->categories )
			&& true === ( $this->categories[ $key ]['required'] ?? false ) ) {
			$this->selected[ $key ] = true;
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
		return view( 'privacy::livewire.cookie-banner' );
	}

	/**
	 * Closes the banner and dispatches the `privacy:banner-closed` browser event.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function close(): void
	{
		$this->visible         = false;
		$this->showPreferences = false;

		$this->dispatch( 'privacy:banner-closed' );
	}

	/**
	 * Returns true when the visitor already has a stored consent decision.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function hasExistingConsent(): bool
	{
		return null !== Cookie::get( (string) config( 'artisanpack.privacy.consent.cookie_name', 'privacy_consent' ) );
	}

	/**
	 * Dispatches the `privacy:consent-updated` browser event with the
	 * category map the visitor confirmed.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, bool>  $payload Category => granted map.
	 *
	 * @return void
	 */
	protected function dispatchConsentUpdated( array $payload ): void
	{
		$this->dispatch( 'privacy:consent-updated', categories: $payload );
	}

	/**
	 * Returns the active cookie-category map, preferring database-backed
	 * categories when present and falling back to the configuration array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function resolveCategories(): array
	{
		return (array) config( 'artisanpack.privacy.cookie_categories', [] );
	}

	/**
	 * Default selection map for the inline preferences panel: required
	 * categories pre-toggled on, optional categories off.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, bool>
	 */
	protected function defaultSelection(): array
	{
		$state = [];

		foreach ( $this->categories as $category => $config ) {
			$state[ $category ] = true === ( $config['required'] ?? false );
		}

		return $state;
	}
}
