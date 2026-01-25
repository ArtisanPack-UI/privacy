# Implement AnonymizationService

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::3" ~"Area::Backend"

## Problem Statement

Personal data often needs to be anonymized rather than deleted to preserve data integrity while protecting privacy.

## Proposed Solution

Create `AnonymizationService` with multiple anonymization strategies per field type.

## Acceptance Criteria

- [ ] `anonymize(Model $model, array $fields)` method
- [ ] `anonymizeField(string $value, string $strategy)` method
- [ ] `mask(string $value)` strategy - j***@e***.com
- [ ] `redact(string $value)` strategy - [REDACTED]
- [ ] `hash(string $value)` strategy - SHA-256 hash
- [ ] `truncate(string $value)` strategy - partial value
- [ ] `generalize(string $value, string $type)` strategy
- [ ] `pseudonymize(string $value)` strategy - reversible
- [ ] Strategy configuration per field type
- [ ] Batch anonymization support
- [ ] Audit logging of anonymization
- [ ] Reversible pseudonymization with key
- [ ] Unit tests for each strategy
- [ ] Feature tests for model anonymization

## Use Cases

1. Email masked: `john.doe@example.com` → `j***@e***.com`
2. Phone redacted: `555-1234` → `[REDACTED]`
3. Address generalized: `123 Main St, NYC` → `New York, USA`
4. IP truncated: `192.168.1.100` → `192.168.1.0`

## Additional Context

Configuration:
```php
'anonymization' => [
    'strategies' => [
        'email' => 'mask',
        'name' => 'pseudonymize',
        'phone' => 'redact',
        'address' => 'generalize',
        'ip_address' => 'truncate',
    ],
    'hash_algorithm' => 'sha256',
    'pseudonymization_prefix' => 'Anon_',
],
```

---

**Related Issues:**
- #018 (DataDeletionService)
- #022 (HasPersonalData Trait)
