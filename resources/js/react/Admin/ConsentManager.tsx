/**
 * Admin\ConsentManager — React table for browsing and filtering consents.
 *
 * Backed by `GET /api/privacy/admin/consents`. Renders a filter bar,
 * paginated table, and JSON / CSV download buttons. Treats every
 * non-success response as an error and renders a sensible empty state.
 *
 * @since 1.0.0
 */

import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';

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

export interface ConsentManagerProps {
	className?: string;
	endpoint?: string;
	heading?: ReactNode;
	csrfToken?: string;
	fetchImpl?: typeof fetch;
	perPage?: number;
}

const DEFAULT_ENDPOINT = '/api/privacy/admin/consents';

type StatusFilter = 'all' | 'active' | 'withdrawn' | 'expired';

interface Filters {
	q: string;
	category: string;
	status: StatusFilter;
	from: string;
	to: string;
}

const INITIAL_FILTERS: Filters = {
	q: '',
	category: '',
	status: 'all',
	from: '',
	to: '',
};

function buildQueryString( filters: Filters, page: number, perPage: number ): string {
	const params = new URLSearchParams();
	if ( '' !== filters.q ) {
		params.set( 'q', filters.q );
	}
	if ( '' !== filters.category ) {
		params.set( 'category', filters.category );
	}
	if ( 'all' !== filters.status ) {
		params.set( 'status', filters.status );
	}
	if ( '' !== filters.from ) {
		params.set( 'from', filters.from );
	}
	if ( '' !== filters.to ) {
		params.set( 'to', filters.to );
	}
	params.set( 'page', String( page ) );
	params.set( 'per_page', String( perPage ) );
	return params.toString();
}

function rowsToCsv( rows: AdminConsentRow[] ): string {
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
	const escape = ( value: unknown ): string => {
		if ( null === value || undefined === value ) {
			return '';
		}
		const str = String( value );
		if ( /[",\n]/.test( str ) ) {
			return `"${ str.replace( /"/g, '""' ) }"`;
		}
		return str;
	};
	const lines = [
		header.join( ',' ),
		...rows.map( ( row ) =>
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
				.map( escape )
				.join( ',' ),
		),
	];
	return lines.join( '\n' );
}

function triggerDownload( blob: Blob, filename: string ): void {
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	document.body.appendChild( link );
	link.click();
	link.remove();
	URL.revokeObjectURL( url );
}

export function ConsentManager( props: ConsentManagerProps ): JSX.Element {
	const {
		className,
		endpoint = DEFAULT_ENDPOINT,
		heading,
		csrfToken,
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

	const [ filters, setFilters ] = useState<Filters>( INITIAL_FILTERS );
	const [ page, setPage ] = useState( 1 );
	const [ payload, setPayload ] = useState<AdminConsentPayload | null>( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState<string | null>( null );

	const load = useCallback( async (): Promise<void> => {
		setLoading( true );
		setError( null );
		try {
			const qs = buildQueryString( filters, page, perPage );
			const url = `${ endpoint.replace( /\/+$/, '' ) }?${ qs }`;
			const response = await fetcher( url, {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
					...( csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {} ),
				},
			} );
			if ( ! response.ok ) {
				throw new Error( `Failed to load consents (${ response.status })` );
			}
			const json = ( await response.json() ) as AdminConsentPayload;
			setPayload( json );
		} catch ( err ) {
			setError( err instanceof Error ? err.message : String( err ) );
		} finally {
			setLoading( false );
		}
	}, [ csrfToken, endpoint, fetcher, filters, page, perPage ] );

	useEffect( () => {
		void load();
	}, [ load ] );

	const setFilter = useCallback( <K extends keyof Filters>( key: K, value: Filters[K] ): void => {
		setFilters( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setPage( 1 );
	}, [] );

	const handleReset = useCallback( (): void => {
		setFilters( INITIAL_FILTERS );
		setPage( 1 );
	}, [] );

	const handleCsv = useCallback( (): void => {
		if ( null === payload ) {
			return;
		}
		const csv = rowsToCsv( payload.data );
		triggerDownload( new Blob( [ csv ], { type: 'text/csv' } ), 'privacy-consents.csv' );
	}, [ payload ] );

	const handleJson = useCallback( (): void => {
		if ( null === payload ) {
			return;
		}
		const json = JSON.stringify( payload.data, null, 2 );
		triggerDownload( new Blob( [ json ], { type: 'application/json' } ), 'privacy-consents.json' );
	}, [ payload ] );

	const meta = payload?.meta ?? null;

	return (
		<div className={ className ?? 'privacy-admin-consent-manager' }>
			<header className="privacy-admin-consent-manager__header">
				{ heading ?? <h2>Consent records</h2> }
				<p>Browse, filter, and export consent activity across all users.</p>
			</header>

			<section className="privacy-admin-consent-manager__filters" aria-label="Filters">
				<label>
					<span>Search</span>
					<input
						type="search"
						value={ filters.q }
						onChange={ ( event ) => setFilter( 'q', event.target.value ) }
						placeholder="User, category, IP…"
					/>
				</label>

				<label>
					<span>Category</span>
					<select
						value={ filters.category }
						onChange={ ( event ) => setFilter( 'category', event.target.value ) }
					>
						<option value="">All categories</option>
						{ ( payload?.categories ?? [] ).map( ( cat ) => (
							<option key={ cat } value={ cat }>{ cat }</option>
						) ) }
					</select>
				</label>

				<label>
					<span>Status</span>
					<select
						value={ filters.status }
						onChange={ ( event ) => setFilter( 'status', event.target.value as StatusFilter ) }
					>
						<option value="all">All</option>
						<option value="active">Active</option>
						<option value="withdrawn">Withdrawn</option>
						<option value="expired">Expired</option>
					</select>
				</label>

				<label>
					<span>From</span>
					<input type="date" value={ filters.from } onChange={ ( event ) => setFilter( 'from', event.target.value ) } />
				</label>

				<label>
					<span>To</span>
					<input type="date" value={ filters.to } onChange={ ( event ) => setFilter( 'to', event.target.value ) } />
				</label>

				<div className="privacy-admin-consent-manager__actions">
					<button type="button" onClick={ handleReset }>Reset</button>
					<button type="button" onClick={ handleCsv } disabled={ null === payload }>Export CSV</button>
					<button type="button" onClick={ handleJson } disabled={ null === payload }>Export JSON</button>
				</div>
			</section>

			{ loading && <p aria-busy="true">Loading…</p> }

			{ null !== error && (
				<p role="alert" className="privacy-admin-consent-manager__error">
					{ error }
				</p>
			) }

			{ null !== payload && ! loading && (
				<>
					<table className="privacy-admin-consent-manager__table">
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
							{ 0 === payload.data.length && (
								<tr>
									<td colSpan={ 7 }>No consent records match the selected filters.</td>
								</tr>
							) }
							{ payload.data.map( ( row ) => (
								<tr key={ row.id }>
									<td>
										<div>{ row.consentable_type }</div>
										<div className="privacy-admin-consent-manager__subject-id">
											#{ row.consentable_id }
										</div>
									</td>
									<td>{ row.category }</td>
									<td>{ row.granted ? 'Yes' : 'No' }</td>
									<td>{ row.regulation ?? '—' }</td>
									<td>{ row.created_at ?? '—' }</td>
									<td>{ row.expires_at ?? '—' }</td>
									<td>{ row.withdrawn_at ?? '—' }</td>
								</tr>
							) ) }
						</tbody>
					</table>

					{ null !== meta && meta.last_page > 1 && (
						<nav className="privacy-admin-consent-manager__pager" aria-label="Pagination">
							<button
								type="button"
								onClick={ () => setPage( ( p ) => Math.max( 1, p - 1 ) ) }
								disabled={ 1 === page }
							>
								Previous
							</button>
							<span>
								Page { meta.current_page } of { meta.last_page }
							</span>
							<button
								type="button"
								onClick={ () => setPage( ( p ) => Math.min( meta.last_page, p + 1 ) ) }
								disabled={ page >= meta.last_page }
							>
								Next
							</button>
						</nav>
					) }
				</>
			) }
		</div>
	);
}
