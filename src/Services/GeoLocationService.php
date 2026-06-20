<?php

/**
 * GeoLocationService — resolves visitor location from an IP address.
 *
 * Used by the regulation resolver and {@see \ArtisanPackUI\Privacy\Http\Middleware\GeolocateUser}
 * middleware to determine which privacy regulation applies to the current
 * request. Supports multiple providers (ip-api, MaxMind, Cloudflare
 * headers) and caches lookup results so repeated requests from the same
 * visitor do not hammer the upstream API.
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

use GeoIp2\Database\Reader as MaxMindReader;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Geolocation lookup service.
 *
 * Looks up a country / region for an IP using a configurable provider and
 * caches the result for `artisanpack.privacy.geolocation.cache_duration`
 * minutes. Reads the configured fallback region when the provider is
 * disabled, returns nothing, or errors out.
 *
 * Configuration:
 *
 *   'geolocation' => [
 *       'enabled'         => true,
 *       'provider'        => 'ip-api', // ip-api | maxmind | cloudflare
 *       'cache_duration'  => 1440,
 *       'fallback_region' => null,
 *   ],
 *
 * @since 1.0.0
 */
class GeoLocationService
{
	public const PROVIDER_IP_API     = 'ip-api';
	public const PROVIDER_MAXMIND    = 'maxmind';
	public const PROVIDER_CLOUDFLARE = 'cloudflare';

	/**
	 * Optional HTTP client factory override (test seam).
	 *
	 * @var HttpFactory|null
	 */
	protected ?HttpFactory $httpFactory = null;

	/**
	 * Optional MaxMind reader factory (test seam).
	 *
	 * @var callable|null
	 */
	protected $maxMindFactory = null;

	/**
	 * @param  HttpFactory|null  $httpFactory     Custom HTTP factory (defaults to Laravel's).
	 * @param  callable|null     $maxMindFactory  Optional callable returning a MaxMind reader.
	 */
	public function __construct(
		?HttpFactory $httpFactory = null,
		?callable $maxMindFactory = null,
	) {
		$this->httpFactory    = $httpFactory;
		$this->maxMindFactory = $maxMindFactory;
	}

	/**
	 * Returns the resolved location for an IP as an associative array of
	 * `country_code` (ISO-3166-1 alpha-2) and optional `region_code`
	 * (ISO-3166-2 subdivision, e.g. `US-CA`). Returns null when the lookup
	 * cannot be completed and no fallback is configured.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $ip  IP address to resolve.
	 *
	 * @return array{country_code: string|null, region_code: string|null}|null
	 */
	public function getLocation( string $ip ): ?array
	{
		if ( ! $this->isEnabled() ) {
			return $this->fallbackLocation();
		}

		$ip = trim( $ip );

		if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $this->fallbackLocation();
		}

		try {
			$location = Cache::remember(
				$this->cacheKey( $ip ),
				$this->cacheTtlSeconds(),
				fn (): ?array => $this->lookup( $ip ),
			);
		} catch ( Throwable $e ) {
			report( $e );
			$location = null;
		}

		if ( null === $location ) {
			return $this->fallbackLocation();
		}

		return $location;
	}

	/**
	 * Returns the resolved ISO-3166-1 country code for an IP, or null when
	 * no country could be determined.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $ip IP address to resolve.
	 *
	 * @return string|null
	 */
	public function getCountryCode( string $ip ): ?string
	{
		$location = $this->getLocation( $ip );

		if ( null === $location ) {
			return null;
		}

		return $location['country_code'] ?? null;
	}

	/**
	 * Returns the resolved ISO-3166-2 region (subdivision) code for an IP,
	 * or null when no region is available.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $ip IP address to resolve.
	 *
	 * @return string|null
	 */
	public function getRegionCode( string $ip ): ?string
	{
		$location = $this->getLocation( $ip );

		if ( null === $location ) {
			return null;
		}

		return $location['region_code'] ?? null;
	}

	/**
	 * Resolves the visitor's privacy region from a request. Returns the
	 * subdivision code when present (e.g. `US-CA`) so the regulation
	 * resolver can match CCPA's `US-CA` rule, otherwise the bare country
	 * code (e.g. `DE`).
	 *
	 * Prefers `CF-IPCountry` from Cloudflare when the configured provider
	 * is `cloudflare`, falling back to the request IP for other providers.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request Incoming request.
	 *
	 * @return string|null
	 */
	public function resolveRegion( Request $request ): ?string
	{
		if ( self::PROVIDER_CLOUDFLARE === $this->provider() ) {
			$header = $request->headers->get( 'CF-IPCountry' );

			if ( is_string( $header ) && '' !== $header && 'XX' !== strtoupper( $header ) ) {
				return strtoupper( $header );
			}
		}

		$ip = $request->ip();

		if ( ! is_string( $ip ) || '' === $ip ) {
			return $this->configuredFallback();
		}

		$location = $this->getLocation( $ip );

		if ( null === $location ) {
			return null;
		}

		$region  = is_string( $location['region_code'] ?? null ) ? strtoupper( $location['region_code'] ) : null;
		$country = is_string( $location['country_code'] ?? null ) ? strtoupper( $location['country_code'] ) : null;

		return $region ?? $country;
	}

	/**
	 * Returns true when geolocation is enabled in configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function isEnabled(): bool
	{
		return (bool) config( 'artisanpack.privacy.geolocation.enabled', true );
	}

	/**
	 * Returns the configured provider key, defaulting to `ip-api`.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function provider(): string
	{
		$provider = (string) config( 'artisanpack.privacy.geolocation.provider', self::PROVIDER_IP_API );

		return '' === $provider ? self::PROVIDER_IP_API : strtolower( $provider );
	}

	/**
	 * Returns the cache TTL in seconds (config value is stored in minutes).
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function cacheTtlSeconds(): int
	{
		$minutes = (int) config( 'artisanpack.privacy.geolocation.cache_duration', 1440 );

		return max( $minutes, 1 ) * 60;
	}

	/**
	 * Returns the cache key for an IP lookup.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $ip IP address.
	 *
	 * @return string
	 */
	protected function cacheKey( string $ip ): string
	{
		return 'privacy:geolocation:' . $this->provider() . ':' . sha1( $ip );
	}

	/**
	 * Performs the actual provider lookup. Returns a location array or null
	 * when the provider could not resolve the IP.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $ip IP address.
	 *
	 * @return array{country_code: string|null, region_code: string|null}|null
	 */
	protected function lookup( string $ip ): ?array
	{
		switch ( $this->provider() ) {
			case self::PROVIDER_MAXMIND:
				return $this->lookupMaxMind( $ip );

			case self::PROVIDER_CLOUDFLARE:
				// Cloudflare values flow in via request headers and cannot be
				// resolved from an IP alone, so cache nothing here — the
				// caller's resolveRegion() short-circuits before reaching us.
				return null;

			case self::PROVIDER_IP_API:
			default:
				return $this->lookupIpApi( $ip );
		}
	}

	/**
	 * Looks up `ip-api.com` for free-tier geolocation. Documented at
	 * {@link https://ip-api.com/docs/api:json}. Honours the X-Ttl /
	 * X-Rl headers by reporting connection errors instead of throwing.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $ip IP address.
	 *
	 * @return array{country_code: string|null, region_code: string|null}|null
	 */
	protected function lookupIpApi( string $ip ): ?array
	{
		$http = $this->httpFactory ?? app( HttpFactory::class );
		$url  = "http://ip-api.com/json/{$ip}?fields=status,countryCode,region";

		try {
			$response = $http->timeout( 3 )->get( $url );
		} catch ( ConnectionException $e ) {
			report( $e );

			return null;
		}

		if ( ! $response->successful() ) {
			return null;
		}

		$payload = $response->json();

		if ( ! is_array( $payload ) || 'success' !== ( $payload['status'] ?? null ) ) {
			return null;
		}

		$country = isset( $payload['countryCode'] ) && is_string( $payload['countryCode'] )
			? strtoupper( $payload['countryCode'] )
			: null;

		$region = isset( $payload['region'] ) && is_string( $payload['region'] ) && '' !== $payload['region']
			? strtoupper( $payload['region'] )
			: null;

		return [
			'country_code' => $country,
			'region_code'  => null !== $country && null !== $region ? "{$country}-{$region}" : null,
		];
	}

	/**
	 * Looks up MaxMind's GeoIP2 city database when the optional
	 * `geoip2/geoip2` package is installed. The database path is
	 * resolved from `artisanpack.privacy.geolocation.maxmind.database`.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $ip IP address.
	 *
	 * @return array{country_code: string|null, region_code: string|null}|null
	 */
	protected function lookupMaxMind( string $ip ): ?array
	{
		$reader = $this->maxMindReader();

		if ( null === $reader ) {
			return null;
		}

		try {
			$record = $reader->city( $ip );
		} catch ( Throwable $e ) {
			report( $e );

			return null;
		}

		$country     = $record->country->isoCode ?? null;
		$subdivision = $record->mostSpecificSubdivision->isoCode ?? null;

		$country = is_string( $country ) && '' !== $country ? strtoupper( $country ) : null;

		if ( null === $country ) {
			return null;
		}

		$region = is_string( $subdivision ) && '' !== $subdivision
			? strtoupper( $country . '-' . $subdivision )
			: null;

		return [
			'country_code' => $country,
			'region_code'  => $region,
		];
	}

	/**
	 * Returns a configured MaxMind reader instance, or null when the
	 * library or database file is not available.
	 *
	 * @since 1.0.0
	 *
	 * @return MaxMindReader|null
	 */
	protected function maxMindReader(): ?MaxMindReader
	{
		if ( null !== $this->maxMindFactory ) {
			$reader = ( $this->maxMindFactory )();

			return $reader instanceof MaxMindReader ? $reader : null;
		}

		if ( ! class_exists( MaxMindReader::class ) ) {
			return null;
		}

		$path = (string) config( 'artisanpack.privacy.geolocation.maxmind.database', '' );

		if ( '' === $path || ! is_file( $path ) ) {
			return null;
		}

		try {
			return new MaxMindReader( $path );
		} catch ( Throwable $e ) {
			report( $e );

			return null;
		}
	}

	/**
	 * Returns a fallback location array derived from the configured
	 * fallback region, or null when no fallback is set.
	 *
	 * @since 1.0.0
	 *
	 * @return array{country_code: string|null, region_code: string|null}|null
	 */
	protected function fallbackLocation(): ?array
	{
		$fallback = $this->configuredFallback();

		if ( null === $fallback ) {
			return null;
		}

		$parts   = explode( '-', $fallback, 2 );
		$country = strtoupper( $parts[0] );

		return [
			'country_code' => '' === $country ? null : $country,
			'region_code'  => isset( $parts[1] ) && '' !== $parts[1] ? strtoupper( $fallback ) : null,
		];
	}

	/**
	 * Returns the configured fallback region string, or null.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	protected function configuredFallback(): ?string
	{
		$fallback = config( 'artisanpack.privacy.geolocation.fallback_region' );

		return is_string( $fallback ) && '' !== $fallback ? strtoupper( $fallback ) : null;
	}
}
