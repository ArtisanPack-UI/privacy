<?php

/**
 * EnsureUpToDateConsent middleware — flags out-of-date consent and, once
 * the grace period elapses, blocks the request.
 *
 * Pairs with {@see \ArtisanPackUI\Privacy\Services\ReconsentService} to
 * detect when the active policy version no longer matches the consent
 * recorded for the request's subject. Sets a `privacy_reconsent_required`
 * request attribute so the reconsent banner Livewire/React/Vue
 * components can render. Returns a redirect or 403 response once the
 * grace period has elapsed.
 *
 * The middleware does not call {@see \Illuminate\Support\Facades\View::share()}
 * — that helper writes to a process-global bag that would leak between
 * requests on Octane/Swoole/RoadRunner. The request attribute is
 * per-request and safe; consumers can read it via
 * `request()->attributes->get('privacy_reconsent_required')` in their
 * layouts.
 *
 * Usage:
 *
 *   Route::middleware('privacy.reconsent')->group(...);
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Http\Middleware;

use ArtisanPackUI\Privacy\Models\PrivacyPolicy;
use ArtisanPackUI\Privacy\Services\ReconsentService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route as RouteFacade;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard that flags / blocks subjects with out-of-date consent.
 *
 * @since 1.0.0
 */
class EnsureUpToDateConsent
{
	/**
	 * Session key used to dedupe the `PolicyReconsentRequired` event so
	 * listeners (notifications, audit log) fire at most once per
	 * session per policy version instead of once per request.
	 *
	 * @var string
	 */
	protected const NOTIFIED_SESSION_KEY = 'privacy.reconsent.notified_for';

	/**
	 * Route names that should never trigger the block — even when the
	 * subject is out-of-date and the grace period has elapsed — because
	 * the visitor needs to reach them to actually re-consent.
	 *
	 * @var array<int, string>
	 */
	protected const BYPASS_ROUTE_NAMES = [
		'privacy.policy.show',
		'privacy.policy.show-version',
		'privacy.policy.reconsent',
	];

	/**
	 * @param ReconsentService $reconsent Service used to check policy alignment.
	 */
	public function __construct( protected ReconsentService $reconsent )
	{
	}

	/**
	 * Handle an incoming request.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request Incoming request.
	 * @param  Closure  $next    Next middleware.
	 *
	 * @return Response
	 */
	public function handle( Request $request, Closure $next ): Response
	{
		$policy = $this->reconsent->currentPolicy();

		if ( ! $policy instanceof PrivacyPolicy || true !== (bool) $policy->requires_reconsent ) {
			return $next( $request );
		}

		if ( $this->reconsent->isUpToDate() ) {
			return $next( $request );
		}

		$request->attributes->set( 'privacy_reconsent_required', true );
		$request->attributes->set( 'privacy_reconsent_policy', $policy );

		$this->notifyOnce( $request, $policy );

		if ( $this->reconsent->isBlocked() && ! $this->isBypassRoute( $request ) ) {
			return $this->buildBlockedResponse( $request );
		}

		return $next( $request );
	}

	/**
	 * Fires `PolicyReconsentRequired` at most once per session per policy
	 * version so listeners (logging, notifications) aren't woken on every
	 * page load while the user is still inside the grace period.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request        $request Incoming request.
	 * @param  PrivacyPolicy  $policy  Policy that triggered the requirement.
	 *
	 * @return void
	 */
	protected function notifyOnce( Request $request, PrivacyPolicy $policy ): void
	{
		$session = $request->hasSession() ? $request->session() : null;

		if ( null === $session ) {
			$this->reconsent->notifyRequired( $policy, null, $request );

			return;
		}

		$signature = (string) $policy->getKey() . ':' . (string) $policy->version;

		if ( $session->get( self::NOTIFIED_SESSION_KEY ) === $signature ) {
			return;
		}

		$this->reconsent->notifyRequired( $policy, null, $request );
		$session->put( self::NOTIFIED_SESSION_KEY, $signature );
	}

	/**
	 * Returns true when the current request targets a route the visitor
	 * needs to read or POST to in order to re-consent. The block check
	 * skips these routes so the user always has a path forward.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request Incoming request.
	 *
	 * @return bool
	 */
	protected function isBypassRoute( Request $request ): bool
	{
		$name = $request->route()?->getName();

		if ( null === $name ) {
			return false;
		}

		$bypass = (array) config(
			'artisanpack.privacy.policy.bypass_routes',
			self::BYPASS_ROUTE_NAMES,
		);

		return in_array( $name, $bypass, true );
	}

	/**
	 * Builds the response served when the grace period has elapsed and
	 * `block_on_no_reconsent` is enabled. Falls back to the configured
	 * abort status when the redirect target can't be resolved (e.g. the
	 * consumer disabled the package's built-in routes).
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request Incoming request.
	 *
	 * @return Response
	 */
	protected function buildBlockedResponse( Request $request ): Response
	{
		$config = (array) config( 'artisanpack.privacy.policy.blocked_response', [] );
		$status = (int) ( $config['status'] ?? 403 );
		$route  = $config['redirect_route'] ?? 'privacy.policy.show';

		if ( is_string( $route ) && '' !== $route && RouteFacade::has( $route ) ) {
			try {
				return redirect()->to( (string) route( $route ) );
			} catch ( UrlGenerationException | InvalidArgumentException ) {
				// Fall through to abort below.
			}
		}

		abort( $status, (string) ( $config['message'] ?? 'You must accept the updated privacy policy to continue.' ) );
	}
}
