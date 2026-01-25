# Implement GDPR regulation rules

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::5" ~"Area::Backend"

## Problem Statement

GDPR (General Data Protection Regulation) has specific requirements that must be enforced for EU/EEA users.

## Proposed Solution

Create `GDPR` regulation class implementing all GDPR-specific rules.

## Acceptance Criteria

- [ ] `GDPR` class extending `BaseRegulation`
- [ ] Consent requirements (explicit, granular, withdrawable)
- [ ] Data rights: access, rectification, erasure, portability, restriction, objection
- [ ] 72-hour breach notification window
- [ ] 30-day response deadline
- [ ] Consent expiry (365 days default)
- [ ] Geographic detection (EU/EEA/UK)
- [ ] Legal basis tracking (consent, legitimate interest, contract, etc.)
- [ ] DPO contact information support
- [ ] Unit tests for all rules

## Use Cases

1. User from Germany visits → GDPR applies
2. 72-hour countdown starts on breach discovery
3. Data request must be fulfilled within 30 days
4. Consent must be renewed annually

## Additional Context

Countries covered: All EU member states, EEA (Norway, Iceland, Liechtenstein), UK (UK GDPR).

Configuration:
```php
'gdpr' => [
    'enabled' => true,
    'applies_to' => ['EU', 'EEA', 'UK'],
    'consent_expiry_days' => 365,
    'breach_notification_hours' => 72,
],
```

---

**Related Issues:**
- #025 (BaseRegulation Class)
- #028 (Geolocation Service)
