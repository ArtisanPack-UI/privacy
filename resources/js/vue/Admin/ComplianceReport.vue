<script setup lang="ts">
/**
 * Admin\ComplianceReport — Vue compliance report panel.
 *
 * Backed by `GET /api/privacy/admin/compliance-report`. Renders consent,
 * request, and breach metrics for the selected date range with optional
 * regulation filtering and CSV export.
 *
 * @since 1.0.0
 */

import { computed, onMounted, ref, watch } from 'vue';

export interface ComplianceConsentStats {
	total: number;
	granted: number;
	withdrawn: number;
	expired: number;
	grant_rate: number;
	withdrawal_rate: number;
	by_category: Record<string, number>;
}

export interface ComplianceRequestStats {
	total: number;
	overdue: number;
	completed: number;
	average_response_seconds: number;
	average_response_by_type: Record<string, number>;
	percentiles_seconds: { p50: number; p90: number; p99: number };
	deadline_compliance_percent: number;
	by_type: Record<string, number>;
}

export interface ComplianceBreachStats {
	total: number;
	by_severity: Record<string, number>;
	authority_notified_on_time: number;
	authority_notified_late: number;
	authority_notification_pending: number;
	authority_notification_compliance_percent: number;
}

export interface ComplianceReportPayload {
	meta: { from: string; to: string; regulation: string | null; generated_at: string };
	consents: ComplianceConsentStats;
	requests: ComplianceRequestStats;
	breaches: ComplianceBreachStats;
}

export interface ComplianceReportResponse {
	data: ComplianceReportPayload;
	regulations: string[];
}

interface ComplianceReportProps {
	className?: string;
	endpoint?: string;
	fetchImpl?: typeof fetch;
}

const props = withDefaults( defineProps<ComplianceReportProps>(), {
	className: 'privacy-admin-compliance-report',
	endpoint: '/api/privacy/admin/compliance-report',
} );

function defaultFrom(): string {
	const date = new Date();
	date.setDate( date.getDate() - 30 );
	return date.toISOString().slice( 0, 10 );
}

function defaultTo(): string {
	return new Date().toISOString().slice( 0, 10 );
}

const from = ref<string>( defaultFrom() );
const to = ref<string>( defaultTo() );
const regulation = ref( '' );
const payload = ref<ComplianceReportResponse | null>( null );
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
		params.set( 'from', from.value );
		params.set( 'to', to.value );
		if ( '' !== regulation.value ) {
			params.set( 'regulation', regulation.value );
		}
		const response = await fetcher()(
			`${ props.endpoint.replace( /\/+$/, '' ) }?${ params.toString() }`,
			{
				method: 'GET',
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			},
		);
		if ( ! response.ok ) {
			throw new Error( `Failed to load report (${ response.status })` );
		}
		if ( ticket === sequence ) {
			payload.value = ( await response.json() ) as ComplianceReportResponse;
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

function exportCsv(): void {
	if ( typeof document === 'undefined' || null === payload.value ) {
		return;
	}
	const report = payload.value.data;
	const rows: string[] = [ 'section,metric,value' ];
	const sections: Array<'consents' | 'requests' | 'breaches'> = [ 'consents', 'requests', 'breaches' ];
	const escape = ( value: string ): string => `"${ value.replace( /"/g, '""' ) }"`;

	for ( const section of sections ) {
		const data = report[ section ] as Record<string, unknown>;
		for ( const metric of Object.keys( data ) ) {
			const value = data[ metric ];
			if ( value !== null && 'object' === typeof value ) {
				for ( const [ subKey, subValue ] of Object.entries( value as Record<string, unknown> ) ) {
					rows.push( `${ section },${ escape( `${ metric }.${ subKey }` ) },${ escape( String( subValue ) ) }` );
				}
				continue;
			}
			rows.push( `${ section },${ escape( metric ) },${ escape( String( value ) ) }` );
		}
	}

	const blob = new Blob( [ rows.join( '\n' ) ], { type: 'text/csv' } );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = `privacy-compliance-${ from.value }-to-${ to.value }.csv`;
	document.body.appendChild( link );
	link.click();
	document.body.removeChild( link );
	URL.revokeObjectURL( url );
}

watch( [ from, to, regulation ], () => {
	void load();
} );

onMounted( () => {
	void load();
} );

const report = computed( () => payload.value?.data ?? null );
const regulations = computed( () => payload.value?.regulations ?? [] );
</script>

<template>
	<div :class="props.className">
		<header class="privacy-admin-compliance-report__header">
			<slot name="heading">
				<h2>Compliance report</h2>
			</slot>
			<p>Consent, request, and breach metrics for the selected reporting window.</p>
		</header>

		<section class="privacy-admin-compliance-report__filters" aria-label="Filters">
			<label>
				<span>From</span>
				<input type="date" v-model="from" />
			</label>
			<label>
				<span>To</span>
				<input type="date" v-model="to" />
			</label>
			<label>
				<span>Regulation</span>
				<select v-model="regulation">
					<option value="">All regulations</option>
					<option v-for="key in regulations" :key="key" :value="key">{{ key.toUpperCase() }}</option>
				</select>
			</label>
			<button type="button" :disabled="null === report" @click="exportCsv">Export CSV</button>
		</section>

		<p v-if="loading" aria-busy="true">Loading…</p>
		<p v-if="null !== error" role="alert">{{ error }}</p>

		<div v-if="null !== report" class="privacy-admin-compliance-report__grid">
			<section aria-labelledby="report-consents">
				<h3 id="report-consents">Consents</h3>
				<dl>
					<dt>Total</dt><dd>{{ report.consents.total }}</dd>
					<dt>Granted</dt><dd>{{ report.consents.granted }}</dd>
					<dt>Withdrawn</dt><dd>{{ report.consents.withdrawn }}</dd>
					<dt>Expired</dt><dd>{{ report.consents.expired }}</dd>
					<dt>Grant rate</dt><dd>{{ report.consents.grant_rate }}%</dd>
					<dt>Withdrawal rate</dt><dd>{{ report.consents.withdrawal_rate }}%</dd>
				</dl>
				<ul v-if="Object.keys( report.consents.by_category ).length > 0">
					<li v-for="( count, category ) in report.consents.by_category" :key="category">
						{{ category }}: <strong>{{ count }}</strong>
					</li>
				</ul>
			</section>

			<section aria-labelledby="report-requests">
				<h3 id="report-requests">Data subject requests</h3>
				<dl>
					<dt>Total</dt><dd>{{ report.requests.total }}</dd>
					<dt>Completed</dt><dd>{{ report.requests.completed }}</dd>
					<dt>Overdue</dt><dd>{{ report.requests.overdue }}</dd>
					<dt>Average response (s)</dt><dd>{{ report.requests.average_response_seconds }}</dd>
					<dt>p50 / p90 / p99 (s)</dt>
					<dd>
						{{ report.requests.percentiles_seconds.p50 }} /
						{{ report.requests.percentiles_seconds.p90 }} /
						{{ report.requests.percentiles_seconds.p99 }}
					</dd>
					<dt>Deadline compliance</dt><dd>{{ report.requests.deadline_compliance_percent }}%</dd>
				</dl>
				<ul v-if="Object.keys( report.requests.by_type ).length > 0">
					<li v-for="( count, type ) in report.requests.by_type" :key="type">
						{{ type }}: <strong>{{ count }}</strong>
					</li>
				</ul>
			</section>

			<section aria-labelledby="report-breaches">
				<h3 id="report-breaches">Breaches</h3>
				<dl>
					<dt>Total</dt><dd>{{ report.breaches.total }}</dd>
					<dt>Notified on time</dt><dd>{{ report.breaches.authority_notified_on_time }}</dd>
					<dt>Notified late</dt><dd>{{ report.breaches.authority_notified_late }}</dd>
					<dt>Awaiting notification</dt><dd>{{ report.breaches.authority_notification_pending }}</dd>
					<dt>Authority compliance</dt><dd>{{ report.breaches.authority_notification_compliance_percent }}%</dd>
				</dl>
				<ul v-if="Object.keys( report.breaches.by_severity ).length > 0">
					<li v-for="( count, severity ) in report.breaches.by_severity" :key="severity">
						{{ severity }}: <strong>{{ count }}</strong>
					</li>
				</ul>
			</section>
		</div>
	</div>
</template>
