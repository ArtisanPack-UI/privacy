<?php

/**
 * DataRequestService — central factory for data subject requests (access/export/deletion).
 *
 * Builds the {@see DataRequest} row, sets the regulatory deadline based on
 * the configured response-day map, and dispatches the matching event so
 * downstream listeners (auto-processing, admin notifications) can react.
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

use ArtisanPackUI\Privacy\Events\DataAccessRequested;
use ArtisanPackUI\Privacy\Events\DataDeletionRequested;
use ArtisanPackUI\Privacy\Events\DataExportRequested;
use ArtisanPackUI\Privacy\Events\DataRectificationRequested;
use ArtisanPackUI\Privacy\Models\DataRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Data subject request service.
 *
 * @since 1.0.0
 */
class DataRequestService
{
	/**
	 * @param ConsentService $consents Used to resolve the applicable regulation for due-date calculation.
	 */
	public function __construct( protected ConsentService $consents )
	{
	}

	/**
	 * Creates an access request and fires {@see DataAccessRequested}.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model        $subject Subject the request is filed for.
	 * @param  string|null  $reason  Optional human-readable reason.
	 *
	 * @return DataRequest
	 */
	public function createAccessRequest( Model $subject, ?string $reason = null ): DataRequest
	{
		$request = $this->create( $subject, DataRequest::TYPE_ACCESS, $reason );

		Event::dispatch( new DataAccessRequested( $request ) );

		return $request;
	}

	/**
	 * Creates an export request and fires {@see DataExportRequested}.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model        $subject Subject the request is filed for.
	 * @param  string|null  $reason  Optional human-readable reason.
	 *
	 * @return DataRequest
	 */
	public function createExportRequest( Model $subject, ?string $reason = null ): DataRequest
	{
		$request = $this->create( $subject, DataRequest::TYPE_EXPORT, $reason );

		Event::dispatch( new DataExportRequested( $request ) );

		return $request;
	}

	/**
	 * Creates a deletion request and fires {@see DataDeletionRequested}.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model        $subject Subject the request is filed for.
	 * @param  string|null  $reason  Optional human-readable reason.
	 *
	 * @return DataRequest
	 */
	public function createDeletionRequest( Model $subject, ?string $reason = null ): DataRequest
	{
		$request = $this->create( $subject, DataRequest::TYPE_DELETION, $reason );

		Event::dispatch( new DataDeletionRequested( $request ) );

		return $request;
	}

	/**
	 * Creates a rectification request and fires {@see DataRectificationRequested}.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model        $subject Subject the request is filed for.
	 * @param  string|null  $reason  Optional human-readable reason.
	 *
	 * @return DataRequest
	 */
	public function createRectificationRequest( Model $subject, ?string $reason = null ): DataRequest
	{
		$request = $this->create( $subject, DataRequest::TYPE_RECTIFICATION, $reason );

		Event::dispatch( new DataRectificationRequested( $request ) );

		return $request;
	}

	/**
	 * Returns true when the subject has no in-flight deletion request blocking deletion.
	 *
	 * Pending or processing deletion requests are treated as blockers because
	 * cascading a second deletion would race with the first.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $subject Subject to check.
	 *
	 * @return bool
	 */
	public function canDelete( Model $subject ): bool
	{
		return ! DataRequest::query()
			->where( 'requestable_type', $subject->getMorphClass() )
			->where( 'requestable_id', $subject->getKey() )
			->ofType( DataRequest::TYPE_DELETION )
			->whereIn( 'status', [ DataRequest::STATUS_PENDING, DataRequest::STATUS_PROCESSING ] )
			->exists();
	}

	/**
	 * Persists a request row and computes its regulatory deadline.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model        $subject Subject the request is filed for.
	 * @param  string       $type    Request type constant from {@see DataRequest}.
	 * @param  string|null  $reason  Optional human-readable reason.
	 *
	 * @return DataRequest
	 */
	protected function create( Model $subject, string $type, ?string $reason ): DataRequest
	{
		$regulation = $this->consents->getApplicableRegulation();

		return DataRequest::query()->create( [
			'requestable_type'   => $subject->getMorphClass(),
			'requestable_id'     => $subject->getKey(),
			'type'               => $type,
			'status'             => DataRequest::STATUS_PENDING,
			'regulation'         => $regulation,
			'reason'             => $reason,
			'verification_token' => Str::random( 40 ),
			'due_at'             => $this->resolveDueDate( $regulation ),
		] );
	}

	/**
	 * Computes the response deadline from `data_requests.response_days`.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|null  $regulation Regulation key or null.
	 *
	 * @return Carbon|null
	 */
	protected function resolveDueDate( ?string $regulation ): ?Carbon
	{
		$days = null !== $regulation
			? config( "artisanpack.privacy.data_requests.response_days.{$regulation}" )
			: null;

		$days ??= config( 'artisanpack.privacy.data_requests.response_days.default' );

		return null === $days ? null : now()->addDays( (int) $days );
	}
}
