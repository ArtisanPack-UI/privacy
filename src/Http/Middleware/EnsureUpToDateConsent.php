<?php

/**
 * EnsureUpToDateConsent middleware — flags out-of-date consent and, once
 * the grace period elapses, blocks the request.
 *
 * Pairs with {@see \ArtisanPackUI\Privacy\Services\ReconsentService} to
 * detect when the active policy version no longer matches the consent
 * recorded for the request's subject. Sets a `privacy_reconsent_required`
 * request attribute (and a matching view variable) so the reconsent banner
 * Livewire/React/Vue components can render. Returns a redirect or 403
 * response once the grace period has elapsed.
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

use ArtisanPackUI\Privacy\Services\ReconsentService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard that flags / blocks subjects with out-of-date consent.
 *
 * @since 1.0.0
 */
class EnsureUpToDateConsent
{
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

		if ( null === $policy || true !== (bool) $policy->requires_reconsent ) {
			return $next( $request );
		}

		if ( $this->reconsent->isUpToDate() ) {
			return $next( $request );
		}

		$request->attributes->set( 'privacy_reconsent_required', true );
		$request->attributes->set( 'privacy_reconsent_policy', $policy );

		View::share( 'privacyReconsentRequired', true );
		View::share( 'privacyReconsentPolicy', $policy );

		$this->reconsent->notifyRequired( $policy, null, $request );

		if ( $this->reconsent->isBlocked() ) {
			return $this->buildBlockedResponse( $request );
		}

		return $next( $request );
	}

	/**
	 * Builds the response served when the grace period has elapsed and
	 * `block_on_no_reconsent` is enabled.
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

		if ( is_string( $route ) && '' !== $route ) {
			$url = (string) route( $route );

			if ( ! str_starts_with( (string) $request->path(), trim( parse_url( $url, PHP_URL_PATH ) ?: '/', '/' ) ) ) {
				return redirect()->to( $url );
			}
		}

		abort( $status, (string) ( $config['message'] ?? 'You must accept the updated privacy policy to continue.' ) );
	}
}
