<?php

/**
 * VerificationService — issues, validates, and confirms identity-verification
 * tokens for data subject requests.
 *
 * The token lives on the {@see DataRequest} row (`verification_token`) and
 * is considered expired once the request's `created_at` is older than
 * `artisanpack.privacy.verification.token_ttl_minutes`.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Services;

use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Models\DataRequestLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Identity verification service.
 *
 * @since 1.0.0
 */
class VerificationService
{
	/**
	 * Looks up a request by its verification token (regardless of state).
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $token Token from the verification link.
	 *
	 * @return DataRequest|null
	 */
	public function findByToken( string $token ): ?DataRequest
	{
		if ( '' === $token ) {
			return null;
		}

		$hash = hash( 'sha256', $token );

		$match = DataRequest::query()->where( 'verification_token_hash', $hash )->first();

		if ( $match instanceof DataRequest ) {
			// Constant-time guard against any future malicious row whose
			// `verification_token_hash` could be guessed via a successful
			// lookup; the hash lookup above and this comparison both run
			// regardless of outcome.
			if ( is_string( $match->verification_token_hash )
				&& hash_equals( $match->verification_token_hash, $hash ) ) {
				return $match;
			}

			return null;
		}

		// Backfill grace: rows created before the 1.0 security patch never
		// got a `verification_token_hash`, so fall back to the legacy
		// plaintext column when the hashed lookup misses AND the row has no
		// hash recorded. Drops in 1.1 once all live rows have a hash.
		$legacy = DataRequest::query()
			->where( 'verification_token', $token )
			->whereNull( 'verification_token_hash' )
			->first();

		return $legacy instanceof DataRequest ? $legacy : null;
	}

	/**
	 * Marks the supplied request as verified (sets `verified_at` and moves
	 * the request from `pending` → `processing`).
	 *
	 * Returns false when the request is already verified, expired, or no
	 * longer in a state that accepts verification.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataRequest  $request Request to verify.
	 * @param  string|null  $method  Channel that confirmed identity (`email`, `manual`, etc).
	 * @param  int|null     $performedBy Optional admin user id for manual verification.
	 *
	 * @return bool
	 */
	public function confirm( DataRequest $request, ?string $method = null, ?int $performedBy = null ): bool
	{
		if ( null !== $request->verified_at ) {
			return false;
		}

		if ( $this->isExpired( $request ) ) {
			return false;
		}

		if ( DataRequest::STATUS_PENDING !== $request->status ) {
			return false;
		}

		$request->update( [
			'verified_at' => Carbon::now(),
			'status'      => DataRequest::STATUS_PROCESSING,
		] );

		DataRequestLog::query()->create( [
			'data_request_id' => $request->getKey(),
			'action'          => 'verification.confirmed',
			'description'     => __( 'Identity verified via :method.', [
				'method' => $method ?? config( 'artisanpack.privacy.data_requests.verification_method', 'email' ),
			] ),
			'performed_by'    => $performedBy,
		] );

		return true;
	}

	/**
	 * Convenience wrapper for admin-driven manual verification.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataRequest  $request Request to verify.
	 * @param  int|Model    $admin   Admin user (or id) recording the verification.
	 *
	 * @return bool
	 */
	public function manuallyVerify( DataRequest $request, Model|int $admin ): bool
	{
		$adminId = $admin instanceof Model ? (int) $admin->getKey() : (int) $admin;

		return $this->confirm( $request, 'manual', $adminId );
	}

	/**
	 * Returns true when the request's token is past its TTL.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataRequest  $request Request to evaluate.
	 *
	 * @return bool
	 */
	public function isExpired( DataRequest $request ): bool
	{
		$ttl = (int) config( 'artisanpack.privacy.verification.token_ttl_minutes', 60 );

		if ( $ttl <= 0 ) {
			return false;
		}

		$reference = $request->created_at ?? Carbon::now();

		return $reference->copy()->addMinutes( $ttl )->isPast();
	}

	/**
	 * Re-issues a fresh token on an existing request and returns it.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataRequest  $request Request to refresh.
	 *
	 * @return string
	 */
	public function refreshToken( DataRequest $request ): string
	{
		$token = Str::random( 40 );

		$request->forceFill( [
			'verification_token'      => $token,
			'verification_token_hash' => hash( 'sha256', $token ),
			'verified_at'             => null,
		] )->save();

		return $token;
	}

	/**
	 * Builds the user-facing verification URL for the supplied request.
	 *
	 * @since 1.0.0
	 *
	 * @param  DataRequest  $request Request whose link should be generated.
	 *
	 * @return string|null Null when no token is set.
	 */
	public function verificationUrl( DataRequest $request ): ?string
	{
		if ( null === $request->verification_token || '' === $request->verification_token ) {
			return null;
		}

		return URL::route( 'privacy.verification.show', [ 'token' => $request->verification_token ] );
	}
}
