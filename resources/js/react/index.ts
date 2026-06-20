/**
 * Barrel export for the React companion components and hook.
 *
 * @since 1.0.0
 */

export { CookieBanner } from './CookieBanner';
export type { CookieBannerProps } from './CookieBanner';
export { ConsentPreferences } from './ConsentPreferences';
export type { ConsentPreferencesProps } from './ConsentPreferences';
export { DataRequestForm } from './DataRequestForm';
export type { DataRequestFormProps, DataRequestResult, DataRequestType } from './DataRequestForm';
export { VerifyDataRequest } from './VerifyDataRequest';
export type { VerifyDataRequestProps } from './VerifyDataRequest';
export { PrivacyDashboard } from './PrivacyDashboard';
export type {
	PrivacyDashboardProps,
	PrivacyDashboardRequest,
	PrivacyDashboardHistoryPayload,
} from './PrivacyDashboard';
export { useConsent } from './useConsent';
export type { UseConsentOptions, UseConsentResult } from './useConsent';
export {
	ConsentManager as AdminConsentManager,
	DataRequestManager as AdminDataRequestManager,
} from './Admin';
export type {
	AdminConsentPayload,
	AdminConsentRow,
	AdminDataRequestDetails,
	AdminDataRequestPayload,
	AdminDataRequestRow,
	ConsentManagerProps as AdminConsentManagerProps,
	DataRequestManagerProps as AdminDataRequestManagerProps,
} from './Admin';
export type {
	ConsentMap,
	ConsentState,
	CookieCategories,
	CookieCategory,
	PrivacyClient,
	PrivacyClientOptions,
} from '../privacy';
