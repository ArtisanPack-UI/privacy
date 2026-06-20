<?php

/**
 * HasPersonalData trait — alias namespace.
 *
 * The canonical implementation lives at
 * {@see \ArtisanPackUI\Privacy\Concerns\HasPersonalData} (the Laravel-style
 * namespace). This alias is provided so applications following the more
 * generic `App\Traits\…` convention can `use ArtisanPackUI\Privacy\Traits\HasPersonalData`
 * without having to think about the Concerns alternative.
 *
 * The package's services still check for the canonical trait via
 * `class_uses_recursive()`, so consumers may import either namespace —
 * both register the same underlying trait.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Traits;

use ArtisanPackUI\Privacy\Concerns\HasPersonalData as CanonicalHasPersonalData;

trait HasPersonalData
{
	use CanonicalHasPersonalData;
}
