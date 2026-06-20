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
		// Resolve the regulation in a way that honours both regulation-tagged
		// and general (NULL) policies — callers that don't know the visitor's
		// applicable regulation should still find the active GDPR/CCPA/LGPD/
		// PIPEDA row instead of only matching `regulation IS NULL`.
		$base = PrivacyPolicy::query()->active()->latestFirst();

		if ( is_string( $regulation ) && '' !== $regulation ) {
			$specific = $this->firstForLocale(
				( clone $base )->forRegulation( $regulation ),
				$locale,
			);

			if ( $specific instanceof PrivacyPolicy ) {
				return $specific;
			}
		}

		$general = $this->firstForLocale(
			( clone $base )->forRegulation( null ),
			$locale,
		);

		if ( $general instanceof PrivacyPolicy ) {
			return $general;
		}

		return $this->firstForLocale( $base, $locale );
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
			// Guests have no stored policy_version; treat them as up-to-date
			// here and let the cookie banner / first-touch flow capture the
			// policy version when they make their initial choice.
			return true;
		}

		$consents = Consent::query()
			->where( 'consentable_type', $subject->getMorphClass() )
			->where( 'consentable_id', $subject->getKey() );

		if ( null !== $policy->regulation ) {
			$consents->where( function ( $query ) use ( $policy ): void {
				$query->where( 'regulation', $policy->regulation )
					->orWhereNull( 'regulation' );
			} );
		}

		$latest = $consents->orderByDesc( 'created_at' )->first();

		if ( ! $latest instanceof Consent ) {
			// A subject that has never consented is not "out of date" — they
			// have never been asked. The cookie banner is responsible for
			// capturing initial consent; we only fire re-consent for
			// subjects who actually have a stored consent on an older
			// policy version.
			return true;
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

		// Scope the update to consents that actually belong to this policy's
		// regulation (general policy = NULL regulation also matches NULL or
		// any regulation row), and skip withdrawn rows so a re-consent
		// doesn't accidentally re-stamp opted-out categories with the new
		// policy version.
		$consents = Consent::query()
			->where( 'consentable_type', $subject->getMorphClass() )
			->where( 'consentable_id', $subject->getKey() )
			->whereNull( 'withdrawn_at' );

		if ( null !== $policy->regulation ) {
			$consents->where( function ( $query ) use ( $policy ): void {
				$query->where( 'regulation', $policy->regulation )
					->orWhereNull( 'regulation' );
			} );
		}

		$updated = $consents->update( [
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
	 * Returns the first matching policy for the given locale, falling back
	 * to the locale-agnostic query when no localised row exists.
	 *
	 * @since 1.0.0
	 *
	 * @param  \Illuminate\Database\Eloquent\Builder  $query  Pre-built query.
	 * @param  string|null                            $locale Optional locale.
	 *
	 * @return PrivacyPolicy|null
	 */
	protected function firstForLocale( $query, ?string $locale ): ?PrivacyPolicy
	{
		if ( is_string( $locale ) && '' !== $locale ) {
			$localised = ( clone $query )->forLocale( $locale )->first();

			if ( $localised instanceof PrivacyPolicy ) {
				return $localised;
			}
		}

		return $query->first();
	}
}
