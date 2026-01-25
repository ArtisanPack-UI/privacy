# Implement PrivacyDashboard Livewire component (user-facing)

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::3" ~"Area::Frontend"

## Problem Statement

Users need a central place to view their privacy settings, consent status, and data request history.

## Proposed Solution

Create a `PrivacyDashboard` Livewire component for authenticated users.

## Acceptance Criteria

- [ ] Livewire component at `src/Livewire/PrivacyDashboard.php`
- [ ] View current consent status per category
- [ ] Update consent preferences inline
- [ ] Submit new data requests
- [ ] View data request history with status
- [ ] Download completed exports
- [ ] View applicable regulation
- [ ] Link to privacy policy
- [ ] Responsive design
- [ ] Accessible interface
- [ ] Feature tests for dashboard

## Use Cases

1. User views their current consent settings
2. User toggles analytics consent off
3. User submits data export request
4. User downloads previously requested export

## Additional Context

```blade
<livewire:privacy-dashboard />
```

Dashboard should integrate:
- ConsentPreferences component
- DataRequestForm component
- Request history table

---

**Related Issues:**
- #009 (ConsentPreferences Component)
- #015 (DataRequestForm Component)
