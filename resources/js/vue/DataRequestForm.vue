<script setup lang="ts">
/**
 * DataRequestForm — Vue data subject request form.
 *
 * Mirrors the React/Livewire form: a select for the request type, an
 * optional reason field, client-side validation, error/loading state,
 * and a confirmation panel that mentions identity verification when
 * required by the package config.
 *
 * @since 1.0.0
 */

import { computed, ref } from 'vue';

export type DataRequestType = 'access' | 'export' | 'deletion' | 'rectification';

export interface DataRequestResult {
	id: number;
	type: string;
	status: string;
	verification_sent: boolean;
}

interface DataRequestFormProps {
	requestTypes?: DataRequestType[];
	requireReason?: boolean;
	endpoint?: string;
	csrfToken?: string;
	className?: string;
	labels?: Partial<Record<DataRequestType, string>>;
	fetchImpl?: typeof fetch;
}

const props = withDefaults( defineProps<DataRequestFormProps>(), {
	requestTypes: () => [ 'access', 'export', 'deletion', 'rectification' ] as DataRequestType[],
	requireReason: false,
	endpoint: '/api/privacy/data-requests',
	className: 'privacy-data-request-form',
} );

const emit = defineEmits<{
	( e: 'submitted', result: DataRequestResult ): void;
}>();

const DEFAULT_LABELS: Record<DataRequestType, string> = {
	access: 'Request a copy of my data',
	export: 'Export my data',
	deletion: 'Delete my data',
	rectification: 'Correct my data',
};

const labels = computed<Record<DataRequestType, string>>( () => ( {
	...DEFAULT_LABELS,
	...( props.labels ?? {} ),
} ) );

const type = ref<DataRequestType | ''>( '' );
const reason = ref( '' );
const saving = ref( false );
const error = ref<string | null>( null );
const result = ref<DataRequestResult | null>( null );

function resolveCsrfToken(): string | undefined {
	if ( props.csrfToken ) {
		return props.csrfToken;
	}
	if ( typeof document === 'undefined' ) {
		return undefined;
	}
	const meta = document.querySelector<HTMLMetaElement>( 'meta[name="csrf-token"]' );
	return meta?.content ?? undefined;
}

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
	error.value = null;

	if ( '' === type.value ) {
		error.value = 'Please choose a request type.';
		return;
	}

	if ( props.requireReason && '' === reason.value.trim() ) {
		error.value = 'A reason is required for this request.';
		return;
	}

	saving.value = true;

	const csrfToken = resolveCsrfToken();
	const headers: Record<string, string> = {
		Accept: 'application/json',
		'Content-Type': 'application/json',
		'X-Requested-With': 'XMLHttpRequest',
	};
	if ( csrfToken ) {
		headers[ 'X-CSRF-TOKEN' ] = csrfToken;
	}

	try {
		const response = await fetcher()( props.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers,
			body: JSON.stringify( {
				type: type.value,
				reason: '' === reason.value ? null : reason.value,
			} ),
		} );

		if ( ! response.ok ) {
			const payload = ( await response.json().catch( () => null ) ) as { message?: string } | null;
			throw new Error( payload?.message ?? `Request failed (${ response.status })` );
		}

		const data = ( await response.json() ) as DataRequestResult;
		result.value = data;
		emit( 'submitted', data );
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : String( err );
	} finally {
		saving.value = false;
	}
}

function startNewRequest(): void {
	result.value = null;
	type.value = '';
	reason.value = '';
	error.value = null;
}
</script>

<template>
	<div
		v-if="result !== null"
		:class="props.className"
		role="region"
		aria-label="Privacy data request submitted"
	>
		<div class="privacy-data-request-form__success" role="status" aria-live="polite">
			<h3>Your request was submitted.</h3>
			<p>
				{{ result.verification_sent
					? 'We have emailed you a verification link. Confirm your identity to start processing.'
					: 'Our team has been notified and will follow up shortly.' }}
			</p>
			<button type="button" @click="startNewRequest">
				Submit another request
			</button>
		</div>
	</div>
	<form
		v-else
		:class="props.className"
		:aria-busy="saving"
		@submit.prevent="submit"
	>
		<slot name="heading">
			<h2>Privacy request</h2>
		</slot>
		<slot name="description" />

		<div class="privacy-data-request-form__field">
			<label for="privacy-data-request-type-vue">Request type</label>
			<select
				id="privacy-data-request-type-vue"
				v-model="type"
				:disabled="saving"
				required
			>
				<option value="">Choose one…</option>
				<option
					v-for="option in props.requestTypes"
					:key="option"
					:value="option"
				>
					{{ labels[ option ] }}
				</option>
			</select>
		</div>

		<div class="privacy-data-request-form__field">
			<label for="privacy-data-request-reason-vue">
				Reason<template v-if="! props.requireReason"> (optional)</template>
			</label>
			<textarea
				id="privacy-data-request-reason-vue"
				v-model="reason"
				:disabled="saving"
				:required="props.requireReason"
				rows="3"
				maxlength="1000"
			/>
		</div>

		<p v-if="error !== null" role="alert" class="privacy-data-request-form__error">
			{{ error }}
		</p>

		<div class="privacy-data-request-form__actions">
			<button type="submit" :disabled="saving">
				{{ saving ? 'Submitting…' : 'Submit request' }}
			</button>
		</div>
	</form>
</template>
