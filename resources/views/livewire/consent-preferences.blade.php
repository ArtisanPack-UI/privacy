<div class="space-y-4">
	@if($saved)
		<div
			class="alert alert-success"
			role="status"
			aria-live="polite"
		>
			<span>{{ __( 'Your preferences have been saved.' ) }}</span>
		</div>
	@endif

	<div role="group" aria-label="{{ __( 'Cookie categories' ) }}" class="space-y-3">
		@foreach($categories as $key => $category)
			@php
				$required = (bool) ($category['required'] ?? false);
				$inputId = 'privacy-preferences-toggle-' . $key;
			@endphp
			<div class="flex items-start justify-between gap-4 p-4 rounded border border-base-300">
				<div class="flex-1">
					<label for="{{ $inputId }}" class="font-medium">
						{{ $category['name'] ?? $key }}
						@if($required)
							<span class="badge badge-ghost badge-sm ml-1">
								{{ __( 'Required' ) }}
							</span>
						@endif
					</label>

					@if($showDescriptions && ! empty($category['description']))
						<p class="text-sm opacity-70 mt-1">{{ $category['description'] }}</p>
					@endif

					@if($showCookieList && ! empty($category['cookies']))
						<ul class="text-xs opacity-70 mt-2 list-disc list-inside">
							@foreach((array) $category['cookies'] as $cookie)
								<li>{{ $cookie }}</li>
							@endforeach
						</ul>
					@endif
				</div>
				<input
					id="{{ $inputId }}"
					type="checkbox"
					class="toggle toggle-primary"
					wire:model.live="consents.{{ $key }}"
					@disabled($required)
					aria-describedby="{{ $inputId }}-description"
				/>
			</div>
		@endforeach
	</div>

	<div class="flex justify-end">
		<button
			type="button"
			class="btn btn-primary"
			wire:click="save"
			@disabled(! $dirty)
		>
			{{ __( 'Save preferences' ) }}
		</button>
	</div>
</div>
