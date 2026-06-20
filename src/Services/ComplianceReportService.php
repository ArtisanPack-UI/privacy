<?php

/**
 * ComplianceReportService — aggregates consent, request, and breach metrics
 * for the admin compliance report panel.
 *
 * The service is intentionally framework-agnostic — it returns plain
 * arrays so the Livewire, React, and Vue admin components can consume
 * the same payload (the JSON API for React/Vue ultimately delegates here
 * too).
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

use ArtisanPackUI\Privacy\Models\BreachNotification;
use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Models\DataRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates compliance metrics for a date range and optional regulation.
 *
 * @since 1.0.0
 */
class ComplianceReportService
{
	/**
	 * Build the full compliance report payload for the supplied range.
	 *
	 * @since 1.0.0
	 *
	 * @param  Carbon       $from       Inclusive start of the reporting window.
	 * @param  Carbon       $to         Inclusive end of the reporting window.
	 * @param  string|null  $regulation Optional regulation filter (`gdpr`, `ccpa`, …).
	 *
	 * @return array<string, mixed>
	 */
	public function generate( Carbon $from, Carbon $to, ?string $regulation = null ): array
	{
		return [
			'meta'     => [
				'from'         => $from->toIso8601String(),
				'to'           => $to->toIso8601String(),
				'regulation'   => $regulation,
				'generated_at' => now()->toIso8601String(),
			],
			'consents' => $this->consentStats( $from, $to, $regulation ),
			'requests' => $this->requestStats( $from, $to, $regulation ),
			'breaches' => $this->breachStats( $from, $to ),
		];
	}

	/**
	 * Consent metrics: totals by category, grant vs withdrawal rates.
	 *
	 * @since 1.0.0
	 *
	 * @param  Carbon       $from       Window start.
	 * @param  Carbon       $to         Window end.
	 * @param  string|null  $regulation Optional regulation filter.
	 *
	 * @return array<string, mixed>
	 */
	public function consentStats( Carbon $from, Carbon $to, ?string $regulation = null ): array
	{
		$base = Consent::query()->whereBetween( 'created_at', [ $from, $to ] );

		if ( null !== $regulation && '' !== $regulation ) {
			$base->where( 'regulation', $regulation );
		}

		$granted   = ( clone $base )->where( 'granted', true )->count();
		$withdrawn = ( clone $base )->whereNotNull( 'withdrawn_at' )->count();
		$expired   = ( clone $base )
			->whereNotNull( 'expires_at' )
			->where( 'expires_at', '<', now() )
			->count();
		$total     = ( clone $base )->count();

		$byCategory = ( clone $base )
			->select( 'category', DB::raw( 'count(*) as total' ) )
			->groupBy( 'category' )
			->pluck( 'total', 'category' )
			->all();

		return [
			'total'              => $total,
			'granted'            => $granted,
			'withdrawn'          => $withdrawn,
			'expired'            => $expired,
			'grant_rate'         => $total > 0 ? round( ( $granted / $total ) * 100, 2 ) : 0.0,
			'withdrawal_rate'    => $total > 0 ? round( ( $withdrawn / $total ) * 100, 2 ) : 0.0,
			'by_category'        => $byCategory,
		];
	}

	/**
	 * Request metrics: volume by type, response time percentiles, deadline
	 * compliance percentage, overdue count.
	 *
	 * @since 1.0.0
	 *
	 * @param  Carbon       $from       Window start.
	 * @param  Carbon       $to         Window end.
	 * @param  string|null  $regulation Optional regulation filter.
	 *
	 * @return array<string, mixed>
	 */
	public function requestStats( Carbon $from, Carbon $to, ?string $regulation = null ): array
	{
		$base = DataRequest::query()->whereBetween( 'created_at', [ $from, $to ] );

		if ( null !== $regulation && '' !== $regulation ) {
			$base->where( 'regulation', $regulation );
		}

		$total      = ( clone $base )->count();
		$byType     = ( clone $base )
			->select( 'type', DB::raw( 'count(*) as total' ) )
			->groupBy( 'type' )
			->pluck( 'total', 'type' )
			->all();

		$completed  = ( clone $base )->where( 'status', DataRequest::STATUS_COMPLETED )->get();
		$overdue    = ( clone $base )
			->whereNotNull( 'due_at' )
			->where( 'due_at', '<', now() )
			->whereNotIn( 'status', [ DataRequest::STATUS_COMPLETED, DataRequest::STATUS_REJECTED ] )
			->count();

		$responseSeconds      = [];
		$responseByType       = [];
		$withinDeadlineCount  = 0;
		$completedWithDueDate = 0;

		foreach ( $completed as $request ) {
			if ( null === $request->completed_at || null === $request->created_at ) {
				continue;
			}

			$seconds                            = (int) abs( $request->created_at->diffInSeconds( $request->completed_at, false ) );
			$responseSeconds[]                  = $seconds;
			$responseByType[ $request->type ][] = $seconds;

			if ( null !== $request->due_at ) {
				++$completedWithDueDate;

				if ( $request->completed_at->lte( $request->due_at ) ) {
					++$withinDeadlineCount;
				}
			}
		}

		$averageResponseSeconds = [] === $responseSeconds
			? 0.0
			: round( array_sum( $responseSeconds ) / count( $responseSeconds ), 2 );

		$averageByType = [];
		foreach ( $responseByType as $type => $values ) {
			$averageByType[ $type ] = round( array_sum( $values ) / count( $values ), 2 );
		}

		return [
			'total'                       => $total,
			'overdue'                     => $overdue,
			'by_type'                     => $byType,
			'completed'                   => $completed->count(),
			'average_response_seconds'    => $averageResponseSeconds,
			'average_response_by_type'    => $averageByType,
			'percentiles_seconds'         => $this->percentiles( $responseSeconds ),
			'deadline_compliance_percent' => $completedWithDueDate > 0
				? round( ( $withinDeadlineCount / $completedWithDueDate ) * 100, 2 )
				: 0.0,
		];
	}

	/**
	 * Breach metrics: total count, severity distribution, authority
	 * notification timeline adherence.
	 *
	 * @since 1.0.0
	 *
	 * @param  Carbon  $from Window start.
	 * @param  Carbon  $to   Window end.
	 *
	 * @return array<string, mixed>
	 */
	public function breachStats( Carbon $from, Carbon $to ): array
	{
		$base = BreachNotification::query()->whereBetween( 'discovered_at', [ $from, $to ] );

		$total      = ( clone $base )->count();
		$bySeverity = ( clone $base )
			->select( 'severity', DB::raw( 'count(*) as total' ) )
			->groupBy( 'severity' )
			->pluck( 'total', 'severity' )
			->all();

		$service        = app( BreachNotificationService::class );
		$onTime         = 0;
		$late           = 0;
		$pending        = 0;
		$notifiedTotal  = 0;

		( clone $base )->chunkById( 200, static function ( $rows ) use ( $service, &$onTime, &$late, &$pending, &$notifiedTotal ): void {
			foreach ( $rows as $breach ) {
				if ( null === $breach->authority_notified_at ) {
					++$pending;
					continue;
				}

				$deadline = $service->getNotificationDeadline( $breach );
				++$notifiedTotal;

				if ( $breach->authority_notified_at->lte( $deadline ) ) {
					++$onTime;
				} else {
					++$late;
				}
			}
		} );

		return [
			'total'                                     => $total,
			'by_severity'                               => $bySeverity,
			'authority_notified_on_time'                => $onTime,
			'authority_notified_late'                   => $late,
			'authority_notification_pending'            => $pending,
			'authority_notification_compliance_percent' => $notifiedTotal > 0
				? round( ( $onTime / $notifiedTotal ) * 100, 2 )
				: 0.0,
		];
	}

	/**
	 * Compute p50/p90/p99 percentile values from a numeric series.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, float|int>  $values Values to summarize.
	 *
	 * @return array<string, float>
	 */
	protected function percentiles( array $values ): array
	{
		if ( [] === $values ) {
			return [ 'p50' => 0.0, 'p90' => 0.0, 'p99' => 0.0 ];
		}

		sort( $values );
		$count = count( $values );

		$pick = static function ( float $rank ) use ( $values, $count ): float {
			$index = (int) max( 0, min( $count - 1, ceil( $rank * $count ) - 1 ) );

			return (float) $values[ $index ];
		};

		return [
			'p50' => $pick( 0.5 ),
			'p90' => $pick( 0.9 ),
			'p99' => $pick( 0.99 ),
		];
	}
}
