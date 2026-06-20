/**
 * ConsentPreferences — React preferences panel.
 *
 * Stand-alone (or modal-embedded) component that lets a visitor edit
 * every configured category. Tracks unsaved changes locally and only
 * persists on the explicit save action.
 *
 * @since 1.0.0
 */

import { useEffect, useState } from 'react';
import { useConsent, type UseConsentOptions } from './useConsent';
import type { ConsentMap } from '../privacy';

export interface ConsentPreferencesProps extends UseConsentOptions {
	className?: string;
	showDescriptions?: boolean;
	showCookieList?: boolean;
	saveLabel?: string;
	savedLabel?: string;
	onSaved?: ( consents: ConsentMap ) => void;
}

export function ConsentPreferences( props: ConsentPreferencesProps ): JSX.Element {
	const {
		className,
		showDescriptions = true,
		showCookieList = false,
		saveLabel = 'Save preferences',
		savedLabel = 'Your preferences have been saved.',
		onSaved,
		...hookOptions
	} = props;

	const { state, loading, setConsents } = useConsent( hookOptions );
	const [ selected, setSelected ] = useState<ConsentMap>( {} );
	const [ initial, setInitial ] = useState<ConsentMap>( {} );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );

	useEffect( () => {
		if ( ! state?.categories ) {
			return;
		}
		const next: ConsentMap = {};
		Object.entries( state.categories ).forEach( ( [ key, config ] ) => {
			next[ key ] = true === config.required || state.consents[ key ] === true;
		} );
		setSelected( next );
		setInitial( next );
	}, [ state?.categories, state?.consents ] );

	if ( loading || null === state ) {
		return <div className={ className ?? 'privacy-consent-preferences' } aria-busy="true" />;
	}

	const dirty = Object.entries( selected ).some( ( [ key, value ] ) => initial[ key ] !== value );

	const toggle = ( key: string, granted: boolean ): void => {
		const required = true === state.categories[ key ]?.required;
		setSelected( ( prev ) => ( { ...prev, [ key ]: required ? true : granted } ) );
		setSaved( false );
	};

	const save = async (): Promise<void> => {
		setSaving( true );
		try {
			const result = await setConsents( selected );
			setInitial( selected );
			setSaved( true );
			onSaved?.( result.consents );
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className={ className ?? 'privacy-consent-preferences' }>
			{ saved ? (
				<p role="status" aria-live="polite" className="privacy-consent-preferences__notice">
					{ savedLabel }
				</p>
			) : null }

			<ul role="group" aria-label="Cookie categories" className="privacy-consent-preferences__list">
				{ Object.entries( state.categories ).map( ( [ key, config ] ) => {
					const required = true === config.required;
					const inputId = `privacy-preferences-react-${ key }`;
					const cookies = Array.isArray( config.cookies ) ? config.cookies : [];
					return (
						<li key={ key } className="privacy-consent-preferences__item">
							<div>
								<label htmlFor={ inputId }>
									<strong>{ config.name ?? key }</strong>
									{ required ? <em> (required)</em> : null }
								</label>
								{ showDescriptions && config.description ? (
									<p className="privacy-consent-preferences__description">{ config.description }</p>
								) : null }
								{ showCookieList && cookies.length > 0 ? (
									<ul className="privacy-consent-preferences__cookies">
										{ cookies.map( ( cookie ) => (
											<li key={ cookie }>{ cookie }</li>
										) ) }
									</ul>
								) : null }
							</div>
							<input
								id={ inputId }
								type="checkbox"
								checked={ selected[ key ] ?? required }
								disabled={ required || saving }
								onChange={ ( event ) => toggle( key, event.target.checked ) }
							/>
						</li>
					);
				} ) }
			</ul>

			<div className="privacy-consent-preferences__actions">
				<button type="button" disabled={ ! dirty || saving } onClick={ () => void save() }>
					{ saveLabel }
				</button>
			</div>
		</div>
	);
}
