/**
 * PolicyReconsentBanner — React companion to the Livewire re-consent banner.
 *
 * Polls the package's consent endpoint to discover whether the current
 * active policy requires re-consent (carried via the `_policy_version`
 * metadata on the consent state). Renders nothing until a stale policy
 * version is detected, then exposes Accept / Review / Dismiss actions.
 *
 * @since 1.0.0
 */

import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { getPrivacyClient, type PrivacyClientOptions } from '../privacy';

export interface PolicyReconsentPayload {
	version: string;
	regulation?: string | null;
	url?: string;
}

export interface PolicyReconsentBannerProps extends PrivacyClientOptions {
	policy: PolicyReconsentPayload | null;
	className?: string;
	buttonClassName?: string;
	header?: ReactNode;
	description?: ReactNode;
	footer?: ReactNode;
	labels?: {
		title?: string;
		description?: string;
		review?: string;
		accept?: string;
		dismiss?: string;
	};
	policyUrl?: string;
	reconsentUrl?: string;
	onAccept?: ( version: string ) => void;
	onDismiss?: () => void;
}

export function PolicyReconsentBanner( props: PolicyReconsentBannerProps ): JSX.Element | null {
	const {
		policy,
		className,
		buttonClassName,
		header,
		description,
		footer,
		labels,
		policyUrl = '/privacy/policy',
		reconsentUrl = '/privacy/reconsent',
		onAccept,
		onDismiss,
		...clientOptions
	} = props;

	const client = useMemo( () => getPrivacyClient( clientOptions ), [ clientOptions ] );
	const [ dismissed, setDismissed ] = useState( false );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		setDismissed( false );
	}, [ policy?.version ] );

	if ( null === policy || dismissed ) {
		return null;
	}

	const resolvedLabels = {
		title: labels?.title ?? 'Our privacy policy has been updated',
		description: labels?.description ?? 'Please review the updated policy and confirm that you accept the new terms.',
		review: labels?.review ?? 'Review policy',
		accept: labels?.accept ?? 'Accept updated policy',
		dismiss: labels?.dismiss ?? 'Remind me later',
	};

	const handleAccept = async (): Promise<void> => {
		setSaving( true );
		try {
			const csrf =
				typeof document !== 'undefined'
					? document.querySelector<HTMLMetaElement>( 'meta[name="csrf-token"]' )?.content
					: undefined;
			await fetch( reconsentUrl, {
				method: 'POST',
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json',
					...( csrf ? { 'X-CSRF-TOKEN': csrf } : {} ),
				},
				credentials: 'same-origin',
				body: JSON.stringify( {
					version: policy.version,
					regulation: policy.regulation ?? null,
				} ),
			} );
			setDismissed( true );
			onAccept?.( policy.version );
			void client.load();
		} finally {
			setSaving( false );
		}
	};

	const handleDismiss = (): void => {
		setDismissed( true );
		onDismiss?.();
	};

	const buttonClass = buttonClassName ?? 'privacy-reconsent-banner__btn';

	return (
		<div
			role="alertdialog"
			aria-modal="false"
			aria-labelledby="privacy-reconsent-title"
			className={ className ?? 'privacy-reconsent-banner' }
			data-privacy-reconsent
			data-policy-version={ policy.version }
		>
			<div className="privacy-reconsent-banner__inner">
				{ header }
				<h2 id="privacy-reconsent-title">{ resolvedLabels.title }</h2>
				{ description ?? <p>{ resolvedLabels.description }</p> }
				<p className="privacy-reconsent-banner__version">
					Version: <strong>{ policy.version }</strong>
				</p>
				<div className="privacy-reconsent-banner__actions">
					<a
						className={ buttonClass }
						href={ policy.url ?? policyUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ resolvedLabels.review }
					</a>
					<button type="button" className={ buttonClass } disabled={ saving } onClick={ handleDismiss }>
						{ resolvedLabels.dismiss }
					</button>
					<button type="button" className={ `${ buttonClass } btn-primary` } disabled={ saving } onClick={ () => void handleAccept() }>
						{ resolvedLabels.accept }
					</button>
				</div>
				{ footer }
			</div>
		</div>
	);
}
