# Implement privacy policy display routes and views

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::8" ~"Area::Frontend"

## Problem Statement

Users need to view the current privacy policy and be notified of policy changes.

## Proposed Solution

Create routes and views for displaying privacy policies.

## Acceptance Criteria

- [ ] Route: `GET /privacy` - display current policy
- [ ] Route: `GET /privacy/{version}` - display specific version
- [ ] Policy view with Markdown rendering
- [ ] Table of contents generation
- [ ] Print-friendly styling
- [ ] Version history link
- [ ] Last updated date display
- [ ] Language switcher (if multi-language)
- [ ] Feature tests for routes

## Use Cases

1. User clicks privacy policy link
2. User views current policy
3. User can view previous versions
4. User can print policy

## Additional Context

View should be publishable for customization:
```bash
php artisan vendor:publish --tag=privacy-views
```

---

**Related Issues:**
- #036 (Privacy Policy Management)
- #039 (Re-consent Workflow)
