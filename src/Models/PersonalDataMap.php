<?php

/**
 * PersonalDataMap model — registry of personal data fields across the application's models.
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

use ArtisanPackUI\Privacy\Database\Factories\PersonalDataMapFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `privacy_personal_data_maps` table.
 *
 * @property int          $id
 * @property string       $model
 * @property string       $field
 * @property string       $data_type
 * @property string       $sensitivity
 * @property string|null  $retention_period
 * @property string       $deletion_strategy
 * @property string|null  $export_format
 * @property bool         $auto_discovered
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */
class PersonalDataMap extends Model
{
	use HasFactory;

	public const SENSITIVITY_NORMAL           = 'normal';
	public const SENSITIVITY_SENSITIVE        = 'sensitive';
	public const SENSITIVITY_SPECIAL_CATEGORY = 'special_category';

	public const STRATEGY_DELETE       = 'delete';
	public const STRATEGY_ANONYMIZE    = 'anonymize';
	public const STRATEGY_PSEUDONYMIZE = 'pseudonymize';

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'privacy_personal_data_maps';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'model',
		'field',
		'data_type',
		'sensitivity',
		'retention_period',
		'deletion_strategy',
		'export_format',
		'auto_discovered',
	];

	/**
	 * Scope: limit to fields registered against a specific model class.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 * @param  string   $model  Fully-qualified model class.
	 *
	 * @return Builder
	 */
	public function scopeForModel( Builder $query, string $model ): Builder
	{
		return $query->where( 'model', $model );
	}

	/**
	 * Scope: only sensitive or special-category fields.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 *
	 * @return Builder
	 */
	public function scopeSensitive( Builder $query ): Builder
	{
		return $query->whereIn( 'sensitivity', [
			self::SENSITIVITY_SENSITIVE,
			self::SENSITIVITY_SPECIAL_CATEGORY,
		] );
	}

	/**
	 * Newly created factory instance for this model.
	 *
	 * @since 1.0.0
	 *
	 * @return PersonalDataMapFactory
	 */
	protected static function newFactory(): PersonalDataMapFactory
	{
		return PersonalDataMapFactory::new();
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
			'auto_discovered' => 'boolean',
		];
	}
}
