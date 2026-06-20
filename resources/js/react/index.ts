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
export { PolicyReconsentBanner } from './PolicyReconsentBanner';
export type {
	PolicyReconsentBannerProps,
	PolicyReconsentPayload,
} from './PolicyReconsentBanner';
export { useConsent } from './useConsent';
export type { UseConsentOptions, UseConsentResult } from './useConsent';
export {
	ConsentManager as AdminConsentManager,
	DataRequestManager as AdminDataRequestManager,
	ComplianceReport as AdminComplianceReport,
	BreachManager as AdminBreachManager,
	BreachReportForm as AdminBreachReportForm,
	BreachDetail as AdminBreachDetail,
} from './Admin';
export type {
	AdminBreachDetailPayload,
	AdminBreachPayload,
	AdminBreachRow,
	AdminConsentPayload,
	AdminConsentRow,
	AdminDataRequestDetails,
	AdminDataRequestPayload,
	AdminDataRequestRow,
	BreachDetailProps as AdminBreachDetailProps,
	BreachManagerProps as AdminBreachManagerProps,
	BreachReportFormProps as AdminBreachReportFormProps,
	ComplianceBreachStats,
	ComplianceConsentStats,
	ComplianceReportPayload,
	ComplianceReportProps as AdminComplianceReportProps,
	ComplianceReportResponse,
	ComplianceRequestStats,
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
