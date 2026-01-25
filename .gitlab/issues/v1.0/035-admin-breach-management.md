# Implement admin breach management UI

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::7" ~"Area::Frontend"

## Problem Statement

Administrators need a UI to report, track, and manage data breach incidents.

## Proposed Solution

Create admin Livewire components for breach management.

## Acceptance Criteria

- [ ] Breach list component with filters
- [ ] Breach report form component
- [ ] Breach detail view component
- [ ] Severity indicator display
- [ ] Deadline countdown/overdue warning
- [ ] Notification status tracking
- [ ] Send authority notification action
- [ ] Send user notification action
- [ ] Add remediation notes
- [ ] Update status workflow
- [ ] Export breach documentation
- [ ] Feature tests for components

## Use Cases

1. Security officer reports new breach
2. Admin tracks investigation status
3. Admin generates authority notification
4. Admin marks breach as resolved

## Additional Context

Breach detail should show:
- Timeline of events
- Affected data categories
- Notification history
- Remediation actions taken

---

**Related Issues:**
- #033 (BreachNotification Model)
- #034 (Breach Notification Templates)
