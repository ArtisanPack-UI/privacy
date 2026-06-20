<?php

/**
 * PrivacyPolicyGenerator — builds versioned PrivacyPolicy records from
 * regulation-specific Markdown templates.
 *
 * The generator reads templates shipped with the package (and any overrides
 * published to `resources/views/vendor/artisanpack-ui/privacy/templates/policies`),
 * interpolates `{{placeholder}}` tokens against caller-supplied data merged
 * with the package's configured company defaults, and persists a new
 * {@see PrivacyPolicy} row whose `content` holds the raw Markdown and whose
 * `sections` JSON holds the heading outline used by the table of contents.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Services;

use ArtisanPackUI\Privacy\Models\PrivacyPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Generates {@see PrivacyPolicy} records from Markdown templates.
 *
 * @since 1.0.0
 */
class PrivacyPolicyGenerator
{
	/**
	 * Regulations the generator ships first-party templates for.
	 *
	 * @var array<int, string>
	 */
	public const SUPPORTED_REGULATIONS = [ 'gdpr', 'ccpa', 'lgpd', 'pipeda' ];

	/**
	 * Builds a {@see PrivacyPolicy} model from the resolved template and
	 * persists it. The new policy is created in draft (`active = false`,
	 * `published_at = null`); call {@see PrivacyPolicy::publish()} once it
	 * has been reviewed.
	 *
	 * Recognised `$options` keys:
	 * - `regulation` (string|null)  Template key. Defaults to general.
	 * - `locale`     (string)        Locale code. Defaults to app locale.
	 * - `version`    (string)        Semantic version string. Defaults to "1.0.0".
	 * - `data`       (array)         Placeholder overrides.
	 * - `created_by` (int|Model|null) Author for the audit trail.
	 * - `activate`   (bool)          Publish + activate after creation.
	 * - `requires_reconsent` (bool)  Force returning users to reconsent.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $options Generation options.
	 *
	 * @return PrivacyPolicy
	 */
	public function generate( array $options = [] ): PrivacyPolicy
	{
		$regulation = $this->resolveRegulation( $options['regulation'] ?? null );
		$locale     = (string) ( $options['locale'] ?? config( 'app.locale', 'en' ) );
		$version    = (string) ( $options['version'] ?? '1.0.0' );
		$data       = $this->resolveData( (array) ( $options['data'] ?? [] ) );

		$rawTemplate = $this->loadTemplate( $regulation, $locale );
		$content     = $this->renderTemplate( $rawTemplate, $data );
		$sections    = $this->extractSections( $content );

		$policy = PrivacyPolicy::query()->create( [
			'version'            => $version,
			'regulation'         => $regulation,
			'locale'             => $locale,
			'content'            => $content,
			'sections'           => $sections,
			'active'             => false,
			'requires_reconsent' => (bool) ( $options['requires_reconsent'] ?? false ),
			'published_at'       => null,
			'created_by'         => $this->resolveCreatedBy( $options['created_by'] ?? null ),
		] );

		if ( true === ( $options['activate'] ?? false ) ) {
			$policy->publish();
		}

		return $policy->refresh();
	}

	/**
	 * Renders a template string with caller-supplied data without touching
	 * the database. Public so callers can preview before persisting.
	 *
	 * @since 1.0.0
	 *
	 * @param  string                $template Markdown template body.
	 * @param  array<string, mixed>  $data     Placeholder values.
	 *
	 * @return string
	 */
	public function renderTemplate( string $template, array $data ): string
	{
		$rendered = $this->renderBlockSections( $template, $data );

		return $this->renderPlaceholders( $rendered, $data );
	}

	/**
	 * Returns the raw Markdown template body for the given regulation/locale.
	 * Falls back to English when a locale-specific template is missing.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null  $regulation Regulation key or null for the general template.
	 * @param  string       $locale     Locale code.
	 *
	 * @throws RuntimeException When no template exists for the regulation.
	 *
	 * @return string
	 */
	public function loadTemplate( ?string $regulation, string $locale = 'en' ): string
	{
		$regulation = $this->resolveRegulation( $regulation );
		$candidates = $this->templateCandidates( $regulation, $locale );

		foreach ( $candidates as $candidate ) {
			if ( is_file( $candidate ) ) {
				$contents = file_get_contents( $candidate );

				if ( false !== $contents ) {
					return $contents;
				}
			}
		}

		throw new RuntimeException(
			"No privacy policy template found for regulation [{$regulation}] (locale: {$locale}).",
		);
	}

	/**
	 * Returns the structured outline (`heading`/`slug`/`level`) for a
	 * Markdown document — used both to populate `PrivacyPolicy::$sections`
	 * and to drive the table of contents on the display view.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $markdown Rendered Markdown.
	 *
	 * @return array<int, array{heading: string, slug: string, level: int}>
	 */
	public function extractSections( string $markdown ): array
	{
		$sections = [];
		$lines    = preg_split( '/\R/u', $markdown ) ?: [];

		foreach ( $lines as $line ) {
			if ( 1 !== preg_match( '/^(#{1,6})\s+(.+)$/u', trim( $line ), $matches ) ) {
				continue;
			}

			$level   = strlen( $matches[1] );
			$heading = trim( $matches[2] );

			$sections[] = [
				'heading' => $heading,
				'slug'    => Str::slug( $heading ),
				'level'   => $level,
			];
		}

		return $sections;
	}

	/**
	 * Returns the candidate template paths in priority order, allowing
	 * application overrides published to `resources/views/vendor/...` to
	 * supersede the package defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $regulation Regulation key (or `general`).
	 * @param  string  $locale     Locale code.
	 *
	 * @return array<int, string>
	 */
	protected function templateCandidates( string $regulation, string $locale ): array
	{
		$packageBase   = __DIR__ . '/../../resources/templates/policies';
		$publishedBase = function_exists( 'resource_path' )
			? resource_path( 'views/vendor/artisanpack-ui/privacy/templates/policies' )
			: $packageBase;

		$candidates = [];

		foreach ( [ $publishedBase, $packageBase ] as $base ) {
			$candidates[] = "{$base}/{$regulation}.{$locale}.md";
			$candidates[] = "{$base}/{$regulation}.md";
		}

		return $candidates;
	}

	/**
	 * Normalises the supplied regulation key. Unknown keys collapse to
	 * `general` so callers can pass user-supplied input safely.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null  $regulation Raw regulation key.
	 *
	 * @return string
	 */
	protected function resolveRegulation( ?string $regulation ): string
	{
		if ( null === $regulation || '' === $regulation ) {
			return 'general';
		}

		$normalised = strtolower( $regulation );

		if ( 'general' === $normalised ) {
			return 'general';
		}

		return in_array( $normalised, self::SUPPORTED_REGULATIONS, true )
			? $normalised
			: 'general';
	}

	/**
	 * Merges caller-supplied placeholders with the package's configured
	 * company/DPO defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data Caller overrides.
	 *
	 * @return array<string, mixed>
	 */
	protected function resolveData( array $data ): array
	{
		$defaults = [
			'company_name'           => (string) config( 'artisanpack.privacy.breach.organization.name', config( 'app.name', 'Our organization' ) ),
			'company_email'          => (string) config( 'artisanpack.privacy.breach.organization.contact', config( 'artisanpack.privacy.data_requests.admin_email', '' ) ),
			'company_address'        => (string) config( 'artisanpack.privacy.breach.organization.address', '' ),
			'company_website'        => (string) config( 'artisanpack.privacy.breach.organization.website', config( 'app.url', '' ) ),
			'dpo_email'              => (string) config( 'artisanpack.privacy.breach.dpo.email', '' ),
			'dpo_phone'              => (string) config( 'artisanpack.privacy.breach.dpo.phone', '' ),
			'effective_date'         => Carbon::now()->toFormattedDateString(),
			'retention_account_days' => (string) (int) config( 'artisanpack.privacy.policy.retention_account_days', 90 ),
		];

		return array_merge( $defaults, $data );
	}

	/**
	 * Strips conditional `{{#key}}…{{/key}}` blocks whose value is empty,
	 * keeping the inner content when the value is truthy. Run before
	 * placeholder substitution so empty defaults vanish cleanly.
	 *
	 * @since 1.0.0
	 *
	 * @param  string                $template Raw template body.
	 * @param  array<string, mixed>  $data     Placeholder values.
	 *
	 * @return string
	 */
	protected function renderBlockSections( string $template, array $data ): string
	{
		return (string) preg_replace_callback(
			'/{{#([a-zA-Z0-9_]+)}}(.*?){{\/\1}}/s',
			static function ( array $matches ) use ( $data ): string {
				$value = $data[ $matches[1] ] ?? null;

				if ( null === $value || '' === $value || false === $value ) {
					return '';
				}

				return $matches[2];
			},
			$template,
		);
	}

	/**
	 * Substitutes `{{placeholder}}` tokens with their corresponding values.
	 * Unknown placeholders are left intact so reviewers can spot them.
	 *
	 * @since 1.0.0
	 *
	 * @param  string                $template Pre-rendered template.
	 * @param  array<string, mixed>  $data     Placeholder values.
	 *
	 * @return string
	 */
	protected function renderPlaceholders( string $template, array $data ): string
	{
		return (string) preg_replace_callback(
			'/{{\s*([a-zA-Z0-9_]+)\s*}}/',
			static function ( array $matches ) use ( $data ): string {
				$key = $matches[1];

				if ( ! array_key_exists( $key, $data ) ) {
					return $matches[0];
				}

				return (string) $data[ $key ];
			},
			$template,
		);
	}

	/**
	 * Resolves the `created_by` value down to a user identifier.
	 *
	 * @since 1.0.0
	 *
	 * @param  int|Model|null  $createdBy Author seed.
	 *
	 * @return int|null
	 */
	protected function resolveCreatedBy( mixed $createdBy ): ?int
	{
		if ( $createdBy instanceof Model ) {
			$key = $createdBy->getKey();

			return is_numeric( $key ) ? (int) $key : null;
		}

		if ( is_numeric( $createdBy ) ) {
			return (int) $createdBy;
		}

		return null;
	}
}
