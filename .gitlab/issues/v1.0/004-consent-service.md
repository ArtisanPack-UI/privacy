# Implement ConsentService for consent management

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::1" ~"Area::Backend"

## Problem Statement

The package needs a central service to manage user consents, including checking, granting, and revoking consent.

## Proposed Solution

Create `ConsentService` with methods for all consent operations, supporting both database and cookie storage.

## Acceptance Criteria

- [ ] `hasConsent(string $category, ?Model $user)` method
- [ ] `getConsents(?Model $user)` method
- [ ] `grantConsent(string $category, ?Model $user, array $metadata)` method
- [ ] `revokeConsent(string $category, ?Model $user)` method
- [ ] `grantAllConsents(?Model $user)` method
- [ ] `revokeAllConsents(?Model $user)` method
- [ ] Cookie operations (`getConsentCookie`, `setConsentCookie`)
- [ ] `isConsentExpired(Consent $consent)` method
- [ ] `getApplicableRegulation()` method
- [ ] Service registered in container
- [ ] Unit tests for all methods
- [ ] Works for both authenticated users and guests

## Use Cases

1. Check if user consented to analytics: `$service->hasConsent('analytics')`
2. Grant marketing consent: `$service->grantConsent('marketing', $user)`
3. Revoke all non-essential consents: `$service->revokeAllConsents($user)`

## Additional Context

Service should fire events (`ConsentGiven`, `ConsentWithdrawn`) for each operation.

---

**Related Issues:**
- #003 (Core Models)
- #006 (Events System)
