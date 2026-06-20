<script setup lang="ts">
/**
 * Admin\ConsentManager — Vue table for browsing and filtering consents.
 *
 * Backed by `GET /api/privacy/admin/consents`. Renders a filter bar,
 * paginated table, and JSON / CSV download buttons.
 *
 * @since 1.0.0
 */

import { computed, onMounted, ref, watch } from 'vue';

export interface AdminConsentRow {
	id: number;
	consentable_type: string;
	consentable_id: number | string;
	category: string;
	granted: boolean;
	regulation: string | null;
	ip_address: string | null;
	created_at: string | null;
	expires_at: string | null;
	withdrawn_at: string | null;
}

export interface AdminConsentPayload {
	data: AdminConsentRow[];
	meta: {
		current_page: number;
		per_page: number;
		total: number;
		last_page: number;
	};
	categories: string[];
}

interface ConsentManagerProps {
	className?: string;
	endpoint?: string;
	csrfToken?: string;
	fetchImpl?: typeof fetch;
	perPage?: number;
}

const props = withDefaults( defineProps<ConsentManagerProps>(), {
	className: 'privacy-admin-consent-manager',
	endpoint: '/api/privacy/admin/consents',
	perPage: 25,
} );

type StatusFilter = 'all' | 'active' | 'withdrawn' | 'expired';

const filters = ref( {
	q: '',
	category: '',
	status: 'all' as StatusFilter,
	from: '',
	to: '',
} );

const page = ref( 1 );
const payload = ref<AdminConsentPayload | null>( null );
const loading = ref( false );
const error = ref<string | null>( null );

function fetcher(): typeof fetch {
	if ( props.fetchImpl ) {
		return props.fetchImpl;
	}
	if ( typeof globalThis !== 'undefined' && 'function' === typeof globalThis.fetch ) {
		return globalThis.fetch.bind( globalThis ) as typeof fetch;
	}
	return ( () => Promise.reject( new Error( 'fetch is not available' ) ) ) as typeof fetch;
}

function buildQuery(): string {
	const params = new URLSearchParams();
	if ( '' !== filters.value.q ) {
		params.set( 'q', filters.value.q );
	}
	if ( '' !== filters.value.category ) {
		params.set( 'category', filters.value.category );
	}
	if ( 'all' !== filters.value.status ) {
		params.set( 'status', filters.value.status );
	}
	if ( '' !== filters.value.from ) {
		params.set( 'from', filters.value.from );
	}
	if ( '' !== filters.value.to ) {
		params.set( 'to', filters.value.to );
	}
	params.set( 'page', String( page.value ) );
	params.set( 'per_page', String( props.perPage ) );
	return params.toString();
}

async function load(): Promise<void> {
	loading.value = true;
	error.value = null;
	try {
		const url = `${ props.endpoint.replace( /\/+$/, '' ) }?${ buildQuery() }`;
		const response = await fetcher()( url, {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
			},
		} );
		if ( ! response.ok ) {
			throw new Error( `Failed to load consents (${ response.status })` );
		}
		payload.value = ( await response.json() ) as AdminConsentPayload;
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : String( err );
	} finally {
		loading.value = false;
	}
}

watch(
	() => [ filters.value.q, filters.value.category, filters.value.status, filters.value.from, filters.value.to, page.value ],
	() => {
		void load();
	},
	{ deep: false },
);

onMounted( () => {
	void load();
} );

function reset(): void {
	filters.value = { q: '', category: '', status: 'all', from: '', to: '' };
	page.value = 1;
}

function triggerDownload( contents: string, mime: string, filename: string ): void {
	const blob = new Blob( [ contents ], { type: mime } );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	document.body.appendChild( link );
	link.click();
	link.remove();
	URL.revokeObjectURL( url );
}

function escapeCsv( value: unknown ): string {
	if ( null === value || undefined === value ) {
		return '';
	}
	const str = String( value );
	if ( /[",\n]/.test( str ) ) {
		return `"${ str.replace( /"/g, '""' ) }"`;
	}
	return str;
}

function exportCsv(): void {
	if ( null === payload.value ) {
		return;
	}
	const header = [
		'id',
		'consentable_type',
		'consentable_id',
		'category',
		'granted',
		'regulation',
		'ip_address',
		'created_at',
		'expires_at',
		'withdrawn_at',
	];
	const lines = [
		header.join( ',' ),
		...payload.value.data.map( ( row ) =>
			[
				row.id,
				row.consentable_type,
				row.consentable_id,
				row.category,
				row.granted ? 1 : 0,
				row.regulation,
				row.ip_address,
				row.created_at,
				row.expires_at,
				row.withdrawn_at,
			]
				.map( escapeCsv )
				.join( ',' ),
		),
	];
	triggerDownload( lines.join( '\n' ), 'text/csv', 'privacy-consents.csv' );
}

function exportJson(): void {
	if ( null === payload.value ) {
		return;
	}
	triggerDownload( JSON.stringify( payload.value.data, null, 2 ), 'application/json', 'privacy-consents.json' );
}

const meta = computed( () => payload.value?.meta ?? null );
const categories = computed( () => payload.value?.categories ?? [] );
</script>

<template>
	<div :class="props.className">
		<header class="privacy-admin-consent-manager__header">
			<slot name="heading">
				<h2>Consent records</h2>
			</slot>
			<p>Browse, filter, and export consent activity across all users.</p>
		</header>

		<section class="privacy-admin-consent-manager__filters" aria-label="Filters">
			<label>
				<span>Search</span>
				<input v-model="filters.q" type="search" placeholder="User, category, IP…" />
			</label>

			<label>
				<span>Category</span>
				<select v-model="filters.category">
					<option value="">All categories</option>
					<option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
				</select>
			</label>

			<label>
				<span>Status</span>
				<select v-model="filters.status">
					<option value="all">All</option>
					<option value="active">Active</option>
					<option value="withdrawn">Withdrawn</option>
					<option value="expired">Expired</option>
				</select>
			</label>

			<label>
				<span>From</span>
				<input v-model="filters.from" type="date" />
			</label>

			<label>
				<span>To</span>
				<input v-model="filters.to" type="date" />
			</label>

			<div class="privacy-admin-consent-manager__actions">
				<button type="button" @click="reset">Reset</button>
				<button type="button" :disabled="payload === null" @click="exportCsv">Export CSV</button>
				<button type="button" :disabled="payload === null" @click="exportJson">Export JSON</button>
			</div>
		</section>

		<p v-if="loading" aria-busy="true">Loading…</p>

		<p v-if="error !== null" role="alert" class="privacy-admin-consent-manager__error">
			{{ error }}
		</p>

		<template v-if="payload !== null && ! loading">
			<table class="privacy-admin-consent-manager__table">
				<thead>
					<tr>
						<th scope="col">Subject</th>
						<th scope="col">Category</th>
						<th scope="col">Granted</th>
						<th scope="col">Regulation</th>
						<th scope="col">Recorded</th>
						<th scope="col">Expires</th>
						<th scope="col">Withdrawn</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="payload.data.length === 0">
						<td colspan="7">No consent records match the selected filters.</td>
					</tr>
					<tr v-for="row in payload.data" :key="row.id">
						<td>
							<div>{{ row.consentable_type }}</div>
							<div class="privacy-admin-consent-manager__subject-id">#{{ row.consentable_id }}</div>
						</td>
						<td>{{ row.category }}</td>
						<td>{{ row.granted ? 'Yes' : 'No' }}</td>
						<td>{{ row.regulation ?? '—' }}</td>
						<td>{{ row.created_at ?? '—' }}</td>
						<td>{{ row.expires_at ?? '—' }}</td>
						<td>{{ row.withdrawn_at ?? '—' }}</td>
					</tr>
				</tbody>
			</table>

			<nav v-if="meta !== null && meta.last_page > 1" class="privacy-admin-consent-manager__pager" aria-label="Pagination">
				<button type="button" :disabled="page === 1" @click="page = Math.max( 1, page - 1 )">
					Previous
				</button>
				<span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
				<button
					type="button"
					:disabled="page >= meta.last_page"
					@click="page = Math.min( meta.last_page, page + 1 )"
				>
					Next
				</button>
			</nav>
		</template>
	</div>
</template>
