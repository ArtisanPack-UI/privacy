# Implement data request notifications

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::3" ~"Area::Backend"

## Problem Statement

Users and admins need to be notified at various stages of the data request process.

## Proposed Solution

Create notification classes for key data request events.

## Acceptance Criteria

### Notifications
- [ ] `DataRequestReceived` - sent to user on submission
- [ ] `DataRequestVerificationRequired` - verification link
- [ ] `DataRequestProcessing` - sent when processing starts
- [ ] `DataRequestCompleted` - sent with result/download link
- [ ] `DataRequestRejected` - sent if request denied
- [ ] `AdminDataRequestNotification` - sent to admin

### Features
- [ ] Mail channel support
- [ ] Database channel support
- [ ] Configurable notification channels
- [ ] Notification templates customizable
- [ ] Queue support for notifications
- [ ] Rate limiting to prevent spam
- [ ] Unit tests for notification content
- [ ] Feature tests for notification triggers

## Use Cases

1. User submits request → receives confirmation
2. Admin receives notification of new request
3. User receives download link when export ready
4. User notified if request rejected with reason

## Additional Context

Notifications should be translatable and use slots for custom content.

---

**Related Issues:**
- #015 (DataRequestForm Component)
- #017 (DataExportService)
