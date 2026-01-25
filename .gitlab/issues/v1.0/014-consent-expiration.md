# Implement consent expiration and re-consent

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::2" ~"Area::Backend"

## Problem Statement

Privacy regulations require consent to be renewed periodically. Expired consent should trigger re-consent prompts.

## Proposed Solution

Add expiration tracking to consents and create a command to purge expired consents.

## Acceptance Criteria

- [ ] `expires_at` field populated on consent creation
- [ ] Expiration configurable per regulation
- [ ] `isConsentExpired()` method in ConsentService
- [ ] Expired consent triggers banner display
- [ ] `privacy:purge-expired` Artisan command
- [ ] Optional user notification before expiration
- [ ] Re-consent preserves previous preferences as defaults
- [ ] Scheduled command for cleanup
- [ ] Unit tests for expiration logic
- [ ] Feature tests for re-consent flow

## Use Cases

1. GDPR consent expires after 365 days
2. User returns after expiration, sees banner again
3. Admin runs cleanup command to remove old records
4. User receives email 30 days before expiration

## Additional Context

Configuration:
```php
'regulations' => [
    'gdpr' => [
        'consent_expiry_days' => 365,
    ],
],
```

---

**Related Issues:**
- #004 (ConsentService)
- #006 (Events System)
