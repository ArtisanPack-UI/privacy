<?php

/**
 * GDPR regulation — General Data Protection Regulation (EU/EEA/UK).
 *
 * Encodes the rules every controller must comply with for visitors from the
 * EU, EEA, and UK: explicit, granular, withdrawable consent; the eight data
 * subject rights; a 72-hour breach notification window; and a 30-day
 * default response deadline.
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

use Illuminate\Http\Request;

/**
 * GDPR / UK GDPR compliance ruleset.
 *
 * @since 1.0.0
 */
class Gdpr extends BaseRegulation
{
	public const LEGAL_BASIS_CONSENT             = 'consent';
	public const LEGAL_BASIS_CONTRACT            = 'contract';
	public const LEGAL_BASIS_LEGAL_OBLIGATION    = 'legal_obligation';
	public const LEGAL_BASIS_VITAL_INTERESTS     = 'vital_interests';
	public const LEGAL_BASIS_PUBLIC_TASK         = 'public_task';
	public const LEGAL_BASIS_LEGITIMATE_INTEREST = 'legitimate_interest';

	/**
	 * Default region match list, used when no `applies_to` is configured.
	 *
	 * @var array<int, string>
	 */
	protected const DEFAULT_REGIONS = [ 'EU', 'EEA', 'UK' ];

	/**
	 * EU member state ISO-3166-1 alpha-2 codes. Used to map a per-country
	 * region (`DE`, `FR`, …) to the `EU` umbrella token configured in
	 * `applies_to`.
	 *
	 * @var array<int, string>
	 */
	protected const EU_MEMBER_STATES = [
		'AT',
		'BE',
		'BG',
		'HR',
		'CY',
		'CZ',
		'DK',
		'EE',
		'FI',
		'FR',
		'DE',
		'GR',
		'HU',
		'IE',
		'IT',
		'LV',
		'LT',
		'LU',
		'MT',
		'NL',
		'PL',
		'PT',
		'RO',
		'SK',
		'SI',
		'ES',
		'SE',
	];

	/**
	 * Additional EEA countries beyond the EU member states.
	 *
	 * @var array<int, string>
	 */
	protected const EEA_EXTRAS = [ 'IS', 'LI', 'NO' ];

	/**
	 * Stable identifier.
	 *
	 * @var string
	 */
	protected string $key = 'gdpr';

	/**
	 * Display name.
	 *
	 * @var string
	 */
	protected string $name = 'GDPR';

	/**
	 * GDPR mandates 72-hour breach notification under Article 33.
	 *
	 * @var int
	 */
	protected int $defaultBreachNotificationHours = 72;

	/**
	 * @inheritDoc
	 *
	 * Extends the base region check with EU/EEA/UK membership translation
	 * so an application that records a per-country region on the request
	 * (`DE`, `FR`, …) still matches a `applies_to => [EU, EEA, UK]`
	 * configuration.
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

		$region  = strtoupper( $region );
		$regions = $this->regions();

		if ( in_array( $region, $regions, true ) ) {
			return true;
		}

		$country = $this->extractCountry( $region );

		if ( null === $country ) {
			return false;
		}

		if ( in_array( $country, $regions, true ) ) {
			return true;
		}

		if ( in_array( 'EU', $regions, true ) && in_array( $country, self::EU_MEMBER_STATES, true ) ) {
			return true;
		}

		if ( in_array( 'EEA', $regions, true )
			&& ( in_array( $country, self::EU_MEMBER_STATES, true )
				|| in_array( $country, self::EEA_EXTRAS, true ) ) ) {
			return true;
		}

		if ( in_array( 'UK', $regions, true ) && in_array( $country, [ 'GB', 'UK' ], true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns the configured consent expiry window in days.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function consentExpiryDays(): int
	{
		return (int) config( "artisanpack.privacy.regulations.{$this->key}.consent_expiry_days", 365 );
	}

	/**
	 * Returns the configured DPO contact information, when supplied.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function dataProtectionOfficer(): array
	{
		$dpo = config( "artisanpack.privacy.regulations.{$this->key}.dpo", [] );

		if ( ! is_array( $dpo ) ) {
			return [];
		}

		$out = [];

		foreach ( [ 'name', 'email', 'phone', 'address' ] as $field ) {
			if ( isset( $dpo[ $field ] ) && is_string( $dpo[ $field ] ) && '' !== $dpo[ $field ] ) {
				$out[ $field ] = $dpo[ $field ];
			}
		}

		return $out;
	}

	/**
	 * Returns the list of permitted legal bases for processing under GDPR.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function legalBases(): array
	{
		return [
			self::LEGAL_BASIS_CONSENT,
			self::LEGAL_BASIS_CONTRACT,
			self::LEGAL_BASIS_LEGAL_OBLIGATION,
			self::LEGAL_BASIS_VITAL_INTERESTS,
			self::LEGAL_BASIS_PUBLIC_TASK,
			self::LEGAL_BASIS_LEGITIMATE_INTEREST,
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function consentRequirements(): array
	{
		return [
			'explicit_opt_in'     => true,
			'granular'            => true,
			'withdrawable'        => true,
			'unbundled'           => true,
			'no_pre_ticked_boxes' => true,
			'records_required'    => true,
			'parental_consent'    => 16,
			'expiry_days'         => $this->consentExpiryDays(),
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function dataRights(): array
	{
		return [
			'access',
			'rectification',
			'erasure',
			'portability',
			'restriction',
			'objection',
			'automated_decision_making',
			'be_informed',
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function retentionRules(): array
	{
		return [
			'consent'      => $this->consentExpiryDays(),
			'audit_log'    => 365 * 3,
			'data_request' => 365 * 3,
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function defaultResponseDays(): array
	{
		return [
			'default'       => 30,
			'access'        => 30,
			'export'        => 30,
			'deletion'      => 30,
			'rectification' => 30,
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function regions(): array
	{
		$configured = (array) config( "artisanpack.privacy.regulations.{$this->key}.applies_to", self::DEFAULT_REGIONS );

		if ( [] === $configured ) {
			$configured = self::DEFAULT_REGIONS;
		}

		return array_map( static fn ( $region ): string => strtoupper( (string) $region ), $configured );
	}

	/**
	 * Extracts a bare ISO-3166-1 country code from a `country` or
	 * `country-subdivision` region string.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $region Region string (e.g. `US-CA`, `DE`, `EU`).
	 *
	 * @return string|null
	 */
	protected function extractCountry( string $region ): ?string
	{
		$parts   = explode( '-', $region, 2 );
		$country = strtoupper( $parts[0] );

		return '' === $country ? null : $country;
	}
}
