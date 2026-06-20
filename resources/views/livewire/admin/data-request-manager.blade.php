<div
	class="privacy-admin-data-request-manager space-y-4"
	role="region"
	aria-label="{{ __( 'Data subject requests' ) }}"
>
	<header class="space-y-1">
		<h2 class="text-2xl font-semibold">{{ __( 'Data subject requests' ) }}</h2>
		<p class="opacity-70 text-sm">{{ __( 'Process pending requests, manually verify identities, and track deadlines.' ) }}</p>
	</header>

	<section
		class="flex flex-wrap gap-3 items-end"
		aria-label="{{ __( 'Filters' ) }}"
	>
		<label class="form-control">
			<span class="label-text">{{ __( 'Type' ) }}</span>
			<select class="select select-bordered" wire:model.live="typeFilter">
				<option value="">{{ __( 'All types' ) }}</option>
				@foreach($this->types as $type)
					<option value="{{ $type }}">{{ ucfirst( $type ) }}</option>
				@endforeach
			</select>
		</label>

		<label class="form-control">
			<span class="label-text">{{ __( 'Status' ) }}</span>
			<select class="select select-bordered" wire:model.live="statusFilter">
				<option value="">{{ __( 'All statuses' ) }}</option>
				<option value="{{ \ArtisanPackUI\Privacy\Models\DataRequest::STATUS_PENDING }}">{{ __( 'Pending' ) }}</option>
				<option value="{{ \ArtisanPackUI\Privacy\Models\DataRequest::STATUS_PROCESSING }}">{{ __( 'Processing' ) }}</option>
				<option value="{{ \ArtisanPackUI\Privacy\Models\DataRequest::STATUS_COMPLETED }}">{{ __( 'Completed' ) }}</option>
				<option value="{{ \ArtisanPackUI\Privacy\Models\DataRequest::STATUS_REJECTED }}">{{ __( 'Rejected' ) }}</option>
			</select>
		</label>

		<label class="form-control">
			<span class="label-text">{{ __( 'Sort by' ) }}</span>
			<select class="select select-bordered" wire:model.live="sort">
				<option value="deadline">{{ __( 'Closest deadline' ) }}</option>
				<option value="newest">{{ __( 'Newest first' ) }}</option>
				<option value="overdue">{{ __( 'Overdue only' ) }}</option>
			</select>
		</label>
	</section>

	@php $requests = $this->requests; $active = $this->activeRequest; @endphp

	<div class="grid grid-cols-1 @if($active) lg:grid-cols-[2fr_1fr] @endif gap-6">
		<div class="overflow-x-auto">
			<table class="table">
				<thead>
					<tr>
						<th scope="col">{{ __( 'Type' ) }}</th>
						<th scope="col">{{ __( 'Status' ) }}</th>
						<th scope="col">{{ __( 'Subject' ) }}</th>
						<th scope="col">{{ __( 'Filed' ) }}</th>
						<th scope="col">{{ __( 'Due' ) }}</th>
						<th scope="col" class="text-right">{{ __( 'Actions' ) }}</th>
					</tr>
				</thead>
				<tbody>
					@forelse($requests as $request)
						@php
							$overdue = $request->due_at !== null
								&& $request->due_at->isPast()
								&& ! in_array( $request->status, [ \ArtisanPackUI\Privacy\Models\DataRequest::STATUS_COMPLETED, \ArtisanPackUI\Privacy\Models\DataRequest::STATUS_REJECTED ], true );
						@endphp
						<tr wire:key="data-request-{{ $request->id }}" @class([ 'bg-error/10' => $overdue ])>
							<td>{{ ucfirst( $request->type ) }}</td>
							<td>
								<span @class([
									'badge',
									'badge-warning' => $request->status === \ArtisanPackUI\Privacy\Models\DataRequest::STATUS_PENDING,
									'badge-info'    => $request->status === \ArtisanPackUI\Privacy\Models\DataRequest::STATUS_PROCESSING,
									'badge-success' => $request->status === \ArtisanPackUI\Privacy\Models\DataRequest::STATUS_COMPLETED,
									'badge-error'   => $request->status === \ArtisanPackUI\Privacy\Models\DataRequest::STATUS_REJECTED,
								])>
									{{ ucfirst( $request->status ) }}
								</span>
							</td>
							<td>
								<div class="font-medium">{{ $request->requestable_type }}</div>
								<div class="opacity-70 text-xs">#{{ $request->requestable_id }}</div>
							</td>
							<td>{{ optional( $request->created_at )->toFormattedDateString() }}</td>
							<td>
								@if($request->due_at)
									<span @class([ 'text-error font-medium' => $overdue ])>
										{{ $request->due_at->toFormattedDateString() }}
									</span>
									@if($overdue)
										<div class="text-error text-xs">{{ __( 'Overdue' ) }}</div>
									@endif
								@else
									—
								@endif
							</td>
							<td class="text-right">
								<button type="button" class="btn btn-sm" wire:click="view({{ $request->id }})">
									{{ __( 'View' ) }}
								</button>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="6" class="text-center opacity-70 py-6">
								{{ __( 'No requests match the selected filters.' ) }}
							</td>
						</tr>
					@endforelse
				</tbody>
			</table>

			<div class="mt-3">
				{{ $requests->links() }}
			</div>
		</div>

		@if($active)
			<aside class="space-y-4 border border-base-300 rounded-lg p-4" aria-labelledby="data-request-details-heading">
				<header class="flex items-start justify-between gap-3">
					<div>
						<h3 id="data-request-details-heading" class="text-lg font-semibold">
							{{ __( 'Request' ) }} #{{ $active->id }}
						</h3>
						<p class="opacity-70 text-sm">{{ ucfirst( $active->type ) }} · {{ ucfirst( $active->status ) }}</p>
					</div>

					<button type="button" class="btn btn-sm" wire:click="closeDetails">
						{{ __( 'Close' ) }}
					</button>
				</header>

				<dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
					<dt class="opacity-70">{{ __( 'Subject' ) }}</dt>
					<dd>{{ $active->requestable_type }} #{{ $active->requestable_id }}</dd>

					<dt class="opacity-70">{{ __( 'Filed' ) }}</dt>
					<dd>{{ optional( $active->created_at )->toIso8601String() ?? '—' }}</dd>

					<dt class="opacity-70">{{ __( 'Verified' ) }}</dt>
					<dd>{{ optional( $active->verified_at )->toIso8601String() ?? __( 'Not yet' ) }}</dd>

					<dt class="opacity-70">{{ __( 'Due' ) }}</dt>
					<dd>{{ optional( $active->due_at )->toIso8601String() ?? '—' }}</dd>

					<dt class="opacity-70">{{ __( 'Regulation' ) }}</dt>
					<dd>{{ $active->regulation ?? '—' }}</dd>
				</dl>

				@if($active->reason)
					<section class="space-y-1" aria-label="{{ __( 'Subject reason' ) }}">
						<h4 class="font-medium">{{ __( 'Reason' ) }}</h4>
						<p class="opacity-80 text-sm whitespace-pre-line">{{ $active->reason }}</p>
					</section>
				@endif

				@if($active->admin_notes)
					<section class="space-y-1" aria-label="{{ __( 'Admin notes' ) }}">
						<h4 class="font-medium">{{ __( 'Admin notes' ) }}</h4>
						<p class="opacity-80 text-sm whitespace-pre-line">{{ $active->admin_notes }}</p>
					</section>
				@endif

				<section class="space-y-2">
					<label class="form-control">
						<span class="label-text">{{ __( 'Add a note' ) }}</span>
						<textarea
							class="textarea textarea-bordered"
							rows="3"
							wire:model="note"
							placeholder="{{ __( 'Optional context recorded with the next action…' ) }}"
						></textarea>
					</label>

					<div class="flex flex-wrap gap-2">
						@if(null === $active->verified_at)
							<button type="button" class="btn btn-sm" wire:click="verifyManually({{ $active->id }})">
								{{ __( 'Verify manually' ) }}
							</button>
						@endif

						@if(! in_array( $active->status, [ \ArtisanPackUI\Privacy\Models\DataRequest::STATUS_COMPLETED, \ArtisanPackUI\Privacy\Models\DataRequest::STATUS_REJECTED ], true ))
							<button type="button" class="btn btn-sm btn-primary" wire:click="approve({{ $active->id }})">
								{{ __( 'Approve' ) }}
							</button>
							<button type="button" class="btn btn-sm btn-success" wire:click="complete({{ $active->id }})">
								{{ __( 'Complete' ) }}
							</button>
							<button type="button" class="btn btn-sm btn-error" wire:click="reject({{ $active->id }})">
								{{ __( 'Reject' ) }}
							</button>
						@endif
					</div>
				</section>

				@if($active->logs && $active->logs->isNotEmpty())
					<section class="space-y-1" aria-label="{{ __( 'History' ) }}">
						<h4 class="font-medium">{{ __( 'History' ) }}</h4>
						<ul class="text-sm space-y-1">
							@foreach($active->logs as $log)
								<li>
									<strong>{{ $log->action }}</strong>
									<span class="opacity-60">— {{ optional( $log->created_at )->toIso8601String() }}</span>
									@if(is_array( $log->metadata ) && isset( $log->metadata['note'] ) && '' !== $log->metadata['note'])
										<div class="opacity-80 text-xs">{{ $log->metadata['note'] }}</div>
									@endif
								</li>
							@endforeach
						</ul>
					</section>
				@endif
			</aside>
		@endif
	</div>
</div>
