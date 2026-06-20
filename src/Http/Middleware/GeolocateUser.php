<?php

/**
 * GeolocateUser middleware — resolves a region for the incoming request.
 *
 * Delegates to {@see GeoLocationService::resolveRegion()} and stashes the
 * resulting region on the request as `privacy_region`. The base regulation
 * class checks that attribute first when deciding whether to apply, so
 * routes guarded by this middleware get accurate regulation matching for
 * free.
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

use ArtisanPackUI\Privacy\Services\GeoLocationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attach the resolved region to the request.
 *
 * @since 1.0.0
 */
class GeolocateUser
{
	/**
	 * @param  GeoLocationService  $geo  Geolocation service.
	 */
	public function __construct( protected GeoLocationService $geo )
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
		if ( ! $request->attributes->has( 'privacy_region' ) ) {
			$region = $this->geo->resolveRegion( $request );

			if ( null !== $region ) {
				$request->attributes->set( 'privacy_region', $region );
			}
		}

		return $next( $request );
	}
}
