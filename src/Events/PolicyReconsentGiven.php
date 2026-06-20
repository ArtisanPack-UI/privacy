<?php

/**
 * PolicyReconsentGiven event — dispatched after a subject re-consents to
 * the active privacy policy.
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
 * Fired by {@see \ArtisanPackUI\Privacy\Services\ReconsentService::grant()}.
 *
 * @since 1.0.0
 */
class PolicyReconsentGiven
{
	use Dispatchable;
	use SerializesModels;

	/**
	 * @param PrivacyPolicy  $policy  Policy the subject just re-consented to.
	 * @param Model          $subject Subject that re-consented.
	 * @param Request|null   $request Originating request, when available.
	 */
	public function __construct(
		public PrivacyPolicy $policy,
		public Model $subject,
		public ?Request $request = null,
	) {
	}
}
