<?php

/**
 * CategoriesApiController — JSON endpoint listing the configured consent categories.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * GET /api/privacy/categories — read-only listing of the configured cookie
 * categories. Used by the React/Vue components to render category lists
 * without a duplicated server-rendered config.
 *
 * @since 1.0.0
 */
class CategoriesApiController extends Controller
{
	/**
	 * Handle the request.
	 *
	 * @since 1.0.0
	 *
	 * @return JsonResponse
	 */
	public function __invoke(): JsonResponse
	{
		return response()->json( [
			'categories' => (array) config( 'artisanpack.privacy.cookie_categories', [] ),
		] );
	}
}
