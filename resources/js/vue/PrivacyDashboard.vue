<script setup lang="ts">
/**
 * PrivacyDashboard — Vue privacy hub for authenticated users.
 *
 * Composes ConsentPreferences and DataRequestForm with a history table
 * fed by `GET /api/privacy/data-requests`. Refreshes history whenever
 * a new request is submitted via the embedded form.
 *
 * @since 1.0.0
 */

import { computed, onMounted, ref } from 'vue';
import ConsentPreferences from './ConsentPreferences.vue';
import DataRequestForm, { type DataRequestResult } from './DataRequestForm.vue';
import { useConsent, type UseConsentOptions } from './useConsent';

export interface PrivacyDashboardRequest {
	id: number;
	type: string;
	status: string;
	regulation: string | null;
	created_at: string | null;
	due_at: string | null;
	completed_at: string | null;
	download_url: string | null;
}

export interface PrivacyDashboardHistoryPayload {
	regulation: string | null;
	requests: PrivacyDashboardRequest[];
}

interface PrivacyDashboardProps extends UseConsentOptions {
	className?: string;
	policyUrl?: string;
	historyEndpoint?: string;
	requestsEndpoint?: string;
	historyLimit?: number;
	showConsent?: boolean;
	showRequestForm?: boolean;
	showHistory?: boolean;
	csrfToken?: string;
	fetchImpl?: typeof fetch;
}

const props = withDefaults( defineProps<PrivacyDashboardProps>(), {
	className: 'privacy-dashboard',
	historyEndpoint: '/api/privacy/data-requests',
	requestsEndpoint: '/api/privacy/data-requests',
	historyLimit: 25,
	showConsent: true,
	showRequestForm: true,
	showHistory: true,
} );

const consentOptions = computed<UseConsentOptions>( () => ( {
	baseUrl: props.baseUrl,
	csrfToken: props.csrfToken,
	fetch: props.fetch,
	client: props.client,
	skipInitialLoad: props.skipInitialLoad,
} ) );

const consent = useConsent( consentOptions.value );
const history = ref<PrivacyDashboardHistoryPayload | null>( null );
const loadingHistory = ref( false );
const historyError = ref<string | null>( null );

function fetcher(): typeof fetch {
	if ( props.fetchImpl ) {
		return props.fetchImpl;
	}
	if ( typeof globalThis !== 'undefined' && 'function' === typeof globalThis.fetch ) {
		return globalThis.fetch.bind( globalThis ) as typeof fetch;
	}
	return ( () => Promise.reject( new Error( 'fetch is not available' ) ) ) as typeof fetch;
}

function formatDate( iso: string | null ): string {
	if ( null === iso ) {
		return '—';
	}
	const parsed = new Date( iso );
	if ( Number.isNaN( parsed.getTime() ) ) {
		return iso;
	}
	return parsed.toLocaleDateString();
}

function capitalise( value: string ): string {
	if ( '' === value ) {
		return value;
	}
	return value.charAt( 0 ).toUpperCase() + value.slice( 1 );
}

async function loadHistory(): Promise<void> {
	if ( ! props.showHistory ) {
		return;
	}
	loadingHistory.value = true;
	historyError.value = null;
	try {
		const url = `${ props.historyEndpoint.replace( /\/+$/, '' ) }?limit=${ props.historyLimit }`;
		const response = await fetcher()( url, {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
			},
		} );
		if ( ! response.ok ) {
			throw new Error( `Failed to load history (${ response.status })` );
		}
		history.value = ( await response.json() ) as PrivacyDashboardHistoryPayload;
	} catch ( err ) {
		historyError.value = err instanceof Error ? err.message : String( err );
	} finally {
		loadingHistory.value = false;
	}
}

function handleSubmitted( _result: DataRequestResult ): void {
	void loadHistory();
}

const regulation = computed<string | null>(
	() => history.value?.regulation ?? consent.state.value?.regulation ?? null,
);

onMounted( () => {
	void loadHistory();
} );
</script>

<template>
	<div :class="props.className">
		<header class="privacy-dashboard__header">
			<slot name="heading">
				<h2>Your privacy dashboard</h2>
			</slot>
			<p>Review your consent settings, file data requests, and download completed exports.</p>
			<p v-if="regulation !== null" class="privacy-dashboard__regulation" aria-live="polite">
				Regulation: <strong>{{ regulation.toUpperCase() }}</strong>
			</p>
			<p v-if="props.policyUrl">
				<a :href="props.policyUrl" class="privacy-dashboard__policy-link">
					Read our privacy policy
				</a>
			</p>
		</header>

		<section
			v-if="props.showConsent"
			aria-labelledby="privacy-dashboard-consent-heading-vue"
			class="privacy-dashboard__section"
		>
			<h3 id="privacy-dashboard-consent-heading-vue">Consent preferences</h3>
			<ConsentPreferences />
		</section>

		<section
			v-if="props.showRequestForm"
			aria-labelledby="privacy-dashboard-request-heading-vue"
			class="privacy-dashboard__section"
		>
			<h3 id="privacy-dashboard-request-heading-vue">Submit a privacy request</h3>
			<DataRequestForm
				:endpoint="props.requestsEndpoint"
				:csrf-token="props.csrfToken"
				:fetch-impl="props.fetchImpl"
				@submitted="handleSubmitted"
			/>
		</section>

		<section
			v-if="props.showHistory"
			aria-labelledby="privacy-dashboard-history-heading-vue"
			class="privacy-dashboard__section"
		>
			<h3 id="privacy-dashboard-history-heading-vue">Request history</h3>
			<p v-if="loadingHistory" aria-busy="true">Loading…</p>
			<p v-if="historyError !== null" role="alert" class="privacy-dashboard__error">
				{{ historyError }}
			</p>
			<p v-if="! loadingHistory && history !== null && history.requests.length === 0">
				You have not filed any privacy requests yet.
			</p>
			<table v-if="history !== null && history.requests.length > 0" class="privacy-dashboard__table">
				<thead>
					<tr>
						<th scope="col">Type</th>
						<th scope="col">Status</th>
						<th scope="col">Filed</th>
						<th scope="col">Due</th>
						<th scope="col">Actions</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="request in history.requests" :key="request.id">
						<td>{{ capitalise( request.type ) }}</td>
						<td>{{ capitalise( request.status ) }}</td>
						<td>{{ formatDate( request.created_at ) }}</td>
						<td>{{ formatDate( request.due_at ) }}</td>
						<td>
							<a v-if="request.download_url" :href="request.download_url" rel="noopener">
								Download export
							</a>
							<span v-else>—</span>
						</td>
					</tr>
				</tbody>
			</table>
		</section>
	</div>
</template>
