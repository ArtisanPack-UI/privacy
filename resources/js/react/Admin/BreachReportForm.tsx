/**
 * Admin\BreachReportForm — React breach report form.
 *
 * Posts to `POST /api/privacy/admin/breaches`. Captures the minimum
 * fields required to file a new breach and surfaces validation errors
 * returned by the API.
 *
 * @since 1.0.0
 */

import { useCallback, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import type { AdminBreachRow } from './BreachManager';

export interface BreachReportFormProps {
	className?: string;
	endpoint?: string;
	heading?: ReactNode;
	csrfToken?: string;
	fetchImpl?: typeof fetch;
	onSubmitted?: ( breach: AdminBreachRow ) => void;
}

const DEFAULT_ENDPOINT = '/api/privacy/admin/breaches';

const SEVERITIES = [
	{ value: 'low', label: 'Low' },
	{ value: 'medium', label: 'Medium' },
	{ value: 'high', label: 'High' },
	{ value: 'critical', label: 'Critical' },
];

export function BreachReportForm( props: BreachReportFormProps ): JSX.Element {
	const {
		className,
		endpoint = DEFAULT_ENDPOINT,
		heading,
		csrfToken,
		fetchImpl,
		onSubmitted,
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

	const [ description, setDescription ] = useState( '' );
	const [ severity, setSeverity ] = useState( 'medium' );
	const [ dataTypes, setDataTypes ] = useState( '' );
	const [ recordsAffected, setRecordsAffected ] = useState<string>( '' );
	const [ affectedUsers, setAffectedUsers ] = useState( '' );
	const [ cause, setCause ] = useState( '' );
	const [ remediation, setRemediation ] = useState( '' );
	const [ occurredAt, setOccurredAt ] = useState( '' );

	const [ submitting, setSubmitting ] = useState( false );
	const [ error, setError ] = useState<string | null>( null );
	const [ success, setSuccess ] = useState<AdminBreachRow | null>( null );

	const submit = useCallback(
		async ( event: FormEvent<HTMLFormElement> ): Promise<void> => {
			event.preventDefault();
			setSubmitting( true );
			setError( null );

			const dataTypesList = dataTypes
				.split( ',' )
				.map( ( v ) => v.trim() )
				.filter( ( v ) => '' !== v );

			const affectedList = affectedUsers
				.split( /[\r\n]+/ )
				.map( ( v ) => v.trim() )
				.filter( ( v ) => '' !== v );

			try {
				const response = await fetcher( endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						Accept: 'application/json',
						'Content-Type': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						...( csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {} ),
					},
					body: JSON.stringify( {
						description,
						severity,
						data_types_affected: dataTypesList,
						records_affected: '' === recordsAffected ? null : Number( recordsAffected ),
						affected_users: 0 === affectedList.length ? null : affectedList,
						cause: '' === cause ? null : cause,
						remediation: '' === remediation ? null : remediation,
						occurred_at: '' === occurredAt ? null : occurredAt,
					} ),
				} );

				if ( ! response.ok ) {
					throw new Error( `Failed to report breach (${ response.status })` );
				}

				const json = ( await response.json() ) as { data: AdminBreachRow };
				setSuccess( json.data );
				onSubmitted?.( json.data );

				setDescription( '' );
				setDataTypes( '' );
				setRecordsAffected( '' );
				setAffectedUsers( '' );
				setCause( '' );
				setRemediation( '' );
				setOccurredAt( '' );
			} catch ( err ) {
				setError( err instanceof Error ? err.message : String( err ) );
			} finally {
				setSubmitting( false );
			}
		},
		[ affectedUsers, cause, csrfToken, dataTypes, description, endpoint, fetcher, occurredAt, onSubmitted, recordsAffected, remediation, severity ],
	);

	return (
		<div className={ className ?? 'privacy-admin-breach-report-form' }>
			<header>
				{ heading ?? <h2>Report a breach</h2> }
				<p>Document a new incident to start the notification workflow.</p>
			</header>

			{ null !== success && (
				<div className="privacy-admin-breach-report-form__success" role="status">
					Breach recorded successfully ({ success.reference_number }).
				</div>
			) }

			{ null !== error && (
				<p role="alert">{ error }</p>
			) }

			<form onSubmit={ submit }>
				<label>
					<span>Description *</span>
					<textarea
						value={ description }
						onChange={ ( e ) => setDescription( e.target.value ) }
						rows={ 4 }
						required
					/>
				</label>

				<label>
					<span>Severity *</span>
					<select value={ severity } onChange={ ( e ) => setSeverity( e.target.value ) } required>
						{ SEVERITIES.map( ( s ) => (
							<option key={ s.value } value={ s.value }>{ s.label }</option>
						) ) }
					</select>
				</label>

				<label>
					<span>Occurred at (optional)</span>
					<input
						type="datetime-local"
						value={ occurredAt }
						onChange={ ( e ) => setOccurredAt( e.target.value ) }
					/>
				</label>

				<label>
					<span>Data types affected (comma-separated) *</span>
					<input
						type="text"
						value={ dataTypes }
						onChange={ ( e ) => setDataTypes( e.target.value ) }
						placeholder="email, name, hashed_password"
						required
					/>
				</label>

				<label>
					<span>Approximate records affected</span>
					<input
						type="number"
						min={ 0 }
						value={ recordsAffected }
						onChange={ ( e ) => setRecordsAffected( e.target.value ) }
					/>
				</label>

				<label>
					<span>Affected users (one email per line)</span>
					<textarea
						value={ affectedUsers }
						onChange={ ( e ) => setAffectedUsers( e.target.value ) }
						rows={ 3 }
					/>
				</label>

				<label>
					<span>Suspected cause</span>
					<textarea
						value={ cause }
						onChange={ ( e ) => setCause( e.target.value ) }
						rows={ 2 }
					/>
				</label>

				<label>
					<span>Remediation measures</span>
					<textarea
						value={ remediation }
						onChange={ ( e ) => setRemediation( e.target.value ) }
						rows={ 2 }
					/>
				</label>

				<button type="submit" disabled={ submitting }>
					{ submitting ? 'Recording…' : 'Record breach' }
				</button>
			</form>
		</div>
	);
}
