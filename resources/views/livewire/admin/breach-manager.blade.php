<div
	class="privacy-admin-breach-manager space-y-4"
	role="region"
	aria-label="{{ __( 'Breach incidents' ) }}"
>
	<header class="flex flex-wrap items-end justify-between gap-3">
		<div class="space-y-1">
			<h2 class="text-2xl font-semibold">{{ __( 'Breach incidents' ) }}</h2>
			<p class="opacity-70 text-sm">{{ __( 'Track open and resolved data breach incidents and notification status.' ) }}</p>
		</div>

		<a href="{{ route( 'privacy.admin.breaches.report' ) }}" class="btn btn-primary btn-sm">
			{{ __( 'Report a breach' ) }}
		</a>
	</header>

	<section class="flex flex-wrap gap-3 items-end" aria-label="{{ __( 'Filters' ) }}">
		<label class="form-control">
			<span class="label-text">{{ __( 'Severity' ) }}</span>
			<select class="select select-bordered" wire:model.live="severityFilter">
				<option value="">{{ __( 'All severities' ) }}</option>
				<option value="{{ \ArtisanPackUI\Privacy\Models\BreachNotification::SEVERITY_LOW }}">{{ __( 'Low' ) }}</option>
				<option value="{{ \ArtisanPackUI\Privacy\Models\BreachNotification::SEVERITY_MEDIUM }}">{{ __( 'Medium' ) }}</option>
				<option value="{{ \ArtisanPackUI\Privacy\Models\BreachNotification::SEVERITY_HIGH }}">{{ __( 'High' ) }}</option>
				<option value="{{ \ArtisanPackUI\Privacy\Models\BreachNotification::SEVERITY_CRITICAL }}">{{ __( 'Critical' ) }}</option>
			</select>
		</label>

		<label class="form-control">
			<span class="label-text">{{ __( 'Status' ) }}</span>
			<select class="select select-bordered" wire:model.live="statusFilter">
				<option value="">{{ __( 'All statuses' ) }}</option>
				<option value="{{ \ArtisanPackUI\Privacy\Models\BreachNotification::STATUS_INVESTIGATING }}">{{ __( 'Investigating' ) }}</option>
				<option value="{{ \ArtisanPackUI\Privacy\Models\BreachNotification::STATUS_CONTAINED }}">{{ __( 'Contained' ) }}</option>
				<option value="{{ \ArtisanPackUI\Privacy\Models\BreachNotification::STATUS_RESOLVED }}">{{ __( 'Resolved' ) }}</option>
			</select>
		</label>
	</section>

	@php $breaches = $this->breaches; @endphp

	<div class="overflow-x-auto">
		<table class="table">
			<thead>
				<tr>
					<th scope="col">{{ __( 'Reference' ) }}</th>
					<th scope="col">{{ __( 'Severity' ) }}</th>
					<th scope="col">{{ __( 'Status' ) }}</th>
					<th scope="col">{{ __( 'Discovered' ) }}</th>
					<th scope="col">{{ __( 'Authority deadline' ) }}</th>
					<th scope="col">{{ __( 'Records' ) }}</th>
					<th scope="col" class="text-right">{{ __( 'Actions' ) }}</th>
				</tr>
			</thead>
			<tbody>
				@forelse( $breaches as $breach )
					@php
						$deadline = $service->getNotificationDeadline( $breach );
						$overdue  = null === $breach->authority_notified_at && $deadline->isPast();
					@endphp
					<tr wire:key="breach-{{ $breach->id }}" @class([ 'bg-error/10' => $overdue ])>
						<td>
							<a href="{{ route( 'privacy.admin.breaches.show', $breach->id ) }}" class="link link-hover font-medium">
								{{ $breach->reference_number }}
							</a>
						</td>
						<td>
							<span @class([
								'badge',
								'badge-ghost'   => $breach->severity === \ArtisanPackUI\Privacy\Models\BreachNotification::SEVERITY_LOW,
								'badge-info'    => $breach->severity === \ArtisanPackUI\Privacy\Models\BreachNotification::SEVERITY_MEDIUM,
								'badge-warning' => $breach->severity === \ArtisanPackUI\Privacy\Models\BreachNotification::SEVERITY_HIGH,
								'badge-error'   => $breach->severity === \ArtisanPackUI\Privacy\Models\BreachNotification::SEVERITY_CRITICAL,
							])>
								{{ ucfirst( $breach->severity ) }}
							</span>
						</td>
						<td>
							<span @class([
								'badge',
								'badge-warning' => $breach->status === \ArtisanPackUI\Privacy\Models\BreachNotification::STATUS_INVESTIGATING,
								'badge-info'    => $breach->status === \ArtisanPackUI\Privacy\Models\BreachNotification::STATUS_CONTAINED,
								'badge-success' => $breach->status === \ArtisanPackUI\Privacy\Models\BreachNotification::STATUS_RESOLVED,
							])>
								{{ ucfirst( $breach->status ) }}
							</span>
						</td>
						<td>{{ optional( $breach->discovered_at )->toFormattedDateString() }}</td>
						<td>
							<span @class([ 'text-error font-medium' => $overdue ])>
								{{ $deadline->toFormattedDateString() }}
							</span>
							@if( $overdue )
								<div class="text-error text-xs">{{ __( 'Overdue' ) }}</div>
							@elseif( null !== $breach->authority_notified_at )
								<div class="text-success text-xs">{{ __( 'Notified' ) }}</div>
							@endif
						</td>
						<td>{{ $breach->records_affected ?? '—' }}</td>
						<td class="text-right">
							<a href="{{ route( 'privacy.admin.breaches.show', $breach->id ) }}" class="btn btn-sm">
								{{ __( 'View' ) }}
							</a>
						</td>
					</tr>
				@empty
					<tr>
						<td colspan="7" class="text-center opacity-70 py-6">
							{{ __( 'No breaches match the selected filters.' ) }}
						</td>
					</tr>
				@endforelse
			</tbody>
		</table>

		<div class="mt-3">
			{{ $breaches->links() }}
		</div>
	</div>
</div>
