<?php

/**
 * ReconsentService — detects out-of-date consent and orchestrates the
 * re-consent workflow.
 *
 * Compares the most recent active policy that flips
 * `requires_reconsent = true` against the policy version a subject has
 * already consented under (recorded on `privacy_consents.policy_version`).
 * Exposes helpers to grant re-consent, record the event, and apply the
 * configured grace period before the application blocks the user.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Services;

use ArtisanPackUI\Privacy\Events\PolicyReconsentGiven;
use ArtisanPackUI\Privacy\Events\PolicyReconsentRequired;
use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Models\PrivacyPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

/**
 * Coordinates re-consent comparisons and updates.
 *
 * @since 1.0.0
 */
class ReconsentService
{
	/**
	 * @param ConsentService $consents Inner consent service used to read state.
	 */
	public function __construct( protected ConsentService $consents )
	{
	}

	/**
	 * Returns the active policy that the application should be checking
	 * consent against, or null when no policy has been published.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null  $regulation Regulation key (matches NULL for general).
	 * @param  string|null  $locale     Optional locale to prefer.
	 *
	 * @return PrivacyPolicy|null
	 */
	public function currentPolicy( ?string $regulation = null, ?string $locale = null ): ?PrivacyPolicy
	{
		$query = PrivacyPolicy::query()
			->active()
			->forRegulation( $regulation )
			->latestFirst();

		if ( is_string( $locale ) && '' !== $locale ) {
			$localised = ( clone $query )->forLocale( $locale )->first();

			if ( $localised instanceof PrivacyPolicy ) {
				return $localised;
			}
		}

		return $query->first();
	}

	/**
	 * Returns true when the subject's consent records are still aligned
	 * with the current active policy (or no current policy exists).
	 *
	 * @since 1.0.0
	 *
	 * @param  Model|null   $user       Subject, or null for the current actor.
	 * @param  string|null  $regulation Regulation override.
	 *
	 * @return bool
	 */
	public function isUpToDate( ?Model $user = null, ?string $regulation = null ): bool
	{
		$policy = $this->currentPolicy( $regulation );

		if ( ! $policy instanceof PrivacyPolicy || true !== (bool) $policy->requires_reconsent ) {
			return true;
		}

		$subject = $user ?? Auth::user();

		if ( ! $subject instanceof Model ) {
			return $this->cookieVersionMatches( $policy );
		}

		$latest = Consent::query()
			->where( 'consentable_type', $subject->getMorphClass() )
			->where( 'consentable_id', $subject->getKey() )
			->orderByDesc( 'created_at' )
			->first();

		if ( ! $latest instanceof Consent ) {
			return false;
		}

		return $latest->policy_version === $policy->version;
	}

	/**
	 * Returns true when a subject needs to re-consent AND is no longer
	 * within the configured grace period.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model|null   $user       Subject, or null for the current actor.
	 * @param  string|null  $regulation Regulation override.
	 *
	 * @return bool
	 */
	public function isBlocked( ?Model $user = null, ?string $regulation = null ): bool
	{
		if ( true !== (bool) config( 'artisanpack.privacy.policy.block_on_no_reconsent', false ) ) {
			return false;
		}

		if ( $this->isUpToDate( $user, $regulation ) ) {
			return false;
		}

		$policy = $this->currentPolicy( $regulation );

		if ( ! $policy instanceof PrivacyPolicy ) {
			return false;
		}

		$gracePeriodDays = (int) config( 'artisanpack.privacy.policy.reconsent_grace_period_days', 30 );

		if ( $gracePeriodDays <= 0 ) {
			return true;
		}

		$reference = $policy->published_at instanceof Carbon
			? $policy->published_at->copy()
			: Carbon::now();

		return Carbon::now()->greaterThan( $reference->addDays( $gracePeriodDays ) );
	}

	/**
	 * Records the subject's re-consent to the supplied policy. Updates
	 * every active consent record's `policy_version` so subsequent
	 * comparisons return true.
	 *
	 * @since 1.0.0
	 *
	 * @param  PrivacyPolicy  $policy  Policy the subject just consented to.
	 * @param  Model|null     $user    Subject; defaults to the current actor.
	 * @param  Request|null   $request Source request for capture metadata.
	 *
	 * @return int Number of consent rows updated.
	 */
	public function grant( PrivacyPolicy $policy, ?Model $user = null, ?Request $request = null ): int
	{
		$subject = $user ?? Auth::user();

		if ( ! $subject instanceof Model ) {
			return 0;
		}

		$updated = Consent::query()
			->where( 'consentable_type', $subject->getMorphClass() )
			->where( 'consentable_id', $subject->getKey() )
			->update( [
				'policy_version' => $policy->version,
				'updated_at'     => Carbon::now(),
			] );

		Event::dispatch( new PolicyReconsentGiven( $policy, $subject, $request ) );

		return (int) $updated;
	}

	/**
	 * Fires the "reconsent required" event so listeners (notifications,
	 * audit log) can react. Idempotent — safe to call from middleware.
	 *
	 * @since 1.0.0
	 *
	 * @param  PrivacyPolicy  $policy  Policy that triggered the requirement.
	 * @param  Model|null     $user    Subject; defaults to the current actor.
	 * @param  Request|null   $request Originating request.
	 *
	 * @return void
	 */
	public function notifyRequired( PrivacyPolicy $policy, ?Model $user = null, ?Request $request = null ): void
	{
		$subject = $user ?? Auth::user();

		Event::dispatch( new PolicyReconsentRequired( $policy, $subject, $request ) );
	}

	/**
	 * Compares the cookie-stored policy version against the active one.
	 * Used as the fallback for guests with no database identity.
	 *
	 * @since 1.0.0
	 *
	 * @param  PrivacyPolicy  $policy Active policy.
	 *
	 * @return bool
	 */
	protected function cookieVersionMatches( PrivacyPolicy $policy ): bool
	{
		$cookie = $this->consents->getConsentCookie();

		if ( ! is_array( $cookie ) || ! isset( $cookie['_policy_version'] ) ) {
			return false;
		}

		return (string) $cookie['_policy_version'] === $policy->version;
	}
}
