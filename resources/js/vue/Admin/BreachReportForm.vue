<script setup lang="ts">
/**
 * Admin\BreachReportForm — Vue breach report form.
 *
 * Posts to `POST /api/privacy/admin/breaches`.
 *
 * @since 1.0.0
 */

import { ref } from 'vue';
import type { AdminBreachRow } from './BreachManager.vue';

interface BreachReportFormProps {
	className?: string;
	endpoint?: string;
	csrfToken?: string;
	fetchImpl?: typeof fetch;
}

const props = withDefaults( defineProps<BreachReportFormProps>(), {
	className: 'privacy-admin-breach-report-form',
	endpoint: '/api/privacy/admin/breaches',
} );

const emit = defineEmits<{ ( e: 'submitted', breach: AdminBreachRow ): void }>();

const description = ref( '' );
const severity = ref( 'medium' );
const dataTypes = ref( '' );
const recordsAffected = ref( '' );
const affectedUsers = ref( '' );
const cause = ref( '' );
const remediation = ref( '' );
const occurredAt = ref( '' );

const submitting = ref( false );
const error = ref<string | null>( null );
const success = ref<AdminBreachRow | null>( null );

function fetcher(): typeof fetch {
	if ( props.fetchImpl ) {
		return props.fetchImpl;
	}
	if ( typeof globalThis !== 'undefined' && 'function' === typeof globalThis.fetch ) {
		return globalThis.fetch.bind( globalThis ) as typeof fetch;
	}
	return ( () => Promise.reject( new Error( 'fetch is not available' ) ) ) as typeof fetch;
}

async function submit(): Promise<void> {
	submitting.value = true;
	error.value = null;

	const dataTypesList = dataTypes.value
		.split( ',' )
		.map( ( v ) => v.trim() )
		.filter( ( v ) => '' !== v );

	const affectedList = affectedUsers.value
		.split( /[\r\n]+/ )
		.map( ( v ) => v.trim() )
		.filter( ( v ) => '' !== v );

	try {
		const response = await fetcher()( props.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				...( props.csrfToken ? { 'X-CSRF-TOKEN': props.csrfToken } : {} ),
			},
			body: JSON.stringify( {
				description: description.value,
				severity: severity.value,
				data_types_affected: dataTypesList,
				records_affected: '' === recordsAffected.value ? null : Number( recordsAffected.value ),
				affected_users: 0 === affectedList.length ? null : affectedList,
				cause: '' === cause.value ? null : cause.value,
				remediation: '' === remediation.value ? null : remediation.value,
				occurred_at: '' === occurredAt.value ? null : occurredAt.value,
			} ),
		} );

		if ( ! response.ok ) {
			throw new Error( `Failed to report breach (${ response.status })` );
		}

		const json = ( await response.json() ) as { data: AdminBreachRow };
		success.value = json.data;
		emit( 'submitted', json.data );

		description.value = '';
		dataTypes.value = '';
		recordsAffected.value = '';
		affectedUsers.value = '';
		cause.value = '';
		remediation.value = '';
		occurredAt.value = '';
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : String( err );
	} finally {
		submitting.value = false;
	}
}
</script>

<template>
	<div :class="props.className">
		<header>
			<slot name="heading">
				<h2>Report a breach</h2>
			</slot>
			<p>Document a new incident to start the notification workflow.</p>
		</header>

		<div v-if="null !== success" role="status">
			Breach recorded successfully ({{ success.reference_number }}).
		</div>

		<p v-if="null !== error" role="alert">{{ error }}</p>

		<form @submit.prevent="submit">
			<label>
				<span>Description *</span>
				<textarea v-model="description" rows="4" required></textarea>
			</label>

			<label>
				<span>Severity *</span>
				<select v-model="severity" required>
					<option value="low">Low</option>
					<option value="medium">Medium</option>
					<option value="high">High</option>
					<option value="critical">Critical</option>
				</select>
			</label>

			<label>
				<span>Occurred at (optional)</span>
				<input type="datetime-local" v-model="occurredAt" />
			</label>

			<label>
				<span>Data types affected (comma-separated) *</span>
				<input type="text" v-model="dataTypes" placeholder="email, name, hashed_password" required />
			</label>

			<label>
				<span>Approximate records affected</span>
				<input type="number" min="0" v-model="recordsAffected" />
			</label>

			<label>
				<span>Affected users (one email per line)</span>
				<textarea v-model="affectedUsers" rows="3"></textarea>
			</label>

			<label>
				<span>Suspected cause</span>
				<textarea v-model="cause" rows="2"></textarea>
			</label>

			<label>
				<span>Remediation measures</span>
				<textarea v-model="remediation" rows="2"></textarea>
			</label>

			<button type="submit" :disabled="submitting">
				{{ submitting ? 'Recording…' : 'Record breach' }}
			</button>
		</form>
	</div>
</template>
