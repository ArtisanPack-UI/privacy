<?php

/**
 * DataRequestLog model — audit-trail entry for a data subject request.
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

use ArtisanPackUI\Privacy\Database\Factories\DataRequestLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for the `privacy_data_request_logs` table.
 *
 * @property int          $id
 * @property int          $data_request_id
 * @property string       $action
 * @property string|null  $description
 * @property array|null   $metadata
 * @property int|null     $performed_by
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */
class DataRequestLog extends Model
{
	use HasFactory;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'privacy_data_request_logs';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'data_request_id',
		'action',
		'description',
		'metadata',
		'performed_by',
	];

	/**
	 * The data request this log entry belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsTo
	 */
	public function dataRequest(): BelongsTo
	{
		return $this->belongsTo( DataRequest::class, 'data_request_id' );
	}

	/**
	 * Newly created factory instance for this model.
	 *
	 * @since 1.0.0
	 *
	 * @return DataRequestLogFactory
	 */
	protected static function newFactory(): DataRequestLogFactory
	{
		return DataRequestLogFactory::new();
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
			'metadata' => 'array',
		];
	}
}
