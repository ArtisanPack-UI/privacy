<?php

/**
 * Privacy helper functions.
 *
 * Global wrappers around the service layer so application code can perform
 * common privacy operations without resolving services from the container.
 * Every helper delegates to a registered service — there is no business
 * logic in this file.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */

use ArtisanPackUI\Privacy\Contracts\PrivacyRegulation;
use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Privacy;
use ArtisanPackUI\Privacy\Regulations\RegulationRegistry;
use ArtisanPackUI\Privacy\Services\AnonymizationService;
use ArtisanPackUI\Privacy\Services\ConsentService;
use ArtisanPackUI\Privacy\Services\DataRequestService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

if ( ! function_exists( 'privacy' ) ) {
	/**
	 * Get the Privacy instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Privacy
	 */
	function privacy(): Privacy
	{
		return app( 'privacy' );
	}
}

if ( ! function_exists( 'privacyHasConsent' ) ) {
	/**
	 * Returns true when the current authenticated/guest subject has an active
	 * consent for the given category.
	 *
	 * @since 1.0.0
	 *
	 * @param  string      $category Consent category key.
	 * @param  Model|null  $user     Optional subject override.
	 *
	 * @return bool
	 */
	function privacyHasConsent( string $category, ?Model $user = null ): bool
	{
		return app( ConsentService::class )->hasConsent( $category, $user );
	}
}

if ( ! function_exists( 'privacyGetRegulation' ) ) {
	/**
	 * Returns the regulation key applicable to the current request, or null.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	function privacyGetRegulation(): ?string
	{
		return app( ConsentService::class )->getApplicableRegulation();
	}
}

if ( ! function_exists( 'privacyRegulations' ) ) {
	/**
	 * Returns the regulation registry.
	 *
	 * @since 1.0.0
	 *
	 * @return RegulationRegistry
	 */
	function privacyRegulations(): RegulationRegistry
	{
		return app( RegulationRegistry::class );
	}
}

if ( ! function_exists( 'getApplicableRegulation' ) ) {
	/**
	 * Returns the highest-priority {@see PrivacyRegulation} that applies to
	 * the supplied (or current) request, or null when none apply.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request|null  $request  Request to evaluate. Defaults to the current request.
	 *
	 * @return PrivacyRegulation|null
	 */
	function getApplicableRegulation( ?Request $request = null ): ?PrivacyRegulation
	{
		return app( RegulationRegistry::class )->applicableFor( $request );
	}
}

if ( ! function_exists( 'privacyRequestDataExport' ) ) {
	/**
	 * Files an export request for the given subject and returns the persisted row.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model        $user   Subject the request is filed for.
	 * @param  string|null  $reason Optional human-readable reason.
	 *
	 * @return DataRequest
	 */
	function privacyRequestDataExport( Model $user, ?string $reason = null ): DataRequest
	{
		return app( DataRequestService::class )->createExportRequest( $user, $reason );
	}
}

if ( ! function_exists( 'privacyRequestDataDeletion' ) ) {
	/**
	 * Files a deletion request for the given subject and returns the persisted row.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model        $user   Subject the request is filed for.
	 * @param  string|null  $reason Optional human-readable reason.
	 *
	 * @return DataRequest
	 */
	function privacyRequestDataDeletion( Model $user, ?string $reason = null ): DataRequest
	{
		return app( DataRequestService::class )->createDeletionRequest( $user, $reason );
	}
}

if ( ! function_exists( 'privacyAnonymize' ) ) {
	/**
	 * Applies configured anonymization strategies to the model in place.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $model Model to anonymize.
	 *
	 * @return bool True when at least one field was mutated and persisted.
	 */
	function privacyAnonymize( Model $model ): bool
	{
		return app( AnonymizationService::class )->anonymize( $model );
	}
}

if ( ! function_exists( 'privacyCanDelete' ) ) {
	/**
	 * Returns true when the subject has no in-flight deletion request blocking deletion.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $user Subject to check.
	 *
	 * @return bool
	 */
	function privacyCanDelete( Model $user ): bool
	{
		return app( DataRequestService::class )->canDelete( $user );
	}
}
