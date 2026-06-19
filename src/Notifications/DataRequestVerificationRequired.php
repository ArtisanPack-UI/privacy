<?php

/**
 * DataRequestVerificationRequired — sends the subject the verification link.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Notifications;

use ArtisanPackUI\Privacy\Services\VerificationService;
use Illuminate\Notifications\Messages\MailMessage;

class DataRequestVerificationRequired extends DataRequestNotification
{
	public function toMail( object $notifiable ): MailMessage
	{
		$url = app( VerificationService::class )->verificationUrl( $this->request ) ?? '#';

		return ( new MailMessage() )
			->subject( __( 'Verify your data request' ) )
			->line( __( 'Confirm that you submitted the :type request (#:id) by visiting the link below.', [
				'type' => $this->request->type,
				'id'   => $this->request->id,
			] ) )
			->action( __( 'Verify Request' ), $url )
			->line( __( 'This link expires in :minutes minutes.', [
				'minutes' => (int) config( 'artisanpack.privacy.verification.token_ttl_minutes', 60 ),
			] ) );
	}

	public function toArray( object $notifiable ): array
	{
		return array_merge( parent::toArray( $notifiable ), [
			'verification_url' => app( VerificationService::class )->verificationUrl( $this->request ),
		] );
	}

	protected function notificationKey(): string
	{
		return 'verification';
	}
}
