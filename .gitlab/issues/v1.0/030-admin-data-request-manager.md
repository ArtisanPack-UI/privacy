# Implement Admin DataRequestManager Livewire component

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::6" ~"Area::Frontend"

## Problem Statement

Administrators need to view, process, and manage data subject requests with deadline tracking.

## Proposed Solution

Create `Admin\DataRequestManager` Livewire component for request administration.

## Acceptance Criteria

- [ ] Livewire component at `src/Livewire/Admin/DataRequestManager.php`
- [ ] List all requests with status indicators
- [ ] Filter by type, status, deadline
- [ ] Sort by urgency (deadline approaching)
- [ ] Highlight overdue requests
- [ ] Process request actions (approve, reject, complete)
- [ ] Add admin notes
- [ ] View request details and history
- [ ] Manual verification option
- [ ] Deadline countdown display
- [ ] Notification to user on action
- [ ] Authorization gate check
- [ ] Feature tests for component

## Use Cases

1. Admin sees pending deletion requests
2. Admin reviews and approves request
3. Admin adds note explaining rejection
4. Admin sees requests due within 7 days

## Additional Context

Status workflow: pending → verified → processing → completed/rejected

Deadline calculation based on applicable regulation.

---

**Related Issues:**
- #003 (Core Models)
- #032 (Admin Authorization)
