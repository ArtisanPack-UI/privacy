<?php

/**
 * ConsentApiController — JSON endpoints for reading and updating consent.
 *
 * Powers the package's React, Vue, and vanilla-JS clients. Authenticated
 * requests are persisted to the database (when the configured storage
 * allows it); guest requests fall back to the consent cookie.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Http\Controllers\Api;

use ArtisanPackUI\Privacy\Http\Requests\UpdateConsentRequest;
use ArtisanPackUI\Privacy\Services\ConsentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * RESTful consent controller.
 *
 * Routes registered by {@see \ArtisanPackUI\Privacy\PrivacyServiceProvider}
 * under the `artisanpack.privacy.routes.api_prefix` prefix.
 *
 * @since 1.0.0
 */
class ConsentApiController extends Controller
{
	/**
	 * @param ConsentService $consents Consent service.
	 */
	public function __construct( protected ConsentService $consents )
	{
	}

	/**
	 * GET /api/privacy/consent — returns the current consent map and regulation.
	 *
	 * Output shape:
	 *   { "regulation": "gdpr"|null,
	 *     "consents":   { "<category>": true|false, ... },
	 *     "categories": { "<category>": {...config...}, ... } }
	 *
	 * @since 1.0.0
	 *
	 * @return JsonResponse
	 */
	public function show(): JsonResponse
	{
		$categories = (array) config( 'artisanpack.privacy.cookie_categories', [] );
		$subject    = $this->resolveSubject();
		$map        = [];

		foreach ( $categories as $category => $config ) {
			$map[ $category ] = true === ( $config['required'] ?? false )
				|| $this->consents->hasConsent( (string) $category, $subject );
		}

		// For guests with no DB rows, fall back to whatever the cookie says
		// so the UI can render the visitor's prior selections accurately.
		if ( null === $subject ) {
			$cookie = $this->consents->getConsentCookie() ?? [];

			foreach ( $cookie as $category => $granted ) {
				if ( is_string( $category ) && array_key_exists( $category, $map ) ) {
					$map[ $category ] = (bool) $granted
						|| true === ( $categories[ $category ]['required'] ?? false );
				}
			}
		}

		return response()->json( [
			'regulation' => $this->consents->getApplicableRegulation(),
			'consents'   => $map,
			'categories' => $categories,
		] );
	}

	/**
	 * POST /api/privacy/consent — applies one or many consent changes.
	 *
	 * Required categories are forced to `true` regardless of the payload
	 * to honour the "strictly necessary" contract. The endpoint returns
	 * the post-write consent map (same shape as {@see show()}).
	 *
	 * @since 1.0.0
	 *
	 * @param  UpdateConsentRequest  $request Validated request.
	 *
	 * @return JsonResponse
	 */
	public function update( UpdateConsentRequest $request ): JsonResponse
	{
		$categories = (array) config( 'artisanpack.privacy.cookie_categories', [] );
		$subject    = $this->resolveSubject();
		$incoming   = $request->normalisedConsents();

		foreach ( $incoming as $category => $granted ) {
			if ( ! array_key_exists( $category, $categories ) ) {
				continue;
			}

			$isRequired = true === ( $categories[ $category ]['required'] ?? false );

			if ( $isRequired || true === $granted ) {
				$this->consents->grantConsent( $category, $subject );
				continue;
			}

			$this->consents->revokeConsent( $category, $subject );
		}

		return $this->show();
	}

	/**
	 * Resolves the current authenticated user as a {@see Model}, or null
	 * when the request is from a guest.
	 *
	 * @since 1.0.0
	 *
	 * @return Model|null
	 */
	protected function resolveSubject(): ?Model
	{
		$user = Auth::user();

		return $user instanceof Model ? $user : null;
	}
}
