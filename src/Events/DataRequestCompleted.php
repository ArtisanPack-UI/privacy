<?php

/**
 * DataRequestCompleted event — dispatched when a data subject request finishes processing.
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

use ArtisanPackUI\Privacy\Models\DataRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a DataRequest has been marked completed (or rejected) by an
 * automated processor or an admin.
 *
 * @since 1.0.0
 */
class DataRequestCompleted
{
	use Dispatchable;
	use SerializesModels;

	/**
	 * @param DataRequest $request The completed (or rejected) request.
	 */
	public function __construct( public DataRequest $request )
	{
	}
}
