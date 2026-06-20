/**
 * Barrel export for the admin Vue components.
 *
 * @since 1.0.0
 */

export { default as ConsentManager } from './ConsentManager.vue';
export type {
	AdminConsentPayload,
	AdminConsentRow,
} from './ConsentManager.vue';
export { default as DataRequestManager } from './DataRequestManager.vue';
export type {
	AdminDataRequestDetails,
	AdminDataRequestPayload,
	AdminDataRequestRow,
} from './DataRequestManager.vue';
export { default as ComplianceReport } from './ComplianceReport.vue';
export type {
	ComplianceBreachStats,
	ComplianceConsentStats,
	ComplianceReportPayload,
	ComplianceReportResponse,
	ComplianceRequestStats,
} from './ComplianceReport.vue';
export { default as BreachManager } from './BreachManager.vue';
export type {
	AdminBreachPayload,
	AdminBreachRow,
} from './BreachManager.vue';
export { default as BreachReportForm } from './BreachReportForm.vue';
export { default as BreachDetail } from './BreachDetail.vue';
export type { AdminBreachDetailPayload } from './BreachDetail.vue';
