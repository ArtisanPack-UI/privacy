<?php

/**
 * DataRequestNotification — base class shared by the package's data-request
 * notifications. Resolves the configured channel list and queue connection
 * once so subclasses only need to override the mail/database payloads.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Notifications;

use ArtisanPackUI\Privacy\Models\DataRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Abstract base notification.
 *
 * @since 1.0.0
 */
abstract class DataRequestNotification extends Notification implements ShouldQueue
{
	use Queueable;

	/**
	 * @param DataRequest $request The data request the notification relates to.
	 */
	public function __construct( public DataRequest $request )
	{
		$this->onQueue( (string) config( 'artisanpack.privacy.notifications.queue', 'default' ) );
	}

	/**
	 * Returns the configured channels for this notification key (`received`,
	 * `verification`, etc.), falling back to the package-wide default.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function via( object $notifiable ): array
	{
		$default  = (array) config( 'artisanpack.privacy.notifications.channels', [ 'mail' ] );
		$override = (array) config( 'artisanpack.privacy.notifications.' . $this->notificationKey() . '.channels', $default );

		return [] === $override ? $default : $override;
	}

	/**
	 * Default database payload — subclasses may override to add fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function toArray( object $notifiable ): array
	{
		return [
			'data_request_id' => $this->request->id,
			'type'            => $this->request->type,
			'status'          => $this->request->status,
			'notification'    => $this->notificationKey(),
		];
	}

	/**
	 * Stable identifier used when looking up per-notification overrides in
	 * the config (e.g. `notifications.verification.channels`).
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	abstract protected function notificationKey(): string;
}
