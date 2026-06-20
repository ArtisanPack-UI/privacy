/**
 * Admin\DataRequestManager — React queue for data subject requests.
 *
 * Backed by `GET /api/privacy/admin/data-requests` and
 * `POST /api/privacy/admin/data-requests/{id}/actions`. Renders a
 * filtered, deadline-aware table with a details side panel for
 * verification, approval, rejection, and completion.
 *
 * @since 1.0.0
 */

import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';

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

export interface DataRequestManagerProps {
	className?: string;
	endpoint?: string;
	heading?: ReactNode;
	csrfToken?: string;
	fetchImpl?: typeof fetch;
	perPage?: number;
}

type SortKey = 'newest' | 'deadline' | 'overdue';
type ActionKey = 'approve' | 'reject' | 'complete' | 'verify';

interface Filters {
	type: string;
	status: string;
	sort: SortKey;
}

const DEFAULT_ENDPOINT = '/api/privacy/admin/data-requests';
const STATUS_OPTIONS = [
	{ value: '', label: 'All statuses' },
	{ value: 'pending', label: 'Pending' },
	{ value: 'processing', label: 'Processing' },
	{ value: 'completed', label: 'Completed' },
	{ value: 'rejected', label: 'Rejected' },
];

const INITIAL_FILTERS: Filters = {
	type: '',
	status: '',
	sort: 'deadline',
};

function buildQueryString( filters: Filters, page: number, perPage: number ): string {
	const params = new URLSearchParams();
	if ( '' !== filters.type ) {
		params.set( 'type', filters.type );
	}
	if ( '' !== filters.status ) {
		params.set( 'status', filters.status );
	}
	if ( 'deadline' !== filters.sort ) {
		params.set( 'sort', filters.sort );
	}
	params.set( 'page', String( page ) );
	params.set( 'per_page', String( perPage ) );
	return params.toString();
}

export function DataRequestManager( props: DataRequestManagerProps ): JSX.Element {
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
	const [ payload, setPayload ] = useState<AdminDataRequestPayload | null>( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState<string | null>( null );

	const [ details, setDetails ] = useState<AdminDataRequestDetails | null>( null );
	const [ note, setNote ] = useState( '' );
	const [ acting, setActing ] = useState<ActionKey | null>( null );

	const loadSequence = useRef( 0 );
	const detailsSequence = useRef( 0 );

	const load = useCallback( async (): Promise<void> => {
		const sequence = ++loadSequence.current;
		setLoading( true );
		setError( null );
		try {
			const url = `${ endpoint.replace( /\/+$/, '' ) }?${ buildQueryString( filters, page, perPage ) }`;
			const response = await fetcher( url, {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
			} );
			if ( ! response.ok ) {
				throw new Error( `Failed to load requests (${ response.status })` );
			}
			if ( sequence === loadSequence.current ) {
				setPayload( ( await response.json() ) as AdminDataRequestPayload );
			}
		} catch ( err ) {
			if ( sequence === loadSequence.current ) {
				setError( err instanceof Error ? err.message : String( err ) );
			}
		} finally {
			if ( sequence === loadSequence.current ) {
				setLoading( false );
			}
		}
	}, [ endpoint, fetcher, filters, page, perPage ] );

	useEffect( () => {
		void load();
	}, [ load ] );

	const setFilter = useCallback( <K extends keyof Filters>( key: K, value: Filters[K] ): void => {
		setFilters( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setPage( 1 );
	}, [] );

	const openDetails = useCallback(
		async ( id: number ): Promise<void> => {
			const sequence = ++detailsSequence.current;
			try {
				const response = await fetcher( `${ endpoint.replace( /\/+$/, '' ) }/${ id }`, {
					method: 'GET',
					credentials: 'same-origin',
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				} );
				if ( ! response.ok ) {
					throw new Error( `Failed to load request (${ response.status })` );
				}
				const json = ( await response.json() ) as { data: AdminDataRequestDetails };
				if ( sequence === detailsSequence.current ) {
					setDetails( json.data );
					setNote( '' );
				}
			} catch ( err ) {
				if ( sequence === detailsSequence.current ) {
					setError( err instanceof Error ? err.message : String( err ) );
				}
			}
		},
		[ endpoint, fetcher ],
	);

	const closeDetails = useCallback( (): void => {
		setDetails( null );
		setNote( '' );
	}, [] );

	const performAction = useCallback(
		async ( action: ActionKey ): Promise<void> => {
			if ( null === details ) {
				return;
			}
			setActing( action );
			try {
				const response = await fetcher(
					`${ endpoint.replace( /\/+$/, '' ) }/${ details.id }/actions`,
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							Accept: 'application/json',
							'Content-Type': 'application/json',
							'X-Requested-With': 'XMLHttpRequest',
							...( csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {} ),
						},
						body: JSON.stringify( { action, note } ),
					},
				);
				if ( ! response.ok ) {
					throw new Error( `Action failed (${ response.status })` );
				}
				const json = ( await response.json() ) as { data: AdminDataRequestDetails };
				setDetails( json.data );
				setNote( '' );
				await load();
			} catch ( err ) {
				setError( err instanceof Error ? err.message : String( err ) );
			} finally {
				setActing( null );
			}
		},
		[ csrfToken, details, endpoint, fetcher, load, note ],
	);

	const meta = payload?.meta ?? null;
	const allowedTypes = payload?.allowed_types ?? [];

	return (
		<div className={ className ?? 'privacy-admin-data-request-manager' }>
			<header className="privacy-admin-data-request-manager__header">
				{ heading ?? <h2>Data subject requests</h2> }
				<p>Process pending requests, verify identities manually, and track deadlines.</p>
			</header>

			<section className="privacy-admin-data-request-manager__filters" aria-label="Filters">
				<label>
					<span>Type</span>
					<select value={ filters.type } onChange={ ( e ) => setFilter( 'type', e.target.value ) }>
						<option value="">All types</option>
						{ allowedTypes.map( ( t ) => (
							<option key={ t } value={ t }>
								{ t.charAt( 0 ).toUpperCase() + t.slice( 1 ) }
							</option>
						) ) }
					</select>
				</label>

				<label>
					<span>Status</span>
					<select value={ filters.status } onChange={ ( e ) => setFilter( 'status', e.target.value ) }>
						{ STATUS_OPTIONS.map( ( opt ) => (
							<option key={ opt.value } value={ opt.value }>{ opt.label }</option>
						) ) }
					</select>
				</label>

				<label>
					<span>Sort by</span>
					<select value={ filters.sort } onChange={ ( e ) => setFilter( 'sort', e.target.value as SortKey ) }>
						<option value="deadline">Closest deadline</option>
						<option value="newest">Newest first</option>
						<option value="overdue">Overdue only</option>
					</select>
				</label>
			</section>

			{ loading && <p aria-busy="true">Loading…</p> }

			{ null !== error && (
				<p role="alert" className="privacy-admin-data-request-manager__error">
					{ error }
				</p>
			) }

			<div className="privacy-admin-data-request-manager__layout">
				<table className="privacy-admin-data-request-manager__table">
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
						{ null !== payload && 0 === payload.data.length && (
							<tr>
								<td colSpan={ 6 }>No requests match the selected filters.</td>
							</tr>
						) }
						{ ( payload?.data ?? [] ).map( ( row ) => (
							<tr key={ row.id } className={ row.overdue ? 'is-overdue' : undefined }>
								<td>{ row.type }</td>
								<td>{ row.status }</td>
								<td>
									<div>{ row.requestable_type }</div>
									<div className="privacy-admin-data-request-manager__subject-id">
										#{ row.requestable_id }
									</div>
								</td>
								<td>{ row.created_at ?? '—' }</td>
								<td>
									{ row.due_at ?? '—' }
									{ row.overdue && <div className="privacy-admin-data-request-manager__overdue">Overdue</div> }
								</td>
								<td>
									<button type="button" onClick={ () => void openDetails( row.id ) }>
										View
									</button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>

				{ null !== details && (
					<aside className="privacy-admin-data-request-manager__details" aria-label="Request details">
						<header>
							<h3>Request #{ details.id }</h3>
							<p>{ details.type } · { details.status }</p>
							<button type="button" onClick={ closeDetails }>Close</button>
						</header>

						<dl>
							<dt>Subject</dt>
							<dd>{ details.requestable_type } #{ details.requestable_id }</dd>
							<dt>Filed</dt>
							<dd>{ details.created_at ?? '—' }</dd>
							<dt>Verified</dt>
							<dd>{ details.verified_at ?? 'Not yet' }</dd>
							<dt>Due</dt>
							<dd>{ details.due_at ?? '—' }</dd>
							<dt>Regulation</dt>
							<dd>{ details.regulation ?? '—' }</dd>
						</dl>

						{ details.reason && (
							<section>
								<h4>Reason</h4>
								<p>{ details.reason }</p>
							</section>
						) }

						{ details.admin_notes && (
							<section>
								<h4>Admin notes</h4>
								<p>{ details.admin_notes }</p>
							</section>
						) }

						<section>
							<label>
								<span>Add a note</span>
								<textarea value={ note } onChange={ ( e ) => setNote( e.target.value ) } rows={ 3 } />
							</label>
							<div className="privacy-admin-data-request-manager__action-buttons">
								{ null === details.verified_at && (
									<button type="button" onClick={ () => void performAction( 'verify' ) } disabled={ null !== acting }>
										Verify manually
									</button>
								) }
								{ 'completed' !== details.status && 'rejected' !== details.status && (
									<>
										<button type="button" onClick={ () => void performAction( 'approve' ) } disabled={ null !== acting }>
											Approve
										</button>
										<button type="button" onClick={ () => void performAction( 'complete' ) } disabled={ null !== acting }>
											Complete
										</button>
										<button type="button" onClick={ () => void performAction( 'reject' ) } disabled={ null !== acting }>
											Reject
										</button>
									</>
								) }
							</div>
						</section>

						{ details.logs.length > 0 && (
							<section>
								<h4>History</h4>
								<ul>
									{ details.logs.map( ( log ) => (
										<li key={ log.id }>
											<strong>{ log.action }</strong>
											<span> — { log.created_at ?? '' }</span>
											{ log.metadata && 'note' in log.metadata && (
												<div>{ String( log.metadata.note ) }</div>
											) }
										</li>
									) ) }
								</ul>
							</section>
						) }
					</aside>
				) }
			</div>

			{ null !== meta && meta.last_page > 1 && (
				<nav className="privacy-admin-data-request-manager__pager" aria-label="Pagination">
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
