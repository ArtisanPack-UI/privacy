<?php

/**
 * Privacy helper functions.
 *
 * This file contains global helper functions for the package.
 * Add your custom helper functions below.
 *
 * @since      1.0.0
 * @subpackage Privacy
 *
 * @package    ArtisanPack_UI
 */

use ArtisanPackUI\Privacy\Privacy;

if ( ! function_exists( 'privacy' ) ) {
	/**
	 * Get the Privacy instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Privacy
	 */
	function privacy(): Privacy
	{
		return app( 'privacy' );
	}
}

// Add your custom helper functions below
