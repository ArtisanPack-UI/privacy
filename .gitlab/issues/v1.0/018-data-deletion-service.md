# Implement DataDeletionService

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::3" ~"Area::Backend"

## Problem Statement

Users have the right to request deletion of their personal data (GDPR Article 17, CCPA).

## Proposed Solution

Create `DataDeletionService` with configurable deletion strategies.

## Acceptance Criteria

- [ ] `delete(Model $user, array $options)` method
- [ ] `anonymize(Model $user)` method
- [ ] `pseudonymize(Model $user)` method
- [ ] `deleteRelated(Model $user, array $relations)` method
- [ ] `scheduleForDeletion(Model $user, int $gracePeriodDays)` method
- [ ] `cancelScheduledDeletion(Model $user)` method
- [ ] Cascade deletion to related models
- [ ] Preserve audit logs (anonymized)
- [ ] Grace period before permanent deletion
- [ ] Configurable default strategy
- [ ] Events fired on deletion
- [ ] Reversible soft-delete option
- [ ] Unit tests for each strategy
- [ ] Feature tests for deletion flow

## Use Cases

1. User requests deletion
2. Admin reviews and approves
3. Grace period starts (30 days)
4. User can cancel during grace period
5. Data permanently deleted/anonymized

## Additional Context

Configuration:
```php
'deletion' => [
    'default_strategy' => 'anonymize', // delete, anonymize, pseudonymize
    'cascade' => true,
    'preserve_audit' => true,
    'grace_period_days' => 30,
],
```

---

**Related Issues:**
- #016 (Identity Verification)
- #019 (AnonymizationService)
