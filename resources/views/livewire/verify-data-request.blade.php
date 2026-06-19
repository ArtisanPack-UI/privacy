<div class="privacy-verify-data-request">
	@if ( null === $dataRequest )
		<p class="privacy-verify-data-request__error">
			{{ __( 'Verification link is invalid or no longer available.' ) }}
		</p>
	@elseif ( $confirmed )
		<p class="privacy-verify-data-request__success">
			{{ $message ?? __( 'This request has already been verified.' ) }}
		</p>
	@elseif ( $expired )
		<p class="privacy-verify-data-request__error">
			{{ __( 'This verification link has expired. Please submit a new request.' ) }}
		</p>
	@else
		<p class="privacy-verify-data-request__intro">
			{{ __( 'Confirm you submitted request #:id (:type) to continue.', [
				'id'   => $dataRequest->id,
				'type' => $dataRequest->type,
			] ) }}
		</p>
		<button
			type="button"
			wire:click="confirm"
			wire:loading.attr="disabled"
			class="privacy-verify-data-request__button"
		>
			<span wire:loading.remove>{{ __( 'Confirm Identity' ) }}</span>
			<span wire:loading>{{ __( 'Verifying…' ) }}</span>
		</button>

		@if ( null !== $message )
			<p class="privacy-verify-data-request__message">{{ $message }}</p>
		@endif
	@endif
</div>
