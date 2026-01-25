# Implement Admin ConsentManager Livewire component

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::6" ~"Area::Frontend"

## Problem Statement

Administrators need a dashboard to view and manage consent records across all users.

## Proposed Solution

Create `Admin\ConsentManager` Livewire component for consent administration.

## Acceptance Criteria

- [ ] Livewire component at `src/Livewire/Admin/ConsentManager.php`
- [ ] List all consent records with pagination
- [ ] Filter by category, status, date range
- [ ] Filter by user/guest identifier
- [ ] Search functionality
- [ ] Bulk selection support
- [ ] Export consent audit log (CSV, JSON)
- [ ] View consent details
- [ ] Responsive table design
- [ ] Authorization gate check
- [ ] Feature tests for component

## Use Cases

1. Admin views all analytics consents granted today
2. Admin exports consent log for GDPR audit
3. Admin searches for specific user's consent history
4. Admin filters to see all withdrawn consents

## Additional Context

Component should use `x-artisanpack-table` from livewire-ui-components.

---

**Related Issues:**
- #003 (Core Models)
- #032 (Admin Authorization)
