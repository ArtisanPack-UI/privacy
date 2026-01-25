# Implement BaseRegulation class and regulation system

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::5" ~"Area::Backend"

## Problem Statement

Different privacy regulations have different requirements. The package needs a structured way to define and enforce regulation-specific rules.

## Proposed Solution

Create a `BaseRegulation` abstract class and concrete implementations for each supported regulation.

## Acceptance Criteria

- [ ] `BaseRegulation` abstract class
- [ ] `PrivacyRegulation` contract/interface
- [ ] Methods: `getConsentRequirements()`, `getDataRights()`, `getRetentionRules()`
- [ ] Methods: `getBreachNotificationWindow()`, `getResponseDeadline()`
- [ ] `applies(Request $request)` method for regulation detection
- [ ] Regulation registry for management
- [ ] `getApplicableRegulation()` helper
- [ ] Multiple regulation support (user may be subject to several)
- [ ] Unit tests for base class

## Use Cases

1. Determine which regulation applies to a user
2. Get consent requirements for applicable regulation
3. Calculate response deadline for data request
4. Check breach notification window

## Additional Context

```php
abstract class BaseRegulation implements PrivacyRegulation
{
    abstract public function getConsentRequirements(): array;
    abstract public function getDataRights(): array;
    abstract public function getBreachNotificationHours(): int;
    abstract public function getResponseDays(string $requestType): int;
    abstract public function applies(Request $request): bool;
}
```

---

**Related Issues:**
- #026 (GDPR Regulation)
- #027 (CCPA Regulation)
