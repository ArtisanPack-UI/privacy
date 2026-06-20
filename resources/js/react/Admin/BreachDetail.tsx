/**
 * Admin\BreachDetail — React breach detail panel.
 *
 * Backed by `GET /api/privacy/admin/breaches/{id}` and
 * `POST /api/privacy/admin/breaches/{id}/actions`. Renders the incident
 * record, the notification timeline, and exposes notification dispatch,
 * remediation logging, and status-workflow actions.
 *
 * @since 1.0.0
 */

import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';

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

export interface BreachDetailProps {
	breachId: number;
	className?: string;
	endpoint?: string;
	csrfToken?: string;
	fetchImpl?: typeof fetch;
	heading?: ReactNode;
}

const DEFAULT_ENDPOINT = '/api/privacy/admin/breaches';

type ActionKey = 'notify_authority' | 'notify_users' | 'set_status' | 'add_remediation';

export function BreachDetail( props: BreachDetailProps ): JSX.Element {
	const {
		breachId,
		className,
		endpoint = DEFAULT_ENDPOINT,
		csrfToken,
		fetchImpl,
		heading,
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

	const [ breach, setBreach ] = useState<AdminBreachDetailPayload | null>( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState<string | null>( null );
	const [ acting, setActing ] = useState<ActionKey | null>( null );
	const [ remediationNote, setRemediationNote ] = useState( '' );

	const sequence = useRef( 0 );
	const baseUrl = useMemo( () => `${ endpoint.replace( /\/+$/, '' ) }/${ breachId }`, [ breachId, endpoint ] );

	const load = useCallback( async (): Promise<void> => {
		const ticket = ++sequence.current;
		setLoading( true );
		setError( null );
		try {
			const response = await fetcher( baseUrl, {
				method: 'GET',
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			} );
			if ( ! response.ok ) {
				throw new Error( `Failed to load breach (${ response.status })` );
			}
			const json = ( await response.json() ) as { data: AdminBreachDetailPayload };
			if ( ticket === sequence.current ) {
				setBreach( json.data );
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
	}, [ baseUrl, fetcher ] );

	useEffect( () => {
		void load();
	}, [ load ] );

	const dispatchAction = useCallback(
		async ( action: ActionKey, body: Record<string, unknown> = {} ): Promise<void> => {
			setActing( action );
			try {
				const response = await fetcher( `${ baseUrl }/actions`, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						Accept: 'application/json',
						'Content-Type': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						...( csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {} ),
					},
					body: JSON.stringify( { action, ...body } ),
				} );
				if ( ! response.ok ) {
					throw new Error( `Action failed (${ response.status })` );
				}
				const json = ( await response.json() ) as { data: AdminBreachDetailPayload };
				setBreach( json.data );
				if ( 'add_remediation' === action ) {
					setRemediationNote( '' );
				}
			} catch ( err ) {
				setError( err instanceof Error ? err.message : String( err ) );
			} finally {
				setActing( null );
			}
		},
		[ baseUrl, csrfToken, fetcher ],
	);

	return (
		<div className={ className ?? 'privacy-admin-breach-detail' }>
			{ loading && <p aria-busy="true">Loading…</p> }
			{ null !== error && <p role="alert">{ error }</p> }

			{ null !== breach && (
				<>
					<header>
						{ heading ?? <h2>Breach { breach.reference_number }</h2> }
						<p>{ breach.severity } · { breach.status }</p>
					</header>

					<section aria-labelledby="breach-timeline">
						<h3 id="breach-timeline">Timeline</h3>
						<dl>
							<dt>Discovered</dt><dd>{ breach.discovered_at ?? '—' }</dd>
							<dt>Occurred</dt><dd>{ breach.occurred_at ?? '—' }</dd>
							<dt>Authority deadline</dt>
							<dd>
								{ breach.authority_deadline }
								{ breach.authority_overdue && <span> — Overdue</span> }
							</dd>
							<dt>Authority notified</dt><dd>{ breach.authority_notified_at ?? 'Not yet' }</dd>
							<dt>Users notified</dt><dd>{ breach.users_notified_at ?? 'Not yet' }</dd>
						</dl>
					</section>

					<section aria-labelledby="breach-data">
						<h3 id="breach-data">Affected data</h3>
						<p><strong>Categories:</strong> { breach.data_types_affected.join( ', ' ) || '—' }</p>
						<p><strong>Records:</strong> { breach.records_affected ?? 'Unknown' }</p>
						<p><strong>Description:</strong> { breach.description }</p>
						{ breach.cause && <p><strong>Cause:</strong> { breach.cause }</p> }
					</section>

					<section aria-labelledby="breach-remediation">
						<h3 id="breach-remediation">Remediation</h3>
						{ breach.remediation
							? <p>{ breach.remediation }</p>
							: <p>No remediation notes recorded yet.</p> }
						<label>
							<span>Add a remediation note</span>
							<textarea
								value={ remediationNote }
								onChange={ ( e ) => setRemediationNote( e.target.value ) }
								rows={ 3 }
							/>
						</label>
						<button
							type="button"
							onClick={ () => void dispatchAction( 'add_remediation', { note: remediationNote } ) }
							disabled={ null !== acting || '' === remediationNote.trim() }
						>
							Append note
						</button>
					</section>

					<section aria-labelledby="breach-actions">
						<h3 id="breach-actions">Notifications and status</h3>
						<div>
							<button
								type="button"
								onClick={ () => void dispatchAction( 'notify_authority' ) }
								disabled={ null !== acting }
							>
								Send authority notification
							</button>
							<button
								type="button"
								onClick={ () => void dispatchAction( 'notify_users' ) }
								disabled={ null !== acting }
							>
								Notify affected users
							</button>
						</div>
						<div>
							{ ( [ 'investigating', 'contained', 'resolved' ] as const ).map( ( s ) => (
								<button
									key={ s }
									type="button"
									onClick={ () => void dispatchAction( 'set_status', { status: s } ) }
									disabled={ null !== acting }
								>
									Mark { s }
								</button>
							) ) }
						</div>
					</section>

					{ breach.notifications_sent.length > 0 && (
						<section aria-labelledby="breach-notifications">
							<h3 id="breach-notifications">Notification history</h3>
							<ul>
								{ breach.notifications_sent.map( ( entry, i ) => (
									<li key={ i }>
										<strong>{ String( entry.channel ?? '—' ) }</strong>
										{ entry.recipient && <span> — { String( entry.recipient ) }</span> }
										{ undefined !== entry.recipient_count && (
											<span> — { String( entry.recipient_count ) } recipient(s)</span>
										) }
										<span> · { String( entry.notified_at ?? '' ) }</span>
									</li>
								) ) }
							</ul>
						</section>
					) }
				</>
			) }
		</div>
	);
}
