<?php

/**
 * DataRequestReceived — confirms to the data subject that their request
 * was received successfully.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class DataRequestReceived extends DataRequestNotification
{
	public function toMail( object $notifiable ): MailMessage
	{
		return ( new MailMessage() )
			->subject( __( 'We received your data request' ) )
			->line( __( 'Your :type request (#:id) has been received.', [
				'type' => $this->request->type,
				'id'   => $this->request->id,
			] ) )
			->line( __( 'We will get back to you within :days days as required by the applicable regulations.', [
				'days' => optional( $this->request->due_at )->diffInDays( now() ) ?? config( 'artisanpack.privacy.data_requests.response_days.default', 30 ),
			] ) );
	}

	protected function notificationKey(): string
	{
		return 'received';
	}
}
