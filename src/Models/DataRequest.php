<?php

/**
 * DataRequest model — represents a data subject request (access, deletion, export, rectification).
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

use ArtisanPackUI\Privacy\Database\Factories\DataRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eloquent model for the `privacy_data_requests` table.
 *
 * @property int                              $id
 * @property string                           $requestable_type
 * @property int                              $requestable_id
 * @property string                           $type
 * @property string                           $status
 * @property string|null                      $regulation
 * @property string|null                      $reason
 * @property array|null                       $data
 * @property string|null                      $verification_token
 * @property \Illuminate\Support\Carbon|null  $verified_at
 * @property \Illuminate\Support\Carbon|null  $due_at
 * @property \Illuminate\Support\Carbon|null  $completed_at
 * @property int|null                         $processed_by
 * @property string|null                      $admin_notes
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */
class DataRequest extends Model
{
	use HasFactory;

	public const STATUS_PENDING    = 'pending';
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_COMPLETED  = 'completed';
	public const STATUS_REJECTED   = 'rejected';

	public const TYPE_ACCESS        = 'access';
	public const TYPE_DELETION      = 'deletion';
	public const TYPE_EXPORT        = 'export';
	public const TYPE_RECTIFICATION = 'rectification';

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'privacy_data_requests';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'requestable_type',
		'requestable_id',
		'type',
		'status',
		'regulation',
		'reason',
		'data',
		'verified_at',
		'due_at',
		'completed_at',
		'processed_by',
		'admin_notes',
	];

	/**
	 * Attributes hidden from array / JSON serialization. Verification tokens
	 * (plaintext + hashed) are written by {@see \ArtisanPackUI\Privacy\Services\DataRequestService}
	 * and never reflected back to API callers.
	 *
	 * @var array<int, string>
	 *
	 * @deprecated 1.0 `verification_token` column is retained for backfill
	 *             only; it will be dropped in 1.1 in favour of
	 *             `verification_token_hash`.
	 */
	protected $hidden = [
		'verification_token',
		'verification_token_hash',
	];

	/**
	 * The subject the request was filed on behalf of.
	 *
	 * @since 1.0.0
	 *
	 * @return MorphTo
	 */
	public function requestable(): MorphTo
	{
		return $this->morphTo();
	}

	/**
	 * Audit-trail entries for this request.
	 *
	 * @since 1.0.0
	 *
	 * @return HasMany
	 */
	public function logs(): HasMany
	{
		return $this->hasMany( DataRequestLog::class, 'data_request_id' );
	}

	/**
	 * Scope: pending requests.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 *
	 * @return Builder
	 */
	public function scopePending( Builder $query ): Builder
	{
		return $query->where( 'status', self::STATUS_PENDING );
	}

	/**
	 * Scope: processing requests.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 *
	 * @return Builder
	 */
	public function scopeProcessing( Builder $query ): Builder
	{
		return $query->where( 'status', self::STATUS_PROCESSING );
	}

	/**
	 * Scope: completed requests.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 *
	 * @return Builder
	 */
	public function scopeCompleted( Builder $query ): Builder
	{
		return $query->where( 'status', self::STATUS_COMPLETED );
	}

	/**
	 * Scope: rejected requests.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 *
	 * @return Builder
	 */
	public function scopeRejected( Builder $query ): Builder
	{
		return $query->where( 'status', self::STATUS_REJECTED );
	}

	/**
	 * Scope: requests that are past their regulatory deadline and still unresolved.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 *
	 * @return Builder
	 */
	public function scopeOverdue( Builder $query ): Builder
	{
		return $query->whereNotNull( 'due_at' )
			->where( 'due_at', '<', now() )
			->whereNotIn( 'status', [ self::STATUS_COMPLETED, self::STATUS_REJECTED ] );
	}

	/**
	 * Scope: requests of a given type.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 * @param  string   $type   Request type.
	 *
	 * @return Builder
	 */
	public function scopeOfType( Builder $query, string $type ): Builder
	{
		return $query->where( 'type', $type );
	}

	/**
	 * Newly created factory instance for this model.
	 *
	 * @since 1.0.0
	 *
	 * @return DataRequestFactory
	 */
	protected static function newFactory(): DataRequestFactory
	{
		return DataRequestFactory::new();
	}

	/**
	 * Attribute casts.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'data'         => 'array',
			'verified_at'  => 'datetime',
			'due_at'       => 'datetime',
			'completed_at' => 'datetime',
		];
	}
}
