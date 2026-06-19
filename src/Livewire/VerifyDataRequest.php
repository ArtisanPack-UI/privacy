<?php

/**
 * VerifyDataRequest Livewire component — confirms a data subject request's
 * identity verification token.
 *
 * Embeddable on the host application's verification page. Renders the
 * current verification state, lets the subject confirm, and transitions
 * the underlying {@see \ArtisanPackUI\Privacy\Models\DataRequest} from
 * `pending` → `processing` via {@see VerificationService}.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Livewire;

use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Services\VerificationService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Livewire verification component.
 *
 * @since 1.0.0
 */
class VerifyDataRequest extends Component
{
	#[Locked]
	public string $token = '';

	public ?DataRequest $dataRequest = null;

	public bool $expired = false;

	public bool $confirmed = false;

	public ?string $message = null;

	/**
	 * Resolve the token's request when the component mounts.
	 *
	 * @since 1.0.0
	 *
	 * @param  string             $token  Verification token.
	 * @param  VerificationService $verifier Injected service.
	 *
	 * @return void
	 */
	public function mount( string $token, VerificationService $verifier ): void
	{
		$this->token       = $token;
		$this->dataRequest = $verifier->findByToken( $token );

		if ( null !== $this->dataRequest ) {
			$this->expired   = $verifier->isExpired( $this->dataRequest );
			$this->confirmed = null !== $this->dataRequest->verified_at;
		}
	}

	/**
	 * Confirm the request.
	 *
	 * @since 1.0.0
	 *
	 * @param  VerificationService  $verifier Injected service.
	 *
	 * @return void
	 */
	public function confirm( VerificationService $verifier ): void
	{
		if ( null === $this->dataRequest ) {
			$this->message = __( 'Verification link is invalid.' );
			return;
		}

		if ( $verifier->confirm( $this->dataRequest, config( 'artisanpack.privacy.data_requests.verification_method', 'email' ) ) ) {
			$this->confirmed = true;
			$this->message   = __( 'Your identity has been verified and your request is now being processed.' );
			$this->dataRequest->refresh();
			return;
		}

		$this->message = __( 'This request has already been verified or is no longer pending.' );
	}

	/**
	 * Render the component.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'privacy::livewire.verify-data-request' );
	}
}
