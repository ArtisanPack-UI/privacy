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

use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Services\ConsentService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
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
	 * Extra CSS classes applied to the banner outer container.
	 *
	 * @var string
	 */
	public string $class = '';

	/**
	 * Extra CSS classes applied to the action buttons.
	 *
	 * @var string
	 */
	public string $buttonClasses = '';

	/**
	 * Overrides for the labels rendered inside the banner.
	 *
	 * Recognised keys: `title`, `description`, `accept_all`, `reject_all`,
	 * `customise`, `save`, `required_badge`.
	 *
	 * @var array<string, string>
	 */
	public array $labels = [];

	/**
	 * Mount the component.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null            $position      Override the configured banner position.
	 * @param  string|null            $style         Override the configured banner style.
	 * @param  array|null             $categories    Explicit cookie-category map override.
	 * @param  string|null            $class         Extra CSS classes on the outer container.
	 * @param  string|null            $buttonClasses Extra CSS classes on the action buttons.
	 * @param  array<string, string>  $labels        Label overrides for the rendered copy.
	 *
	 * @return void
	 */
	public function mount(
		?string $position = null,
		?string $style = null,
		?array $categories = null,
		?string $class = null,
		?string $buttonClasses = null,
		array $labels = [],
	): void {
		$ui = (array) config( 'artisanpack.privacy.ui.cookie_banner', [] );

		$this->position      = $position ?? (string) ( $ui['position'] ?? 'bottom' );
		$this->style         = $style ?? (string) ( $ui['style'] ?? 'bar' );
		$this->categories    = $categories ?? $this->resolveCategories();
		$this->selected      = $this->previousSelections();
		$this->visible       = ! $this->hasExistingConsent() || $this->hasExpiredConsent();
		$this->class         = $class ?? (string) config( 'artisanpack.privacy.ui.custom_css_class', '' );
		$this->buttonClasses = $buttonClasses ?? '';
		$this->labels        = $labels;
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
	 * Handles the `privacy:open-preferences` browser event dispatched by the
	 * `window.PrivacyConsent.openPreferences()` JavaScript helper.
	 *
	 * Re-opens the banner if the visitor has already dismissed it so they can
	 * revisit their previous choices.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function openFromGlobal(): void
	{
		$this->visible         = true;
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
	 * Re-consent helper that pre-seeds the inline preferences panel with
	 * the visitor's most recent selections. Prefers the cookie when present;
	 * falls back to the database for `storage = database` deployments where
	 * the cookie is never written.
	 *
	 * Uses `FILTER_VALIDATE_BOOLEAN` so string values written by a partner
	 * CMP (`"false"`, `"0"`, etc.) are not silently promoted to `true`.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, bool>
	 */
	protected function previousSelections(): array
	{
		$prior = [];

		foreach ( $this->categories as $category => $config ) {
			$prior[ (string) $category ] = true === ( $config['required'] ?? false );
		}

		$service = app( ConsentService::class );
		$cookie  = $service->getConsentCookie();

		if ( is_array( $cookie ) ) {
			foreach ( $cookie as $category => $granted ) {
				if ( is_string( $category ) && array_key_exists( $category, $prior ) ) {
					$normalized = filter_var(
						$granted,
						FILTER_VALIDATE_BOOLEAN,
						FILTER_NULL_ON_FAILURE,
					);

					$prior[ $category ] = ( true === $normalized )
						|| true === ( $this->categories[ $category ]['required'] ?? false );
				}
			}

			return $prior;
		}

		$user = Auth::user();
		if ( $user instanceof Model ) {
			foreach ( $service->getConsents( $user ) as $row ) {
				$category = (string) $row->category;
				if ( array_key_exists( $category, $prior ) ) {
					$prior[ $category ] = true;
				}
			}
		}

		return $prior;
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
	 * Returns true when the authenticated visitor has at least one consent
	 * row whose `expires_at` is in the past AND is still the latest decision
	 * for its category (no newer row supersedes it). Drives the re-consent
	 * flow: an expired consent should pop the banner back open so the visitor
	 * can confirm their preferences.
	 *
	 * Deliberately does NOT filter on `withdrawn_at` — the scheduled
	 * {@see \ArtisanPackUI\Privacy\Console\Commands\PurgeExpiredConsents}
	 * command sets that column when it sweeps expired rows, and we still want
	 * the banner to re-prompt afterwards. The `whereNotExists` subquery
	 * suppresses the re-prompt once the visitor has actually re-consented
	 * (the new row is newer than the expired one).
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function hasExpiredConsent(): bool
	{
		$user = Auth::user();

		if ( ! $user instanceof Model ) {
			return false;
		}

		$key = 'privacy.consent.expired.' . sha1( $user->getMorphClass() . '|' . (string) $user->getKey() );
		$ttl = (int) config( 'artisanpack.privacy.cache.banner_ttl', 60 );

		return (bool) Cache::remember( $key, $ttl, static fn (): bool => Consent::query()
			->forSubject( $user )
			->whereNotNull( 'expires_at' )
			->where( 'expires_at', '<', now() )
			->whereNotExists( function ( $query ): void {
				$query->select( DB::raw( 1 ) )
					->from( 'privacy_consents as newer' )
					->whereColumn( 'newer.consentable_type', 'privacy_consents.consentable_type' )
					->whereColumn( 'newer.consentable_id', 'privacy_consents.consentable_id' )
					->whereColumn( 'newer.category', 'privacy_consents.category' )
					->whereColumn( 'newer.created_at', '>', 'privacy_consents.created_at' );
			} )
			->exists() );
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
}
