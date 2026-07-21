<?php

/**
 * Registers backwards-compatibility aliases for renamed Privacy hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Support;

/**
 * Registers deprecated hook aliases for the Privacy package so existing
 * subscribers to the legacy `privacy.*` names continue firing while
 * canonical fires migrate to the `ap.privacy.*` camelCase convention.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.1.0
 */
class HookAliases
{
	/**
	 * Registers all legacy → canonical hook aliases via HookDeprecations.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function register(): void
	{
		if ( ! function_exists( 'deprecateHook' ) ) {
			return;
		}

		deprecateHook( 'privacy.export.data',        'ap.privacy.exportData' );
		deprecateHook( 'privacy.export-data',        'ap.privacy.exportData' );
		deprecateHook( 'privacy.export-formats',     'ap.privacy.exportFormats' );
		deprecateHook( 'privacy.export-format',      'ap.privacy.formatExport' );
		deprecateHook( 'privacy.delete-data',        'ap.privacy.deleteData' );
		deprecateHook( 'privacy.anonymize-data',     'ap.privacy.anonymizeData' );
		deprecateHook( 'privacy.has-data',           'ap.privacy.hasData' );
		deprecateHook( 'privacy.data-summary',       'ap.privacy.dataSummary' );
		deprecateHook( 'privacy.consent-granted',    'ap.privacy.consentGranted' );
		deprecateHook( 'privacy.consent-revoked',    'ap.privacy.consentRevoked' );
		deprecateHook( 'privacy.consent-status',     'ap.privacy.consentStatus' );
		deprecateHook( 'privacy.consent-categories', 'ap.privacy.consentCategories' );
	}
}
