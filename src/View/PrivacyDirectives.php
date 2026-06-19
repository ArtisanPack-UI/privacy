<?php

/**
 * PrivacyDirectives — shared callable behind the package's Blade directives.
 *
 * Keeps the Blade compile result side-effect-free: each directive compiles
 * to a static call into this class, so cached views never need to resolve
 * a service from the container.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\View;

use ArtisanPackUI\Privacy\Services\ConsentService;
use Throwable;

/**
 * Static helpers consumed by `@hasConsent` and `@consentRequired`.
 *
 * @since 1.0.0
 */
class PrivacyDirectives
{
	/**
	 * Returns true when the current subject has an active consent for the
	 * given category. Swallows resolution errors so cached views never blow
	 * up because a misconfigured app forgot to register the service.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $category Consent category key.
	 *
	 * @return bool
	 */
	public static function hasConsent( string $category ): bool
	{
		try {
			return app( ConsentService::class )->hasConsent( $category );
		} catch ( Throwable $e ) {
			// Fall back to "no consent" so cached views never blow up, but
			// surface the underlying failure to the application's reporter
			// so operators can see when the directive layer is failing.
			report( $e );

			return false;
		}
	}
}
