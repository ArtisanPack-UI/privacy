<script setup lang="ts">
/**
 * Admin\DataRequestManager — Vue queue for data subject requests.
 *
 * Backed by `GET /api/privacy/admin/data-requests` and
 * `POST /api/privacy/admin/data-requests/{id}/actions`. Renders a
 * filtered table with a details side panel for verification, approval,
 * rejection, and completion.
 *
 * @since 1.0.0
 */

import { computed, onMounted, ref, watch } from 'vue';

export interface AdminDataRequestRow {
	id: number;
	type: string;
	status: string;
	regulation: string | null;
	requestable_type: string;
	requestable_id: number | string;
	verified_at: string | null;
	due_at: string | null;
	completed_at: string | null;
	created_at: string | null;
	overdue: boolean;
}

export interface AdminDataRequestDetails extends AdminDataRequestRow {
	reason: string | null;
	admin_notes: string | null;
	data: Record<string, unknown> | null;
	logs: Array<{
		id: number;
		action: string;
		description: string | null;
		metadata: Record<string, unknown> | null;
		performed_by: number | null;
		created_at: string | null;
	}>;
}

export interface AdminDataRequestPayload {
	data: AdminDataRequestRow[];
	meta: {
		current_page: number;
		per_page: number;
		total: number;
		last_page: number;
	};
	allowed_types: string[];
}

interface DataRequestManagerProps {
	className?: string;
	endpoint?: string;
	csrfToken?: string;
	fetchImpl?: typeof fetch;
	perPage?: number;
}

const props = withDefaults( defineProps<DataRequestManagerProps>(), {
	className: 'privacy-admin-data-request-manager',
	endpoint: '/api/privacy/admin/data-requests',
	perPage: 25,
} );

type SortKey = 'newest' | 'deadline' | 'overdue';
type ActionKey = 'approve' | 'reject' | 'complete' | 'verify';

const filters = ref( {
	type: '',
	status: '',
	sort: 'deadline' as SortKey,
} );

const page = ref( 1 );
const payload = ref<AdminDataRequestPayload | null>( null );
const loading = ref( false );
const error = ref<string | null>( null );

const details = ref<AdminDataRequestDetails | null>( null );
const note = ref( '' );
const acting = ref<ActionKey | null>( null );

let loadSequence = 0;
let detailsSequence = 0;

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
	if ( '' !== filters.value.type ) {
		params.set( 'type', filters.value.type );
	}
	if ( '' !== filters.value.status ) {
		params.set( 'status', filters.value.status );
	}
	if ( 'deadline' !== filters.value.sort ) {
		params.set( 'sort', filters.value.sort );
	}
	params.set( 'page', String( page.value ) );
	params.set( 'per_page', String( props.perPage ) );
	return params.toString();
}

async function load(): Promise<void> {
	const sequence = ++loadSequence;
	loading.value = true;
	error.value = null;
	try {
		const response = await fetcher()(
			`${ props.endpoint.replace( /\/+$/, '' ) }?${ buildQuery() }`,
			{
				method: 'GET',
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			},
		);
		if ( ! response.ok ) {
			throw new Error( `Failed to load requests (${ response.status })` );
		}
		if ( sequence === loadSequence ) {
			payload.value = ( await response.json() ) as AdminDataRequestPayload;
		}
	} catch ( err ) {
		if ( sequence === loadSequence ) {
			error.value = err instanceof Error ? err.message : String( err );
		}
	} finally {
		if ( sequence === loadSequence ) {
			loading.value = false;
		}
	}
}

async function openDetails( id: number ): Promise<void> {
	const sequence = ++detailsSequence;
	try {
		const response = await fetcher()(
			`${ props.endpoint.replace( /\/+$/, '' ) }/${ id }`,
			{
				method: 'GET',
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			},
		);
		if ( ! response.ok ) {
			throw new Error( `Failed to load request (${ response.status })` );
		}
		const json = ( await response.json() ) as { data: AdminDataRequestDetails };
		if ( sequence === detailsSequence ) {
			details.value = json.data;
			note.value = '';
		}
	} catch ( err ) {
		if ( sequence === detailsSequence ) {
			error.value = err instanceof Error ? err.message : String( err );
		}
	}
}

function closeDetails(): void {
	details.value = null;
	note.value = '';
}

async function performAction( action: ActionKey ): Promise<void> {
	if ( null === details.value ) {
		return;
	}
	acting.value = action;
	try {
		const response = await fetcher()(
			`${ props.endpoint.replace( /\/+$/, '' ) }/${ details.value.id }/actions`,
			{
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
					...( props.csrfToken ? { 'X-CSRF-TOKEN': props.csrfToken } : {} ),
				},
				body: JSON.stringify( { action, note: note.value } ),
			},
		);
		if ( ! response.ok ) {
			throw new Error( `Action failed (${ response.status })` );
		}
		const json = ( await response.json() ) as { data: AdminDataRequestDetails };
		details.value = json.data;
		note.value = '';
		await load();
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : String( err );
	} finally {
		acting.value = null;
	}
}

watch(
	() => [ filters.value.type, filters.value.status, filters.value.sort, page.value ],
	() => {
		void load();
	},
	{ deep: false },
);

onMounted( () => {
	void load();
} );

const meta = computed( () => payload.value?.meta ?? null );
const allowedTypes = computed( () => payload.value?.allowed_types ?? [] );
</script>

<template>
	<div :class="props.className">
		<header class="privacy-admin-data-request-manager__header">
			<slot name="heading">
				<h2>Data subject requests</h2>
			</slot>
			<p>Process pending requests, verify identities manually, and track deadlines.</p>
		</header>

		<section class="privacy-admin-data-request-manager__filters" aria-label="Filters">
			<label>
				<span>Type</span>
				<select v-model="filters.type">
					<option value="">All types</option>
					<option v-for="t in allowedTypes" :key="t" :value="t">
						{{ t.charAt( 0 ).toUpperCase() + t.slice( 1 ) }}
					</option>
				</select>
			</label>

			<label>
				<span>Status</span>
				<select v-model="filters.status">
					<option value="">All statuses</option>
					<option value="pending">Pending</option>
					<option value="processing">Processing</option>
					<option value="completed">Completed</option>
					<option value="rejected">Rejected</option>
				</select>
			</label>

			<label>
				<span>Sort by</span>
				<select v-model="filters.sort">
					<option value="deadline">Closest deadline</option>
					<option value="newest">Newest first</option>
					<option value="overdue">Overdue only</option>
				</select>
			</label>
		</section>

		<p v-if="loading" aria-busy="true">Loading…</p>

		<p v-if="error !== null" role="alert" class="privacy-admin-data-request-manager__error">
			{{ error }}
		</p>

		<div class="privacy-admin-data-request-manager__layout">
			<table class="privacy-admin-data-request-manager__table">
				<thead>
					<tr>
						<th scope="col">Type</th>
						<th scope="col">Status</th>
						<th scope="col">Subject</th>
						<th scope="col">Filed</th>
						<th scope="col">Due</th>
						<th scope="col">Actions</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="payload !== null && payload.data.length === 0">
						<td colspan="6">No requests match the selected filters.</td>
					</tr>
					<tr
						v-for="row in payload?.data ?? []"
						:key="row.id"
						:class="row.overdue ? 'is-overdue' : undefined"
					>
						<td>{{ row.type }}</td>
						<td>{{ row.status }}</td>
						<td>
							<div>{{ row.requestable_type }}</div>
							<div class="privacy-admin-data-request-manager__subject-id">#{{ row.requestable_id }}</div>
						</td>
						<td>{{ row.created_at ?? '—' }}</td>
						<td>
							{{ row.due_at ?? '—' }}
							<div v-if="row.overdue" class="privacy-admin-data-request-manager__overdue">Overdue</div>
						</td>
						<td>
							<button type="button" @click="openDetails( row.id )">View</button>
						</td>
					</tr>
				</tbody>
			</table>

			<aside
				v-if="details !== null"
				class="privacy-admin-data-request-manager__details"
				aria-label="Request details"
			>
				<header>
					<h3>Request #{{ details.id }}</h3>
					<p>{{ details.type }} · {{ details.status }}</p>
					<button type="button" @click="closeDetails">Close</button>
				</header>

				<dl>
					<dt>Subject</dt>
					<dd>{{ details.requestable_type }} #{{ details.requestable_id }}</dd>
					<dt>Filed</dt>
					<dd>{{ details.created_at ?? '—' }}</dd>
					<dt>Verified</dt>
					<dd>{{ details.verified_at ?? 'Not yet' }}</dd>
					<dt>Due</dt>
					<dd>{{ details.due_at ?? '—' }}</dd>
					<dt>Regulation</dt>
					<dd>{{ details.regulation ?? '—' }}</dd>
				</dl>

				<section v-if="details.reason">
					<h4>Reason</h4>
					<p>{{ details.reason }}</p>
				</section>

				<section v-if="details.admin_notes">
					<h4>Admin notes</h4>
					<p>{{ details.admin_notes }}</p>
				</section>

				<section>
					<label>
						<span>Add a note</span>
						<textarea v-model="note" rows="3" />
					</label>
					<div class="privacy-admin-data-request-manager__action-buttons">
						<button
							v-if="details.verified_at === null"
							type="button"
							:disabled="acting !== null"
							@click="performAction( 'verify' )"
						>
							Verify manually
						</button>
						<template v-if="details.status !== 'completed' && details.status !== 'rejected'">
							<button type="button" :disabled="acting !== null" @click="performAction( 'approve' )">
								Approve
							</button>
							<button type="button" :disabled="acting !== null" @click="performAction( 'complete' )">
								Complete
							</button>
							<button type="button" :disabled="acting !== null" @click="performAction( 'reject' )">
								Reject
							</button>
						</template>
					</div>
				</section>

				<section v-if="details.logs.length > 0">
					<h4>History</h4>
					<ul>
						<li v-for="log in details.logs" :key="log.id">
							<strong>{{ log.action }}</strong>
							<span> — {{ log.created_at ?? '' }}</span>
							<div v-if="log.metadata && 'note' in log.metadata">
								{{ String( log.metadata.note ) }}
							</div>
						</li>
					</ul>
				</section>
			</aside>
		</div>

		<nav
			v-if="meta !== null && meta.last_page > 1"
			class="privacy-admin-data-request-manager__pager"
			aria-label="Pagination"
		>
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
	</div>
</template>
