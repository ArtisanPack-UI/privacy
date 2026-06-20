<?php

/**
 * DataRequestVerificationController — handles the user-facing identity
 * verification flow for data subject requests.
 *
 * `show` renders the verification view bundled with the package; `verify`
 * confirms the token and transitions the underlying request from
 * `pending` → `processing`.
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

use ArtisanPackUI\Privacy\Services\VerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Verification controller.
 *
 * @since 1.0.0
 */
class DataRequestVerificationController extends Controller
{
	public function __construct( protected VerificationService $verifier )
	{
	}

	/**
	 * Renders the verification page so the user can confirm their request.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $token Token from the verification link.
	 *
	 * @return View
	 */
	public function show( string $token ): View
	{
		$request = $this->verifier->findByToken( $token );

		// Uniform 404 for both unknown and expired tokens so the page can't
		// be used as an existence oracle (probing valid-but-expired tokens).
		abort_if( null === $request, 404, __( 'This verification link is no longer valid.' ) );

		return view( 'privacy::verification.show', [
			'dataRequest' => $request,
			'token'       => $token,
			'expired'     => $this->verifier->isExpired( $request ),
		] );
	}

	/**
	 * Confirms the token and transitions the request to processing.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request HTTP request (unused but kept for middleware DI).
	 * @param  string   $token   Token from the URL.
	 *
	 * @return RedirectResponse
	 */
	public function verify( Request $request, string $token ): RedirectResponse
	{
		$dataRequest = $this->verifier->findByToken( $token );

		// Uniform 404 for both unknown and expired tokens so the endpoint
		// can't be used as an existence oracle.
		abort_if( null === $dataRequest, 404, __( 'This verification link is no longer valid.' ) );

		if ( $this->verifier->isExpired( $dataRequest ) ) {
			abort( 404, __( 'This verification link is no longer valid.' ) );
		}

		$confirmed = $this->verifier->confirm( $dataRequest, config( 'artisanpack.privacy.data_requests.verification_method', 'email' ) );

		return redirect()
			->route( 'privacy.verification.show', [ 'token' => $token ] )
			->with( 'status', $confirmed
				? __( 'Your identity has been verified and your request is now being processed.' )
				: __( 'This request has already been verified or is no longer pending.' ),
			);
	}
}
