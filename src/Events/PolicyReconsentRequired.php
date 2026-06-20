<?php

/**
 * PolicyReconsentRequired event — dispatched when a subject is out of
 * sync with the active privacy policy and must re-consent.
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by {@see \ArtisanPackUI\Privacy\Services\ReconsentService::notifyRequired()}
 * when consent state no longer matches the active policy version.
 *
 * @since 1.0.0
 */
class PolicyReconsentRequired
{
	use Dispatchable;
	use SerializesModels;

	/**
	 * @param PrivacyPolicy   $policy  Policy whose version is now in effect.
	 * @param Model|null      $subject Subject that needs to re-consent.
	 * @param Request|null    $request Originating request, when available.
	 */
	public function __construct(
		public PrivacyPolicy $policy,
		public ?Model $subject = null,
		public ?Request $request = null,
	) {
	}
}
