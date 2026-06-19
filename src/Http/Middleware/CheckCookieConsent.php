<?php

/**
 * CheckCookieConsent middleware — exposes the consent map to views and JS.
 *
 * Builds the `category => granted` map for the current request and attaches
 * it to the request attributes as `privacy_consent`. A global view composer
 * registered in {@see \ArtisanPackUI\Privacy\PrivacyServiceProvider} reads
 * that attribute and exposes the same map to every view as
 * `$privacyConsent`. Sharing through the request keeps the state strictly
 * per-request, so Octane / queue workers cannot leak one visitor's map into
 * another's response.
 *
 * The middleware never resolves a database row when the cookie has the
 * answer — that's the cheap path the issue calls for.
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injects the consent state into the view layer.
 *
 * @since 1.0.0
 */
class CheckCookieConsent
{
	/**
	 * @param ConsentService $consents Consent service used to resolve state.
	 */
	public function __construct( protected ConsentService $consents )
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
		$request->attributes->set( 'privacy_consent', $this->resolveConsentMap() );

		return $next( $request );
	}

	/**
	 * Build the category => granted map for the current request, preferring
	 * the cookie so we avoid a DB hit when an answer is already present, and
	 * batching the DB lookup into a single query when we have to fall back.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, bool>
	 */
	protected function resolveConsentMap(): array
	{
		$categories = (array) config( 'artisanpack.privacy.cookie_categories', [] );
		$cookie     = $this->consents->getConsentCookie() ?? [];
		$user       = Auth::user();
		$map        = [];
		$missing    = [];

		foreach ( $categories as $category => $config ) {
			$key      = (string) $category;
			$required = true === ( $config['required'] ?? false );

			if ( $required ) {
				$map[ $key ] = true;
				continue;
			}

			if ( array_key_exists( $key, $cookie ) ) {
				$normalized = filter_var(
					$cookie[ $key ],
					FILTER_VALIDATE_BOOLEAN,
					FILTER_NULL_ON_FAILURE,
				);

				$map[ $key ] = true === $normalized;
				continue;
			}

			$missing[] = $key;
		}

		if ( [] !== $missing && $user instanceof Model ) {
			$granted = $this->consents->getConsents( $user )
				->whereIn( 'category', $missing )
				->pluck( 'category' )
				->all();

			foreach ( $missing as $key ) {
				$map[ $key ] = in_array( $key, $granted, true );
			}

			return $map;
		}

		foreach ( $missing as $key ) {
			$map[ $key ] = false;
		}

		return $map;
	}
}
