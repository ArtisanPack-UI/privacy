/**
 * VerifyDataRequest — React identity-verification component.
 *
 * Posts the supplied token to the package's verification endpoint and
 * surfaces the resulting confirmation/expired/already-verified state.
 *
 * @since 1.0.0
 */

import { useState, type ReactNode } from 'react';

export interface VerifyDataRequestProps {
	token: string;
	endpoint?: string;
	csrfToken?: string;
	fetchImpl?: typeof fetch;
	className?: string;
	heading?: ReactNode;
	onVerified?: () => void;
}

type Phase = 'idle' | 'pending' | 'success' | 'expired' | 'error';

function resolveCsrfToken(): string | undefined {
	if ( typeof document === 'undefined' ) {
		return undefined;
	}

	const meta = document.querySelector<HTMLMetaElement>( 'meta[name="csrf-token"]' );

	return meta?.content;
}

export function VerifyDataRequest( props: VerifyDataRequestProps ): JSX.Element {
	const {
		token,
		endpoint = `/privacy/verify/${ encodeURIComponent( token ) }`,
		csrfToken = resolveCsrfToken(),
		fetchImpl = fetch,
		className = 'privacy-verify-data-request',
		heading = 'Verify your data request',
		onVerified,
	} = props;

	const [ phase, setPhase ] = useState<Phase>( 'idle' );
	const [ message, setMessage ] = useState<string>( '' );

	async function submit() {
		if ( phase === 'pending' || phase === 'success' ) {
			return;
		}

		setPhase( 'pending' );
		setMessage( '' );

		try {
			const headers: Record<string, string> = {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
			};

			if ( csrfToken ) {
				headers[ 'X-CSRF-TOKEN' ] = csrfToken;
			}

			const response = await fetchImpl( endpoint, {
				method: 'POST',
				headers,
				credentials: 'same-origin',
			} );

			if ( response.status === 410 || response.status === 422 ) {
				setPhase( 'expired' );
				setMessage( 'This verification link has expired. Please submit a new request.' );
				return;
			}

			if ( ! response.ok ) {
				setPhase( 'error' );
				setMessage( 'We could not verify this request. Please try again.' );
				return;
			}

			setPhase( 'success' );
			setMessage( 'Your identity has been verified and your request is now being processed.' );
			onVerified?.();
		} catch ( e ) {
			setPhase( 'error' );
			setMessage( 'We could not reach the verification endpoint.' );
		}
	}

	return (
		<div className={ className }>
			{ heading && <h2 className={ `${ className }__heading` }>{ heading }</h2> }

			{ phase === 'success' && <p className={ `${ className }__success` }>{ message }</p> }
			{ phase === 'expired' && <p className={ `${ className }__error` }>{ message }</p> }
			{ phase === 'error' && <p className={ `${ className }__error` }>{ message }</p> }

			{ phase !== 'success' && phase !== 'expired' && (
				<button
					type="button"
					onClick={ submit }
					disabled={ phase === 'pending' }
					className={ `${ className }__button` }
				>
					{ phase === 'pending' ? 'Verifying…' : 'Confirm Identity' }
				</button>
			) }
		</div>
	);
}
