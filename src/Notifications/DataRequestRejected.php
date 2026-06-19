<?php

/**
 * DataRequestRejected — notifies the subject that their request was denied.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class DataRequestRejected extends DataRequestNotification
{
	public function __construct( public \ArtisanPackUI\Privacy\Models\DataRequest $request, public ?string $reason = null )
	{
		parent::__construct( $request );
	}

	public function toMail( object $notifiable ): MailMessage
	{
		$message = ( new MailMessage() )
			->subject( __( 'Your data request was not approved' ) )
			->line( __( 'Your :type request (#:id) could not be approved at this time.', [
				'type' => $this->request->type,
				'id'   => $this->request->id,
			] ) );

		if ( null !== $this->reason && '' !== $this->reason ) {
			$message->line( __( 'Reason: :reason', [ 'reason' => $this->reason ] ) );
		}

		return $message;
	}

	public function toArray( object $notifiable ): array
	{
		return array_merge( parent::toArray( $notifiable ), [
			'reason' => $this->reason,
		] );
	}

	protected function notificationKey(): string
	{
		return 'rejected';
	}
}
