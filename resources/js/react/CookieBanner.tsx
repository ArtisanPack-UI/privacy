/**
 * CookieBanner — React cookie consent banner.
 *
 * Renders nothing until the consent state has loaded. Once loaded, hides
 * itself if the visitor has already made a choice for at least one
 * non-required category. Provides Accept all / Reject all / Customise
 * actions backed by the JSON API.
 *
 * @since 1.0.0
 */

import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { useConsent, type UseConsentOptions } from './useConsent';
import type { ConsentMap } from '../privacy';

export interface CookieBannerProps extends UseConsentOptions {
	className?: string;
	header?: ReactNode;
	description?: ReactNode;
	footer?: ReactNode;
	acceptLabel?: string;
	rejectLabel?: string;
	customiseLabel?: string;
	saveLabel?: string;
	onClose?: () => void;
}

/**
 * True when at least one non-required category already has an explicit
 * stored choice (true OR false). We can't distinguish "no choice" from
 * "false" reliably with the current API, so we use the same heuristic
 * as the Livewire banner: hide once any non-required is granted.
 */
function hasVisitorMadeChoice( consents: ConsentMap, requiredKeys: string[] ): boolean {
	return Object.entries( consents ).some(
		( [ key, value ] ) => ! requiredKeys.includes( key ) && true === value,
	);
}

export function CookieBanner( props: CookieBannerProps ): JSX.Element | null {
	const {
		className,
		header,
		description,
		footer,
		acceptLabel = 'Accept all',
		rejectLabel = 'Reject all',
		customiseLabel = 'Customise',
		saveLabel = 'Save selection',
		onClose,
		...hookOptions
	} = props;

	const { state, loading, setConsents } = useConsent( hookOptions );
	const [ showPreferences, setShowPreferences ] = useState( false );
	const [ selected, setSelected ] = useState<ConsentMap>( {} );
	const [ dismissed, setDismissed ] = useState( false );
	const [ saving, setSaving ] = useState( false );

	const requiredKeys = useMemo( () => {
		if ( ! state?.categories ) {
			return [];
		}
		return Object.entries( state.categories )
			.filter( ( [ , config ] ) => true === config.required )
			.map( ( [ key ] ) => key );
	}, [ state?.categories ] );

	useEffect( () => {
		if ( ! state?.categories ) {
			return;
		}
		setSelected( ( prev ) => {
			const next: ConsentMap = { ...prev };
			Object.entries( state.categories ).forEach( ( [ key, config ] ) => {
				if ( ! ( key in next ) ) {
					next[ key ] = true === config.required ? true : state.consents[ key ] === true;
				} else if ( true === config.required ) {
					next[ key ] = true;
				}
			} );
			return next;
		} );
	}, [ state?.categories, state?.consents ] );

	if ( loading || null === state ) {
		return null;
	}

	if ( dismissed || hasVisitorMadeChoice( state.consents, requiredKeys ) ) {
		return null;
	}

	const close = (): void => {
		setDismissed( true );
		onClose?.();
	};

	const buildAllTrueMap = (): ConsentMap => {
		const map: ConsentMap = {};
		Object.keys( state.categories ).forEach( ( key ) => {
			map[ key ] = true;
		} );
		return map;
	};

	const buildRejectMap = (): ConsentMap => {
		const map: ConsentMap = {};
		Object.entries( state.categories ).forEach( ( [ key, config ] ) => {
			map[ key ] = true === config.required;
		} );
		return map;
	};

	const submit = async ( consents: ConsentMap ): Promise<void> => {
		setSaving( true );
		try {
			await setConsents( consents );
			close();
		} finally {
			setSaving( false );
		}
	};

	const onToggle = ( category: string, granted: boolean ): void => {
		setSelected( ( prev ) => {
			const next = { ...prev, [ category ]: granted };
			if ( requiredKeys.includes( category ) ) {
				next[ category ] = true;
			}
			return next;
		} );
	};

	return (
		<div
			role="dialog"
			aria-modal="false"
			aria-label="Cookie consent"
			className={ className ?? 'privacy-cookie-banner' }
		>
			<div className="privacy-cookie-banner__inner">
				{ header ?? <h2>We value your privacy</h2> }
				{ description ?? (
					<p>
						We use cookies to make our site work and to understand how it is used. You can
						choose which categories you allow.
					</p>
				) }

				{ showPreferences && (
					<ul className="privacy-cookie-banner__categories" role="group" aria-label="Cookie categories">
						{ Object.entries( state.categories ).map( ( [ key, config ] ) => {
							const required = true === config.required;
							const checkboxId = `privacy-banner-react-${ key }`;
							return (
								<li key={ key } className="privacy-cookie-banner__category">
									<label htmlFor={ checkboxId }>
										<span>
											<strong>{ config.name ?? key }</strong>
											{ required ? <em> (required)</em> : null }
										</span>
										{ config.description ? <small>{ config.description }</small> : null }
									</label>
									<input
										id={ checkboxId }
										type="checkbox"
										checked={ selected[ key ] ?? required }
										disabled={ required || saving }
										onChange={ ( event ) => onToggle( key, event.target.checked ) }
									/>
								</li>
							);
						} ) }
					</ul>
				) }

				<div className="privacy-cookie-banner__actions">
					{ showPreferences ? (
						<button type="button" disabled={ saving } onClick={ () => void submit( selected ) }>
							{ saveLabel }
						</button>
					) : (
						<>
							<button type="button" disabled={ saving } onClick={ () => setShowPreferences( true ) }>
								{ customiseLabel }
							</button>
							<button type="button" disabled={ saving } onClick={ () => void submit( buildRejectMap() ) }>
								{ rejectLabel }
							</button>
							<button type="button" disabled={ saving } onClick={ () => void submit( buildAllTrueMap() ) }>
								{ acceptLabel }
							</button>
						</>
					) }
				</div>

				{ footer }
			</div>
		</div>
	);
}
