# Implement events and listeners system

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::1" ~"Area::Backend"

## Problem Statement

The package needs events to allow external packages and application code to react to privacy-related actions.

## Proposed Solution

Create event classes for all major actions and default listeners for common operations.

## Acceptance Criteria

### Events
- [ ] `ConsentGiven` event with `Consent` payload
- [ ] `ConsentWithdrawn` event with `Consent` payload
- [ ] `DataAccessRequested` event with `DataRequest` payload
- [ ] `DataDeletionRequested` event with `DataRequest` payload
- [ ] `DataExportRequested` event with `DataRequest` payload
- [ ] `DataRequestCompleted` event with `DataRequest` payload
- [ ] `DataBreach` event with `BreachNotification` payload
- [ ] `PrivacyPolicyUpdated` event with `PrivacyPolicy` payload

### Listeners
- [ ] `LogConsentActivity` listener
- [ ] `ProcessDataAccessRequest` listener
- [ ] `ProcessDataExportRequest` listener
- [ ] `NotifyAdminOfRequest` listener
- [ ] `NotifyDataBreach` listener

### Tests
- [ ] Events are dispatchable
- [ ] Listeners are triggered correctly
- [ ] Event-listener mapping in service provider

## Use Cases

1. Analytics package listens for `ConsentGiven` to enable tracking
2. Application logs consent changes to audit trail
3. Admin receives notification on new data request

## Additional Context

Events should be serializable for queue processing.

---

**Related Issues:**
- #003 (Core Models)
- #004 (ConsentService)
