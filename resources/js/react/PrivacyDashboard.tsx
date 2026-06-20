/**
 * PrivacyDashboard — React privacy hub for authenticated users.
 *
 * Composes ConsentPreferences and DataRequestForm with a history table
 * fed by `GET /api/privacy/data-requests`. Refreshes history whenever a
 * new request is submitted via the embedded form.
 *
 * @since 1.0.0
 */

import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { ConsentPreferences } from './ConsentPreferences';
import { DataRequestForm, type DataRequestResult } from './DataRequestForm';
import { useConsent, type UseConsentOptions } from './useConsent';

export interface PrivacyDashboardRequest {
	id: number;
	type: string;
	status: string;
	regulation: string | null;
	created_at: string | null;
	due_at: string | null;
	completed_at: string | null;
	download_url: string | null;
}

export interface PrivacyDashboardHistoryPayload {
	regulation: string | null;
	requests: PrivacyDashboardRequest[];
}

export interface PrivacyDashboardProps extends UseConsentOptions {
	className?: string;
	policyUrl?: string;
	historyEndpoint?: string;
	requestsEndpoint?: string;
	historyLimit?: number;
	showConsent?: boolean;
	showRequestForm?: boolean;
	showHistory?: boolean;
	heading?: ReactNode;
	csrfToken?: string;
	fetchImpl?: typeof fetch;
}

const DEFAULT_HISTORY_ENDPOINT = '/api/privacy/data-requests';
const DEFAULT_REQUESTS_ENDPOINT = '/api/privacy/data-requests';

function formatDate( iso: string | null ): string {
	if ( null === iso ) {
		return '—';
	}
	const parsed = new Date( iso );
	if ( Number.isNaN( parsed.getTime() ) ) {
		return iso;
	}
	return parsed.toLocaleDateString();
}

function capitalise( value: string ): string {
	if ( '' === value ) {
		return value;
	}
	return value.charAt( 0 ).toUpperCase() + value.slice( 1 );
}

export function PrivacyDashboard( props: PrivacyDashboardProps ): JSX.Element {
	const {
		className,
		policyUrl,
		historyEndpoint = DEFAULT_HISTORY_ENDPOINT,
		requestsEndpoint = DEFAULT_REQUESTS_ENDPOINT,
		historyLimit = 25,
		showConsent = true,
		showRequestForm = true,
		showHistory = true,
		heading,
		csrfToken,
		fetchImpl,
		...consentOptions
	} = props;

	const consent = useConsent( consentOptions );
	const [ history, setHistory ] = useState<PrivacyDashboardHistoryPayload | null>( null );
	const [ loadingHistory, setLoadingHistory ] = useState( false );
	const [ historyError, setHistoryError ] = useState<string | null>( null );

	const fetcher = useMemo<typeof fetch>( () => {
		if ( fetchImpl ) {
			return fetchImpl;
		}
		if ( typeof globalThis !== 'undefined' && 'function' === typeof globalThis.fetch ) {
			return globalThis.fetch.bind( globalThis ) as typeof fetch;
		}
		return ( () => Promise.reject( new Error( 'fetch is not available' ) ) ) as typeof fetch;
	}, [ fetchImpl ] );

	const loadHistory = useCallback( async (): Promise<void> => {
		if ( ! showHistory ) {
			return;
		}
		setLoadingHistory( true );
		setHistoryError( null );
		try {
			const url = `${ historyEndpoint.replace( /\/+$/, '' ) }?limit=${ historyLimit }`;
			const response = await fetcher( url, {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
			} );
			if ( ! response.ok ) {
				throw new Error( `Failed to load history (${ response.status })` );
			}
			const payload = ( await response.json() ) as PrivacyDashboardHistoryPayload;
			setHistory( payload );
		} catch ( err ) {
			setHistoryError( err instanceof Error ? err.message : String( err ) );
		} finally {
			setLoadingHistory( false );
		}
	}, [ fetcher, historyEndpoint, historyLimit, showHistory ] );

	useEffect( () => {
		void loadHistory();
	}, [ loadHistory ] );

	const handleSubmitted = useCallback(
		( _result: DataRequestResult ): void => {
			void loadHistory();
		},
		[ loadHistory ],
	);

	const regulation = history?.regulation ?? consent.state?.regulation ?? null;

	return (
		<div className={ className ?? 'privacy-dashboard' }>
			<header className="privacy-dashboard__header">
				{ heading ?? <h2>Your privacy dashboard</h2> }
				<p>Review your consent settings, file data requests, and download completed exports.</p>
				{ null !== regulation && (
					<p className="privacy-dashboard__regulation" aria-live="polite">
						Regulation: <strong>{ regulation.toUpperCase() }</strong>
					</p>
				) }
				{ policyUrl && (
					<p>
						<a href={ policyUrl } className="privacy-dashboard__policy-link">
							Read our privacy policy
						</a>
					</p>
				) }
			</header>

			{ showConsent && (
				<section aria-labelledby="privacy-dashboard-consent-heading" className="privacy-dashboard__section">
					<h3 id="privacy-dashboard-consent-heading">Consent preferences</h3>
					<ConsentPreferences { ...consentOptions } />
				</section>
			) }

			{ showRequestForm && (
				<section aria-labelledby="privacy-dashboard-request-heading" className="privacy-dashboard__section">
					<h3 id="privacy-dashboard-request-heading">Submit a privacy request</h3>
					<DataRequestForm
						endpoint={ requestsEndpoint }
						csrfToken={ csrfToken }
						fetchImpl={ fetcher }
						onSubmitted={ handleSubmitted }
					/>
				</section>
			) }

			{ showHistory && (
				<section
					aria-labelledby="privacy-dashboard-history-heading"
					className="privacy-dashboard__section"
				>
					<h3 id="privacy-dashboard-history-heading">Request history</h3>
					{ loadingHistory && <p aria-busy="true">Loading…</p> }
					{ null !== historyError && (
						<p role="alert" className="privacy-dashboard__error">
							{ historyError }
						</p>
					) }
					{ ! loadingHistory && null !== history && 0 === history.requests.length && (
						<p>You have not filed any privacy requests yet.</p>
					) }
					{ null !== history && history.requests.length > 0 && (
						<table className="privacy-dashboard__table">
							<thead>
								<tr>
									<th scope="col">Type</th>
									<th scope="col">Status</th>
									<th scope="col">Filed</th>
									<th scope="col">Due</th>
									<th scope="col">Actions</th>
								</tr>
							</thead>
							<tbody>
								{ history.requests.map( ( request ) => (
									<tr key={ request.id }>
										<td>{ capitalise( request.type ) }</td>
										<td>{ capitalise( request.status ) }</td>
										<td>{ formatDate( request.created_at ) }</td>
										<td>{ formatDate( request.due_at ) }</td>
										<td>
											{ request.download_url ? (
												<a href={ request.download_url } rel="noopener">
													Download export
												</a>
											) : (
												'—'
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
				</section>
			) }
		</div>
	);
}
