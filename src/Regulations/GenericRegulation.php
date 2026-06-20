<?php

/**
 * GenericRegulation — config-only regulation fallback.
 *
 * Used by the registry when an application enables a regulation key
 * (`lgpd`, `pipeda`) without supplying a dedicated subclass. Pulls every
 * answer from configuration so applications can model lightweight
 * jurisdictions without writing a new class.
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
 * Config-driven regulation used as a fallback when no dedicated subclass
 * is registered for a given key.
 *
 * Reads its display name, consent requirements, data rights, retention
 * rules, breach window, and response deadlines from
 * `regulations.{key}.regulation.*` (or falls back to sensible defaults).
 *
 * @since 1.0.0
 */
class GenericRegulation extends BaseRegulation
{
	/**
	 * @param  string  $key  Regulation key (e.g. `lgpd`).
	 * @param  string  $name Display name (defaults to the upper-cased key).
	 */
	public function __construct( string $key, string $name = '' )
	{
		$this->key  = $key;
		$this->name = '' === $name ? strtoupper( $key ) : $name;
	}

	/**
	 * @inheritDoc
	 */
	protected function consentRequirements(): array
	{
		return (array) config( "artisanpack.privacy.regulations.{$this->key}.regulation.consent_requirements", [] );
	}

	/**
	 * @inheritDoc
	 */
	protected function dataRights(): array
	{
		return (array) config(
			"artisanpack.privacy.regulations.{$this->key}.regulation.data_rights",
			[ 'access', 'export', 'deletion', 'rectification' ],
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function retentionRules(): array
	{
		return (array) config( "artisanpack.privacy.regulations.{$this->key}.regulation.retention", [] );
	}

	/**
	 * @inheritDoc
	 */
	protected function defaultResponseDays(): array
	{
		$days = (int) config( 'artisanpack.privacy.data_requests.response_days.default', 30 );

		return [ 'default' => $days ];
	}
}
