<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}" data-theme="{{ config( 'artisanpack.privacy.ui.theme', 'light' ) }}">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="csrf-token" content="{{ csrf_token() }}" />
	<title>{{ $title ?? __( 'Privacy admin' ) }}</title>

	{{--
		The admin views use Tailwind + daisyUI utility classes. When the
		consuming application has already loaded these stylesheets via Vite
		the CDN fallback below is harmless. Publish this layout via
		`php artisan vendor:publish --tag=privacy-admin-layout` to swap in
		your own asset pipeline.
	--}}
	@if( ! ( $disableCdnAssets ?? false ) )
		<link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
		<script src="https://cdn.tailwindcss.com"></script>
	@endif

	@livewireStyles
</head>
<body class="min-h-screen bg-base-200 text-base-content">
	<header class="bg-base-100 border-b border-base-300">
		<div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center gap-4">
			<a href="{{ route( 'privacy.admin.dashboard' ) }}" class="font-semibold text-lg">
				{{ __( 'Privacy admin' ) }}
			</a>
			<nav class="flex flex-wrap gap-3 text-sm" aria-label="{{ __( 'Privacy admin navigation' ) }}">
				<a href="{{ route( 'privacy.admin.consents' ) }}" class="link link-hover">{{ __( 'Consents' ) }}</a>
				<a href="{{ route( 'privacy.admin.data-requests' ) }}" class="link link-hover">{{ __( 'Data requests' ) }}</a>
				<a href="{{ route( 'privacy.admin.compliance-report' ) }}" class="link link-hover">{{ __( 'Compliance report' ) }}</a>
				<a href="{{ route( 'privacy.admin.breaches' ) }}" class="link link-hover">{{ __( 'Breaches' ) }}</a>
				<a href="{{ route( 'privacy.admin.breaches.report' ) }}" class="link link-hover">{{ __( 'Report breach' ) }}</a>
			</nav>
		</div>
	</header>

	<main class="max-w-7xl mx-auto px-4 py-6">
		{{ $slot ?? '' }}
		@yield( 'content' )
	</main>

	@livewireScripts
</body>
</html>
