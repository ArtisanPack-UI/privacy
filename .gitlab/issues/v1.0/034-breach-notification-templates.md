# Implement breach notification templates

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::7" ~"Area::Backend"

## Problem Statement

Breach notifications to authorities and users must follow specific formats and include required information.

## Proposed Solution

Create notification templates for authorities and affected users.

## Acceptance Criteria

### Authority Notification Template
- [ ] Organization details
- [ ] DPO contact information
- [ ] Nature of breach description
- [ ] Categories of data affected
- [ ] Approximate number of records
- [ ] Likely consequences
- [ ] Measures taken/proposed
- [ ] Meets GDPR Article 33 requirements

### User Notification Template
- [ ] Clear, plain language
- [ ] Nature of breach
- [ ] Data affected (specific to user)
- [ ] Measures user should take
- [ ] Organization contact details
- [ ] Meets GDPR Article 34 requirements

### Features
- [ ] Markdown and HTML output
- [ ] Customizable via publishing
- [ ] Translatable
- [ ] Unit tests for template rendering

## Use Cases

1. Generate authority notification document
2. Send email to affected users
3. Customize template for company branding

## Additional Context

Templates should be publishable:
```bash
php artisan vendor:publish --tag=privacy-breach-templates
```

---

**Related Issues:**
- #033 (BreachNotification Model)
