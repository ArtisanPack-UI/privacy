<?php

/**
 * DataSubjectDeletionScheduled event — fired when a deletion enters the
 * grace period and is scheduled for future processing.
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

use ArtisanPackUI\Privacy\Models\ScheduledDeletion;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Carries the {@see ScheduledDeletion} row so listeners can notify the
 * subject of the cooling-off window.
 *
 * @since 1.0.0
 */
class DataSubjectDeletionScheduled
{
	use Dispatchable;

	public function __construct( public ScheduledDeletion $scheduledDeletion )
	{
	}
}
