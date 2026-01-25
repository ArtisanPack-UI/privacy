# Implement BreachNotification model and service

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::7" ~"Area::Backend"

## Problem Statement

Organizations must document data breaches and notify authorities within regulation-specific timeframes (e.g., 72 hours for GDPR).

## Proposed Solution

Create `BreachNotification` model and `BreachNotificationService` for breach management.

## Acceptance Criteria

### Model
- [ ] Reference number generation
- [ ] Severity levels (low, medium, high, critical)
- [ ] Affected data types tracking
- [ ] Records affected count
- [ ] Cause documentation
- [ ] Remediation tracking
- [ ] Notification status tracking
- [ ] Timeline tracking (discovered, occurred, notified)

### Service
- [ ] `reportBreach(array $data)` method
- [ ] `notifyAuthority(BreachNotification $breach)` method
- [ ] `notifyAffectedUsers(BreachNotification $breach)` method
- [ ] `isWithinNotificationWindow()` method
- [ ] `getNotificationDeadline()` method
- [ ] Deadline calculation per regulation
- [ ] Unit tests for deadline logic

## Use Cases

1. Security team reports breach
2. System calculates 72-hour deadline
3. Admin prepares authority notification
4. Affected users notified

## Additional Context

Status workflow: investigating → contained → resolved

Severity affects notification priority.

---

**Related Issues:**
- #034 (Breach Notification Templates)
- #035 (Admin Breach Management)
