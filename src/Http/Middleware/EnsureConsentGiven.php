<?php

/**
 * EnsureConsentGiven middleware — blocks a route until consent is granted.
 *
 * Usage:
 *
 *   Route::middleware('privacy.consent:analytics')->group(...);
 *   Route::middleware('privacy.consent:analytics,marketing')->get(...);
 *
 * When one or more required categories are missing, the middleware either
 * redirects to a configured route (the package default) or aborts with the
 * configured HTTP status. Behaviour is controlled via
 * `artisanpack.privacy.middleware.ensure_consent`.
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

use ArtisanPackUI\Privacy\Services\ConsentService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard that enforces consent for one or more categories.
 *
 * @since 1.0.0
 */
class EnsureConsentGiven
{
	/**
	 * @param ConsentService $consents Consent service used to read state.
	 */
	public function __construct( protected ConsentService $consents )
	{
	}

	/**
	 * Handle an incoming request.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request    Incoming request.
	 * @param  Closure  $next       Next middleware.
	 * @param  string   ...$categories Required consent categories.
	 *
	 * @return Response
	 */
	public function handle( Request $request, Closure $next, string ...$categories ): Response
	{
		$missing = [];

		foreach ( $categories as $category ) {
			if ( ! $this->consents->hasConsent( $category ) ) {
				$missing[] = $category;
			}
		}

		if ( [] === $missing ) {
			return $next( $request );
		}

		$config = (array) config( 'artisanpack.privacy.middleware.ensure_consent', [] );
		$action = (string) ( $config['action'] ?? 'abort' );

		if ( 'redirect' === $action ) {
			$route    = $config['redirect_route'] ?? null;
			$fallback = (string) ( $config['redirect_url'] ?? '/' );

			$target = $fallback;
			if ( null !== $route && RouteFacade::has( (string) $route ) ) {
				$target = route( (string) $route );
			}

			// Flash key intentionally camelCased — Laravel's session() helper
			// resolves through Arr::get which treats dots as path traversal,
			// so a dotted key would be unreachable to downstream consumers.
			return redirect()->to( $target )
				->with( 'privacyConsentRequired', $missing );
		}

		abort(
			(int) ( $config['status'] ?? 403 ),
			(string) ( $config['message'] ?? 'Consent required.' ),
		);
	}
}
