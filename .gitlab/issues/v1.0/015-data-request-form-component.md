# Implement DataRequestForm Livewire component

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::3" ~"Area::Frontend"

## Problem Statement

Users need a form to submit data subject requests (access, export, deletion, rectification) as required by privacy regulations.

## Proposed Solution

Create a `DataRequestForm` Livewire component that handles request submission and identity verification.

## Acceptance Criteria

- [ ] Livewire component at `src/Livewire/DataRequestForm.php`
- [ ] Request type selection (access, export, deletion, rectification)
- [ ] Optional reason field
- [ ] Identity verification initiation
- [ ] Form validation
- [ ] Success confirmation display
- [ ] Props: `requestTypes`, `requireReason`
- [ ] Accessible form controls
- [ ] Loading states during submission
- [ ] Error handling and display
- [ ] Feature tests for component

## Use Cases

1. User selects "Export my data" and submits
2. User receives verification email
3. User confirms identity via link
4. Request is queued for processing

## Additional Context

```blade
<livewire:privacy-data-request-form
    :request-types="['access', 'export', 'deletion']"
/>
```

---

**Related Issues:**
- #003 (Core Models)
- #016 (Identity Verification)
