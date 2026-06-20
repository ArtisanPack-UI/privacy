<?php

/**
 * CCPA / CPRA regulation — California Consumer Privacy Act / Privacy Rights Act.
 *
 * Encodes the rules California residents are entitled to: the rights to
 * know, delete, correct, opt out of sale/sharing, limit use of sensitive
 * personal information, and non-discrimination. Response window is 45 days
 * (extendable to 90 with notice).
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

/**
 * CCPA / CPRA compliance ruleset.
 *
 * @since 1.0.0
 */
class Ccpa extends BaseRegulation
{
	public const RIGHT_TO_KNOW                = 'know';
	public const RIGHT_TO_DELETE              = 'delete';
	public const RIGHT_TO_CORRECT             = 'correct';
	public const RIGHT_TO_OPT_OUT_OF_SALE     = 'opt_out_of_sale';
	public const RIGHT_TO_LIMIT_SENSITIVE_USE = 'limit_use_of_sensitive_information';
	public const RIGHT_TO_NON_DISCRIMINATION  = 'non_discrimination';
	public const RIGHT_TO_DATA_PORTABILITY    = 'data_portability';

	/**
	 * Default regions to which CCPA applies when none are configured.
	 *
	 * @var array<int, string>
	 */
	protected const DEFAULT_REGIONS = [ 'US-CA' ];

	/**
	 * Stable identifier.
	 *
	 * @var string
	 */
	protected string $key = 'ccpa';

	/**
	 * Display name.
	 *
	 * @var string
	 */
	protected string $name = 'CCPA/CPRA';

	/**
	 * CCPA does not impose a hard breach notification clock the way GDPR
	 * does — operators must notify the AG and consumers "in the most
	 * expedient time possible and without unreasonable delay" (Civ. Code
	 * §1798.82). The default below acts as an internal SLA only and is
	 * overridable via configuration.
	 *
	 * @var int
	 */
	protected int $defaultBreachNotificationHours = 720;

	/**
	 * Returns true when the operator meets at least one of the three
	 * thresholds that bring a business under CCPA. Operators that fall
	 * below all three are still free to honour requests as a courtesy.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function meetsBusinessThreshold(): bool
	{
		$revenue    = (int) config( "artisanpack.privacy.regulations.{$this->key}.financial_threshold", 25000000 );
		$revenueHit = (int) config( "artisanpack.privacy.regulations.{$this->key}.annual_revenue", 0 );

		if ( $revenueHit >= $revenue && $revenue > 0 ) {
			return true;
		}

		$consumers    = (int) config( "artisanpack.privacy.regulations.{$this->key}.consumer_threshold", 100000 );
		$consumersHit = (int) config( "artisanpack.privacy.regulations.{$this->key}.annual_consumers", 0 );

		if ( $consumersHit >= $consumers && $consumers > 0 ) {
			return true;
		}

		$saleShare = (float) config( "artisanpack.privacy.regulations.{$this->key}.sale_revenue_share", 0.5 );
		$actual    = (float) config( "artisanpack.privacy.regulations.{$this->key}.actual_sale_revenue_share", 0 );

		return $actual >= $saleShare && $saleShare > 0;
	}

	/**
	 * Returns true when "Do Not Sell My Personal Information" support is
	 * required by configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function requiresDoNotSellLink(): bool
	{
		return (bool) config( "artisanpack.privacy.regulations.{$this->key}.opt_out_sale", true );
	}

	/**
	 * Returns the financial-incentive disclosure metadata, if configured.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function financialIncentives(): array
	{
		$incentives = config( "artisanpack.privacy.regulations.{$this->key}.financial_incentives", [] );

		return is_array( $incentives ) ? $incentives : [];
	}

	/**
	 * @inheritDoc
	 */
	protected function consentRequirements(): array
	{
		return [
			'opt_out_model'          => true,
			'opt_in_for_under_16'    => true,
			'opt_in_for_sensitive'   => true,
			'do_not_sell_link'       => $this->requiresDoNotSellLink(),
			'non_discrimination'     => true,
			'global_privacy_control' => true,
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function dataRights(): array
	{
		return [
			self::RIGHT_TO_KNOW,
			self::RIGHT_TO_DELETE,
			self::RIGHT_TO_CORRECT,
			self::RIGHT_TO_OPT_OUT_OF_SALE,
			self::RIGHT_TO_LIMIT_SENSITIVE_USE,
			self::RIGHT_TO_NON_DISCRIMINATION,
			self::RIGHT_TO_DATA_PORTABILITY,
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function retentionRules(): array
	{
		return [
			'consent'      => 365,
			'audit_log'    => 365 * 2,
			'data_request' => 365 * 2,
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function defaultResponseDays(): array
	{
		return [
			'default'         => 45,
			'access'          => 45,
			'export'          => 45,
			'deletion'        => 45,
			'correct'         => 45,
			'rectification'   => 45,
			'opt_out_of_sale' => 15,
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
}
