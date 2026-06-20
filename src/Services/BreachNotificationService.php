<?php

/**
 * BreachNotificationService — reports breaches and runs the notification workflow.
 *
 * Persists a {@see BreachNotification} record, fires the {@see DataBreach}
 * event for downstream listeners, and exposes deadline-aware helpers for
 * notifying supervisory authorities and affected users. Notification
 * delivery (authority + user) is delegated to Laravel's Mail facade with
 * the publishable markdown templates from `resources/views/breach-templates`.
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

use ArtisanPackUI\Privacy\Events\DataBreach;
use ArtisanPackUI\Privacy\Models\BreachNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Breach notification workflow service.
 *
 * @since 1.0.0
 */
class BreachNotificationService
{
	public const REGULATION_GDPR    = 'gdpr';
	public const REGULATION_CCPA    = 'ccpa';
	public const REGULATION_DEFAULT = 'default';

	/**
	 * Default notification window in hours when no regulation-specific
	 * value is configured.
	 *
	 * @var int
	 */
	public const DEFAULT_NOTIFICATION_HOURS = 72;

	/**
	 * Record a new breach incident and fire the {@see DataBreach} event.
	 *
	 * Required keys: `description`, `severity`, `data_types_affected`.
	 * Optional keys map directly to the BreachNotification columns —
	 * `occurred_at`, `records_affected`, `affected_users`, `cause`,
	 * `remediation`, `status`, `reported_by`. A `discovered_at` value is
	 * defaulted to `now()` when missing, and a `reference_number` is
	 * generated when absent.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data Breach attributes.
	 *
	 * @return BreachNotification
	 */
	public function reportBreach( array $data ): BreachNotification
	{
		foreach ( [ 'description', 'severity', 'data_types_affected' ] as $required ) {
			if ( ! array_key_exists( $required, $data ) || null === $data[ $required ] ) {
				throw new InvalidArgumentException(
					"BreachNotificationService::reportBreach requires the '{$required}' key.",
				);
			}
		}

		$attributes = [
			'reference_number'    => $data['reference_number'] ?? $this->generateReferenceNumber(),
			'discovered_at'       => $data['discovered_at'] ?? now(),
			'occurred_at'         => $data['occurred_at'] ?? null,
			'severity'            => (string) $data['severity'],
			'description'         => (string) $data['description'],
			'data_types_affected' => (array) $data['data_types_affected'],
			'records_affected'    => isset( $data['records_affected'] ) ? (int) $data['records_affected'] : null,
			'affected_users'      => $data['affected_users'] ?? null,
			'cause'               => isset( $data['cause'] ) ? (string) $data['cause'] : null,
			'remediation'         => isset( $data['remediation'] ) ? (string) $data['remediation'] : null,
			'status'              => (string) ( $data['status'] ?? BreachNotification::STATUS_INVESTIGATING ),
			'reported_by'         => isset( $data['reported_by'] ) ? (int) $data['reported_by'] : null,
		];

		$breach = DB::transaction( static function () use ( $attributes ): BreachNotification {
			return BreachNotification::query()->create( $attributes );
		} );

		Event::dispatch( new DataBreach( $breach ) );

		return $breach;
	}

	/**
	 * Send the authority notification email and stamp `authority_notified_at`.
	 *
	 * Reads the recipient from `artisanpack.privacy.breach.authority_email`
	 * — when no recipient is configured, the method records the
	 * intent in `notifications_sent` and returns without dispatching mail
	 * so applications can preview the workflow before wiring real delivery.
	 *
	 * @since 1.0.0
	 *
	 * @param  BreachNotification  $breach Target breach.
	 *
	 * @return bool True when the email was dispatched.
	 */
	public function notifyAuthority( BreachNotification $breach ): bool
	{
		$recipient = (string) config( 'artisanpack.privacy.breach.authority_email', '' );
		$sent      = false;

		$body = $this->renderTemplate( 'authority', [
			'breach'       => $breach,
			'organization' => (array) config( 'artisanpack.privacy.breach.organization', [] ),
			'dpo'          => (array) config( 'artisanpack.privacy.breach.dpo', [] ),
		] );

		if ( '' !== trim( $recipient ) ) {
			Mail::raw( $body, static function ( $message ) use ( $recipient, $breach ): void {
				$message
					->to( $recipient )
					->subject( sprintf( '[Privacy] Breach notification (%s)', $breach->reference_number ) );
			} );

			$sent = true;
		}

		$this->recordNotification( $breach, [
			'channel'      => 'authority',
			'recipient'    => '' === trim( $recipient ) ? null : $recipient,
			'dispatched'   => $sent,
			'notified_at'  => now()->toIso8601String(),
		] );

		$breach->forceFill( [ 'authority_notified_at' => now() ] )->save();

		return $sent;
	}

	/**
	 * Send the user notification email to every affected user and stamp
	 * `users_notified_at`. Returns the number of users successfully
	 * notified.
	 *
	 * Each entry of `affected_users` may be an email string or an
	 * associative array with `email` (required) and optional `name`,
	 * `data` (per-user data scope) and `locale` keys.
	 *
	 * @since 1.0.0
	 *
	 * @param  BreachNotification  $breach Target breach.
	 *
	 * @return int
	 */
	public function notifyAffectedUsers( BreachNotification $breach ): int
	{
		$affected = (array) ( $breach->affected_users ?? [] );
		$count    = 0;

		foreach ( $affected as $entry ) {
			$email = is_array( $entry ) ? (string) ( $entry['email'] ?? '' ) : (string) $entry;

			if ( '' === trim( $email ) ) {
				continue;
			}

			$body = $this->renderTemplate( 'user', [
				'breach'       => $breach,
				'user'         => is_array( $entry ) ? $entry : [ 'email' => $email ],
				'organization' => (array) config( 'artisanpack.privacy.breach.organization', [] ),
			] );

			Mail::raw( $body, static function ( $message ) use ( $email, $breach ): void {
				$message
					->to( $email )
					->subject( sprintf( '[Privacy] Important notice regarding your data (%s)', $breach->reference_number ) );
			} );

			++$count;
		}

		$this->recordNotification( $breach, [
			'channel'         => 'users',
			'recipient_count' => $count,
			'notified_at'     => now()->toIso8601String(),
		] );

		$breach->forceFill( [ 'users_notified_at' => now() ] )->save();

		return $count;
	}

	/**
	 * Returns the deadline by which the supervisory authority must be
	 * notified for the given breach, based on the configured regulation
	 * window.
	 *
	 * @since 1.0.0
	 *
	 * @param  BreachNotification  $breach     Breach being assessed.
	 * @param  string              $regulation Regulation key. Defaults to GDPR.
	 *
	 * @return Carbon
	 */
	public function getNotificationDeadline( BreachNotification $breach, string $regulation = self::REGULATION_GDPR ): Carbon
	{
		$hours     = $this->resolveNotificationWindow( $regulation );
		$reference = $breach->discovered_at instanceof Carbon ? $breach->discovered_at : Carbon::parse( (string) $breach->discovered_at );

		return $reference->copy()->addHours( $hours );
	}

	/**
	 * Returns true when the breach is still within the notification window
	 * for the given regulation.
	 *
	 * @since 1.0.0
	 *
	 * @param  BreachNotification  $breach     Breach being assessed.
	 * @param  string              $regulation Regulation key.
	 *
	 * @return bool
	 */
	public function isWithinNotificationWindow( BreachNotification $breach, string $regulation = self::REGULATION_GDPR ): bool
	{
		return ! $this->getNotificationDeadline( $breach, $regulation )->isPast();
	}

	/**
	 * Returns the regulation-specific notification window in hours.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $regulation Regulation key.
	 *
	 * @return int
	 */
	public function resolveNotificationWindow( string $regulation = self::REGULATION_GDPR ): int
	{
		$key = self::REGULATION_DEFAULT === $regulation ? self::REGULATION_GDPR : $regulation;

		$configured = config(
			"artisanpack.privacy.regulations.{$key}.breach_notification_hours",
			self::DEFAULT_NOTIFICATION_HOURS,
		);

		return is_numeric( $configured ) ? max( 1, (int) $configured ) : self::DEFAULT_NOTIFICATION_HOURS;
	}

	/**
	 * Generate a unique breach reference number.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function generateReferenceNumber(): string
	{
		return 'BR-' . now()->format( 'Ymd' ) . '-' . Str::upper( Str::random( 6 ) );
	}

	/**
	 * Append a notification entry to the breach's `notifications_sent` log.
	 *
	 * @since 1.0.0
	 *
	 * @param  BreachNotification    $breach Target breach.
	 * @param  array<string, mixed>  $entry  Entry to record.
	 *
	 * @return void
	 */
	protected function recordNotification( BreachNotification $breach, array $entry ): void
	{
		$entries   = (array) ( $breach->notifications_sent ?? [] );
		$entries[] = $entry;

		$breach->forceFill( [ 'notifications_sent' => $entries ] )->save();
	}

	/**
	 * Render a publishable breach template by name, falling back to the
	 * package's built-in template when the application hasn't overridden it.
	 *
	 * @since 1.0.0
	 *
	 * @param  string                $name Template name (`authority` or `user`).
	 * @param  array<string, mixed>  $data View data.
	 *
	 * @return string
	 */
	protected function renderTemplate( string $name, array $data ): string
	{
		$view = "privacy::breach-templates.{$name}";

		if ( View::exists( $view ) ) {
			return trim( View::make( $view, $data )->render() );
		}

		return sprintf(
			"Breach reference: %s\nSeverity: %s\nDescription:\n%s",
			(string) ( $data['breach']->reference_number ?? '' ),
			(string) ( $data['breach']->severity ?? '' ),
			(string) ( $data['breach']->description ?? '' ),
		);
	}
}
