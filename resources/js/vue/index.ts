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
	ComplianceBreachStats,
	ComplianceConsentStats,
	ComplianceReportPayload,
	ComplianceReportResponse,
	ComplianceRequestStats,
} from './Admin';
export type {
	ConsentMap,
	ConsentState,
	CookieCategories,
	CookieCategory,
	PrivacyClient,
	PrivacyClientOptions,
} from '../privacy';
