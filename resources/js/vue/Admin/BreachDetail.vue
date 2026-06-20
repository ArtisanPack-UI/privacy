<script setup lang="ts">
/**
 * Admin\BreachDetail — Vue breach detail panel.
 *
 * Backed by `GET /api/privacy/admin/breaches/{id}` and
 * `POST /api/privacy/admin/breaches/{id}/actions`.
 *
 * @since 1.0.0
 */

import { computed, onMounted, ref, watch } from 'vue';

export interface AdminBreachDetailPayload {
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
	description: string;
	cause: string | null;
	remediation: string | null;
	notifications_sent: Array<Record<string, unknown>>;
	affected_users: Array<string | Record<string, unknown>>;
}

interface BreachDetailProps {
	breachId: number;
	className?: string;
	endpoint?: string;
	csrfToken?: string;
	fetchImpl?: typeof fetch;
}

const props = withDefaults( defineProps<BreachDetailProps>(), {
	className: 'privacy-admin-breach-detail',
	endpoint: '/api/privacy/admin/breaches',
} );

type ActionKey = 'notify_authority' | 'notify_users' | 'set_status' | 'add_remediation';

const breach = ref<AdminBreachDetailPayload | null>( null );
const loading = ref( false );
const error = ref<string | null>( null );
const acting = ref<ActionKey | null>( null );
const remediationNote = ref( '' );

let sequence = 0;

const baseUrl = computed( () => `${ props.endpoint.replace( /\/+$/, '' ) }/${ props.breachId }` );

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
		const response = await fetcher()( baseUrl.value, {
			method: 'GET',
			credentials: 'same-origin',
			headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
		} );
		if ( ! response.ok ) {
			throw new Error( `Failed to load breach (${ response.status })` );
		}
		const json = ( await response.json() ) as { data: AdminBreachDetailPayload };
		if ( ticket === sequence ) {
			breach.value = json.data;
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

async function dispatchAction( action: ActionKey, body: Record<string, unknown> = {} ): Promise<void> {
	acting.value = action;
	try {
		const response = await fetcher()( `${ baseUrl.value }/actions`, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				...( props.csrfToken ? { 'X-CSRF-TOKEN': props.csrfToken } : {} ),
			},
			body: JSON.stringify( { action, ...body } ),
		} );
		if ( ! response.ok ) {
			throw new Error( `Action failed (${ response.status })` );
		}
		const json = ( await response.json() ) as { data: AdminBreachDetailPayload };
		breach.value = json.data;
		if ( 'add_remediation' === action ) {
			remediationNote.value = '';
		}
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : String( err );
	} finally {
		acting.value = null;
	}
}

watch( () => props.breachId, () => {
	void load();
} );

onMounted( () => {
	void load();
} );
</script>

<template>
	<div :class="props.className">
		<p v-if="loading" aria-busy="true">Loading…</p>
		<p v-if="null !== error" role="alert">{{ error }}</p>

		<template v-if="null !== breach">
			<header>
				<slot name="heading">
					<h2>Breach {{ breach.reference_number }}</h2>
				</slot>
				<p>{{ breach.severity }} · {{ breach.status }}</p>
			</header>

			<section aria-labelledby="breach-timeline">
				<h3 id="breach-timeline">Timeline</h3>
				<dl>
					<dt>Discovered</dt><dd>{{ breach.discovered_at ?? '—' }}</dd>
					<dt>Occurred</dt><dd>{{ breach.occurred_at ?? '—' }}</dd>
					<dt>Authority deadline</dt>
					<dd>
						{{ breach.authority_deadline }}
						<span v-if="breach.authority_overdue"> — Overdue</span>
					</dd>
					<dt>Authority notified</dt><dd>{{ breach.authority_notified_at ?? 'Not yet' }}</dd>
					<dt>Users notified</dt><dd>{{ breach.users_notified_at ?? 'Not yet' }}</dd>
				</dl>
			</section>

			<section aria-labelledby="breach-data">
				<h3 id="breach-data">Affected data</h3>
				<p><strong>Categories:</strong> {{ breach.data_types_affected.join( ', ' ) || '—' }}</p>
				<p><strong>Records:</strong> {{ breach.records_affected ?? 'Unknown' }}</p>
				<p><strong>Description:</strong> {{ breach.description }}</p>
				<p v-if="breach.cause"><strong>Cause:</strong> {{ breach.cause }}</p>
			</section>

			<section aria-labelledby="breach-remediation">
				<h3 id="breach-remediation">Remediation</h3>
				<p v-if="breach.remediation">{{ breach.remediation }}</p>
				<p v-else>No remediation notes recorded yet.</p>

				<label>
					<span>Add a remediation note</span>
					<textarea v-model="remediationNote" rows="3"></textarea>
				</label>
				<button
					type="button"
					:disabled="null !== acting || '' === remediationNote.trim()"
					@click="dispatchAction( 'add_remediation', { note: remediationNote } )"
				>
					Append note
				</button>
			</section>

			<section aria-labelledby="breach-actions">
				<h3 id="breach-actions">Notifications and status</h3>
				<div>
					<button
						type="button"
						:disabled="null !== acting"
						@click="dispatchAction( 'notify_authority' )"
					>
						Send authority notification
					</button>
					<button
						type="button"
						:disabled="null !== acting"
						@click="dispatchAction( 'notify_users' )"
					>
						Notify affected users
					</button>
				</div>
				<div>
					<button
						v-for="s in [ 'investigating', 'contained', 'resolved' ] as const"
						:key="s"
						type="button"
						:disabled="null !== acting"
						@click="dispatchAction( 'set_status', { status: s } )"
					>
						Mark {{ s }}
					</button>
				</div>
			</section>

			<section v-if="breach.notifications_sent.length > 0" aria-labelledby="breach-notifications">
				<h3 id="breach-notifications">Notification history</h3>
				<ul>
					<li v-for="( entry, i ) in breach.notifications_sent" :key="i">
						<strong>{{ String( entry.channel ?? '—' ) }}</strong>
						<span v-if="entry.recipient"> — {{ String( entry.recipient ) }}</span>
						<span v-else-if="undefined !== entry.recipient_count">
							— {{ String( entry.recipient_count ) }} recipient(s)
						</span>
						<span> · {{ String( entry.notified_at ?? '' ) }}</span>
					</li>
				</ul>
			</section>
		</template>
	</div>
</template>
