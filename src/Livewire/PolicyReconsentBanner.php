<?php

/**
 * PolicyReconsentBanner Livewire component — surfaces the re-consent prompt
 * when the active privacy policy has been updated.
 *
 * Mount the component once, anywhere in the application's main layout:
 *
 *   <livewire:privacy-policy-reconsent-banner />
 *
 * Honours the optional `policy` prop for explicit injection (useful for
 * tests) and the configured `class`, `buttonClasses`, and `labels` props
 * so applications can theme the banner without publishing the view.
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

use ArtisanPackUI\Privacy\Models\PrivacyPolicy;
use ArtisanPackUI\Privacy\Services\ReconsentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Re-consent prompt rendered on every page until the user accepts the new policy.
 *
 * @since 1.0.0
 */
class PolicyReconsentBanner extends Component
{
	/**
	 * Optional CSS class applied to the outer container.
	 *
	 * @var string
	 */
	public string $class = '';

	/**
	 * Optional CSS class applied to action buttons.
	 *
	 * @var string
	 */
	public string $buttonClasses = '';

	/**
	 * Overrides for the labels displayed in the banner.
	 *
	 * Recognised keys: `title`, `description`, `accept`, `review`, `dismiss`.
	 *
	 * @var array<string, string>
	 */
	public array $labels = [];

	/**
	 * Optional override for the regulation key used to resolve the policy.
	 *
	 * @var string|null
	 */
	public ?string $regulation = null;

	/**
	 * The version of the policy currently being prompted on.
	 *
	 * @var string|null
	 */
	#[Locked]
	public ?string $policyVersion = null;

	/**
	 * Whether the banner is visible. Set to false once the user accepts
	 * or dismisses (within the grace period).
	 *
	 * @var bool
	 */
	public bool $visible = true;

	/**
	 * Mount the component.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null            $regulation     Regulation override.
	 * @param  string|null            $class          Outer class override.
	 * @param  string|null            $buttonClasses  Button class override.
	 * @param  array<string, string>  $labels         Label overrides.
	 *
	 * @return void
	 */
	public function mount(
		?string $regulation = null,
		?string $class = null,
		?string $buttonClasses = null,
		array $labels = [],
	): void {
		$this->regulation    = $regulation;
		$this->class         = $class ?? '';
		$this->buttonClasses = $buttonClasses ?? '';
		$this->labels        = $labels;

		$policy              = $this->resolvePolicy();
		$this->policyVersion = $policy?->version;
		$this->visible       = null !== $policy;
	}

	/**
	 * Records the current actor's re-consent.
	 *
	 * Compares the policy version captured at mount time against the
	 * version that is currently active and refuses the grant if they
	 * differ — that way the user can never accidentally accept a newer
	 * policy that was republished after the banner was rendered but
	 * before they clicked. When the versions diverge, the banner
	 * resets so the visitor sees the new policy on the next render.
	 *
	 * @since 1.0.0
	 *
	 * @param  ReconsentService  $reconsent Injected service.
	 *
	 * @return void
	 */
	public function accept( ReconsentService $reconsent ): void
	{
		$current = $reconsent->currentPolicy( $this->regulation );
		$user    = Auth::user();

		if ( null === $current || null === $user ) {
			$this->visible = false;
			return;
		}

		if ( $this->policyVersion !== $current->version ) {
			// Policy was republished between mount and click. Re-render so
			// the user sees the new version and consents to that instead.
			$this->policyVersion = $current->version;
			$this->visible       = true;

			$this->dispatch( 'privacy:reconsent-stale', version: $current->version );

			return;
		}

		$reconsent->grant( $current, $user, request() );

		$this->visible = false;

		$this->dispatch( 'privacy:reconsent-given', version: $current->version );
	}

	/**
	 * Dismisses the banner for the current request only — used while the
	 * grace period is still open so the user can finish what they were
	 * doing before re-consenting.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function dismiss(): void
	{
		$this->visible = false;

		$this->dispatch( 'privacy:reconsent-dismissed' );
	}

	/**
	 * Returns the active policy that triggered the prompt, or null when no
	 * re-consent is currently required.
	 *
	 * @since 1.0.0
	 *
	 * @return PrivacyPolicy|null
	 */
	#[Computed]
	public function policy(): ?PrivacyPolicy
	{
		return $this->resolvePolicy();
	}

	/**
	 * Renders the component view.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'privacy::livewire.policy-reconsent-banner' );
	}

	/**
	 * Resolves the active policy through {@see ReconsentService}.
	 *
	 * @since 1.0.0
	 *
	 * @return PrivacyPolicy|null
	 */
	protected function resolvePolicy(): ?PrivacyPolicy
	{
		$reconsent = app( ReconsentService::class );

		$policy = $reconsent->currentPolicy( $this->regulation );

		if ( null === $policy || $reconsent->isUpToDate( Auth::user(), $this->regulation ) ) {
			return null;
		}

		return $policy;
	}
}
