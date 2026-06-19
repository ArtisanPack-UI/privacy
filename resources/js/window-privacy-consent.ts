/**
 * ArtisanPack UI — `window.PrivacyConsent` global API.
 *
 * Provides the lightweight, framework-agnostic surface that issue #11
 * describes: synchronous `hasConsent` / `getConsents` reads, an
 * `openPreferences` trigger that the bundled Livewire/React/Vue UIs listen
 * for, and helpers for loading or rewriting third-party scripts only after
 * the visitor has granted consent.
 *
 * The API is installed on the global window object by `./privacy.ts` so
 * inline `<script>` tags can use it without a bundler. The state cache is
 * fed by the same `privacy:consent-updated` browser event the package
 * already dispatches from the Livewire components and the JSON client.
 *
 * @since 1.0.0
 */

import {
	getPrivacyClient,
	type ConsentMap,
	type ConsentState,
	type PrivacyClient,
} from './privacy';

const UPDATE_EVENT = 'privacy:consent-updated';
const OPEN_PREFERENCES_EVENT = 'privacy:open-preferences';

export type ConsentChangeListener = ( state: ConsentState ) => void;

export interface ScriptLoadOptions {
	/** Attributes copied onto the inserted `<script>` element. */
	attributes?: Record<string, string>;
	/** Run as an ES module. */
	module?: boolean;
	/** Add `async` to the inserted tag. Defaults to true. */
	async?: boolean;
	/** Add `defer` to the inserted tag. Defaults to false. */
	defer?: boolean;
	/**
	 * Abort signal — when aborted before consent is granted, the returned
	 * Promise rejects with the signal's reason and the consent listener is
	 * unsubscribed.
	 */
	signal?: AbortSignal;
	/**
	 * Reject the Promise after this many milliseconds if consent has not been
	 * granted by then. Defaults to Infinity (never time out).
	 */
	timeoutMs?: number;
}

export interface WaitOptions {
	signal?: AbortSignal;
	timeoutMs?: number;
}

export interface WindowPrivacyConsent {
	/** Synchronously returns true when the named category is granted. */
	hasConsent: ( category: string ) => boolean;
	/** Synchronously returns the latest consent map (empty object before load). */
	getConsents: () => ConsentMap;
	/** Synchronously returns the cached consent state (null before load). */
	getState: () => ConsentState | null;
	/** Loads the consent map from the backend. */
	load: () => Promise<ConsentState>;
	/** Persists a single category change. */
	setConsent: ( category: string, granted: boolean ) => Promise<ConsentState>;
	/** Persists a bulk consent map. */
	setConsents: ( consents: ConsentMap ) => Promise<ConsentState>;
	/** Dispatches `privacy:open-preferences` for the bundled UIs to react to. */
	openPreferences: () => void;
	/** Subscribes to consent updates. Returns an unsubscribe handle. */
	onChange: ( listener: ConsentChangeListener ) => () => void;
	/**
	 * Resolves once the named category is granted. Resolves immediately when
	 * consent is already on file. Pass `{signal}` or `{timeoutMs}` to bound
	 * the wait — otherwise the listener stays subscribed for the lifetime of
	 * the page, which leaks memory in long-lived SPAs.
	 */
	whenConsented: ( category: string, options?: WaitOptions ) => Promise<ConsentState>;
	/**
	 * Inserts a `<script>` tag for `src` only after consent for `category` is
	 * granted. The returned promise resolves once the script has loaded. Pass
	 * `{signal}` or `{timeoutMs}` to bound the wait — otherwise the consent
	 * listener stays subscribed forever if consent is never granted.
	 */
	loadScript: ( src: string, category: string, options?: ScriptLoadOptions ) => Promise<HTMLScriptElement>;
	/**
	 * Rewrites every `<script type="text/plain" data-privacy-category="x">` tag
	 * currently in the document into a live `<script>` tag, but only for
	 * categories the visitor has granted. Re-running after a consent change
	 * picks up newly authorised tags.
	 */
	activatePlaceholderScripts: () => HTMLScriptElement[];
	/** The underlying PrivacyClient — escape hatch for advanced callers. */
	client: PrivacyClient;
}

/**
 * Pull a snapshot of the consent map from the client, falling back to an
 * empty object when the state has not loaded yet so callers never have to
 * null-check.
 */
function snapshotConsents( client: PrivacyClient ): ConsentMap {
	const state = client.getState();
	return null === state ? {} : { ...state.consents };
}

/**
 * Track scripts we have already activated so a re-run does not duplicate
 * insertions. Keyed by the original placeholder element to allow GC once
 * the placeholder is removed from the DOM.
 */
const activatedPlaceholders = new WeakSet<HTMLScriptElement>();

/**
 * Install timeout + AbortSignal guards for a wait-for-consent Promise.
 *
 * Returns a cleanup function that should be called from every other
 * resolution path so the timeout and the signal listener don't leak.
 * `onAbort` is invoked when either guard fires so the caller can detach
 * its own state (consent listener, script element handlers). `reject` is
 * called with the abort reason (or a timeout error).
 */
function installWaitGuards(
	options: WaitOptions,
	onAbort: () => void,
	reject: ( reason: unknown ) => void,
): () => void {
	let timeoutId: ReturnType<typeof setTimeout> | null = null;
	let abortListener: ( () => void ) | null = null;
	let cancelled = false;

	const cleanup = (): void => {
		if ( cancelled ) {
			return;
		}
		cancelled = true;
		if ( null !== timeoutId ) {
			clearTimeout( timeoutId );
			timeoutId = null;
		}
		if ( null !== abortListener && options.signal ) {
			options.signal.removeEventListener( 'abort', abortListener );
			abortListener = null;
		}
	};

	const fireAbort = ( reason: unknown ): void => {
		if ( cancelled ) {
			return;
		}
		cleanup();
		onAbort();
		reject( reason );
	};

	if ( options.signal ) {
		if ( options.signal.aborted ) {
			fireAbort( options.signal.reason ?? new DOMException( 'Aborted', 'AbortError' ) );
			return cleanup;
		}
		abortListener = (): void => {
			fireAbort( options.signal?.reason ?? new DOMException( 'Aborted', 'AbortError' ) );
		};
		options.signal.addEventListener( 'abort', abortListener );
	}

	if ( 'number' === typeof options.timeoutMs && Number.isFinite( options.timeoutMs ) ) {
		timeoutId = setTimeout( () => {
			fireAbort( new Error( `Timed out waiting for consent after ${ options.timeoutMs }ms` ) );
		}, options.timeoutMs );
	}

	return cleanup;
}

/**
 * Attribute names whose meaning is specific to the placeholder pattern —
 * `type="text/plain"` is what blocks the script in the first place, and the
 * `data-src` / `data-type` / `data-async` / `data-defer` attributes are the
 * placeholder's own encoding of how the live tag should be configured. Every
 * other attribute (`nonce`, `integrity`, `crossorigin`, `referrerpolicy`,
 * `id`, `class`, etc.) is forwarded so the activated script behaves the same
 * way as a hand-written `<script>` tag in CSP-strict environments.
 */
const PLACEHOLDER_ONLY_ATTRIBUTES = new Set( [
	'type',
	'data-src',
	'data-type',
	'data-async',
	'data-defer',
] );

/**
 * Insert a fresh `<script>` tag with the same attributes as the placeholder.
 * Returns the inserted element so callers can attach further listeners.
 *
 * Add the placeholder to the activated WeakSet BEFORE removing it from the
 * DOM so the idempotence guarantee does not rely on GC timing for any
 * caller that hangs on to the original reference.
 */
function activatePlaceholder( placeholder: HTMLScriptElement ): HTMLScriptElement | null {
	if ( activatedPlaceholders.has( placeholder ) ) {
		return null;
	}
	activatedPlaceholders.add( placeholder );

	const next = document.createElement( 'script' );
	const src = placeholder.getAttribute( 'data-src' );
	const type = placeholder.getAttribute( 'data-type' );

	if ( src ) {
		next.src = src;
	}
	if ( type ) {
		next.type = type;
	} else {
		next.removeAttribute( 'type' );
	}

	// Dynamically-inserted scripts default to async=true per HTML spec. To
	// honour `data-defer`, we have to explicitly set `async=false` so defer
	// wins; to honour an explicit `data-async`, set it true; otherwise the
	// spec default applies.
	const wantsDefer = placeholder.hasAttribute( 'data-defer' );
	const wantsAsync = placeholder.hasAttribute( 'data-async' );
	if ( wantsDefer ) {
		next.async = false;
		next.defer = true;
	} else if ( wantsAsync ) {
		next.async = true;
	}

	Array.from( placeholder.attributes ).forEach( ( attr ) => {
		if ( PLACEHOLDER_ONLY_ATTRIBUTES.has( attr.name ) ) {
			return;
		}
		next.setAttribute( attr.name, attr.value );
	} );

	if ( ! src ) {
		next.textContent = placeholder.textContent ?? '';
	}

	placeholder.parentNode?.insertBefore( next, placeholder.nextSibling );
	placeholder.remove();
	return next;
}

/**
 * Builds the `window.PrivacyConsent` API around the given client. Exported
 * so tests can pass a synthetic client without touching the DOM-installed
 * singleton.
 */
export function createWindowPrivacyConsent( client: PrivacyClient ): WindowPrivacyConsent {
	const api: WindowPrivacyConsent = {
		client,
		hasConsent: ( category ) => client.hasConsent( category ),
		getConsents: () => snapshotConsents( client ),
		getState: () => client.getState(),
		load: () => client.load(),
		setConsent: ( category, granted ) => client.setConsent( category, granted ),
		setConsents: ( consents ) => client.setConsents( consents ),
		openPreferences: (): void => {
			if ( typeof window === 'undefined' || typeof CustomEvent === 'undefined' ) {
				return;
			}
			window.dispatchEvent( new CustomEvent( OPEN_PREFERENCES_EVENT ) );
		},
		onChange: ( listener ) => client.onChange( listener ),
		whenConsented: ( category, options = {} ) =>
			new Promise<ConsentState>( ( resolve, reject ) => {
				if ( client.hasConsent( category ) ) {
					const state = client.getState();
					if ( null !== state ) {
						resolve( state );
						return;
					}
				}

				const cleanup = installWaitGuards( options, () => {
					unsubscribe();
				}, reject );

				const unsubscribe = client.onChange( ( next ) => {
					if ( true === next.consents[ category ] ) {
						cleanup();
						unsubscribe();
						resolve( next );
					}
				} );

				// If the signal has already fired (or the timeout is 0)
				// the cleanup hook above already rejected. Make sure we
				// don't leave a hanging subscription in that case.
				if ( options.signal?.aborted ) {
					unsubscribe();
				}
			} ),
		loadScript: ( src, category, options = {} ) =>
			new Promise<HTMLScriptElement>( ( resolve, reject ) => {
				if ( typeof document === 'undefined' ) {
					reject( new Error( 'document is not available' ) );
					return;
				}

				let unsubscribe: ( () => void ) | null = null;
				let onLoad: ( () => void ) | null = null;
				let onError: ( () => void ) | null = null;
				let inserted: HTMLScriptElement | null = null;

				const cleanup = installWaitGuards( options, () => {
					unsubscribe?.();
					if ( null !== inserted && null !== onLoad ) {
						inserted.removeEventListener( 'load', onLoad );
					}
					if ( null !== inserted && null !== onError ) {
						inserted.removeEventListener( 'error', onError );
					}
				}, reject );

				const insert = (): void => {
					const tag = document.createElement( 'script' );
					inserted = tag;
					tag.src = src;
					tag.async = false === options.async ? false : true;
					tag.defer = true === options.defer;
					if ( true === options.module ) {
						tag.type = 'module';
					}
					Object.entries( options.attributes ?? {} ).forEach( ( [ name, value ] ) => {
						tag.setAttribute( name, value );
					} );
					tag.setAttribute( 'data-privacy-category', category );
					onLoad = (): void => {
						cleanup();
						tag.removeEventListener( 'load', onLoad! );
						tag.removeEventListener( 'error', onError! );
						resolve( tag );
					};
					onError = (): void => {
						cleanup();
						tag.removeEventListener( 'load', onLoad! );
						tag.removeEventListener( 'error', onError! );
						reject( new Error( `Failed to load ${ src }` ) );
					};
					tag.addEventListener( 'load', onLoad );
					tag.addEventListener( 'error', onError );
					( document.head ?? document.body ).appendChild( tag );
				};

				if ( client.hasConsent( category ) ) {
					insert();
					return;
				}

				unsubscribe = client.onChange( ( next ) => {
					if ( true === next.consents[ category ] ) {
						unsubscribe?.();
						unsubscribe = null;
						insert();
					}
				} );

				if ( options.signal?.aborted ) {
					unsubscribe();
					unsubscribe = null;
				}
			} ),
		activatePlaceholderScripts: (): HTMLScriptElement[] => {
			if ( typeof document === 'undefined' ) {
				return [];
			}

			const placeholders = Array.from(
				document.querySelectorAll<HTMLScriptElement>(
					'script[type="text/plain"][data-privacy-category]',
				),
			);

			const activated: HTMLScriptElement[] = [];

			placeholders.forEach( ( placeholder ) => {
				const category = placeholder.getAttribute( 'data-privacy-category' );
				if ( null === category || '' === category ) {
					return;
				}
				if ( ! client.hasConsent( category ) ) {
					return;
				}
				const next = activatePlaceholder( placeholder );
				if ( null !== next ) {
					activated.push( next );
				}
			} );

			return activated;
		},
	};

	return api;
}

/**
 * Installs `window.PrivacyConsent` (and the legacy `window.ArtisanPackPrivacy`
 * reference for callers that already imported the client directly), wires
 * an automatic placeholder-script re-scan to the `privacy:consent-updated`
 * event, and bridges Livewire's `privacy:open-preferences` browser event
 * back into the consent UIs.
 *
 * Guards so re-importing the module (multiple bundles, HMR) does not
 * replace an already-installed singleton — that would orphan any listeners
 * callers have subscribed.
 */
export function installWindowPrivacyConsent( client: PrivacyClient = getPrivacyClient() ): WindowPrivacyConsent {
	const api = createWindowPrivacyConsent( client );

	if ( typeof window === 'undefined' ) {
		return api;
	}

	const target = window as unknown as {
		PrivacyConsent?: WindowPrivacyConsent;
		ArtisanPackPrivacy?: PrivacyClient;
	};

	if ( ! target.PrivacyConsent ) {
		target.PrivacyConsent = api;
		window.addEventListener( UPDATE_EVENT, () => {
			api.activatePlaceholderScripts();
		} );
	}

	if ( ! target.ArtisanPackPrivacy ) {
		target.ArtisanPackPrivacy = client;
	}

	return target.PrivacyConsent ?? api;
}
