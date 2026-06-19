@php
	$typeLabels = [
		'access'        => __( 'Request a copy of my data' ),
		'export'        => __( 'Export my data' ),
		'deletion'      => __( 'Delete my data' ),
		'rectification' => __( 'Correct my data' ),
	];
@endphp

<div
	class="privacy-data-request-form"
	role="region"
	aria-label="{{ __( 'Privacy data request form' ) }}"
>
	@if($submitted)
		<div
			class="alert alert-success"
			role="status"
			aria-live="polite"
		>
			<h3 class="font-semibold">
				{{ __( 'Your request was submitted.' ) }}
			</h3>
			<p class="text-sm opacity-90">
				@if($verificationSent)
					{{ __( 'We have emailed you a verification link. Confirm your identity to start processing.' ) }}
				@else
					{{ __( 'Our team has been notified and will follow up shortly.' ) }}
				@endif
			</p>
			<button
				type="button"
				class="btn btn-link mt-2 px-0"
				wire:click="startNewRequest"
			>
				{{ __( 'Submit another request' ) }}
			</button>
		</div>
	@else
		<form wire:submit.prevent="submit" class="flex flex-col gap-4">
			<div class="form-control">
				<label for="privacy-data-request-type" class="label">
					<span class="label-text font-medium">{{ __( 'Request type' ) }}</span>
				</label>
				<select
					id="privacy-data-request-type"
					class="select select-bordered"
					wire:model.live="type"
					required
				>
					<option value="">{{ __( 'Choose one…' ) }}</option>
					@foreach($requestTypes as $option)
						<option value="{{ $option }}">
							{{ $typeLabels[$option] ?? $option }}
						</option>
					@endforeach
				</select>
				@error('type')
					<p class="text-error text-sm mt-1" role="alert">{{ $message }}</p>
				@enderror
			</div>

			<div class="form-control">
				<label for="privacy-data-request-reason" class="label">
					<span class="label-text font-medium">
						{{ __( 'Reason' ) }}
						@if(! $requireReason)
							<span class="opacity-60 font-normal">{{ __( '(optional)' ) }}</span>
						@endif
					</span>
				</label>
				<textarea
					id="privacy-data-request-reason"
					class="textarea textarea-bordered"
					rows="3"
					wire:model.defer="reason"
					maxlength="1000"
				></textarea>
				@error('reason')
					<p class="text-error text-sm mt-1" role="alert">{{ $message }}</p>
				@enderror
			</div>

			<div class="flex justify-end gap-2">
				<button
					type="submit"
					class="btn btn-primary"
					wire:loading.attr="disabled"
					wire:target="submit"
				>
					<span wire:loading.remove wire:target="submit">{{ __( 'Submit request' ) }}</span>
					<span wire:loading wire:target="submit">{{ __( 'Submitting…' ) }}</span>
				</button>
			</div>
		</form>
	@endif
</div>
