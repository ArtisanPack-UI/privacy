<?php

/**
 * Privacy Facade.
 *
 * Provides static access to the Privacy class.
 *
 * @since      1.0.0
 * @subpackage Privacy
 *
 * @package    ArtisanPack_UI
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Privacy Facade.
 *
 * @since      1.0.0
 * @see        \ArtisanPackUI\Privacy\Privacy
 *
 * @subpackage Privacy
 *
 * @package    ArtisanPack_UI
 */
class Privacy extends Facade
{
	/**
	 * Get the registered name of the component.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor(): string
	{
		return 'privacy';
	}
}
