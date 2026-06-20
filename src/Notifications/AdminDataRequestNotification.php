<?php

/**
 * AdminDataRequestNotification — notifies the configured admin address of a
 * new incoming data subject request.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class AdminDataRequestNotification extends DataRequestNotification
{
	public function toMail( object $notifiable ): MailMessage
	{
		return ( new MailMessage() )
			->subject( __( 'New data request received (:type)', [ 'type' => $this->request->type ] ) )
			->line( __( 'A new :type request (#:id) was filed by :subject_type #:subject_id.', [
				'type'         => $this->request->type,
				'id'           => $this->request->id,
				'subject_type' => class_basename( $this->request->requestable_type ),
				'subject_id'   => $this->request->requestable_id,
			] ) )
			->line( __( 'Status: :status. Due: :due.', [
				'status' => $this->request->status,
				'due'    => optional( $this->request->due_at )->toDayDateTimeString() ?? '—',
			] ) );
	}

	protected function notificationKey(): string
	{
		return 'admin';
	}
}
