@extends( 'privacy::admin.layout', [ 'title' => __( 'Privacy admin' ) ] )

@section( 'content' )
	<header class="space-y-1 mb-6">
		<h1 class="text-2xl font-semibold">{{ __( 'Privacy admin' ) }}</h1>
		<p class="opacity-70 text-sm">{{ __( 'Manage consents, data subject requests, compliance reporting, and breach notifications.' ) }}</p>
	</header>

	<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
		<a href="{{ route( 'privacy.admin.consents' ) }}" class="card bg-base-100 hover:bg-base-200 border border-base-300 p-4">
			<h2 class="font-semibold mb-1">{{ __( 'Consents' ) }}</h2>
			<p class="opacity-70 text-sm">{{ __( 'Search, filter, and export the consent audit log.' ) }}</p>
		</a>

		<a href="{{ route( 'privacy.admin.data-requests' ) }}" class="card bg-base-100 hover:bg-base-200 border border-base-300 p-4">
			<h2 class="font-semibold mb-1">{{ __( 'Data requests' ) }}</h2>
			<p class="opacity-70 text-sm">{{ __( 'Process pending data subject requests with deadline awareness.' ) }}</p>
		</a>

		<a href="{{ route( 'privacy.admin.compliance-report' ) }}" class="card bg-base-100 hover:bg-base-200 border border-base-300 p-4">
			<h2 class="font-semibold mb-1">{{ __( 'Compliance report' ) }}</h2>
			<p class="opacity-70 text-sm">{{ __( 'Generate exportable reports for internal monitoring and audits.' ) }}</p>
		</a>

		<a href="{{ route( 'privacy.admin.breaches' ) }}" class="card bg-base-100 hover:bg-base-200 border border-base-300 p-4">
			<h2 class="font-semibold mb-1">{{ __( 'Breaches' ) }}</h2>
			<p class="opacity-70 text-sm">{{ __( 'Track open and resolved data breach incidents.' ) }}</p>
		</a>

		<a href="{{ route( 'privacy.admin.breaches.report' ) }}" class="card bg-base-100 hover:bg-base-200 border border-base-300 p-4">
			<h2 class="font-semibold mb-1">{{ __( 'Report a breach' ) }}</h2>
			<p class="opacity-70 text-sm">{{ __( 'Document a new breach and start the notification workflow.' ) }}</p>
		</a>
	</div>
@endsection
