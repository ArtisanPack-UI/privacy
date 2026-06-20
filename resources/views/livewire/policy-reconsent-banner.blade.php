@php
	$policy = $this->policy;
	$labels = array_merge( [
		'title'       => __( 'Our privacy policy has been updated' ),
		'description' => __( 'Please review the updated policy and confirm that you accept the new terms to continue using the service.' ),
		'review'      => __( 'Review policy' ),
		'accept'      => __( 'Accept updated policy' ),
		'dismiss'     => __( 'Remind me later' ),
	], $labels );

	$animation       = (string) config( 'artisanpack.privacy.ui.animation', 'slide-up' );
	$zIndex          = (int) config( 'artisanpack.privacy.ui.z_index', 9999 );
	$useDaisy        = true === config( 'artisanpack.privacy.ui.use_daisyui', true );
	$baseButtonClass = '' !== $buttonClasses
		? $buttonClasses
		: ( $useDaisy ? 'btn' : 'privacy-reconsent-banner__btn' );
@endphp

<div
	x-data="{ visible: @entangle('visible').live }"
	x-cloak
	@if($policy)
		x-show="visible"
		x-transition:enter="transition ease-out duration-300"
		x-transition:enter-start="opacity-0 {{ 'slide-up' === $animation ? 'translate-y-2' : '' }}"
		x-transition:enter-end="opacity-100 translate-y-0"
		x-transition:leave="transition ease-in duration-200"
		x-transition:leave-start="opacity-100 translate-y-0"
		x-transition:leave-end="opacity-0 {{ 'slide-up' === $animation ? 'translate-y-2' : '' }}"
		role="alertdialog"
		aria-modal="false"
		aria-labelledby="privacy-reconsent-title"
		class="fixed bottom-0 left-0 right-0 m-4 privacy-reconsent-banner {{ $class }}"
		style="z-index: {{ $zIndex }};"
		data-privacy-reconsent
		data-policy-version="{{ $policy->version }}"
	@else
		hidden
		data-privacy-reconsent="empty"
	@endif
>
	@if($policy)
		<div
			class="{{ $useDaisy ? 'bg-base-100 text-base-content dark:bg-neutral dark:text-neutral-content' : 'privacy-reconsent-banner__inner' }} shadow-2xl rounded-lg p-5 sm:p-6 max-w-3xl mx-auto"
			style="--privacy-banner-bg: var(--privacy-banner-bg, currentColor);"
		>
			<div class="flex flex-col gap-3">
				<div class="flex flex-col gap-1">
					{{ $header ?? '' }}
					<h2 id="privacy-reconsent-title" class="text-lg font-semibold">
						{{ $labels['title'] }}
					</h2>
					{{ $description ?? '' }}
					<p class="text-sm opacity-80">
						{{ $labels['description'] }}
					</p>
					<p class="text-xs opacity-60">
						{{ __( 'Version' ) }}: <strong>{{ $policy->version }}</strong>
					</p>
				</div>

				<div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
					<a
						href="{{ route( 'privacy.policy.show' ) }}"
						target="_blank"
						rel="noopener"
						class="{{ $baseButtonClass }} {{ $useDaisy ? 'btn-ghost' : '' }}"
					>
						{{ $labels['review'] }}
					</a>
					<button
						type="button"
						class="{{ $baseButtonClass }} {{ $useDaisy ? 'btn-outline' : '' }}"
						wire:click="dismiss"
					>
						{{ $labels['dismiss'] }}
					</button>
					<button
						type="button"
						class="{{ $baseButtonClass }} {{ $useDaisy ? 'btn-primary' : '' }}"
						wire:click="accept"
					>
						{{ $labels['accept'] }}
					</button>
				</div>

				{{ $footer ?? '' }}
			</div>
		</div>
	@endif
</div>
