@php
	$positionClasses = [
		'top'          => 'top-0 left-0 right-0',
		'bottom'       => 'bottom-0 left-0 right-0',
		'bottom-left'  => 'bottom-4 left-4 max-w-md',
		'bottom-right' => 'bottom-4 right-4 max-w-md',
	];
	$position = $positionClasses[$this->position] ?? $positionClasses['bottom'];
	$ariaLabel = __( 'Cookie consent' );
@endphp

<div
	x-data="{ visible: @entangle('visible').live }"
	x-cloak
	x-show="visible"
	x-trap.noscroll.inert="visible && '{{ $this->style }}' === 'modal'"
	role="dialog"
	aria-modal="{{ $this->style === 'modal' ? 'true' : 'false' }}"
	aria-label="{{ $ariaLabel }}"
	@if($style === 'modal')
		class="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center bg-black/40 p-4"
	@else
		class="fixed {{ $position }} z-[9999] m-2 sm:m-4"
	@endif
>
	<div
		class="bg-base-100 text-base-content dark:bg-neutral dark:text-neutral-content shadow-2xl rounded-lg p-5 sm:p-6 w-full"
		style="--privacy-banner-bg: var(--privacy-banner-bg, currentColor);"
	>
		<div class="flex flex-col gap-4">
			<div class="flex flex-col gap-2">
				{{ $header ?? '' }}
				<h2 class="text-lg font-semibold">
					{{ __( 'We value your privacy' ) }}
				</h2>
				{{ $description ?? '' }}
				<p class="text-sm opacity-80">
					{{ __( 'We use cookies to make our website work and to understand how it is used. You can choose which categories you allow.' ) }}
				</p>
			</div>

			@if($showPreferences)
				<div
					class="grid gap-3 max-h-72 overflow-y-auto"
					role="group"
					aria-label="{{ __( 'Cookie category preferences' ) }}"
				>
					@foreach($categories as $key => $category)
						@php
							$required = (bool) ($category['required'] ?? false);
						@endphp
						<label
							for="privacy-banner-toggle-{{ $key }}"
							class="flex items-start justify-between gap-3 cursor-pointer p-3 rounded border border-base-300"
						>
							<span class="flex flex-col">
								<span class="font-medium">
									{{ $category['name'] ?? $key }}
									@if($required)
										<span class="badge badge-ghost badge-sm ml-1">
											{{ __( 'Required' ) }}
										</span>
									@endif
								</span>
								@if(! empty($category['description']))
									<span class="text-xs opacity-70">{{ $category['description'] }}</span>
								@endif
							</span>
							<input
								id="privacy-banner-toggle-{{ $key }}"
								type="checkbox"
								class="toggle toggle-primary"
								wire:model.live="selected.{{ $key }}"
								@disabled($required)
								aria-label="{{ $category['name'] ?? $key }}"
							/>
						</label>
					@endforeach
				</div>
			@endif

			<div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
				@if($showPreferences)
					<button
						type="button"
						class="btn btn-primary"
						wire:click="saveSelected"
					>
						{{ __( 'Save selection' ) }}
					</button>
				@else
					<button
						type="button"
						class="btn btn-ghost"
						wire:click="openPreferences"
					>
						{{ __( 'Customise' ) }}
					</button>
					<button
						type="button"
						class="btn btn-outline"
						wire:click="rejectAll"
					>
						{{ __( 'Reject all' ) }}
					</button>
					<button
						type="button"
						class="btn btn-primary"
						wire:click="acceptAll"
					>
						{{ __( 'Accept all' ) }}
					</button>
				@endif
			</div>

			{{ $footer ?? '' }}
		</div>
	</div>
</div>
