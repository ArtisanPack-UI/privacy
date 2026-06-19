/**
 * Barrel export for the React companion components and hook.
 *
 * @since 1.0.0
 */

export { CookieBanner } from './CookieBanner';
export type { CookieBannerProps } from './CookieBanner';
export { ConsentPreferences } from './ConsentPreferences';
export type { ConsentPreferencesProps } from './ConsentPreferences';
export { useConsent } from './useConsent';
export type { UseConsentOptions, UseConsentResult } from './useConsent';
export type {
	ConsentMap,
	ConsentState,
	CookieCategories,
	CookieCategory,
	PrivacyClient,
	PrivacyClientOptions,
} from '../privacy';
