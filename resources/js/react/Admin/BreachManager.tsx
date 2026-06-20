/**
 * Admin\BreachManager — React breach list panel.
 *
 * Backed by `GET /api/privacy/admin/breaches`. Renders a paginated,
 * deadline-aware list of breach incidents with severity badges and links
 * into the detail panel and the report form.
 *
 * @since 1.0.0
 */

import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';

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
	meta: {
		current_page: number;
		per_page: number;
		total: number;
		last_page: number;
	};
	severities: string[];
	statuses: string[];
}

export interface BreachManagerProps {
	className?: string;
	endpoint?: string;
	detailUrlBuilder?: ( id: number ) => string;
	reportUrl?: string;
	heading?: ReactNode;
	fetchImpl?: typeof fetch;
	perPage?: number;
}

const DEFAULT_ENDPOINT = '/api/privacy/admin/breaches';

export function BreachManager( props: BreachManagerProps ): JSX.Element {
	const {
		className,
		endpoint = DEFAULT_ENDPOINT,
		detailUrlBuilder,
		reportUrl,
		heading,
		fetchImpl,
		perPage = 25,
	} = props;

	const fetcher = useMemo<typeof fetch>( () => {
		if ( fetchImpl ) {
			return fetchImpl;
		}
		if ( typeof globalThis !== 'undefined' && 'function' === typeof globalThis.fetch ) {
			return globalThis.fetch.bind( globalThis ) as typeof fetch;
		}
		return ( () => Promise.reject( new Error( 'fetch is not available' ) ) ) as typeof fetch;
	}, [ fetchImpl ] );

	const [ severity, setSeverity ] = useState( '' );
	const [ status, setStatus ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ payload, setPayload ] = useState<AdminBreachPayload | null>( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState<string | null>( null );

	const sequence = useRef( 0 );

	const load = useCallback( async (): Promise<void> => {
		const ticket = ++sequence.current;
		setLoading( true );
		setError( null );
		try {
			const params = new URLSearchParams();
			if ( '' !== severity ) {
				params.set( 'severity', severity );
			}
			if ( '' !== status ) {
				params.set( 'status', status );
			}
			params.set( 'page', String( page ) );
			params.set( 'per_page', String( perPage ) );

			const response = await fetcher( `${ endpoint.replace( /\/+$/, '' ) }?${ params.toString() }`, {
				method: 'GET',
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			} );
			if ( ! response.ok ) {
				throw new Error( `Failed to load breaches (${ response.status })` );
			}
			if ( ticket === sequence.current ) {
				setPayload( ( await response.json() ) as AdminBreachPayload );
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
	}, [ endpoint, fetcher, page, perPage, severity, status ] );

	useEffect( () => {
		void load();
	}, [ load ] );

	const meta = payload?.meta ?? null;
	const severities = payload?.severities ?? [];
	const statuses = payload?.statuses ?? [];

	return (
		<div className={ className ?? 'privacy-admin-breach-manager' }>
			<header className="privacy-admin-breach-manager__header">
				{ heading ?? <h2>Breach incidents</h2> }
				<p>Track open and resolved data breach incidents and notification status.</p>
				{ reportUrl && (
					<a className="privacy-admin-breach-manager__report-link" href={ reportUrl }>
						Report a breach
					</a>
				) }
			</header>

			<section className="privacy-admin-breach-manager__filters" aria-label="Filters">
				<label>
					<span>Severity</span>
					<select
						value={ severity }
						onChange={ ( e ) => {
							setSeverity( e.target.value );
							setPage( 1 );
						} }
					>
						<option value="">All severities</option>
						{ severities.map( ( s ) => (
							<option key={ s } value={ s }>{ s.charAt( 0 ).toUpperCase() + s.slice( 1 ) }</option>
						) ) }
					</select>
				</label>

				<label>
					<span>Status</span>
					<select
						value={ status }
						onChange={ ( e ) => {
							setStatus( e.target.value );
							setPage( 1 );
						} }
					>
						<option value="">All statuses</option>
						{ statuses.map( ( s ) => (
							<option key={ s } value={ s }>{ s.charAt( 0 ).toUpperCase() + s.slice( 1 ) }</option>
						) ) }
					</select>
				</label>
			</section>

			{ loading && <p aria-busy="true">Loading…</p> }
			{ null !== error && <p role="alert">{ error }</p> }

			<table className="privacy-admin-breach-manager__table">
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
					{ null !== payload && 0 === payload.data.length && (
						<tr>
							<td colSpan={ 7 }>No breaches match the selected filters.</td>
						</tr>
					) }
					{ ( payload?.data ?? [] ).map( ( row ) => (
						<tr key={ row.id } className={ row.authority_overdue ? 'is-overdue' : undefined }>
							<td>
								{ detailUrlBuilder
									? <a href={ detailUrlBuilder( row.id ) }>{ row.reference_number }</a>
									: row.reference_number }
							</td>
							<td>{ row.severity }</td>
							<td>{ row.status }</td>
							<td>{ row.discovered_at ?? '—' }</td>
							<td>
								{ row.authority_deadline }
								{ row.authority_overdue && <div className="is-overdue">Overdue</div> }
							</td>
							<td>{ row.records_affected ?? '—' }</td>
							<td>
								{ detailUrlBuilder && (
									<a href={ detailUrlBuilder( row.id ) }>View</a>
								) }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>

			{ null !== meta && meta.last_page > 1 && (
				<nav className="privacy-admin-breach-manager__pager" aria-label="Pagination">
					<button type="button" onClick={ () => setPage( ( p ) => Math.max( 1, p - 1 ) ) } disabled={ 1 === page }>
						Previous
					</button>
					<span>Page { meta.current_page } of { meta.last_page }</span>
					<button
						type="button"
						onClick={ () => setPage( ( p ) => Math.min( meta.last_page, p + 1 ) ) }
						disabled={ page >= meta.last_page }
					>
						Next
					</button>
				</nav>
			) }
		</div>
	);
}
