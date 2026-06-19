<?php

/**
 * PrivacyPolicy model — versioned privacy policy content.
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

use ArtisanPackUI\Privacy\Database\Factories\PrivacyPolicyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `privacy_policies` table.
 *
 * @property int                              $id
 * @property string                           $version
 * @property string|null                      $regulation
 * @property string                           $locale
 * @property string                           $content
 * @property array|null                       $sections
 * @property bool                             $active
 * @property bool                             $requires_reconsent
 * @property \Illuminate\Support\Carbon|null  $published_at
 * @property int|null                         $created_by
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */
class PrivacyPolicy extends Model
{
	use HasFactory;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'privacy_policies';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'version',
		'regulation',
		'locale',
		'content',
		'sections',
		'active',
		'requires_reconsent',
		'published_at',
		'created_by',
	];

	/**
	 * Scope: only the policy currently marked active.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 *
	 * @return Builder
	 */
	public function scopeActive( Builder $query ): Builder
	{
		return $query->where( 'active', true );
	}

	/**
	 * Scope: limit to a regulation (nullable matches the "general" policy).
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder      $query      Query builder.
	 * @param  string|null  $regulation Regulation key or null for the general policy.
	 *
	 * @return Builder
	 */
	public function scopeForRegulation( Builder $query, ?string $regulation ): Builder
	{
		if ( null === $regulation ) {
			return $query->whereNull( 'regulation' );
		}

		return $query->where( 'regulation', $regulation );
	}

	/**
	 * Scope: limit to a locale.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 * @param  string   $locale Locale code.
	 *
	 * @return Builder
	 */
	public function scopeForLocale( Builder $query, string $locale ): Builder
	{
		return $query->where( 'locale', $locale );
	}

	/**
	 * Newly created factory instance for this model.
	 *
	 * @since 1.0.0
	 *
	 * @return PrivacyPolicyFactory
	 */
	protected static function newFactory(): PrivacyPolicyFactory
	{
		return PrivacyPolicyFactory::new();
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
			'sections'           => 'array',
			'active'             => 'boolean',
			'requires_reconsent' => 'boolean',
			'published_at'       => 'datetime',
		];
	}
}
