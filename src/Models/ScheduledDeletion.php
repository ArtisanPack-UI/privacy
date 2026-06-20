<?php

/**
 * ScheduledDeletion model — represents a future or completed data deletion.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eloquent model for the `privacy_scheduled_deletions` table.
 *
 * @property int                              $id
 * @property string                           $deletable_type
 * @property int                              $deletable_id
 * @property string                           $strategy
 * @property \Illuminate\Support\Carbon       $scheduled_for
 * @property \Illuminate\Support\Carbon|null  $processed_at
 * @property \Illuminate\Support\Carbon|null  $cancelled_at
 * @property string|null                      $cancelled_reason
 * @property int|null                         $requested_by
 * @property array|null                       $options
 *
 * @since 1.0.0
 */
class ScheduledDeletion extends Model
{
	protected $table = 'privacy_scheduled_deletions';

	protected $fillable = [
		'deletable_type',
		'deletable_id',
		'strategy',
		'scheduled_for',
		'processed_at',
		'cancelled_at',
		'cancelled_reason',
		'requested_by',
		'options',
	];

	public function deletable(): MorphTo
	{
		return $this->morphTo();
	}

	public function scopePending( Builder $query ): Builder
	{
		return $query->whereNull( 'processed_at' )->whereNull( 'cancelled_at' );
	}

	public function scopeDue( Builder $query ): Builder
	{
		return $query->pending()->where( 'scheduled_for', '<=', now() );
	}

	public function isPending(): bool
	{
		return null === $this->processed_at && null === $this->cancelled_at;
	}

	protected function casts(): array
	{
		return [
			'scheduled_for' => 'datetime',
			'processed_at'  => 'datetime',
			'cancelled_at'  => 'datetime',
			'options'       => 'array',
		];
	}
}
