/**
 * Barrel export for the Vue companion components and composable.
 *
 * @since 1.0.0
 */

export { default as CookieBanner } from './CookieBanner.vue';
export { default as ConsentPreferences } from './ConsentPreferences.vue';
export { default as DataRequestForm } from './DataRequestForm.vue';
export { default as VerifyDataRequest } from './VerifyDataRequest.vue';
export { default as PrivacyDashboard } from './PrivacyDashboard.vue';
export type {
	PrivacyDashboardRequest,
	PrivacyDashboardHistoryPayload,
} from './PrivacyDashboard.vue';
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
