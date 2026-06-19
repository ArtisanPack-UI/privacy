<?php

declare( strict_types=1 );

namespace Tests\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Minimal Eloquent model used as a consent/data-request subject in tests.
 *
 * Tied to a temporary `test_subjects` table created on demand by tests
 * that need a real morphable record.
 *
 * @since 1.0.0
 */
class TestSubject extends Model
{
	public $timestamps = false;

	protected $table = 'test_subjects';

	protected $guarded = [];
}
