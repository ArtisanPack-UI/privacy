@php
	$positionClasses = [
		'top'          => 'top-0 left-0 right-0',
		'bottom'       => 'bottom-0 left-0 right-0',
		'bottom-left'  => 'bottom-4 left-4 max-w-md',
		'bottom-right' => 'bottom-4 right-4 max-w-md',
	];
	$position  = $positionClasses[$this->position] ?? $positionClasses['bottom'];
	$ariaLabel = __( 'Cookie consent' );

	$labels = array_merge( [
		'title'          => __( 'We value your privacy' ),
		'description'    => __( 'We use cookies to make our website work and to understand how it is used. You can choose which categories you allow.' ),
		'accept_all'     => __( 'Accept all' ),
		'reject_all'     => __( 'Reject all' ),
		'customise'      => __( 'Customise' ),
		'save'           => __( 'Save selection' ),
		'required_badge' => __( 'Required' ),
	], $labels );

	$animation = (string) config( 'artisanpack.privacy.ui.animation', 'slide-up' );
	$zIndex    = (int) config( 'artisanpack.privacy.ui.z_index', 9999 );
	$useDaisy  = true === config( 'artisanpack.privacy.ui.use_daisyui', true );
	$baseBtn   = '' !== $buttonClasses
		? $buttonClasses
		: ( $useDaisy ? 'btn' : 'privacy-cookie-banner__btn' );

	$enterStart = match ( $animation ) {
		'fade'  => 'opacity-0',
		'none'  => '',
		default => 'opacity-0 translate-y-4',
	};
	$enterEnd = match ( $animation ) {
		'none'  => '',
		default => 'opacity-100 translate-y-0',
	};
@endphp

<div
	x-data="{ visible: @entangle('visible').live }"
	x-cloak
	x-show="visible"
	x-on:privacy:open-preferences.window="$wire.openFromGlobal()"
	x-transition:enter="transition ease-out duration-300"
	x-transition:enter-start="{{ $enterStart }}"
	x-transition:enter-end="{{ $enterEnd }}"
	x-transition:leave="transition ease-in duration-200"
	x-trap.noscroll.inert="visible && '{{ $this->style }}' === 'modal'"
	role="dialog"
	aria-modal="{{ $this->style === 'modal' ? 'true' : 'false' }}"
	aria-label="{{ $ariaLabel }}"
	style="
		z-index: {{ $zIndex }};
		--privacy-banner-bg: var(--privacy-banner-bg, currentColor);
		--privacy-banner-radius: var(--privacy-banner-radius, 0.5rem);
		--privacy-banner-shadow: var(--privacy-banner-shadow, 0 25px 50px -12px rgb(0 0 0 / 0.25));
	"
	@if($style === 'modal')
		class="fixed inset-0 flex items-end sm:items-center justify-center bg-black/40 p-4 privacy-cookie-banner {{ $class }}"
	@else
		class="fixed {{ $position }} m-2 sm:m-4 privacy-cookie-banner {{ $class }}"
	@endif
	data-privacy-cookie-banner
>
	<div
		class="{{ $useDaisy ? 'bg-base-100 text-base-content dark:bg-neutral dark:text-neutral-content' : 'privacy-cookie-banner__inner' }} w-full p-5 sm:p-6"
		style="border-radius: var(--privacy-banner-radius); box-shadow: var(--privacy-banner-shadow);"
	>
		<div class="flex flex-col gap-4">
			<div class="flex flex-col gap-2">
				{{ $header ?? '' }}
				<h2 class="text-lg font-semibold">
					{{ $labels['title'] }}
				</h2>
				{{ $description ?? '' }}
				<p class="text-sm opacity-80">
					{{ $labels['description'] }}
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
							class="flex items-start justify-between gap-3 cursor-pointer p-3 rounded {{ $useDaisy ? 'border border-base-300' : 'privacy-cookie-banner__category' }}"
						>
							<span class="flex flex-col">
								<span class="font-medium">
									{{ $category['name'] ?? $key }}
									@if($required)
										<span class="{{ $useDaisy ? 'badge badge-ghost badge-sm' : 'privacy-cookie-banner__badge' }} ml-1">
											{{ $labels['required_badge'] }}
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
								class="{{ $useDaisy ? 'toggle toggle-primary' : 'privacy-cookie-banner__toggle' }}"
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
						class="{{ $baseBtn }} {{ $useDaisy ? 'btn-primary' : '' }}"
						wire:click="saveSelected"
					>
						{{ $labels['save'] }}
					</button>
				@else
					<button
						type="button"
						class="{{ $baseBtn }} {{ $useDaisy ? 'btn-ghost' : '' }}"
						wire:click="openPreferences"
					>
						{{ $labels['customise'] }}
					</button>
					<button
						type="button"
						class="{{ $baseBtn }} {{ $useDaisy ? 'btn-outline' : '' }}"
						wire:click="rejectAll"
					>
						{{ $labels['reject_all'] }}
					</button>
					<button
						type="button"
						class="{{ $baseBtn }} {{ $useDaisy ? 'btn-primary' : '' }}"
						wire:click="acceptAll"
					>
						{{ $labels['accept_all'] }}
					</button>
				@endif
			</div>

			{{ $footer ?? '' }}
		</div>
	</div>
</div>
