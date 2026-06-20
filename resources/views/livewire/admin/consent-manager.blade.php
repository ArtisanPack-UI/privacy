<div
	class="privacy-admin-consent-manager space-y-4"
	role="region"
	aria-label="{{ __( 'Consent records' ) }}"
>
	<header class="space-y-1">
		<h2 class="text-2xl font-semibold">{{ __( 'Consent records' ) }}</h2>
		<p class="opacity-70 text-sm">{{ __( 'Browse, filter, and export consent activity across all users.' ) }}</p>
	</header>

	<section
		class="flex flex-wrap gap-3 items-end"
		aria-label="{{ __( 'Filters' ) }}"
	>
		<label class="form-control">
			<span class="label-text">{{ __( 'Search' ) }}</span>
			<input
				type="search"
				class="input input-bordered"
				wire:model.live.debounce.300ms="search"
				placeholder="{{ __( 'User, category, IP…' ) }}"
				aria-label="{{ __( 'Search consents' ) }}"
			/>
		</label>

		<label class="form-control">
			<span class="label-text">{{ __( 'Category' ) }}</span>
			<select class="select select-bordered" wire:model.live="categoryFilter">
				<option value="">{{ __( 'All categories' ) }}</option>
				@foreach($this->categories as $category)
					<option value="{{ $category }}">{{ $category }}</option>
				@endforeach
			</select>
		</label>

		<label class="form-control">
			<span class="label-text">{{ __( 'Status' ) }}</span>
			<select class="select select-bordered" wire:model.live="statusFilter">
				<option value="all">{{ __( 'All' ) }}</option>
				<option value="active">{{ __( 'Active' ) }}</option>
				<option value="withdrawn">{{ __( 'Withdrawn' ) }}</option>
				<option value="expired">{{ __( 'Expired' ) }}</option>
			</select>
		</label>

		<label class="form-control">
			<span class="label-text">{{ __( 'From' ) }}</span>
			<input type="date" class="input input-bordered" wire:model.live="dateFrom" />
		</label>

		<label class="form-control">
			<span class="label-text">{{ __( 'To' ) }}</span>
			<input type="date" class="input input-bordered" wire:model.live="dateTo" />
		</label>

		<div class="flex gap-2">
			<button type="button" class="btn" wire:click="clearFilters">
				{{ __( 'Reset' ) }}
			</button>
			<button type="button" class="btn btn-primary" wire:click="exportCsv">
				{{ __( 'Export CSV' ) }}
			</button>
			<button type="button" class="btn" wire:click="exportJson">
				{{ __( 'Export JSON' ) }}
			</button>
		</div>
	</section>

	@php $consents = $this->consents; @endphp

	<div class="overflow-x-auto">
		<table class="table">
			<thead>
				<tr>
					<th scope="col">
						<input
							type="checkbox"
							class="checkbox"
							wire:model.live="selectAll"
							aria-label="{{ __( 'Select all consents on this page' ) }}"
						/>
					</th>
					<th scope="col">{{ __( 'Subject' ) }}</th>
					<th scope="col">{{ __( 'Category' ) }}</th>
					<th scope="col">{{ __( 'Granted' ) }}</th>
					<th scope="col">{{ __( 'Regulation' ) }}</th>
					<th scope="col">{{ __( 'Recorded' ) }}</th>
					<th scope="col">{{ __( 'Expires' ) }}</th>
					<th scope="col">{{ __( 'Withdrawn' ) }}</th>
				</tr>
			</thead>
			<tbody>
				@forelse($consents as $consent)
					<tr wire:key="consent-{{ $consent->id }}">
						<td>
							<input
								type="checkbox"
								class="checkbox"
								value="{{ $consent->id }}"
								wire:model.live="selected"
								aria-label="{{ __( 'Select consent :id', [ 'id' => $consent->id ] ) }}"
							/>
						</td>
						<td>
							<div class="font-medium">{{ $consent->consentable_type }}</div>
							<div class="opacity-70 text-xs">#{{ $consent->consentable_id }}</div>
						</td>
						<td>{{ $consent->category }}</td>
						<td>
							@if($consent->granted)
								<span class="badge badge-success">{{ __( 'Yes' ) }}</span>
							@else
								<span class="badge badge-ghost">{{ __( 'No' ) }}</span>
							@endif
						</td>
						<td>{{ $consent->regulation ?? '—' }}</td>
						<td>{{ optional( $consent->created_at )->toFormattedDateString() }}</td>
						<td>{{ optional( $consent->expires_at )->toFormattedDateString() ?? '—' }}</td>
						<td>{{ optional( $consent->withdrawn_at )->toFormattedDateString() ?? '—' }}</td>
					</tr>
				@empty
					<tr>
						<td colspan="8" class="text-center opacity-70 py-6">
							{{ __( 'No consent records match the selected filters.' ) }}
						</td>
					</tr>
				@endforelse
			</tbody>
		</table>
	</div>

	<div>
		{{ $consents->links() }}
	</div>
</div>
