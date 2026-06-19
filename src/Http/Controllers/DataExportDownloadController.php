<?php

/**
 * DataExportDownloadController — streams a previously generated export file
 * to the data subject via Laravel's temporary signed URLs.
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

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Single-action controller that validates the signed link, checks the
 * sidecar expiry marker, and streams the file from the configured disk.
 *
 * @since 1.0.0
 */
class DataExportDownloadController extends Controller
{
	/**
	 * Handle the download request.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request Incoming request.
	 * @param  string   $path    Stored file path (passed via signed URL).
	 *
	 * @return StreamedResponse
	 */
	public function __invoke( Request $request, string $path ): StreamedResponse
	{
		abort_unless( $request->hasValidSignature(), 403, 'Invalid or expired download link.' );

		$directory = trim( (string) config( 'artisanpack.privacy.export.directory', 'privacy-exports' ), '/' );

		abort_if(
			str_contains( $path, '..' ) || ! str_starts_with( $path, $directory . '/' ),
			403,
			'Refusing to serve a path outside the configured export directory.',
		);

		$disk = (string) config( 'artisanpack.privacy.export.disk', 'local' );

		abort_unless( Storage::disk( $disk )->exists( $path ), 404 );

		if ( Storage::disk( $disk )->exists( $path . '.expires' ) ) {
			$expiresAt = (string) Storage::disk( $disk )->get( $path . '.expires' );

			if ( '' !== $expiresAt && Carbon::parse( $expiresAt )->isPast() ) {
				abort( 410, 'Export file has expired.' );
			}
		}

		return Storage::disk( $disk )->download( $path, basename( $path ) );
	}
}
