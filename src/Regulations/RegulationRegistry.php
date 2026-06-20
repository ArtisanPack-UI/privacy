<?php

/**
 * RegulationRegistry — application-wide directory of privacy regulations.
 *
 * Resolves regulation instances by key, lets applications register custom
 * implementations, and answers "which regulations apply to this request?".
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Regulations;

use ArtisanPackUI\Privacy\Contracts\PrivacyRegulation;
use Illuminate\Http\Request;

/**
 * Per-application registry of regulation implementations.
 *
 * Held as a singleton in the container; rebound with custom instances by
 * application code that ships its own regulation class.
 *
 * @since 1.0.0
 */
class RegulationRegistry
{
	/**
	 * Resolved instances keyed by stable regulation key.
	 *
	 * @var array<string, PrivacyRegulation>
	 */
	protected array $instances = [];

	/**
	 * Class-name overrides keyed by stable regulation key. Used to inject
	 * dedicated subclasses (`GdprRegulation`, `CcpaRegulation`, …) before
	 * any instance has been resolved.
	 *
	 * @var array<string, class-string<PrivacyRegulation>>
	 */
	protected array $classMap = [];

	/**
	 * Registers a regulation instance under the supplied key. Replaces any
	 * existing instance for that key.
	 *
	 * @since 1.0.0
	 *
	 * @param  PrivacyRegulation  $regulation Regulation to register.
	 *
	 * @return void
	 */
	public function register( PrivacyRegulation $regulation ): void
	{
		$this->instances[ $regulation->key() ] = $regulation;
	}

	/**
	 * Registers a regulation class to be lazily instantiated when its key is
	 * first requested.
	 *
	 * @since 1.0.0
	 *
	 * @param  string                              $key   Regulation key.
	 * @param  class-string<PrivacyRegulation>     $class Concrete class name.
	 *
	 * @return void
	 */
	public function registerClass( string $key, string $class ): void
	{
		$this->classMap[ $key ] = $class;
		unset( $this->instances[ $key ] );
	}

	/**
	 * Returns the regulation instance for the supplied key, instantiating a
	 * config-driven fallback when no dedicated class is registered.
	 *
	 * Returns null when the key is not present in `regulations.*` at all.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $key Regulation key.
	 *
	 * @return PrivacyRegulation|null
	 */
	public function get( string $key ): ?PrivacyRegulation
	{
		if ( isset( $this->instances[ $key ] ) ) {
			return $this->instances[ $key ];
		}

		if ( isset( $this->classMap[ $key ] ) ) {
			$instance = app( $this->classMap[ $key ] );

			if ( $instance instanceof PrivacyRegulation ) {
				return $this->instances[ $key ] = $instance;
			}
		}

		$regulations = (array) config( 'artisanpack.privacy.regulations', [] );

		if ( ! array_key_exists( $key, $regulations ) ) {
			return null;
		}

		return $this->instances[ $key ] = new GenericRegulation( $key );
	}

	/**
	 * Returns every enabled regulation keyed by stable identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, PrivacyRegulation>
	 */
	public function all(): array
	{
		$out         = [];
		$regulations = (array) config( 'artisanpack.privacy.regulations', [] );

		foreach ( array_keys( $regulations ) as $key ) {
			if ( true !== ( $regulations[ $key ]['enabled'] ?? false ) ) {
				continue;
			}

			$instance = $this->get( (string) $key );

			if ( $instance instanceof PrivacyRegulation ) {
				$out[ (string) $key ] = $instance;
			}
		}

		return $out;
	}

	/**
	 * Returns every regulation that applies to the supplied request — a
	 * visitor may be subject to several (e.g. an EU resident in California).
	 *
	 * @since 1.0.0
	 *
	 * @param  Request  $request Incoming request.
	 *
	 * @return array<int, PrivacyRegulation>
	 */
	public function applicable( Request $request ): array
	{
		$matches = [];

		foreach ( $this->all() as $regulation ) {
			if ( $regulation->applies( $request ) ) {
				$matches[] = $regulation;
			}
		}

		return $matches;
	}

	/**
	 * Returns the single highest-priority regulation for the supplied
	 * request, or null when none apply.
	 *
	 * Priority follows the order keys appear in `regulations.*` — applications
	 * that need a different precedence can reorder the config or rebind the
	 * registry with a custom implementation.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request|null  $request  Request to evaluate. Defaults to the current request.
	 *
	 * @return PrivacyRegulation|null
	 */
	public function applicableFor( ?Request $request = null ): ?PrivacyRegulation
	{
		$request ??= request();

		if ( ! $request instanceof Request ) {
			return null;
		}

		$matches = $this->applicable( $request );

		return $matches[0] ?? null;
	}

	/**
	 * Forgets every cached instance and class-map binding. Test-only helper.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function flush(): void
	{
		$this->instances = [];
		$this->classMap  = [];
	}
}
