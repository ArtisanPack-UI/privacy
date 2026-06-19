<?php

/**
 * DataExportCollecting event — fired while DataExportService assembles a
 * subject's export payload so other packages can contribute extra data.
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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Listeners receive the export payload by reference and can mutate
 * `$event->data` to append additional sections.
 *
 * @since 1.0.0
 */
class DataExportCollecting
{
	use Dispatchable;

	/**
	 * @param Model                $subject Data subject the export belongs to.
	 * @param array<string, mixed> $data    Mutable export payload.
	 */
	public function __construct( public Model $subject, public array $data )
	{
	}
}
