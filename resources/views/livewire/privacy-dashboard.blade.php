<div class="privacy-dashboard space-y-8">
	@php
		$subject = $this->subject;
		$regulation = $this->regulation;
		$requests = $this->requests;
	@endphp

	<header class="space-y-2">
		<h2 class="text-2xl font-semibold">{{ __( 'Your privacy dashboard' ) }}</h2>
		<p class="opacity-70">{{ __( 'Review your consent settings, file data requests, and download completed exports.' ) }}</p>

		@if($regulation)
			<div class="text-sm opacity-80" aria-live="polite">
				<span class="badge badge-ghost">{{ __( 'Regulation' ) }}: {{ strtoupper( $regulation ) }}</span>
			</div>
		@endif

		@if($policyUrl)
			<p>
				<a href="{{ $policyUrl }}" class="link link-primary text-sm">
					{{ __( 'Read our privacy policy' ) }}
				</a>
			</p>
		@endif
	</header>

	@if(! $subject)
		<div class="alert alert-warning" role="alert">
			<span>{{ __( 'Please sign in to view your privacy dashboard.' ) }}</span>
		</div>
	@else
		@if($showConsent)
			<section aria-labelledby="privacy-dashboard-consent-heading" class="space-y-3">
				<h3 id="privacy-dashboard-consent-heading" class="text-lg font-medium">
					{{ __( 'Consent preferences' ) }}
				</h3>
				<livewire:privacy-consent-preferences />
			</section>
		@endif

		@if($showRequestForm)
			<section aria-labelledby="privacy-dashboard-request-heading" class="space-y-3">
				<h3 id="privacy-dashboard-request-heading" class="text-lg font-medium">
					{{ __( 'Submit a privacy request' ) }}
				</h3>
				<livewire:privacy-data-request-form />
			</section>
		@endif

		@if($showHistory)
			<section aria-labelledby="privacy-dashboard-history-heading" class="space-y-3">
				<h3 id="privacy-dashboard-history-heading" class="text-lg font-medium">
					{{ __( 'Request history' ) }}
				</h3>

				@if($requests->isEmpty())
					<p class="opacity-70">{{ __( 'You have not filed any privacy requests yet.' ) }}</p>
				@else
					<div class="overflow-x-auto">
						<table class="table" wire:key="privacy-dashboard-requests-{{ $requestsVersion }}">
							<thead>
								<tr>
									<th scope="col">{{ __( 'Type' ) }}</th>
									<th scope="col">{{ __( 'Status' ) }}</th>
									<th scope="col">{{ __( 'Filed' ) }}</th>
									<th scope="col">{{ __( 'Due' ) }}</th>
									<th scope="col" class="text-right">{{ __( 'Actions' ) }}</th>
								</tr>
							</thead>
							<tbody>
								@foreach($requests as $request)
									<tr wire:key="privacy-dashboard-request-{{ $request->id }}">
										<td>{{ __( ucfirst( $request->type ) ) }}</td>
										<td>
											<span class="badge badge-ghost">{{ __( ucfirst( $request->status ) ) }}</span>
										</td>
										<td>{{ optional( $request->created_at )->toFormattedDateString() }}</td>
										<td>
											@if($request->due_at)
												{{ $request->due_at->toFormattedDateString() }}
											@else
												<span class="opacity-60">{{ __( 'n/a' ) }}</span>
											@endif
										</td>
										<td class="text-right">
											@php $download = $this->downloadUrlFor( $request ); @endphp
											@if($download)
												<a class="btn btn-sm btn-primary" href="{{ $download }}" rel="noopener">
													{{ __( 'Download export' ) }}
												</a>
											@else
												<span class="opacity-60">&mdash;</span>
											@endif
										</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				@endif
			</section>
		@endif
	@endif
</div>
