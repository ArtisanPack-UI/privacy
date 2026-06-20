<?php

/**
 * PrivacyPolicyController — public-facing privacy policy display.
 *
 * Handles the `GET /{prefix}/policy` and `GET /{prefix}/policy/{version}`
 * routes registered by the package: resolves the active policy (or a
 * specific version), renders its Markdown body as HTML, exposes the
 * structured table of contents, and feeds the print-friendly Blade view.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Http\Controllers;

use ArtisanPackUI\Privacy\Models\PrivacyPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Routes public privacy policy views.
 *
 * @since 1.0.0
 */
class PrivacyPolicyController
{
	/**
	 * Shows the active policy for the resolved regulation/locale. When no
	 * policy matches the resolved regulation, falls back to the general
	 * (regulation = NULL) policy so the route always renders something
	 * meaningful.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request Incoming request (carries `?locale`).
	 *
	 * @throws NotFoundHttpException When no policy has ever been published.
	 *
	 * @return View
	 */
	public function show( Request $request ): View
	{
		$locale = $this->resolveLocale( $request );
		$policy = $this->resolveActivePolicy( $request, $locale );

		if ( ! $policy instanceof PrivacyPolicy ) {
			throw new NotFoundHttpException( 'No active privacy policy has been published.' );
		}

		return $this->renderPolicy( $policy, $locale );
	}

	/**
	 * Shows a specific historical policy version. Locale is honoured so
	 * `/policy/1.2.0?locale=fr` resolves the same version in another
	 * locale when one exists.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request Incoming request.
	 * @param  string   $version Version string.
	 *
	 * @throws NotFoundHttpException When the version is unknown.
	 *
	 * @return View
	 */
	public function showVersion( Request $request, string $version ): View
	{
		$locale = $this->resolveLocale( $request );

		$policy = PrivacyPolicy::query()
			->forVersion( $version )
			->forLocale( $locale )
			->latestFirst()
			->first();

		if ( ! $policy instanceof PrivacyPolicy ) {
			$policy = PrivacyPolicy::query()
				->forVersion( $version )
				->latestFirst()
				->first();
		}

		if ( ! $policy instanceof PrivacyPolicy ) {
			throw new NotFoundHttpException(
				"No privacy policy with version [{$version}] exists.",
			);
		}

		return $this->renderPolicy( $policy, $locale );
	}

	/**
	 * Builds the shared view payload — kept here so {@see show()} and
	 * {@see showVersion()} stay aligned.
	 *
	 * @since 1.0.0
	 *
	 * @param  PrivacyPolicy  $policy  Resolved policy.
	 * @param  string         $locale  Resolved locale.
	 *
	 * @return View
	 */
	protected function renderPolicy( PrivacyPolicy $policy, string $locale ): View
	{
		$history = PrivacyPolicy::query()
			->forRegulation( $policy->regulation )
			->forLocale( $policy->locale )
			->whereNotNull( 'published_at' )
			->latestFirst()
			->get();

		$availableLocales = PrivacyPolicy::query()
			->forRegulation( $policy->regulation )
			->whereNotNull( 'published_at' )
			->select( 'locale' )
			->distinct()
			->pluck( 'locale' )
			->all();

		return view( 'privacy::policy.show', [
			'policy'           => $policy,
			'html'             => $policy->renderHtml(),
			'sections'         => $policy->tableOfContents(),
			'history'          => $history,
			'availableLocales' => $availableLocales,
			'locale'           => $locale,
		] );
	}

	/**
	 * Resolves the active policy by stepping through three preferences:
	 * the regulation matching the request, then a general policy, then
	 * any active policy for the requested locale.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request Incoming request.
	 * @param  string   $locale  Resolved locale.
	 *
	 * @return PrivacyPolicy|null
	 */
	protected function resolveActivePolicy( Request $request, string $locale ): ?PrivacyPolicy
	{
		$regulation = $this->resolveRegulation( $request );

		$query = PrivacyPolicy::query()->active()->forLocale( $locale )->latestFirst();

		if ( null !== $regulation ) {
			$match = ( clone $query )->forRegulation( $regulation )->first();

			if ( $match instanceof PrivacyPolicy ) {
				return $match;
			}
		}

		$general = ( clone $query )->forRegulation( null )->first();

		if ( $general instanceof PrivacyPolicy ) {
			return $general;
		}

		return $query->first();
	}

	/**
	 * Resolves the regulation token applicable to the current request.
	 * Honours the `?regulation` query string for previewing locales of
	 * other regulations, falling back to the geolocated regulation.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request Incoming request.
	 *
	 * @return string|null
	 */
	protected function resolveRegulation( Request $request ): ?string
	{
		$override = $request->query( 'regulation' );

		if ( is_string( $override ) && '' !== $override ) {
			return strtolower( $override );
		}

		$attribute = $request->attributes->get( 'privacy_regulation' );

		if ( is_string( $attribute ) && '' !== $attribute ) {
			return strtolower( $attribute );
		}

		return null;
	}

	/**
	 * Resolves the requested locale from the query string, falling back
	 * to the app locale.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request Incoming request.
	 *
	 * @return string
	 */
	protected function resolveLocale( Request $request ): string
	{
		$query = $request->query( 'locale' );

		if ( is_string( $query ) && '' !== $query ) {
			return $query;
		}

		return (string) ( config( 'app.locale' ) ?? 'en' );
	}
}
