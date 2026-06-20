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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
 * @property Carbon|null  $published_at
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
	 * Scope: ordered most-recent-version first using {@see versionSortKey()}
	 * so semantic versions like `1.10.0` correctly outrank `1.9.0`.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query  Query builder.
	 *
	 * @return Builder
	 */
	public function scopeLatestFirst( Builder $query ): Builder
	{
		return $query->orderByDesc( 'published_at' )->orderByDesc( 'id' );
	}

	/**
	 * Scope: limit to a specific version string.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder  $query   Query builder.
	 * @param  string   $version Version string (e.g. `1.2.0`).
	 *
	 * @return Builder
	 */
	public function scopeForVersion( Builder $query, string $version ): Builder
	{
		return $query->where( 'version', $version );
	}

	/**
	 * Marks this policy active, replacing the previously-active record for
	 * the same regulation/locale. Wrapped in a transaction so the swap is
	 * atomic — callers can rely on `PrivacyPolicy::active()->first()`
	 * returning the new row immediately after this method returns.
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public function publish(): self
	{
		return DB::transaction( function (): self {
			static::query()
				->where( 'id', '!=', $this->getKey() )
				->where( 'regulation', $this->regulation )
				->where( 'locale', $this->locale )
				->where( 'active', true )
				->update( [ 'active' => false ] );

			$this->forceFill( [
				'active'       => true,
				'published_at' => $this->published_at ?? Carbon::now(),
			] )->save();

			return $this;
		} );
	}

	/**
	 * Marks the policy inactive without removing it. Historical versions
	 * remain queryable via {@see scopeForVersion()}.
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public function unpublish(): self
	{
		$this->forceFill( [ 'active' => false ] )->save();

		return $this;
	}

	/**
	 * Renders the Markdown body as HTML using Laravel's commonmark wrapper.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function renderHtml(): string
	{
		return Str::markdown( (string) $this->content );
	}

	/**
	 * Returns the structured table of contents (heading/slug/level array).
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{heading: string, slug: string, level: int}>
	 */
	public function tableOfContents(): array
	{
		$sections = $this->sections;

		if ( is_array( $sections ) && [] !== $sections ) {
			return $sections;
		}

		return ( new \ArtisanPackUI\Privacy\Services\PrivacyPolicyGenerator() )
			->extractSections( (string) $this->content );
	}

	/**
	 * Returns a sortable key for the policy's version string. Two- or
	 * three-segment semver-style strings are zero-padded so lexical sorts
	 * are correct (`1.10.0` → `0001.0010.0000`).
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function versionSortKey(): string
	{
		$parts = preg_split( '/[\.\-+]/', (string) $this->version ) ?: [];
		$parts = array_slice( $parts, 0, 3 );

		while ( count( $parts ) < 3 ) {
			$parts[] = '0';
		}

		return implode( '.', array_map(
			static fn ( string $part ): string => str_pad(
				preg_replace( '/[^0-9]/', '', $part ) ?: '0',
				4,
				'0',
				STR_PAD_LEFT,
			),
			$parts,
		) );
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
