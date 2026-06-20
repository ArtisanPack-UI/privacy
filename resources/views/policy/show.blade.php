@php
	$uiConfig          = (array) config( 'artisanpack.privacy.ui', [] );
	$policyConfig      = (array) ( $uiConfig['policy'] ?? [] );
	$customClass       = (string) ( $policyConfig['custom_css_class'] ?? '' );
	$showVersionHistory = true === ( $policyConfig['show_version_history'] ?? true );
	$showLocaleSwitcher = true === ( $policyConfig['show_locale_switcher'] ?? true );
	$activeVersion     = (string) $policy->version;
	$publishedAt       = optional( $policy->published_at )->translatedFormat( 'F j, Y' );
@endphp

<div
	class="privacy-policy {{ $customClass }}"
	data-privacy-policy
	data-policy-version="{{ $policy->version }}"
	data-policy-regulation="{{ $policy->regulation ?? 'general' }}"
	data-policy-locale="{{ $policy->locale }}"
>
	<style>
		.privacy-policy {
			--privacy-policy-max-width: 56rem;
			--privacy-policy-toc-bg: var(--privacy-toc-bg, #f8fafc);
			--privacy-policy-toc-text: var(--privacy-toc-text, #1f2937);
			--privacy-policy-body-text: var(--privacy-body-text, #111827);
			--privacy-policy-link: var(--privacy-link, #1d4ed8);
		}
		@media ( prefers-color-scheme: dark ) {
			.privacy-policy {
				--privacy-policy-toc-bg: var(--privacy-toc-bg-dark, #1f2937);
				--privacy-policy-toc-text: var(--privacy-toc-text-dark, #f9fafb);
				--privacy-policy-body-text: var(--privacy-body-text-dark, #f3f4f6);
				--privacy-policy-link: var(--privacy-link-dark, #93c5fd);
			}
		}
		.privacy-policy__layout {
			max-width: var(--privacy-policy-max-width);
			margin: 0 auto;
			padding: 1.5rem;
			color: var(--privacy-policy-body-text);
		}
		.privacy-policy__layout a { color: var(--privacy-policy-link); }
		.privacy-policy__toc {
			background: var(--privacy-policy-toc-bg);
			color: var(--privacy-policy-toc-text);
			border-radius: 0.5rem;
			padding: 1rem 1.25rem;
			margin-bottom: 1.5rem;
		}
		.privacy-policy__toc ol { list-style: decimal; padding-left: 1.25rem; }
		.privacy-policy__meta { display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.875rem; opacity: 0.8; }
		.privacy-policy__body { line-height: 1.6; }
		.privacy-policy__body h1, .privacy-policy__body h2, .privacy-policy__body h3 {
			scroll-margin-top: 4rem;
		}
		@media print {
			.privacy-policy__toc, .privacy-policy__actions, .privacy-policy__history { display: none !important; }
			.privacy-policy__layout { max-width: none; padding: 0; }
			.privacy-policy a { text-decoration: none; color: inherit; }
		}
	</style>

	<div class="privacy-policy__layout">
		<header class="privacy-policy__header">
			<h1 class="privacy-policy__title">
				{{ __( 'Privacy Policy' ) }}
			</h1>

			<div class="privacy-policy__meta">
				<span>{{ __( 'Version' ) }}: <strong>{{ $activeVersion }}</strong></span>
				@if($publishedAt)
					<span>{{ __( 'Last updated' ) }}: <strong>{{ $publishedAt }}</strong></span>
				@endif
				@if($policy->regulation)
					<span>{{ __( 'Regulation' ) }}: <strong>{{ strtoupper( $policy->regulation ) }}</strong></span>
				@endif
			</div>

			<div class="privacy-policy__actions" style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
				<button
					type="button"
					onclick="window.print();"
					class="btn btn-sm btn-outline"
					data-privacy-policy-print
				>
					{{ __( 'Print' ) }}
				</button>

				@if($showLocaleSwitcher && count( $availableLocales ) > 1)
					<form method="GET" action="" style="display: flex; gap: 0.5rem; align-items: center;">
						<label for="privacy-policy-locale" class="sr-only">{{ __( 'Language' ) }}</label>
						<select id="privacy-policy-locale" name="locale" onchange="this.form.submit();" class="select select-sm">
							@foreach($availableLocales as $availableLocale)
								<option value="{{ $availableLocale }}" @selected( $availableLocale === $locale )>
									{{ strtoupper( $availableLocale ) }}
								</option>
							@endforeach
						</select>
					</form>
				@endif
			</div>
		</header>

		@if( ! empty( $sections ))
			<nav class="privacy-policy__toc" aria-label="{{ __( 'Table of contents' ) }}">
				<h2 style="font-weight: 600; margin-bottom: 0.5rem;">
					{{ __( 'On this page' ) }}
				</h2>
				<ol>
					@foreach($sections as $section)
						@continue($section['level'] > 3)
						<li style="margin-left: {{ ( $section['level'] - 1 ) * 0.75 }}rem;">
							<a href="#{{ $section['slug'] }}">
								{{ $section['heading'] }}
							</a>
						</li>
					@endforeach
				</ol>
			</nav>
		@endif

		<article class="privacy-policy__body prose prose-slate dark:prose-invert max-w-none">
			{!! $html !!}
		</article>

		@if($showVersionHistory && $history->count() > 1)
			<section class="privacy-policy__history" style="margin-top: 2rem;" aria-labelledby="privacy-policy-history-heading">
				<h2 id="privacy-policy-history-heading" style="font-weight: 600; margin-bottom: 0.5rem;">
					{{ __( 'Version history' ) }}
				</h2>
				<ul style="list-style: disc; padding-left: 1.25rem;">
					@foreach($history as $entry)
						<li>
							@if($entry->version === $activeVersion)
								<strong>
									{{ $entry->version }}
									@if($entry->active)
										<span class="badge badge-success">{{ __( 'Current' ) }}</span>
									@endif
								</strong>
								&middot; {{ optional( $entry->published_at )->translatedFormat( 'F j, Y' ) }}
							@else
								<a href="{{ route( 'privacy.policy.show-version', [ 'version' => $entry->version, 'locale' => $locale ] ) }}">
									{{ $entry->version }}
								</a>
								&middot; {{ optional( $entry->published_at )->translatedFormat( 'F j, Y' ) }}
							@endif
						</li>
					@endforeach
				</ul>
			</section>
		@endif
	</div>
</div>
