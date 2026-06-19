<?php

/**
 * Consent model — per-subject, per-category consent record.
 *
 * Stores the consent state for an authenticated user or guest identifier,
 * including the regulation in effect, capture metadata, and lifecycle
 * timestamps (granted_at via timestamps, expires_at, withdrawn_at).
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

use ArtisanPackUI\Privacy\Database\Factories\ConsentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eloquent model for the `privacy_consents` table.
 *
 * @property int                              $id
 * @property string                           $consentable_type
 * @property int                              $consentable_id
 * @property string                           $category
 * @property bool                             $granted
 * @property string|null                      $regulation
 * @property string|null                      $ip_address
 * @property string|null                      $user_agent
 * @property array|null                       $metadata
 * @property \Illuminate\Support\Carbon|null  $expires_at
 * @property \Illuminate\Support\Carbon|null  $withdrawn_at
 * @property \Illuminate\Support\Carbon|null  $created_at
 * @property \Illuminate\Support\Carbon|null  $updated_at
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */
class Consent extends Model
{
	use HasFactory;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'privacy_consents';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'consentable_type',
		'consentable_id',
		'category',
		'granted',
		'regulation',
		'ip_address',
		'user_agent',
		'metadata',
		'expires_at',
		'withdrawn_at',
	];

	/**
	 * Subject of the consent (user or guest identifier model).
	 *
	 * @since 1.0.0
	 *
	 * @return MorphTo
	 */
	public function consentable(): MorphTo
	{
		return $this->morphTo();
	}

	/**
	 * Returns true when the consent has an expiry that has already passed.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function isExpired(): bool
	{
		return null !== $this->expires_at && $this->expires_at->isPast();
	}

	/**
	 * Returns true when the consent has been explicitly withdrawn.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function isWithdrawn(): bool
	{
		return null !== $this->withdrawn_at;
	}

	/**
	 * Returns true when the consent is granted, not withdrawn, and not expired.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->granted && ! $this->isWithdrawn() && ! $this->isExpired();
	}

	/**
	 * Scope: only consents currently in force (granted, not withdrawn, not expired).
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 *
	 * @return Builder
	 */
	public function scopeActive( Builder $query ): Builder
	{
		return $query->where( 'granted', true )
			->whereNull( 'withdrawn_at' )
			->where( function ( Builder $q ): void {
				$q->whereNull( 'expires_at' )->orWhere( 'expires_at', '>', now() );
			} );
	}

	/**
	 * Scope: consents whose `expires_at` has passed.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 *
	 * @return Builder
	 */
	public function scopeExpired( Builder $query ): Builder
	{
		return $query->whereNotNull( 'expires_at' )
			->where( 'expires_at', '<', now() );
	}

	/**
	 * Scope: limit to a specific consent category.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query    Query builder.
	 * @param  string   $category Consent category key.
	 *
	 * @return Builder
	 */
	public function scopeForCategory( Builder $query, string $category ): Builder
	{
		return $query->where( 'category', $category );
	}

	/**
	 * Scope: limit to a specific subject (user/guest) by morph identity.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query   Query builder.
	 * @param  Model    $subject Subject model.
	 *
	 * @return Builder
	 */
	public function scopeForSubject( Builder $query, Model $subject ): Builder
	{
		return $query->where( 'consentable_type', $subject->getMorphClass() )
			->where( 'consentable_id', $subject->getKey() );
	}

	/**
	 * Newly created factory instance for this model.
	 *
	 * @since 1.0.0
	 *
	 * @return ConsentFactory
	 */
	protected static function newFactory(): ConsentFactory
	{
		return ConsentFactory::new();
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
			'granted'      => 'boolean',
			'metadata'     => 'array',
			'expires_at'   => 'datetime',
			'withdrawn_at' => 'datetime',
		];
	}
}
