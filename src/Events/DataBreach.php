<?php

/**
 * DataBreach event — dispatched when a breach incident is reported into the system.
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

use ArtisanPackUI\Privacy\Models\BreachNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a BreachNotification has been persisted.
 *
 * @since 1.0.0
 */
class DataBreach
{
	use Dispatchable;
	use SerializesModels;

	/**
	 * @param BreachNotification $breach The persisted breach notification.
	 */
	public function __construct( public BreachNotification $breach )
	{
	}
}
