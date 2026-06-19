<?php

/**
 * DataRequestProcessing — notifies the subject that processing has started.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class DataRequestProcessing extends DataRequestNotification
{
	public function toMail( object $notifiable ): MailMessage
	{
		return ( new MailMessage() )
			->subject( __( 'Your data request is being processed' ) )
			->line( __( 'We have started processing your :type request (#:id).', [
				'type' => $this->request->type,
				'id'   => $this->request->id,
			] ) );
	}

	protected function notificationKey(): string
	{
		return 'processing';
	}
}
