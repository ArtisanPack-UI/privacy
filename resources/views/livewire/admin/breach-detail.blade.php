<div
	class="privacy-admin-breach-detail space-y-4"
	role="region"
	aria-label="{{ __( 'Breach details' ) }}"
>
	@php $breach = $this->breach; @endphp

	@if( null === $breach )
		<div class="alert alert-warning">{{ __( 'Breach not found.' ) }}</div>
	@else
		@php
			$deadline = $service->getNotificationDeadline( $breach );
			$overdue  = null === $breach->authority_notified_at && $deadline->isPast();
		@endphp

		<header class="flex flex-wrap items-start justify-between gap-3">
			<div class="space-y-1">
				<h2 class="text-2xl font-semibold">
					{{ __( 'Breach' ) }} {{ $breach->reference_number }}
				</h2>
				<p class="opacity-70 text-sm">
					{{ ucfirst( $breach->severity ) }} · {{ ucfirst( $breach->status ) }}
				</p>
			</div>

			<div class="flex flex-wrap gap-2">
				<button type="button" class="btn btn-sm" wire:click="exportDocumentation">
					{{ __( 'Export documentation' ) }}
				</button>
				<a href="{{ route( 'privacy.admin.breaches' ) }}" class="btn btn-sm btn-ghost">
					{{ __( 'Back to list' ) }}
				</a>
			</div>
		</header>

		<section class="card bg-base-100 border border-base-300 p-4" aria-labelledby="breach-timeline-heading">
			<h3 id="breach-timeline-heading" class="font-semibold mb-2">{{ __( 'Timeline' ) }}</h3>
			<dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
				<dt class="opacity-70">{{ __( 'Discovered' ) }}</dt>
				<dd>{{ optional( $breach->discovered_at )->toIso8601String() ?? '—' }}</dd>

				<dt class="opacity-70">{{ __( 'Occurred' ) }}</dt>
				<dd>{{ optional( $breach->occurred_at )->toIso8601String() ?? '—' }}</dd>

				<dt class="opacity-70">{{ __( 'Authority deadline' ) }}</dt>
				<dd>
					<span @class([ 'text-error font-medium' => $overdue ])>
						{{ $deadline->toIso8601String() }}
					</span>
					@if( $overdue )
						<span class="text-error">— {{ __( 'Overdue' ) }}</span>
					@endif
				</dd>

				<dt class="opacity-70">{{ __( 'Authority notified' ) }}</dt>
				<dd>{{ optional( $breach->authority_notified_at )->toIso8601String() ?? __( 'Not yet' ) }}</dd>

				<dt class="opacity-70">{{ __( 'Users notified' ) }}</dt>
				<dd>{{ optional( $breach->users_notified_at )->toIso8601String() ?? __( 'Not yet' ) }}</dd>
			</dl>
		</section>

		<section class="card bg-base-100 border border-base-300 p-4" aria-labelledby="breach-data-heading">
			<h3 id="breach-data-heading" class="font-semibold mb-2">{{ __( 'Affected data' ) }}</h3>
			<dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
				<dt class="opacity-70">{{ __( 'Categories' ) }}</dt>
				<dd>
					@foreach( (array) ( $breach->data_types_affected ?? [] ) as $type )
						<span class="badge badge-ghost mr-1 mb-1">{{ $type }}</span>
					@endforeach
				</dd>

				<dt class="opacity-70">{{ __( 'Records affected' ) }}</dt>
				<dd>{{ $breach->records_affected ?? __( 'Unknown' ) }}</dd>

				<dt class="opacity-70">{{ __( 'Description' ) }}</dt>
				<dd class="whitespace-pre-line">{{ $breach->description }}</dd>

				@if( $breach->cause )
					<dt class="opacity-70">{{ __( 'Cause' ) }}</dt>
					<dd class="whitespace-pre-line">{{ $breach->cause }}</dd>
				@endif
			</dl>
		</section>

		<section class="card bg-base-100 border border-base-300 p-4 space-y-3" aria-labelledby="breach-remediation-heading">
			<h3 id="breach-remediation-heading" class="font-semibold">{{ __( 'Remediation' ) }}</h3>

			@if( $breach->remediation )
				<p class="text-sm whitespace-pre-line opacity-80">{{ $breach->remediation }}</p>
			@else
				<p class="text-sm opacity-60">{{ __( 'No remediation notes recorded yet.' ) }}</p>
			@endif

			<label class="form-control">
				<span class="label-text">{{ __( 'Add a remediation note' ) }}</span>
				<textarea
					class="textarea textarea-bordered"
					rows="3"
					wire:model="remediationNote"
				></textarea>
			</label>

			<div class="flex justify-end">
				<button type="button" class="btn btn-sm" wire:click="addRemediation">
					{{ __( 'Append note' ) }}
				</button>
			</div>
		</section>

		<section class="card bg-base-100 border border-base-300 p-4 space-y-3" aria-labelledby="breach-actions-heading">
			<h3 id="breach-actions-heading" class="font-semibold">{{ __( 'Notifications and status' ) }}</h3>

			<div class="flex flex-wrap gap-2">
				<button type="button" class="btn btn-sm btn-primary" wire:click="notifyAuthority">
					{{ __( 'Send authority notification' ) }}
				</button>
				<button type="button" class="btn btn-sm" wire:click="notifyUsers">
					{{ __( 'Notify affected users' ) }}
				</button>
			</div>

			<div class="flex flex-wrap gap-2">
				<button type="button" class="btn btn-sm" wire:click="setStatus( '{{ \ArtisanPackUI\Privacy\Models\BreachNotification::STATUS_INVESTIGATING }}' )">
					{{ __( 'Mark investigating' ) }}
				</button>
				<button type="button" class="btn btn-sm btn-info" wire:click="setStatus( '{{ \ArtisanPackUI\Privacy\Models\BreachNotification::STATUS_CONTAINED }}' )">
					{{ __( 'Mark contained' ) }}
				</button>
				<button type="button" class="btn btn-sm btn-success" wire:click="setStatus( '{{ \ArtisanPackUI\Privacy\Models\BreachNotification::STATUS_RESOLVED }}' )">
					{{ __( 'Mark resolved' ) }}
				</button>
			</div>
		</section>

		@if( ! empty( (array) $breach->notifications_sent ) )
			<section class="card bg-base-100 border border-base-300 p-4" aria-labelledby="breach-notifications-heading">
				<h3 id="breach-notifications-heading" class="font-semibold mb-2">{{ __( 'Notification history' ) }}</h3>
				<ul class="text-sm space-y-1">
					@foreach( (array) $breach->notifications_sent as $entry )
						<li>
							<strong>{{ $entry['channel'] ?? '—' }}</strong>
							@if( isset( $entry['recipient'] ) && $entry['recipient'] )
								— {{ $entry['recipient'] }}
							@elseif( isset( $entry['recipient_count'] ) )
								— {{ $entry['recipient_count'] }} {{ __( 'recipient(s)' ) }}
							@endif
							<span class="opacity-60">· {{ $entry['notified_at'] ?? '' }}</span>
						</li>
					@endforeach
				</ul>
			</section>
		@endif
	@endif
</div>
