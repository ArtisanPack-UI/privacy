<?php

/**
 * ConsentCategory model — describes a category of consent (e.g. analytics, marketing).
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

use ArtisanPackUI\Privacy\Database\Factories\ConsentCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `privacy_consent_categories` table.
 *
 * @property int          $id
 * @property string       $key
 * @property string       $name
 * @property string|null  $description
 * @property bool         $required
 * @property int          $sort_order
 * @property bool         $active
 * @property array|null   $regulations
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */
class ConsentCategory extends Model
{
	use HasFactory;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'privacy_consent_categories';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'key',
		'name',
		'description',
		'required',
		'sort_order',
		'active',
		'regulations',
	];

	/**
	 * Scope: only active categories.
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
	 * Scope: order by sort_order, then name.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 *
	 * @return Builder
	 */
	public function scopeOrdered( Builder $query ): Builder
	{
		return $query->orderBy( 'sort_order' )->orderBy( 'name' );
	}

	/**
	 * Newly created factory instance for this model.
	 *
	 * @since 1.0.0
	 *
	 * @return ConsentCategoryFactory
	 */
	protected static function newFactory(): ConsentCategoryFactory
	{
		return ConsentCategoryFactory::new();
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
			'required'    => 'boolean',
			'active'      => 'boolean',
			'sort_order'  => 'integer',
			'regulations' => 'array',
		];
	}
}
