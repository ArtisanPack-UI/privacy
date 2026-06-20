<div
	class="privacy-admin-compliance-report space-y-4"
	role="region"
	aria-label="{{ __( 'Compliance report' ) }}"
>
	<header class="space-y-1">
		<h2 class="text-2xl font-semibold">{{ __( 'Compliance report' ) }}</h2>
		<p class="opacity-70 text-sm">{{ __( 'Consent, request, and breach metrics for the selected reporting window.' ) }}</p>
	</header>

	<section
		class="flex flex-wrap gap-3 items-end"
		aria-label="{{ __( 'Filters' ) }}"
	>
		<label class="form-control">
			<span class="label-text">{{ __( 'From' ) }}</span>
			<input
				type="date"
				class="input input-bordered"
				wire:model.live="from"
				aria-label="{{ __( 'Report start date' ) }}"
			/>
		</label>

		<label class="form-control">
			<span class="label-text">{{ __( 'To' ) }}</span>
			<input
				type="date"
				class="input input-bordered"
				wire:model.live="to"
				aria-label="{{ __( 'Report end date' ) }}"
			/>
		</label>

		<label class="form-control">
			<span class="label-text">{{ __( 'Regulation' ) }}</span>
			<select class="select select-bordered" wire:model.live="regulation">
				<option value="">{{ __( 'All regulations' ) }}</option>
				@foreach( $this->regulations as $key )
					<option value="{{ $key }}">{{ strtoupper( $key ) }}</option>
				@endforeach
			</select>
		</label>

		<div class="flex gap-2">
			<button type="button" class="btn btn-sm" wire:click="exportCsv">
				{{ __( 'Export CSV' ) }}
			</button>
			<button type="button" class="btn btn-sm" wire:click="exportPdf">
				{{ __( 'Export PDF' ) }}
			</button>
		</div>
	</section>

	@php $report = $this->report; @endphp

	<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
		<section class="card bg-base-100 border border-base-300 p-4 space-y-2" aria-labelledby="report-consents-heading">
			<h3 id="report-consents-heading" class="font-semibold">{{ __( 'Consents' ) }}</h3>
			<dl class="grid grid-cols-2 gap-y-1 text-sm">
				<dt class="opacity-70">{{ __( 'Total' ) }}</dt>
				<dd>{{ $report['consents']['total'] }}</dd>

				<dt class="opacity-70">{{ __( 'Granted' ) }}</dt>
				<dd>{{ $report['consents']['granted'] }}</dd>

				<dt class="opacity-70">{{ __( 'Withdrawn' ) }}</dt>
				<dd>{{ $report['consents']['withdrawn'] }}</dd>

				<dt class="opacity-70">{{ __( 'Expired' ) }}</dt>
				<dd>{{ $report['consents']['expired'] }}</dd>

				<dt class="opacity-70">{{ __( 'Grant rate' ) }}</dt>
				<dd>{{ $report['consents']['grant_rate'] }}%</dd>

				<dt class="opacity-70">{{ __( 'Withdrawal rate' ) }}</dt>
				<dd>{{ $report['consents']['withdrawal_rate'] }}%</dd>
			</dl>

			@if( ! empty( $report['consents']['by_category'] ) )
				<h4 class="text-sm font-medium mt-2">{{ __( 'By category' ) }}</h4>
				<ul class="text-sm space-y-1">
					@foreach( $report['consents']['by_category'] as $category => $count )
						<li>{{ $category }}: <strong>{{ $count }}</strong></li>
					@endforeach
				</ul>
			@endif
		</section>

		<section class="card bg-base-100 border border-base-300 p-4 space-y-2" aria-labelledby="report-requests-heading">
			<h3 id="report-requests-heading" class="font-semibold">{{ __( 'Data subject requests' ) }}</h3>
			<dl class="grid grid-cols-2 gap-y-1 text-sm">
				<dt class="opacity-70">{{ __( 'Total' ) }}</dt>
				<dd>{{ $report['requests']['total'] }}</dd>

				<dt class="opacity-70">{{ __( 'Completed' ) }}</dt>
				<dd>{{ $report['requests']['completed'] }}</dd>

				<dt class="opacity-70">{{ __( 'Overdue' ) }}</dt>
				<dd>{{ $report['requests']['overdue'] }}</dd>

				<dt class="opacity-70">{{ __( 'Average response (s)' ) }}</dt>
				<dd>{{ $report['requests']['average_response_seconds'] }}</dd>

				<dt class="opacity-70">{{ __( 'p50 / p90 / p99 (s)' ) }}</dt>
				<dd>
					{{ $report['requests']['percentiles_seconds']['p50'] }} /
					{{ $report['requests']['percentiles_seconds']['p90'] }} /
					{{ $report['requests']['percentiles_seconds']['p99'] }}
				</dd>

				<dt class="opacity-70">{{ __( 'Deadline compliance' ) }}</dt>
				<dd>{{ $report['requests']['deadline_compliance_percent'] }}%</dd>
			</dl>

			@if( ! empty( $report['requests']['by_type'] ) )
				<h4 class="text-sm font-medium mt-2">{{ __( 'By type' ) }}</h4>
				<ul class="text-sm space-y-1">
					@foreach( $report['requests']['by_type'] as $type => $count )
						<li>{{ ucfirst( $type ) }}: <strong>{{ $count }}</strong></li>
					@endforeach
				</ul>
			@endif
		</section>

		<section class="card bg-base-100 border border-base-300 p-4 space-y-2" aria-labelledby="report-breaches-heading">
			<h3 id="report-breaches-heading" class="font-semibold">{{ __( 'Breaches' ) }}</h3>
			<dl class="grid grid-cols-2 gap-y-1 text-sm">
				<dt class="opacity-70">{{ __( 'Total' ) }}</dt>
				<dd>{{ $report['breaches']['total'] }}</dd>

				<dt class="opacity-70">{{ __( 'Notified on time' ) }}</dt>
				<dd>{{ $report['breaches']['authority_notified_on_time'] }}</dd>

				<dt class="opacity-70">{{ __( 'Notified late' ) }}</dt>
				<dd>{{ $report['breaches']['authority_notified_late'] }}</dd>

				<dt class="opacity-70">{{ __( 'Awaiting notification' ) }}</dt>
				<dd>{{ $report['breaches']['authority_notification_pending'] }}</dd>

				<dt class="opacity-70">{{ __( 'Authority compliance' ) }}</dt>
				<dd>{{ $report['breaches']['authority_notification_compliance_percent'] }}%</dd>
			</dl>

			@if( ! empty( $report['breaches']['by_severity'] ) )
				<h4 class="text-sm font-medium mt-2">{{ __( 'By severity' ) }}</h4>
				<ul class="text-sm space-y-1">
					@foreach( $report['breaches']['by_severity'] as $severity => $count )
						<li>{{ ucfirst( $severity ) }}: <strong>{{ $count }}</strong></li>
					@endforeach
				</ul>
			@endif
		</section>
	</div>
</div>
