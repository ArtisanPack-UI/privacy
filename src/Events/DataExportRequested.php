<?php

/**
 * DataExportRequested event — dispatched when a data subject export request is filed.
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
 * Fired after a DataRequest of type `export` has been persisted.
 *
 * @since 1.0.0
 */
class DataExportRequested
{
	use Dispatchable;
	use SerializesModels;

	/**
	 * @param DataRequest $request The persisted export request.
	 */
	public function __construct( public DataRequest $request )
	{
	}
}
