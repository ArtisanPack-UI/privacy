<?php

/**
 * PrivacyRegulation contract — common surface for regulation implementations.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Contracts;

use Illuminate\Http\Request;

/**
 * Contract implemented by every concrete regulation class (GDPR, CCPA, …).
 *
 * Concrete implementations live alongside {@see \ArtisanPackUI\Privacy\Regulations\BaseRegulation}
 * and are looked up by key through the regulation registry.
 *
 * @since 1.0.0
 */
interface PrivacyRegulation
{
	/**
	 * Returns the stable key used to identify the regulation in config and
	 * persisted data (`gdpr`, `ccpa`, `lgpd`, …).
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function key(): string;

	/**
	 * Returns the user-friendly display name (`GDPR`, `CCPA`, …).
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function name(): string;

	/**
	 * Returns the consent-related requirements imposed by the regulation —
	 * for example whether opt-in is required, whether silence counts, etc.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getConsentRequirements(): array;

	/**
	 * Returns the data subject rights granted by the regulation as a list of
	 * stable keys (`access`, `export`, `deletion`, `rectification`, …).
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function getDataRights(): array;

	/**
	 * Returns the retention rules the regulation imposes on stored data,
	 * keyed by domain (`consent`, `request`, `audit`, …).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getRetentionRules(): array;

	/**
	 * Returns the number of hours within which a data breach must be reported
	 * to the supervisory authority.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function getBreachNotificationHours(): int;

	/**
	 * Returns the number of days the controller has to respond to a request
	 * of the given type.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $requestType  Request type key (`access`, `export`, …).
	 *
	 * @return int
	 */
	public function getResponseDays( string $requestType ): int;

	/**
	 * Returns true when the regulation applies to the supplied request.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request  Incoming request to evaluate.
	 *
	 * @return bool
	 */
	public function applies( Request $request ): bool;
}
