<div
	class="privacy-admin-breach-report-form space-y-4"
	role="region"
	aria-label="{{ __( 'Report a breach' ) }}"
>
	<header class="space-y-1">
		<h2 class="text-2xl font-semibold">{{ __( 'Report a breach' ) }}</h2>
		<p class="opacity-70 text-sm">{{ __( 'Document a new incident to start the notification workflow.' ) }}</p>
	</header>

	@if( null !== $createdBreachId )
		<div class="alert alert-success" role="status">
			<span>{{ __( 'Breach recorded successfully.' ) }}</span>
			<a href="{{ route( 'privacy.admin.breaches.show', $createdBreachId ) }}" class="link">
				{{ __( 'View incident' ) }}
			</a>
		</div>
	@endif

	<form wire:submit.prevent="submit" class="space-y-4">
		<label class="form-control">
			<span class="label-text">{{ __( 'Description' ) }} <span class="text-error">*</span></span>
			<textarea
				class="textarea textarea-bordered"
				rows="4"
				wire:model="description"
				required
			></textarea>
			@error( 'description' )
				<span class="text-error text-sm">{{ $message }}</span>
			@enderror
		</label>

		<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
			<label class="form-control">
				<span class="label-text">{{ __( 'Severity' ) }} <span class="text-error">*</span></span>
				<select class="select select-bordered" wire:model="severity" required>
					<option value="{{ \ArtisanPackUI\Privacy\Models\BreachNotification::SEVERITY_LOW }}">{{ __( 'Low' ) }}</option>
					<option value="{{ \ArtisanPackUI\Privacy\Models\BreachNotification::SEVERITY_MEDIUM }}">{{ __( 'Medium' ) }}</option>
					<option value="{{ \ArtisanPackUI\Privacy\Models\BreachNotification::SEVERITY_HIGH }}">{{ __( 'High' ) }}</option>
					<option value="{{ \ArtisanPackUI\Privacy\Models\BreachNotification::SEVERITY_CRITICAL }}">{{ __( 'Critical' ) }}</option>
				</select>
				@error( 'severity' )
					<span class="text-error text-sm">{{ $message }}</span>
				@enderror
			</label>

			<label class="form-control">
				<span class="label-text">{{ __( 'Occurred at (optional)' ) }}</span>
				<input
					type="datetime-local"
					class="input input-bordered"
					wire:model="occurredAt"
				/>
			</label>
		</div>

		<label class="form-control">
			<span class="label-text">{{ __( 'Data types affected (comma-separated)' ) }} <span class="text-error">*</span></span>
			<input
				type="text"
				class="input input-bordered"
				wire:model="dataTypes"
				placeholder="email, name, hashed_password"
				required
			/>
			@error( 'dataTypes' )
				<span class="text-error text-sm">{{ $message }}</span>
			@enderror
		</label>

		<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
			<label class="form-control">
				<span class="label-text">{{ __( 'Approximate records affected' ) }}</span>
				<input
					type="number"
					class="input input-bordered"
					wire:model="recordsAffected"
					min="0"
				/>
			</label>

			<label class="form-control">
				<span class="label-text">{{ __( 'Affected users (one email per line)' ) }}</span>
				<textarea
					class="textarea textarea-bordered"
					rows="3"
					wire:model="affectedUsers"
				></textarea>
			</label>
		</div>

		<label class="form-control">
			<span class="label-text">{{ __( 'Suspected cause' ) }}</span>
			<textarea
				class="textarea textarea-bordered"
				rows="2"
				wire:model="cause"
			></textarea>
		</label>

		<label class="form-control">
			<span class="label-text">{{ __( 'Remediation measures taken or proposed' ) }}</span>
			<textarea
				class="textarea textarea-bordered"
				rows="2"
				wire:model="remediation"
			></textarea>
		</label>

		<div class="flex justify-end">
			<button type="submit" class="btn btn-primary">
				{{ __( 'Record breach' ) }}
			</button>
		</div>
	</form>
</div>
