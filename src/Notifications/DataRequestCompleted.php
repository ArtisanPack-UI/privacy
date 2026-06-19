<?php

/**
 * DataRequestCompleted — delivers the final result (or download link) for
 * a completed data subject request.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class DataRequestCompleted extends DataRequestNotification
{
	public function __construct( public \ArtisanPackUI\Privacy\Models\DataRequest $request, public ?string $downloadUrl = null )
	{
		parent::__construct( $request );
	}

	public function toMail( object $notifiable ): MailMessage
	{
		$message = ( new MailMessage() )
			->subject( __( 'Your data request is complete' ) )
			->line( __( 'Your :type request (#:id) has been completed.', [
				'type' => $this->request->type,
				'id'   => $this->request->id,
			] ) );

		if ( null !== $this->downloadUrl ) {
			$message->action( __( 'Download Your Data' ), $this->downloadUrl );
			$message->line( __( 'This download link will expire shortly for your security.' ) );
		}

		return $message;
	}

	public function toArray( object $notifiable ): array
	{
		return array_merge( parent::toArray( $notifiable ), [
			'download_url' => $this->downloadUrl,
		] );
	}

	protected function notificationKey(): string
	{
		return 'completed';
	}
}
