<?php

/**
 * ReconsentController — records an authenticated user's re-consent.
 *
 * Handles the `POST /{prefix}/reconsent` endpoint registered by the
 * package: validates the supplied policy version against the current
 * active policy and persists the alignment through
 * {@see \ArtisanPackUI\Privacy\Services\ReconsentService::grant()}.
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

use ArtisanPackUI\Privacy\Services\ReconsentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Persists re-consent submitted from the banner Livewire/React/Vue UI.
 *
 * @since 1.0.0
 */
class ReconsentController
{
	/**
	 * Validates and stores the subject's re-consent.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request           $request   Incoming request.
	 * @param  ReconsentService  $reconsent Service used to update consent records.
	 *
	 * @return Response
	 */
	public function store( Request $request, ReconsentService $reconsent ): Response
	{
		$data = $request->validate( [
			'version'    => [ 'required', 'string', 'max:64' ],
			'regulation' => [ 'nullable', 'string', 'max:32' ],
			'redirect'   => [ 'nullable', 'string', 'max:2048' ],
		] );

		$policy = $reconsent->currentPolicy( $data['regulation'] ?? null );

		if ( null === $policy || $policy->version !== $data['version'] ) {
			return $this->respond( $request, false, [
				'message' => __( 'The submitted policy version is no longer current.' ),
			], 409 );
		}

		$subject = $request->user();

		if ( ! $subject instanceof Model ) {
			return $this->respond( $request, false, [
				'message' => __( 'Authentication is required to re-consent.' ),
			], 401 );
		}

		$reconsent->grant( $policy, $subject, $request );

		if ( $request->expectsJson() ) {
			return new JsonResponse( [
				'ok'      => true,
				'version' => $policy->version,
			] );
		}

		$redirect = $data['redirect'] ?? $request->headers->get( 'referer' );

		return redirect()->to(
			is_string( $redirect ) && '' !== $redirect ? $redirect : '/',
		);
	}

	/**
	 * Returns a JSON response when the client expects JSON, or a redirect
	 * back otherwise. Centralised so JSON shape stays consistent.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request                $request Incoming request.
	 * @param  bool                   $ok      Outcome flag for the JSON payload.
	 * @param  array<string, mixed>   $payload Additional JSON payload.
	 * @param  int                    $status  HTTP status code.
	 *
	 * @return Response
	 */
	protected function respond( Request $request, bool $ok, array $payload, int $status ): Response
	{
		if ( $request->expectsJson() ) {
			return new JsonResponse( array_merge( [ 'ok' => $ok ], $payload ), $status );
		}

		return back()->withErrors( $payload );
	}
}
