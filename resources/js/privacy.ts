/**
 * ArtisanPack UI — Privacy client API.
 *
 * Vanilla browser API for reading and updating the visitor's consent
 * state. Reads default state from the JSON endpoint exposed by the
 * Laravel package, writes updates back through the same endpoint, and
 * dispatches `privacy:consent-updated` window events for any framework
 * layer (React/Vue/Alpine/Livewire) that wants to listen.
 *
 * The module also installs `window.ArtisanPackPrivacy` so non-bundled
 * scripts can access the same API.
 *
 * @since 1.0.0
 */

export type ConsentMap = Record<string, boolean>;

export interface CookieCategory {
	name?: string;
	description?: string;
	required?: boolean;
	cookies?: string[];
}

export type CookieCategories = Record<string, CookieCategory>;

export interface ConsentState {
	regulation: string | null;
	consents: ConsentMap;
	categories: CookieCategories;
}

export interface PrivacyClientOptions {
	/** Base URL for the consent API. Trailing slashes are trimmed. */
	baseUrl?: string;
	/** CSRF token to send on POST. Defaults to the `csrf-token` meta tag. */
	csrfToken?: string;
	/** Override fetch implementation (useful in tests). */
	fetch?: typeof fetch;
}

export type ConsentListener = ( state: ConsentState ) => void;

const DEFAULT_BASE_URL = '/api/privacy';
const UPDATE_EVENT = 'privacy:consent-updated';

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

/**
 * Normalise the base URL by trimming any trailing slash so we can append
 * route segments unambiguously.
 */
function normaliseBaseUrl( raw: string ): string {
	return raw.replace( /\/+$/, '' );
}

/**
 * Resolve the fetch implementation. The browser's `fetch` is an "illegal
 * invocation" if it is called without `this` pointing at the global
 * Window/WorkerGlobalScope, so we bind it explicitly when stashing the
 * reference. Falls back to a rejecting stub when fetch is unavailable
 * (e.g. legacy SSR contexts).
 */
function resolveFetch( override?: typeof fetch ): typeof fetch {
	if ( override ) {
		return override;
	}
	if ( typeof globalThis !== 'undefined' && 'function' === typeof globalThis.fetch ) {
		return globalThis.fetch.bind( globalThis ) as typeof fetch;
	}
	return ( () => Promise.reject( new Error( 'fetch is not available' ) ) ) as typeof fetch;
}

/**
 * Build the request init for fetch calls, including JSON headers and an
 * optional CSRF token.
 */
function buildHeaders( csrfToken?: string ): HeadersInit {
	const headers: Record<string, string> = {
		Accept: 'application/json',
		'X-Requested-With': 'XMLHttpRequest',
	};
	if ( csrfToken ) {
		headers[ 'X-CSRF-TOKEN' ] = csrfToken;
	}
	return headers;
}

export class PrivacyClient {
	private readonly baseUrl: string;

	private readonly csrfToken: string | undefined;

	private readonly fetchImpl: typeof fetch;

	private readonly listeners = new Set<ConsentListener>();

	private state: ConsentState | null = null;

	constructor( options: PrivacyClientOptions = {} ) {
		this.baseUrl = normaliseBaseUrl( options.baseUrl ?? DEFAULT_BASE_URL );
		this.csrfToken = options.csrfToken ?? resolveCsrfToken();
		this.fetchImpl = resolveFetch( options.fetch );
	}

	/**
	 * Fetches the current consent state and caches it.
	 */
	async load(): Promise<ConsentState> {
		const response = await this.fetchImpl( `${ this.baseUrl }/consent`, {
			method: 'GET',
			credentials: 'same-origin',
			headers: buildHeaders( this.csrfToken ),
		} );
		if ( ! response.ok ) {
			throw new Error( `Failed to load consent state: ${ response.status }` );
		}
		const data = ( await response.json() ) as ConsentState;
		this.setState( data, { dispatch: false } );
		return data;
	}

	/**
	 * Returns the cached state (or null when {@link load} hasn't run yet).
	 */
	getState(): ConsentState | null {
		return this.state;
	}

	/**
	 * Returns true when the visitor has granted consent for the named
	 * category. Falls back to false until state has been loaded.
	 */
	hasConsent( category: string ): boolean {
		return this.state?.consents?.[ category ] === true;
	}

	/**
	 * Persists a single category change and returns the new state.
	 */
	async setConsent( category: string, granted: boolean ): Promise<ConsentState> {
		return this.update( { category, granted } );
	}

	/**
	 * Persists a bulk consent map and returns the new state.
	 */
	async setConsents( consents: ConsentMap ): Promise<ConsentState> {
		return this.update( { consents } );
	}

	/**
	 * Subscribes a listener to consent updates. Returns an unsubscribe handle.
	 */
	onChange( listener: ConsentListener ): () => void {
		this.listeners.add( listener );
		return () => {
			this.listeners.delete( listener );
		};
	}

	private async update( payload: Record<string, unknown> ): Promise<ConsentState> {
		const response = await this.fetchImpl( `${ this.baseUrl }/consent`, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				...buildHeaders( this.csrfToken ),
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( payload ),
		} );
		if ( ! response.ok ) {
			throw new Error( `Failed to update consent: ${ response.status }` );
		}
		const data = ( await response.json() ) as ConsentState;
		this.setState( data );
		return data;
	}

	private setState( next: ConsentState, options: { dispatch?: boolean } = {} ): void {
		this.state = next;

		if ( false === options.dispatch ) {
			return;
		}

		this.listeners.forEach( ( listener ) => listener( next ) );

		if ( typeof window !== 'undefined' && typeof CustomEvent !== 'undefined' ) {
			window.dispatchEvent( new CustomEvent( UPDATE_EVENT, { detail: next } ) );
		}
	}
}

let defaultClient: PrivacyClient | null = null;

/**
 * Returns (and lazily creates) the process-wide default client. Most
 * applications use a single shared instance.
 */
export function getPrivacyClient( options?: PrivacyClientOptions ): PrivacyClient {
	if ( null === defaultClient ) {
		defaultClient = new PrivacyClient( options );
	}
	return defaultClient;
}

/**
 * Resets the default client. Intended for tests; rarely needed in app code.
 */
export function resetPrivacyClient(): void {
	defaultClient = null;
}

// Synchronous install — must run before any inline script reads
// `window.PrivacyConsent` or `window.ArtisanPackPrivacy`, and before any
// Livewire/Alpine handler can dispatch `privacy:consent-updated`. The
// install function is idempotent so re-importing this module (multiple
// bundles, code-split chunks, HMR) is safe.
//
// `installWindowPrivacyConsent` only depends on `PrivacyClient` and the
// `getPrivacyClient()` factory from this module — no eval-time
// side-effects flow back, so the static import does not create a
// circular-init problem.
import { installWindowPrivacyConsent } from './window-privacy-consent';

if ( typeof window !== 'undefined' ) {
	installWindowPrivacyConsent();
}
