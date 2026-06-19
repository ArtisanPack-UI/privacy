<?php

/**
 * ConsentGiven event — dispatched when a subject grants consent for a category.
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

use ArtisanPackUI\Privacy\Models\Consent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a Consent row has been persisted in the granted state.
 *
 * @since 1.0.0
 */
class ConsentGiven
{
	use Dispatchable;
	use SerializesModels;

	/**
	 * @param Consent $consent The consent row that was just granted.
	 */
	public function __construct( public Consent $consent )
	{
	}
}
