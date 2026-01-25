# Implement re-consent workflow for policy changes

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::8" ~"Area::Backend"

## Problem Statement

When privacy policies change significantly, users may need to re-consent to the new terms.

## Proposed Solution

Create a workflow for detecting policy changes that require re-consent and prompting users.

## Acceptance Criteria

- [ ] `requires_reconsent` flag on PrivacyPolicy
- [ ] Compare current consent to active policy version
- [ ] Middleware to detect out-of-date consent
- [ ] Banner/modal for re-consent prompt
- [ ] Notification of policy change
- [ ] Grace period configuration
- [ ] Log re-consent events
- [ ] Handle users who don't re-consent
- [ ] Feature tests for workflow

## Use Cases

1. New policy published with `requires_reconsent = true`
2. Users with old consent see re-consent prompt
3. User reviews changes and re-consents
4. New consent recorded with new policy version

## Additional Context

Configuration:
```php
'policy' => [
    'reconsent_grace_period_days' => 30,
    'block_on_no_reconsent' => false,
],
```

---

**Related Issues:**
- #036 (Privacy Policy Management)
- #006 (Events System)
