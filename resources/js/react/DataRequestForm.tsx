/**
 * DataRequestForm — React data subject request form.
 *
 * Renders a controlled form for filing access / export / deletion /
 * rectification requests against the privacy package's JSON API. Handles
 * client-side validation, loading state, error display, and a confirmation
 * panel that mentions identity verification when required.
 *
 * @since 1.0.0
 */

import { useMemo, useState, type FormEvent, type ReactNode } from 'react';

export type DataRequestType = 'access' | 'export' | 'deletion' | 'rectification';

export interface DataRequestFormProps {
	requestTypes?: DataRequestType[];
	requireReason?: boolean;
	endpoint?: string;
	csrfToken?: string;
	fetchImpl?: typeof fetch;
	className?: string;
	labels?: Partial<Record<DataRequestType, string>>;
	heading?: ReactNode;
	description?: ReactNode;
	onSubmitted?: ( result: DataRequestResult ) => void;
}

export interface DataRequestResult {
	id: number;
	type: string;
	status: string;
	verification_sent: boolean;
}

const DEFAULT_TYPES: DataRequestType[] = [ 'access', 'export', 'deletion', 'rectification' ];
const DEFAULT_LABELS: Record<DataRequestType, string> = {
	access: 'Request a copy of my data',
	export: 'Export my data',
	deletion: 'Delete my data',
	rectification: 'Correct my data',
};

/**
 * Resolve the CSRF token from a meta tag if one is present in the document.
 */
function resolveCsrfToken(): string | undefined {
	if ( typeof document === 'undefined' ) {
		return undefined;
	}
	const meta = document.querySelector<HTMLMetaElement>( 'meta[name="csrf-token"]' );
	return meta?.content ?? undefined;
}

export function DataRequestForm( props: DataRequestFormProps ): JSX.Element {
	const {
		requestTypes = DEFAULT_TYPES,
		requireReason = false,
		endpoint = '/api/privacy/data-requests',
		csrfToken,
		fetchImpl,
		className,
		labels,
		heading,
		description,
		onSubmitted,
	} = props;

	const [ type, setType ] = useState<DataRequestType | ''>( '' );
	const [ reason, setReason ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState<string | null>( null );
	const [ result, setResult ] = useState<DataRequestResult | null>( null );

	const fetcher = useMemo<typeof fetch>( () => {
		if ( fetchImpl ) {
			return fetchImpl;
		}
		if ( typeof globalThis !== 'undefined' && 'function' === typeof globalThis.fetch ) {
			return globalThis.fetch.bind( globalThis ) as typeof fetch;
		}
		return ( () => Promise.reject( new Error( 'fetch is not available' ) ) ) as typeof fetch;
	}, [ fetchImpl ] );

	const resolvedLabels: Record<DataRequestType, string> = {
		...DEFAULT_LABELS,
		...( labels ?? {} ),
	};

	const submit = async ( event: FormEvent<HTMLFormElement> ): Promise<void> => {
		event.preventDefault();
		setError( null );

		if ( '' === type ) {
			setError( 'Please choose a request type.' );
			return;
		}

		if ( requireReason && '' === reason.trim() ) {
			setError( 'A reason is required for this request.' );
			return;
		}

		setSaving( true );
		try {
			const response = await fetcher( endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
					...( csrfToken || resolveCsrfToken()
						? { 'X-CSRF-TOKEN': ( csrfToken ?? resolveCsrfToken() ) as string }
						: {} ),
				},
				body: JSON.stringify( {
					type,
					reason: '' === reason ? null : reason,
				} ),
			} );

			if ( ! response.ok ) {
				const payload = ( await response.json().catch( () => null ) ) as { message?: string } | null;
				throw new Error( payload?.message ?? `Request failed (${ response.status })` );
			}

			const data = ( await response.json() ) as DataRequestResult;
			setResult( data );
			onSubmitted?.( data );
		} catch ( err ) {
			setError( err instanceof Error ? err.message : String( err ) );
		} finally {
			setSaving( false );
		}
	};

	if ( null !== result ) {
		return (
			<div
				className={ className ?? 'privacy-data-request-form' }
				role="region"
				aria-label="Privacy data request submitted"
			>
				<div className="privacy-data-request-form__success" role="status" aria-live="polite">
					<h3>Your request was submitted.</h3>
					<p>
						{ result.verification_sent
							? 'We have emailed you a verification link. Confirm your identity to start processing.'
							: 'Our team has been notified and will follow up shortly.' }
					</p>
					<button
						type="button"
						onClick={ () => {
							setResult( null );
							setType( '' );
							setReason( '' );
						} }
					>
						Submit another request
					</button>
				</div>
			</div>
		);
	}

	return (
		<form
			className={ className ?? 'privacy-data-request-form' }
			onSubmit={ ( event ) => void submit( event ) }
			aria-busy={ saving }
		>
			{ heading ?? <h2>Privacy request</h2> }
			{ description }

			<div className="privacy-data-request-form__field">
				<label htmlFor="privacy-data-request-type-react">Request type</label>
				<select
					id="privacy-data-request-type-react"
					value={ type }
					onChange={ ( event ) => setType( event.target.value as DataRequestType ) }
					required
					disabled={ saving }
				>
					<option value="">Choose one…</option>
					{ requestTypes.map( ( option ) => (
						<option key={ option } value={ option }>
							{ resolvedLabels[ option ] }
						</option>
					) ) }
				</select>
			</div>

			<div className="privacy-data-request-form__field">
				<label htmlFor="privacy-data-request-reason-react">
					Reason{ requireReason ? '' : ' (optional)' }
				</label>
				<textarea
					id="privacy-data-request-reason-react"
					value={ reason }
					onChange={ ( event ) => setReason( event.target.value ) }
					rows={ 3 }
					maxLength={ 1000 }
					required={ requireReason }
					disabled={ saving }
				/>
			</div>

			{ null !== error && (
				<p role="alert" className="privacy-data-request-form__error">
					{ error }
				</p>
			) }

			<div className="privacy-data-request-form__actions">
				<button type="submit" disabled={ saving }>
					{ saving ? 'Submitting…' : 'Submit request' }
				</button>
			</div>
		</form>
	);
}
