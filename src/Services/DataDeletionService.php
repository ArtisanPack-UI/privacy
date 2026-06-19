<?php

/**
 * DataDeletionService — applies configurable deletion strategies to data subjects.
 *
 * Supports hard delete, anonymize, and pseudonymize strategies plus a grace
 * period (the subject's deletion is scheduled and can be cancelled until
 * its `scheduled_for` timestamp passes). Cascade deletion follows the
 * relations declared by the model's {@see HasPersonalData} trait.
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

use ArtisanPackUI\Privacy\Concerns\HasPersonalData;
use ArtisanPackUI\Privacy\Events\DataSubjectDeleted;
use ArtisanPackUI\Privacy\Events\DataSubjectDeletionScheduled;
use ArtisanPackUI\Privacy\Models\ScheduledDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Data subject deletion / anonymization / pseudonymization service.
 *
 * @since 1.0.0
 */
class DataDeletionService
{
	public const STRATEGY_DELETE       = 'delete';
	public const STRATEGY_ANONYMIZE    = 'anonymize';
	public const STRATEGY_PSEUDONYMIZE = 'pseudonymize';

	/**
	 * @param AnonymizationService $anonymizer Used for the anonymize/pseudonymize strategies.
	 */
	public function __construct( protected AnonymizationService $anonymizer )
	{
	}

	/**
	 * Applies the configured (or supplied) strategy to a subject.
	 *
	 * Recognised options:
	 *  - `strategy`       (string) Override the default strategy.
	 *  - `cascade`        (bool)   Override `artisanpack.privacy.deletion.cascade`.
	 *  - `relations`      (array)  Explicit relation list (overrides trait).
	 *  - `preserve_audit` (bool)   When true, audit-log rows are not pruned.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model                $subject Subject to act upon.
	 * @param  array<string, mixed> $options Override flags.
	 *
	 * @throws InvalidArgumentException When the strategy is unknown.
	 *
	 * @return bool True when the strategy applied successfully.
	 */
	public function delete( Model $subject, array $options = [] ): bool
	{
		$strategy = (string) ( $options['strategy'] ?? config( 'artisanpack.privacy.deletion.default_strategy', self::STRATEGY_ANONYMIZE ) );
		$cascade  = (bool) ( $options['cascade'] ?? config( 'artisanpack.privacy.deletion.cascade', true ) );

		$result = match ( $strategy ) {
			self::STRATEGY_DELETE       => $this->hardDelete( $subject ),
			self::STRATEGY_ANONYMIZE    => $this->anonymize( $subject ),
			self::STRATEGY_PSEUDONYMIZE => $this->pseudonymize( $subject ),
			default                     => throw new InvalidArgumentException( "Unknown deletion strategy: {$strategy}" ),
		};

		if ( $result && $cascade ) {
			$this->cascadeRelations( $subject, $strategy, $options['relations'] ?? null );
		}

		if ( $result ) {
			Event::dispatch( new DataSubjectDeleted( $subject, $strategy ) );
		}

		return $result;
	}

	/**
	 * Anonymizes the subject in place.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $subject Subject to anonymize.
	 *
	 * @return bool True when at least one field was mutated.
	 */
	public function anonymize( Model $subject ): bool
	{
		return $this->anonymizer->anonymize( $subject );
	}

	/**
	 * Pseudonymizes the subject's discoverable fields with the deterministic
	 * (non-reversible) hash strategy so distinct subjects remain distinguishable
	 * in audit logs without exposing the original values.
	 *
	 * For reversible pseudonymization (recoverable via the configured
	 * encryption key), call {@see AnonymizationService::anonymize()} directly
	 * with `STRATEGY_REVERSIBLE_PSEUDONYMIZE`.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $subject Subject to pseudonymize.
	 *
	 * @return bool
	 */
	public function pseudonymize( Model $subject ): bool
	{
		$strategies = (array) config( 'artisanpack.privacy.anonymization.strategies', [] );
		$map        = [];

		foreach ( array_keys( $strategies ) as $type ) {
			$patterns = (array) config( "artisanpack.privacy.discovery.field_patterns.{$type}", [ $type ] );

			foreach ( $patterns as $column ) {
				if ( array_key_exists( $column, $subject->getAttributes() ) ) {
					$map[ $column ] = AnonymizationService::STRATEGY_PSEUDONYMIZE;
					break;
				}
			}
		}

		return [] !== $map && $this->anonymizer->anonymize( $subject, $map );
	}

	/**
	 * Cascades a strategy to declared related models.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model                       $subject  Parent subject.
	 * @param  array<int, string>          $relations Relation names to cascade across.
	 *
	 * @return int Count of related records mutated.
	 */
	public function deleteRelated( Model $subject, array $relations ): int
	{
		$strategy = (string) config( 'artisanpack.privacy.deletion.default_strategy', self::STRATEGY_ANONYMIZE );

		return $this->cascadeRelations( $subject, $strategy, $relations );
	}

	/**
	 * Schedules a deletion for the configured (or supplied) grace period.
	 *
	 * Returns the resulting {@see ScheduledDeletion} row so callers can
	 * present the user with a "you can cancel until X" affordance.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model                $subject Subject to schedule.
	 * @param  int|null             $gracePeriodDays Override the configured grace period (days).
	 * @param  array<string, mixed> $options Free-form options stored on the row.
	 *
	 * @return ScheduledDeletion
	 */
	public function scheduleForDeletion( Model $subject, ?int $gracePeriodDays = null, array $options = [] ): ScheduledDeletion
	{
		$days = $gracePeriodDays ?? (int) config( 'artisanpack.privacy.deletion.grace_period_days', 30 );

		$scheduled = ScheduledDeletion::query()->create( [
			'deletable_type'   => $subject->getMorphClass(),
			'deletable_id'     => $subject->getKey(),
			'strategy'         => (string) ( $options['strategy'] ?? config( 'artisanpack.privacy.deletion.default_strategy', self::STRATEGY_ANONYMIZE ) ),
			'scheduled_for'    => Carbon::now()->addDays( $days ),
			'requested_by'     => $options['requested_by'] ?? null,
			'options'          => $options,
		] );

		Event::dispatch( new DataSubjectDeletionScheduled( $scheduled ) );

		return $scheduled;
	}

	/**
	 * Cancels the most recent pending scheduled deletion for the subject.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model        $subject Subject whose scheduled deletion is being cancelled.
	 * @param  string|null  $reason  Optional cancellation reason.
	 *
	 * @return bool True when a pending row was cancelled.
	 */
	public function cancelScheduledDeletion( Model $subject, ?string $reason = null ): bool
	{
		$pending = ScheduledDeletion::query()
			->where( 'deletable_type', $subject->getMorphClass() )
			->where( 'deletable_id', $subject->getKey() )
			->pending()
			->latest( 'id' )
			->first();

		if ( null === $pending ) {
			return false;
		}

		$pending->update( [
			'cancelled_at'     => Carbon::now(),
			'cancelled_reason' => $reason,
		] );

		return true;
	}

	/**
	 * Processes a due scheduled deletion and applies its strategy.
	 *
	 * Called by the scheduler/queue worker once the grace period has passed.
	 *
	 * @since 1.0.0
	 *
	 * @param  ScheduledDeletion  $row Row to process.
	 *
	 * @return bool
	 */
	public function processScheduled( ScheduledDeletion $row ): bool
	{
		if ( ! $row->isPending() ) {
			return false;
		}

		$subject = $row->deletable;

		if ( ! $subject instanceof Model ) {
			$row->update( [ 'processed_at' => Carbon::now() ] );
			return false;
		}

		$result = $this->delete( $subject, array_merge(
			(array) $row->options,
			[ 'strategy' => $row->strategy ],
		) );

		$row->update( [ 'processed_at' => Carbon::now() ] );

		return $result;
	}

	/**
	 * Hard-deletes the subject inside a transaction so cascade work is
	 * atomic with the delete call.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $subject Subject to delete.
	 *
	 * @return bool
	 */
	protected function hardDelete( Model $subject ): bool
	{
		return (bool) DB::transaction( static fn (): bool => (bool) $subject->delete() );
	}

	/**
	 * Cascades the strategy across the supplied (or trait-declared) relations.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model                     $subject   Parent subject.
	 * @param  string                    $strategy  Strategy to apply to related models.
	 * @param  array<int, string>|null   $relations Explicit relation list (overrides the trait).
	 *
	 * @return int
	 */
	protected function cascadeRelations( Model $subject, string $strategy, ?array $relations = null ): int
	{
		$relations ??= $this->usesPersonalData( $subject ) ? $subject->personalDataRelations() : [];
		$count       = 0;

		foreach ( (array) $relations as $relation ) {
			if ( ! method_exists( $subject, $relation ) ) {
				continue;
			}

			$query = $subject->{$relation}();

			if ( ! $query instanceof Relation ) {
				continue;
			}

			foreach ( $query->get() as $related ) {
				self::STRATEGY_DELETE === $strategy
					? $this->hardDelete( $related )
					: $this->delete( $related, [ 'strategy' => $strategy, 'cascade' => false ] );

				++$count;
			}
		}

		return $count;
	}

	/**
	 * Returns true when the subject uses the {@see HasPersonalData} trait.
	 *
	 * @since 1.0.0
	 *
	 * @param  Model  $subject Subject to inspect.
	 *
	 * @return bool
	 */
	protected function usesPersonalData( Model $subject ): bool
	{
		return in_array( HasPersonalData::class, class_uses_recursive( $subject ), true );
	}
}
