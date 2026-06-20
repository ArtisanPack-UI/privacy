<?php

/**
 * BaseRegulation — shared base for every supported privacy regulation.
 *
 * Concrete subclasses (GDPR, CCPA, LGPD, PIPEDA, …) extend this class to
 * declare their per-regulation requirements while inheriting config-driven
 * defaults for breach windows, response deadlines, and region matching.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Regulations;

use ArtisanPackUI\Privacy\Contracts\PrivacyRegulation;
use Illuminate\Http\Request;

/**
 * Abstract base every concrete regulation extends.
 *
 * Subclasses customize {@see consentRequirements()}, {@see dataRights()},
 * {@see retentionRules()}, and {@see defaultResponseDays()}; the remaining
 * surface is satisfied here using `artisanpack.privacy.regulations.{key}`.
 *
 * @since 1.0.0
 */
abstract class BaseRegulation implements PrivacyRegulation
{
	/**
	 * Stable identifier used in config and persisted data.
	 *
	 * @var string
	 */
	protected string $key = '';

	/**
	 * Human-readable display name.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Default breach notification window in hours. Overridden by
	 * `regulations.{key}.breach_notification_hours` when configured.
	 *
	 * @var int
	 */
	protected int $defaultBreachNotificationHours = 72;

	/**
	 * @inheritDoc
	 */
	public function key(): string
	{
		return $this->key;
	}

	/**
	 * @inheritDoc
	 */
	public function name(): string
	{
		return $this->name;
	}

	/**
	 * @inheritDoc
	 */
	public function getConsentRequirements(): array
	{
		return $this->consentRequirements();
	}

	/**
	 * @inheritDoc
	 */
	public function getDataRights(): array
	{
		return $this->dataRights();
	}

	/**
	 * @inheritDoc
	 */
	public function getRetentionRules(): array
	{
		return $this->retentionRules();
	}

	/**
	 * @inheritDoc
	 */
	public function getBreachNotificationHours(): int
	{
		$configured = config( "artisanpack.privacy.regulations.{$this->key}.breach_notification_hours" );

		return null === $configured ? $this->defaultBreachNotificationHours : (int) $configured;
	}

	/**
	 * @inheritDoc
	 */
	public function getResponseDays( string $requestType ): int
	{
		$configured = config( "artisanpack.privacy.data_requests.response_days.{$this->key}" );

		if ( null !== $configured ) {
			return (int) $configured;
		}

		$default = $this->defaultResponseDays();

		return $default[ $requestType ] ?? $default['default'] ?? 30;
	}

	/**
	 * @inheritDoc
	 *
	 * Default match: the regulation `applies_to` list (configured under
	 * `regulations.{key}.applies_to`) contains a region recorded on the
	 * request — either as the `X-Region` header (CDN-friendly), the
	 * `privacy_region` request attribute (populated by upstream middleware),
	 * or the `geolocation.fallback_region` config value.
	 *
	 * Subclasses may override to add provider-specific lookups (IP, account
	 * preferences, etc.) without re-implementing the base check.
	 */
	public function applies( Request $request ): bool
	{
		if ( ! $this->isEnabled() ) {
			return false;
		}

		$region = $this->resolveRegion( $request );

		if ( null === $region ) {
			return false;
		}

		return in_array( strtoupper( $region ), $this->regions(), true );
	}

	/**
	 * Returns the regulation's consent requirements. Subclasses override.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function consentRequirements(): array;

	/**
	 * Returns the regulation's data subject rights. Subclasses override.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	abstract protected function dataRights(): array;

	/**
	 * Returns the regulation's retention rules. Subclasses override.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function retentionRules(): array;

	/**
	 * Returns the default response-day map keyed by request type, plus an
	 * optional `default` fallback. Subclasses override.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, int>
	 */
	abstract protected function defaultResponseDays(): array;

	/**
	 * Returns true when the regulation is enabled in configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function isEnabled(): bool
	{
		return (bool) config( "artisanpack.privacy.regulations.{$this->key}.enabled", false );
	}

	/**
	 * Returns the configured `applies_to` region list, uppercased.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function regions(): array
	{
		$regions = (array) config( "artisanpack.privacy.regulations.{$this->key}.applies_to", [] );

		return array_map( static fn ( $region ): string => strtoupper( (string) $region ), $regions );
	}

	/**
	 * Resolves the visitor's region from the most-trusted source available.
	 *
	 * Order:
	 *  1. `privacy_region` request attribute (set by upstream middleware).
	 *  2. `X-Region` header (commonly populated by CDNs).
	 *  3. `geolocation.fallback_region` config value.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request  Incoming request.
	 *
	 * @return string|null
	 */
	protected function resolveRegion( Request $request ): ?string
	{
		$attribute = $request->attributes->get( 'privacy_region' );

		if ( is_string( $attribute ) && '' !== $attribute ) {
			return $attribute;
		}

		$header = $request->headers->get( 'X-Region' );

		if ( is_string( $header ) && '' !== $header ) {
			return $header;
		}

		$fallback = config( 'artisanpack.privacy.geolocation.fallback_region' );

		return is_string( $fallback ) && '' !== $fallback ? $fallback : null;
	}
}
