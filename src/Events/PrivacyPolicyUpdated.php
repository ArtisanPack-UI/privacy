<?php

/**
 * PrivacyPolicyUpdated event — dispatched when a new privacy policy version is published.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Events;

use ArtisanPackUI\Privacy\Models\PrivacyPolicy;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a PrivacyPolicy has been activated (typically a new version is
 * published or an existing inactive version is reactivated).
 *
 * @since 1.0.0
 */
class PrivacyPolicyUpdated
{
	use Dispatchable;
	use SerializesModels;

	/**
	 * @param PrivacyPolicy $policy The policy that became active.
	 */
	public function __construct( public PrivacyPolicy $policy )
	{
	}
}
