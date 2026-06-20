/**
 * Admin\ComplianceReport — React compliance report panel.
 *
 * Backed by `GET /api/privacy/admin/compliance-report`. Renders consent,
 * request, and breach metrics for the selected date range with optional
 * regulation filtering and CSV export.
 *
 * @since 1.0.0
 */

import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';

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
	meta: {
		from: string;
		to: string;
		regulation: string | null;
		generated_at: string;
	};
	consents: ComplianceConsentStats;
	requests: ComplianceRequestStats;
	breaches: ComplianceBreachStats;
}

export interface ComplianceReportResponse {
	data: ComplianceReportPayload;
	regulations: string[];
}

export interface ComplianceReportProps {
	className?: string;
	endpoint?: string;
	heading?: ReactNode;
	fetchImpl?: typeof fetch;
}

const DEFAULT_ENDPOINT = '/api/privacy/admin/compliance-report';

function defaultFrom(): string {
	const date = new Date();
	date.setDate( date.getDate() - 30 );
	return date.toISOString().slice( 0, 10 );
}

function defaultTo(): string {
	return new Date().toISOString().slice( 0, 10 );
}

function downloadCsv( payload: ComplianceReportPayload, from: string, to: string ): void {
	if ( typeof document === 'undefined' ) {
		return;
	}

	const rows: string[] = [ 'section,metric,value' ];
	const sections: Array<keyof Pick<ComplianceReportPayload, 'consents' | 'requests' | 'breaches'>> = [
		'consents',
		'requests',
		'breaches',
	];

	const escape = ( value: string ): string =>
		`"${ value.replace( /"/g, '""' ) }"`;

	for ( const section of sections ) {
		const data = payload[ section ] as Record<string, unknown>;
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
	link.download = `privacy-compliance-${ from }-to-${ to }.csv`;
	document.body.appendChild( link );
	link.click();
	document.body.removeChild( link );
	URL.revokeObjectURL( url );
}

export function ComplianceReport( props: ComplianceReportProps ): JSX.Element {
	const { className, endpoint = DEFAULT_ENDPOINT, heading, fetchImpl } = props;

	const fetcher = useMemo<typeof fetch>( () => {
		if ( fetchImpl ) {
			return fetchImpl;
		}
		if ( typeof globalThis !== 'undefined' && 'function' === typeof globalThis.fetch ) {
			return globalThis.fetch.bind( globalThis ) as typeof fetch;
		}
		return ( () => Promise.reject( new Error( 'fetch is not available' ) ) ) as typeof fetch;
	}, [ fetchImpl ] );

	const [ from, setFrom ] = useState<string>( () => defaultFrom() );
	const [ to, setTo ] = useState<string>( () => defaultTo() );
	const [ regulation, setRegulation ] = useState( '' );
	const [ payload, setPayload ] = useState<ComplianceReportResponse | null>( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState<string | null>( null );

	const sequence = useRef( 0 );

	const load = useCallback( async (): Promise<void> => {
		const ticket = ++sequence.current;
		setLoading( true );
		setError( null );
		try {
			const params = new URLSearchParams();
			params.set( 'from', from );
			params.set( 'to', to );
			if ( '' !== regulation ) {
				params.set( 'regulation', regulation );
			}
			const response = await fetcher( `${ endpoint.replace( /\/+$/, '' ) }?${ params.toString() }`, {
				method: 'GET',
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			} );
			if ( ! response.ok ) {
				throw new Error( `Failed to load report (${ response.status })` );
			}
			if ( ticket === sequence.current ) {
				setPayload( ( await response.json() ) as ComplianceReportResponse );
			}
		} catch ( err ) {
			if ( ticket === sequence.current ) {
				setError( err instanceof Error ? err.message : String( err ) );
			}
		} finally {
			if ( ticket === sequence.current ) {
				setLoading( false );
			}
		}
	}, [ endpoint, fetcher, from, regulation, to ] );

	useEffect( () => {
		void load();
	}, [ load ] );

	const report = payload?.data ?? null;
	const regulations = payload?.regulations ?? [];

	return (
		<div className={ className ?? 'privacy-admin-compliance-report' }>
			<header className="privacy-admin-compliance-report__header">
				{ heading ?? <h2>Compliance report</h2> }
				<p>Consent, request, and breach metrics for the selected reporting window.</p>
			</header>

			<section className="privacy-admin-compliance-report__filters" aria-label="Filters">
				<label>
					<span>From</span>
					<input type="date" value={ from } onChange={ ( e ) => setFrom( e.target.value ) } />
				</label>
				<label>
					<span>To</span>
					<input type="date" value={ to } onChange={ ( e ) => setTo( e.target.value ) } />
				</label>
				<label>
					<span>Regulation</span>
					<select value={ regulation } onChange={ ( e ) => setRegulation( e.target.value ) }>
						<option value="">All regulations</option>
						{ regulations.map( ( key ) => (
							<option key={ key } value={ key }>{ key.toUpperCase() }</option>
						) ) }
					</select>
				</label>
				<button
					type="button"
					onClick={ () => report && downloadCsv( report, from, to ) }
					disabled={ null === report }
				>
					Export CSV
				</button>
			</section>

			{ loading && <p aria-busy="true">Loading…</p> }
			{ null !== error && <p role="alert">{ error }</p> }

			{ null !== report && (
				<div className="privacy-admin-compliance-report__grid">
					<section aria-labelledby="report-consents">
						<h3 id="report-consents">Consents</h3>
						<dl>
							<dt>Total</dt><dd>{ report.consents.total }</dd>
							<dt>Granted</dt><dd>{ report.consents.granted }</dd>
							<dt>Withdrawn</dt><dd>{ report.consents.withdrawn }</dd>
							<dt>Expired</dt><dd>{ report.consents.expired }</dd>
							<dt>Grant rate</dt><dd>{ report.consents.grant_rate }%</dd>
							<dt>Withdrawal rate</dt><dd>{ report.consents.withdrawal_rate }%</dd>
						</dl>
						{ Object.keys( report.consents.by_category ).length > 0 && (
							<ul>
								{ Object.entries( report.consents.by_category ).map( ( [ category, count ] ) => (
									<li key={ category }>{ category }: <strong>{ count }</strong></li>
								) ) }
							</ul>
						) }
					</section>

					<section aria-labelledby="report-requests">
						<h3 id="report-requests">Data subject requests</h3>
						<dl>
							<dt>Total</dt><dd>{ report.requests.total }</dd>
							<dt>Completed</dt><dd>{ report.requests.completed }</dd>
							<dt>Overdue</dt><dd>{ report.requests.overdue }</dd>
							<dt>Average response (s)</dt><dd>{ report.requests.average_response_seconds }</dd>
							<dt>p50 / p90 / p99 (s)</dt>
							<dd>
								{ report.requests.percentiles_seconds.p50 } /
								{ report.requests.percentiles_seconds.p90 } /
								{ report.requests.percentiles_seconds.p99 }
							</dd>
							<dt>Deadline compliance</dt><dd>{ report.requests.deadline_compliance_percent }%</dd>
						</dl>
						{ Object.keys( report.requests.by_type ).length > 0 && (
							<ul>
								{ Object.entries( report.requests.by_type ).map( ( [ type, count ] ) => (
									<li key={ type }>{ type }: <strong>{ count }</strong></li>
								) ) }
							</ul>
						) }
					</section>

					<section aria-labelledby="report-breaches">
						<h3 id="report-breaches">Breaches</h3>
						<dl>
							<dt>Total</dt><dd>{ report.breaches.total }</dd>
							<dt>Notified on time</dt><dd>{ report.breaches.authority_notified_on_time }</dd>
							<dt>Notified late</dt><dd>{ report.breaches.authority_notified_late }</dd>
							<dt>Awaiting notification</dt><dd>{ report.breaches.authority_notification_pending }</dd>
							<dt>Authority compliance</dt><dd>{ report.breaches.authority_notification_compliance_percent }%</dd>
						</dl>
						{ Object.keys( report.breaches.by_severity ).length > 0 && (
							<ul>
								{ Object.entries( report.breaches.by_severity ).map( ( [ severity, count ] ) => (
									<li key={ severity }>{ severity }: <strong>{ count }</strong></li>
								) ) }
							</ul>
						) }
					</section>
				</div>
			) }
		</div>
	);
}
