<?php

/**
 * DataSubjectDeleted event — fired after {@see DataDeletionService} applies
 * a deletion or anonymization strategy to a subject.
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
 * Carries the subject and the strategy that was applied (`delete`,
 * `anonymize`, `pseudonymize`).
 *
 * @since 1.0.0
 */
class DataSubjectDeleted
{
	use Dispatchable;

	/**
	 * @param Model  $subject  Subject that was deleted/anonymized.
	 * @param string $strategy Strategy applied.
	 */
	public function __construct( public Model $subject, public string $strategy )
	{
	}
}
