<?php

declare( strict_types=1 );

namespace Tests\Support;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal Eloquent model used as a consent/data-request subject in tests.
 *
 * Tied to a temporary `test_subjects` table created on demand by tests
 * that need a real morphable record. Implements {@see Authenticatable}
 * so it can be passed to `actingAs()` in Livewire and HTTP tests.
 *
 * @since 1.0.0
 */
class TestSubject extends Model implements Authenticatable
{
	use AuthenticatableTrait;

	public $timestamps = false;

	protected $table = 'test_subjects';

	protected $guarded = [];
}
