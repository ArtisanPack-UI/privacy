# Implement global helper functions

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::1" ~"Area::Backend"

## Problem Statement

Developers need convenient global helper functions to check consent and perform common privacy operations without injecting services.

## Proposed Solution

Create `helpers.php` with global functions that wrap the service layer.

## Acceptance Criteria

- [ ] `privacyHasConsent(string $category)` function
- [ ] `privacyGetRegulation()` function
- [ ] `privacyRequestDataExport(Model $user)` function
- [ ] `privacyRequestDataDeletion(Model $user, ?string $reason)` function
- [ ] `privacyAnonymize(Model $model)` function
- [ ] `privacyCanDelete(Model $user)` function
- [ ] Helper file autoloaded via Composer
- [ ] Functions handle edge cases gracefully
- [ ] Unit tests for all helpers
- [ ] Functions work in both web and console contexts

## Use Cases

1. Check consent in controller: `if (privacyHasConsent('analytics')) { ... }`
2. Process deletion in job: `privacyRequestDataDeletion($user, 'Account closed')`
3. Check deletability: `if (privacyCanDelete($user)) { ... }`

## Additional Context

Helpers should delegate to services, not contain business logic.

---

**Related Issues:**
- #004 (ConsentService)
