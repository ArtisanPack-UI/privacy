/**
 * useConsent — React hook that exposes the package's consent state.
 *
 * Loads state from the configured PrivacyClient on first mount, keeps it
 * in sync via the client's `onChange` subscription, and returns helpers
 * for granting/revoking individual categories or saving a full map.
 *
 * @since 1.0.0
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import {
	type ConsentMap,
	type ConsentState,
	getPrivacyClient,
	type PrivacyClient,
	type PrivacyClientOptions,
} from '../privacy';

export interface UseConsentOptions extends PrivacyClientOptions {
	/** Provide an existing client instead of resolving the default one. */
	client?: PrivacyClient;
	/** Skip the initial `load()` call (e.g. when state is already hydrated). */
	skipInitialLoad?: boolean;
}

export interface UseConsentResult {
	state: ConsentState | null;
	loading: boolean;
	error: Error | null;
	hasConsent: ( category: string ) => boolean;
	setConsent: ( category: string, granted: boolean ) => Promise<ConsentState>;
	setConsents: ( consents: ConsentMap ) => Promise<ConsentState>;
	reload: () => Promise<ConsentState>;
}

export function useConsent( options: UseConsentOptions = {} ): UseConsentResult {
	const clientRef = useRef<PrivacyClient | null>( null );
	if ( null === clientRef.current ) {
		clientRef.current = options.client ?? getPrivacyClient( options );
	}
	const client = clientRef.current;

	const [ state, setState ] = useState<ConsentState | null>( () => client.getState() );
	const [ loading, setLoading ] = useState<boolean>( ! options.skipInitialLoad && null === client.getState() );
	const [ error, setError ] = useState<Error | null>( null );

	useEffect( () => {
		const unsubscribe = client.onChange( ( next ) => setState( next ) );

		if ( options.skipInitialLoad || null !== client.getState() ) {
			setState( client.getState() );
			return unsubscribe;
		}

		let cancelled = false;
		setLoading( true );
		client
			.load()
			.then( ( next ) => {
				if ( ! cancelled ) {
					setState( next );
				}
			} )
			.catch( ( err: unknown ) => {
				if ( ! cancelled ) {
					setError( err instanceof Error ? err : new Error( String( err ) ) );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
			unsubscribe();
		};
	}, [ client, options.skipInitialLoad ] );

	const hasConsent = useCallback(
		( category: string ): boolean => state?.consents?.[ category ] === true,
		[ state ],
	);

	const setConsent = useCallback(
		( category: string, granted: boolean ): Promise<ConsentState> =>
			client.setConsent( category, granted ),
		[ client ],
	);

	const setConsents = useCallback(
		( consents: ConsentMap ): Promise<ConsentState> => client.setConsents( consents ),
		[ client ],
	);

	const reload = useCallback( (): Promise<ConsentState> => client.load(), [ client ] );

	return { state, loading, error, hasConsent, setConsent, setConsents, reload };
}
