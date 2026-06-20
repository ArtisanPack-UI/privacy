<script setup lang="ts">
/**
 * Admin\BreachManager — Vue breach list panel.
 *
 * Backed by `GET /api/privacy/admin/breaches`. Renders a paginated,
 * deadline-aware list of breach incidents with severity badges.
 *
 * @since 1.0.0
 */

import { computed, onMounted, ref, watch } from 'vue';

export interface AdminBreachRow {
	id: number;
	reference_number: string;
	severity: string;
	status: string;
	discovered_at: string | null;
	occurred_at: string | null;
	authority_notified_at: string | null;
	users_notified_at: string | null;
	authority_deadline: string;
	authority_overdue: boolean;
	records_affected: number | null;
	data_types_affected: string[];
}

export interface AdminBreachPayload {
	data: AdminBreachRow[];
	meta: { current_page: number; per_page: number; total: number; last_page: number };
	severities: string[];
	statuses: string[];
}

interface BreachManagerProps {
	className?: string;
	endpoint?: string;
	detailUrlBuilder?: ( id: number ) => string;
	reportUrl?: string;
	fetchImpl?: typeof fetch;
	perPage?: number;
}

const props = withDefaults( defineProps<BreachManagerProps>(), {
	className: 'privacy-admin-breach-manager',
	endpoint: '/api/privacy/admin/breaches',
	perPage: 25,
} );

const severity = ref( '' );
const status = ref( '' );
const page = ref( 1 );
const payload = ref<AdminBreachPayload | null>( null );
const loading = ref( false );
const error = ref<string | null>( null );

let sequence = 0;

function fetcher(): typeof fetch {
	if ( props.fetchImpl ) {
		return props.fetchImpl;
	}
	if ( typeof globalThis !== 'undefined' && 'function' === typeof globalThis.fetch ) {
		return globalThis.fetch.bind( globalThis ) as typeof fetch;
	}
	return ( () => Promise.reject( new Error( 'fetch is not available' ) ) ) as typeof fetch;
}

async function load(): Promise<void> {
	const ticket = ++sequence;
	loading.value = true;
	error.value = null;
	try {
		const params = new URLSearchParams();
		if ( '' !== severity.value ) {
			params.set( 'severity', severity.value );
		}
		if ( '' !== status.value ) {
			params.set( 'status', status.value );
		}
		params.set( 'page', String( page.value ) );
		params.set( 'per_page', String( props.perPage ) );

		const response = await fetcher()(
			`${ props.endpoint.replace( /\/+$/, '' ) }?${ params.toString() }`,
			{
				method: 'GET',
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			},
		);
		if ( ! response.ok ) {
			throw new Error( `Failed to load breaches (${ response.status })` );
		}
		if ( ticket === sequence ) {
			payload.value = ( await response.json() ) as AdminBreachPayload;
		}
	} catch ( err ) {
		if ( ticket === sequence ) {
			error.value = err instanceof Error ? err.message : String( err );
		}
	} finally {
		if ( ticket === sequence ) {
			loading.value = false;
		}
	}
}

watch( [ severity, status ], () => {
	page.value = 1;
} );
watch( [ severity, status, page ], () => {
	void load();
} );

onMounted( () => {
	void load();
} );

const meta = computed( () => payload.value?.meta ?? null );
const severities = computed( () => payload.value?.severities ?? [] );
const statuses = computed( () => payload.value?.statuses ?? [] );
</script>

<template>
	<div :class="props.className">
		<header class="privacy-admin-breach-manager__header">
			<slot name="heading">
				<h2>Breach incidents</h2>
			</slot>
			<p>Track open and resolved data breach incidents and notification status.</p>
			<a v-if="props.reportUrl" :href="props.reportUrl">Report a breach</a>
		</header>

		<section class="privacy-admin-breach-manager__filters" aria-label="Filters">
			<label>
				<span>Severity</span>
				<select v-model="severity">
					<option value="">All severities</option>
					<option v-for="s in severities" :key="s" :value="s">
						{{ s.charAt( 0 ).toUpperCase() + s.slice( 1 ) }}
					</option>
				</select>
			</label>
			<label>
				<span>Status</span>
				<select v-model="status">
					<option value="">All statuses</option>
					<option v-for="s in statuses" :key="s" :value="s">
						{{ s.charAt( 0 ).toUpperCase() + s.slice( 1 ) }}
					</option>
				</select>
			</label>
		</section>

		<p v-if="loading" aria-busy="true">Loading…</p>
		<p v-if="null !== error" role="alert">{{ error }}</p>

		<table class="privacy-admin-breach-manager__table">
			<thead>
				<tr>
					<th scope="col">Reference</th>
					<th scope="col">Severity</th>
					<th scope="col">Status</th>
					<th scope="col">Discovered</th>
					<th scope="col">Authority deadline</th>
					<th scope="col">Records</th>
					<th scope="col">Actions</th>
				</tr>
			</thead>
			<tbody>
				<tr v-if="null !== payload && 0 === payload.data.length">
					<td colspan="7">No breaches match the selected filters.</td>
				</tr>
				<tr
					v-for="row in payload?.data ?? []"
					:key="row.id"
					:class="row.authority_overdue ? 'is-overdue' : undefined"
				>
					<td>
						<a v-if="props.detailUrlBuilder" :href="props.detailUrlBuilder( row.id )">{{ row.reference_number }}</a>
						<template v-else>{{ row.reference_number }}</template>
					</td>
					<td>{{ row.severity }}</td>
					<td>{{ row.status }}</td>
					<td>{{ row.discovered_at ?? '—' }}</td>
					<td>
						{{ row.authority_deadline }}
						<div v-if="row.authority_overdue" class="is-overdue">Overdue</div>
					</td>
					<td>{{ row.records_affected ?? '—' }}</td>
					<td>
						<a v-if="props.detailUrlBuilder" :href="props.detailUrlBuilder( row.id )">View</a>
					</td>
				</tr>
			</tbody>
		</table>

		<nav
			v-if="null !== meta && meta.last_page > 1"
			class="privacy-admin-breach-manager__pager"
			aria-label="Pagination"
		>
			<button type="button" :disabled="1 === page" @click="page = Math.max( 1, page - 1 )">Previous</button>
			<span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
			<button
				type="button"
				:disabled="page >= meta.last_page"
				@click="page = Math.min( meta.last_page, page + 1 )"
			>
				Next
			</button>
		</nav>
	</div>
</template>
