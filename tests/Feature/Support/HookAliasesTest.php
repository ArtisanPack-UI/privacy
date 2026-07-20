<?php

declare( strict_types=1 );

it( 'fires legacy subscribers via alias', function ( string $oldName, string $newName ): void {
    $ran = false;

    addFilter( $oldName, function ( $value ) use ( &$ran ) {
        $ran = true;
        return $value;
    } );

    applyFilters( $newName, 'seed' );

    removeAllFilters( $oldName );
    removeAllFilters( $newName );

    expect( $ran )->toBeTrue();
} )->with( [
    [ 'privacy.export.data',        'ap.privacy.exportData' ],
    [ 'privacy.export-data',        'ap.privacy.exportData' ],
    [ 'privacy.export-formats',     'ap.privacy.exportFormats' ],
    [ 'privacy.export-format',      'ap.privacy.formatExport' ],
    [ 'privacy.delete-data',        'ap.privacy.deleteData' ],
    [ 'privacy.anonymize-data',     'ap.privacy.anonymizeData' ],
    [ 'privacy.has-data',           'ap.privacy.hasData' ],
    [ 'privacy.data-summary',       'ap.privacy.dataSummary' ],
    [ 'privacy.consent-granted',    'ap.privacy.consentGranted' ],
    [ 'privacy.consent-revoked',    'ap.privacy.consentRevoked' ],
    [ 'privacy.consent-status',     'ap.privacy.consentStatus' ],
    [ 'privacy.consent-categories', 'ap.privacy.consentCategories' ],
] );
