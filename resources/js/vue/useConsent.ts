/**
 * useConsent — Vue composable that exposes the package's consent state.
 *
 * @since 1.0.0
 */

import { computed, onBeforeUnmount, onMounted, ref, type ComputedRef, type Ref } from 'vue';
import {
	type ConsentMap,
	type ConsentState,
	getPrivacyClient,
	type PrivacyClient,
	type PrivacyClientOptions,
} from '../privacy';

export interface UseConsentOptions extends PrivacyClientOptions {
	client?: PrivacyClient;
	skipInitialLoad?: boolean;
}

export interface UseConsentResult {
	state: Ref<ConsentState | null>;
	loading: Ref<boolean>;
	error: Ref<Error | null>;
	hasConsent: ( category: string ) => boolean;
	setConsent: ( category: string, granted: boolean ) => Promise<ConsentState>;
	setConsents: ( consents: ConsentMap ) => Promise<ConsentState>;
	reload: () => Promise<ConsentState>;
	categories: ComputedRef<ConsentState[ 'categories' ]>;
}

export function useConsent( options: UseConsentOptions = {} ): UseConsentResult {
	const client: PrivacyClient = options.client ?? getPrivacyClient( options );

	const state = ref<ConsentState | null>( client.getState() );
	const loading = ref<boolean>( ! options.skipInitialLoad && null === client.getState() );
	const error = ref<Error | null>( null );

	let unsubscribe: ( () => void ) | null = null;

	onMounted( () => {
		unsubscribe = client.onChange( ( next ) => {
			state.value = next;
		} );

		if ( options.skipInitialLoad || null !== client.getState() ) {
			state.value = client.getState();
			return;
		}

		loading.value = true;
		client
			.load()
			.then( ( next ) => {
				state.value = next;
			} )
			.catch( ( err: unknown ) => {
				error.value = err instanceof Error ? err : new Error( String( err ) );
			} )
			.finally( () => {
				loading.value = false;
			} );
	} );

	onBeforeUnmount( () => {
		if ( null !== unsubscribe ) {
			unsubscribe();
			unsubscribe = null;
		}
	} );

	const categories = computed( () => state.value?.categories ?? {} );

	return {
		state,
		loading,
		error,
		hasConsent: ( category: string ): boolean => state.value?.consents?.[ category ] === true,
		setConsent: ( category, granted ) => client.setConsent( category, granted ),
		setConsents: ( consents ) => client.setConsents( consents ),
		reload: () => client.load(),
		categories,
	};
}
