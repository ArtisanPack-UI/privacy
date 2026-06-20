<?php

/**
 * ConsentService — central service for managing user consent.
 *
 * Handles granting, revoking, and querying consent for both authenticated
 * users and guests; persists to the database, the cookie jar, or both
 * (governed by `artisanpack.privacy.consent.storage`); fires events on
 * every grant/revoke; and exposes a regulation resolver.
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

use ArtisanPackUI\Privacy\Events\ConsentGiven;
use ArtisanPackUI\Privacy\Events\ConsentWithdrawn;
use ArtisanPackUI\Privacy\Models\Consent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;

/**
 * Consent management service.
 *
 * @since 1.0.0
 */
class ConsentService
{
	public const STORAGE_DATABASE = 'database';
	public const STORAGE_COOKIE   = 'cookie';
	public const STORAGE_BOTH     = 'both';

	/**
	 * Returns true when the subject has an active consent for the given category.
	 *
	 * Falls back to the consent cookie when no authenticated subject is provided
	 * and database storage is unavailable.
	 *
	 * @since 1.0.0
	 *
	 * @param  string      $category Consent category key.
	 * @param  Model|null  $user     Subject model, or null for the current authenticated/guest user.
	 *
	 * @return bool
	 */
	public function hasConsent( string $category, ?Model $user = null ): bool
	{
		$subject = $user ?? $this->resolveSubject();

		if ( $subject instanceof Model && $this->usesDatabase() ) {
			return Consent::query()
				->forSubject( $subject )
				->forCategory( $category )
				->active()
				->exists();
		}

		$cookie = $this->getConsentCookie();

		return is_array( $cookie ) && ( $cookie[ $category ] ?? false ) === true;
	}

	/**
	 * Returns all active consents for the subject.
	 *
	 * For guest subjects (no model), this returns an empty collection — guests'
	 * consents live in the cookie and are exposed via {@see getConsentCookie()}.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model|null  $user  Subject model, or null for the current authenticated/guest user.
	 *
	 * @return Collection<int, Consent>
	 */
	public function getConsents( ?Model $user = null ): Collection
	{
		$subject = $user ?? $this->resolveSubject();

		if ( ! $subject instanceof Model || ! $this->usesDatabase() ) {
			return new Collection();
		}

		return Consent::query()
			->forSubject( $subject )
			->active()
			->get();
	}

	/**
	 * Grants consent for the given category, persists it, and fires
	 * {@see ConsentGiven}.
	 *
	 * Withdraws any previously-active rows for the same subject+category
	 * before recording the new grant so the active set stays single-row
	 * per category.
	 *
	 * @since 1.0.0
	 *
	 * @param  string                $category Category key.
	 * @param  Model|null            $user     Subject model, or null for the current authenticated/guest user.
	 * @param  array<string, mixed>  $metadata Additional context to persist with the consent record.
	 *
	 * @return Consent
	 */
	public function grantConsent( string $category, ?Model $user = null, array $metadata = [] ): Consent
	{
		$subject    = $user ?? $this->resolveSubject();
		$regulation = $this->getApplicableRegulation();

		if ( $this->usesDatabase() && $subject instanceof Model ) {
			$superseded = Consent::query()
				->forSubject( $subject )
				->forCategory( $category )
				->active()
				->get();

			foreach ( $superseded as $previous ) {
				$previous->update( [
					'granted'      => false,
					'withdrawn_at' => now(),
				] );

				Event::dispatch( new ConsentWithdrawn( $previous ) );
			}

			$consent = Consent::query()->create( [
				'consentable_type' => $subject->getMorphClass(),
				'consentable_id'   => $subject->getKey(),
				'category'         => $category,
				'granted'          => true,
				'regulation'       => $regulation,
				'ip_address'       => $this->currentIpAddress(),
				'user_agent'       => $this->currentUserAgent(),
				'metadata'         => empty( $metadata ) ? null : $metadata,
				'expires_at'       => $this->resolveExpiry( $regulation ),
			] );
		} else {
			$consent = new Consent( [
				'category'   => $category,
				'granted'    => true,
				'regulation' => $regulation,
				'ip_address' => $this->currentIpAddress(),
				'user_agent' => $this->currentUserAgent(),
				'metadata'   => empty( $metadata ) ? null : $metadata,
				'expires_at' => $this->resolveExpiry( $regulation ),
			] );
		}

		if ( $this->usesCookie() ) {
			$cookie               = $this->getConsentCookie() ?? [];
			$cookie[ $category ]  = true;
			$this->setConsentCookie( $cookie );
		}

		Event::dispatch( new ConsentGiven( $consent ) );

		return $consent;
	}

	/**
	 * Revokes consent for the given category and fires {@see ConsentWithdrawn}
	 * for each affected row.
	 *
	 * @since 1.0.0
	 *
	 * @param  string      $category Category key.
	 * @param  Model|null  $user     Subject model, or null for the current authenticated/guest user.
	 *
	 * @return bool True when at least one row was withdrawn or the cookie was updated.
	 */
	public function revokeConsent( string $category, ?Model $user = null ): bool
	{
		$subject = $user ?? $this->resolveSubject();
		$changed = false;

		if ( $this->usesDatabase() && $subject instanceof Model ) {
			$active = Consent::query()
				->forSubject( $subject )
				->forCategory( $category )
				->active()
				->get();

			foreach ( $active as $consent ) {
				$consent->update( [
					'granted'      => false,
					'withdrawn_at' => now(),
				] );

				Event::dispatch( new ConsentWithdrawn( $consent ) );
				$changed = true;
			}
		}

		if ( $this->usesCookie() ) {
			$cookie         = $this->getConsentCookie() ?? [];
			$wasTrue        = ( $cookie[ $category ] ?? false ) === true;
			$wasNotRecorded = ! array_key_exists( $category, $cookie );

			$cookie[ $category ] = false;
			$this->setConsentCookie( $cookie );

			if ( $wasTrue || $wasNotRecorded ) {
				$changed = $changed || $wasTrue;
			}
		}

		return $changed;
	}

	/**
	 * Grants consent for every configured cookie category.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model|null  $user  Subject model, or null for the current authenticated/guest user.
	 *
	 * @return Collection<int, Consent>
	 */
	public function grantAllConsents( ?Model $user = null ): Collection
	{
		$categories = $this->configuredCategories();
		$consents   = new Collection();

		foreach ( $categories as $category ) {
			$consents->push( $this->grantConsent( $category, $user ) );
		}

		return $consents;
	}

	/**
	 * Revokes consent for every non-required cookie category.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model|null  $user  Subject model, or null for the current authenticated/guest user.
	 *
	 * @return bool True when at least one revocation took place.
	 */
	public function revokeAllConsents( ?Model $user = null ): bool
	{
		$changed = false;

		foreach ( $this->configuredCategories() as $category ) {
			if ( $this->isRequiredCategory( $category ) ) {
				continue;
			}

			$changed = $this->revokeConsent( $category, $user ) || $changed;
		}

		return $changed;
	}

	/**
	 * Syncs the cookie-based consent map into the database for the given subject.
	 *
	 * Intended for the moment a guest authenticates: any categories with a
	 * `true` value in the cookie are recorded as database grants tied to the
	 * authenticated subject; `false` values are recorded as withdrawals.
	 * Skips no-ops, so calling this repeatedly is safe.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $user Subject that should own the synced rows.
	 *
	 * @return int Number of consents written (granted + revoked) during sync.
	 */
	public function syncCookieToDatabase( Model $user ): int
	{
		if ( ! $this->usesDatabase() ) {
			return 0;
		}

		$cookie = $this->getConsentCookie();

		if ( null === $cookie ) {
			return 0;
		}

		$written = 0;

		foreach ( $cookie as $category => $granted ) {
			if ( ! is_string( $category ) ) {
				continue;
			}

			if ( true === $granted ) {
				$alreadyGranted = Consent::query()
					->forSubject( $user )
					->forCategory( $category )
					->active()
					->exists();

				if ( $alreadyGranted ) {
					continue;
				}

				$this->grantConsent( $category, $user, [ 'synced_from_cookie' => true ] );
				$written++;
				continue;
			}

			if ( false === $granted && $this->revokeConsent( $category, $user ) ) {
				$written++;
			}
		}

		return $written;
	}

	/**
	 * Resolves a stable, opaque identifier for the current guest visitor.
	 *
	 * Strategy is controlled by `artisanpack.privacy.consent.guest_identifier`:
	 *  - `session`     uses the current session id (falls back to ip when no session is bound)
	 *  - `ip`          uses the request IP address
	 *  - `fingerprint` (default) returns a sha-256 hash of session id + IP + user agent
	 *
	 * Returns null when no source is available (typical for console contexts).
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function resolveGuestIdentifier(): ?string
	{
		$strategy = (string) config( 'artisanpack.privacy.consent.guest_identifier', 'fingerprint' );
		$ip       = $this->currentIpAddress();
		$session  = function_exists( 'session' ) && session()->isStarted() ? session()->getId() : null;

		return match ( $strategy ) {
			'session'     => $session ?? $ip,
			'ip'          => $ip,
			'fingerprint' => $this->fingerprintIdentifier( $session, $ip, $this->currentUserAgent() ),
			default       => $this->fingerprintIdentifier( $session, $ip, $this->currentUserAgent() ),
		};
	}

	/**
	 * Returns the consent map currently stored in the cookie jar.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, bool>|null
	 */
	public function getConsentCookie(): ?array
	{
		$raw = Cookie::get( $this->cookieName() );

		if ( null === $raw ) {
			return null;
		}

		$decoded = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Persists the given consent map in the cookie jar.
	 *
	 * The cookie is encrypted by Laravel's `EncryptCookies` middleware as
	 * long as the cookie name is not added to the middleware's `$except`
	 * list — that is the default and recommended setup.
	 *
	 * The current request's cookie bag is also updated so that subsequent
	 * reads inside the same request (for example, the second iteration of
	 * a multi-category grant) see the new state.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, bool>  $consents Map of category => granted.
	 *
	 * @return void
	 */
	public function setConsentCookie( array $consents ): void
	{
		$encoded = json_encode( $consents );

		Cookie::queue(
			Cookie::make(
				$this->cookieName(),
				$encoded,
				$this->cookieLifetimeMinutes(),
			),
		);

		Request::instance()->cookies->set( $this->cookieName(), $encoded );
	}

	/**
	 * Returns true when the consent row has expired.
	 *
	 * @since 1.0.0
	 *
	 * @param  Consent  $consent Consent row.
	 *
	 * @return bool
	 */
	public function isConsentExpired( Consent $consent ): bool
	{
		return $consent->isExpired();
	}

	/**
	 * Returns the regulation key that applies to the current request, or null
	 * if none is enabled.
	 *
	 * Resolution strategy:
	 *  1. If `geolocation.fallback_region` is set, use it to match a regulation
	 *     via the `regulations.*.applies_to` lists.
	 *  2. Otherwise return the first enabled regulation in config order.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function getApplicableRegulation(): ?string
	{
		// Delegate to the regulation registry so consent rows, data requests,
		// and the user-facing dashboard all agree on the same key for any
		// given request. The registry's `currentKey()` already implements the
		// region-match-then-first-enabled fallback this method used to do
		// inline, so callers in console contexts (where no request signals
		// are available) still get a sensible default.
		return app( \ArtisanPackUI\Privacy\Regulations\RegulationRegistry::class )->currentKey();
	}

	/**
	 * Resolves the subject to act on: the authenticated user when one exists.
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

	/**
	 * Returns true when the configured storage uses the database.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function usesDatabase(): bool
	{
		$storage = config( 'artisanpack.privacy.consent.storage', self::STORAGE_BOTH );

		return in_array( $storage, [ self::STORAGE_DATABASE, self::STORAGE_BOTH ], true );
	}

	/**
	 * Returns true when the configured storage uses cookies.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function usesCookie(): bool
	{
		$storage = config( 'artisanpack.privacy.consent.storage', self::STORAGE_BOTH );

		return in_array( $storage, [ self::STORAGE_COOKIE, self::STORAGE_BOTH ], true );
	}

	/**
	 * Returns the configured cookie name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function cookieName(): string
	{
		return (string) config( 'artisanpack.privacy.consent.cookie_name', 'privacy_consent' );
	}

	/**
	 * Returns the configured cookie lifetime in minutes.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function cookieLifetimeMinutes(): int
	{
		$days = (int) config( 'artisanpack.privacy.consent.cookie_lifetime', 365 );

		return $days * 24 * 60;
	}

	/**
	 * Returns the list of category keys defined in configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function configuredCategories(): array
	{
		return array_keys( (array) config( 'artisanpack.privacy.cookie_categories', [] ) );
	}

	/**
	 * Returns true when the named category is marked as required (strictly necessary).
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $category Category key.
	 *
	 * @return bool
	 */
	protected function isRequiredCategory( string $category ): bool
	{
		return (bool) config( "artisanpack.privacy.cookie_categories.{$category}.required", false );
	}

	/**
	 * Computes the expiry timestamp for a new consent given a regulation key.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null  $regulation Regulation key or null.
	 *
	 * @return \Illuminate\Support\Carbon|null
	 */
	protected function resolveExpiry( ?string $regulation ): ?\Illuminate\Support\Carbon
	{
		if ( null === $regulation ) {
			return null;
		}

		$days = config( "artisanpack.privacy.regulations.{$regulation}.consent_expiry_days" );

		return null === $days ? null : now()->addDays( (int) $days );
	}

	/**
	 * Best-effort capture of the requester's IP address (null in console contexts).
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	protected function currentIpAddress(): ?string
	{
		return Request::ip();
	}

	/**
	 * Best-effort capture of the requester's user agent header (null in console contexts).
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	protected function currentUserAgent(): ?string
	{
		return Request::userAgent();
	}

	/**
	 * Builds a deterministic fingerprint identifier from the available
	 * per-visitor signals. Returns null when none are available.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null  $session   Session id, if any.
	 * @param  string|null  $ip        IP address, if any.
	 * @param  string|null  $userAgent User agent header, if any.
	 *
	 * @return string|null
	 */
	protected function fingerprintIdentifier( ?string $session, ?string $ip, ?string $userAgent ): ?string
	{
		$parts = array_filter(
			[ $session, $ip, $userAgent ],
			static fn ( ?string $value ): bool => null !== $value && '' !== $value,
		);

		if ( [] === $parts ) {
			return null;
		}

		return hash( 'sha256', implode( '|', $parts ) );
	}
}
