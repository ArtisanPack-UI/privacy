<?php

/**
 * BreachNotification model — record of a data-breach incident and its notification workflow.
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

use ArtisanPackUI\Privacy\Database\Factories\BreachNotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `privacy_breach_notifications` table.
 *
 * @property int                              $id
 * @property string                           $reference_number
 * @property \Illuminate\Support\Carbon       $discovered_at
 * @property \Illuminate\Support\Carbon|null  $occurred_at
 * @property string                           $severity
 * @property string                           $description
 * @property array                            $data_types_affected
 * @property int|null                         $records_affected
 * @property array|null                       $affected_users
 * @property string|null                      $cause
 * @property string|null                      $remediation
 * @property array|null                       $notifications_sent
 * @property \Illuminate\Support\Carbon|null  $authority_notified_at
 * @property \Illuminate\Support\Carbon|null  $users_notified_at
 * @property string                           $status
 * @property int|null                         $reported_by
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */
class BreachNotification extends Model
{
	use HasFactory;

	public const SEVERITY_LOW      = 'low';
	public const SEVERITY_MEDIUM   = 'medium';
	public const SEVERITY_HIGH     = 'high';
	public const SEVERITY_CRITICAL = 'critical';

	public const STATUS_INVESTIGATING = 'investigating';
	public const STATUS_CONTAINED     = 'contained';
	public const STATUS_RESOLVED      = 'resolved';

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'privacy_breach_notifications';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'reference_number',
		'discovered_at',
		'occurred_at',
		'severity',
		'description',
		'data_types_affected',
		'records_affected',
		'affected_users',
		'cause',
		'remediation',
		'notifications_sent',
		'authority_notified_at',
		'users_notified_at',
		'status',
		'reported_by',
	];

	/**
	 * Scope: breaches at a given severity.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query    Query builder.
	 * @param  string   $severity Severity level.
	 *
	 * @return Builder
	 */
	public function scopeOfSeverity( Builder $query, string $severity ): Builder
	{
		return $query->where( 'severity', $severity );
	}

	/**
	 * Scope: breaches still being investigated.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 *
	 * @return Builder
	 */
	public function scopeUnresolved( Builder $query ): Builder
	{
		return $query->whereIn( 'status', [ self::STATUS_INVESTIGATING, self::STATUS_CONTAINED ] );
	}

	/**
	 * Newly created factory instance for this model.
	 *
	 * @since 1.0.0
	 *
	 * @return BreachNotificationFactory
	 */
	protected static function newFactory(): BreachNotificationFactory
	{
		return BreachNotificationFactory::new();
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
			'discovered_at'         => 'datetime',
			'occurred_at'           => 'datetime',
			'authority_notified_at' => 'datetime',
			'users_notified_at'     => 'datetime',
			'data_types_affected'   => 'array',
			'affected_users'        => 'array',
			'notifications_sent'    => 'array',
			'records_affected'      => 'integer',
		];
	}
}
