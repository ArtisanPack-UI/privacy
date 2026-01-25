# Implement PersonalDataScanner service

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::4" ~"Area::Backend"

## Problem Statement

Organizations need to discover and document where personal data is stored across their application for compliance audits.

## Proposed Solution

Create `PersonalDataScanner` service that auto-detects personal data fields in Eloquent models.

## Acceptance Criteria

- [ ] `scan()` method to scan all models
- [ ] `scanModel(string $modelClass)` method
- [ ] `registerField(string $model, string $field, array $config)` method
- [ ] `getFieldMappings(string $model)` method
- [ ] `generateDataInventory()` method
- [ ] Pattern detection for common PII field names
- [ ] Configurable scan paths
- [ ] Model exclusion support
- [ ] Sensitivity classification
- [ ] Store discovered mappings in database
- [ ] Merge auto-discovered with manual definitions
- [ ] Unit tests for detection logic

## Use Cases

1. Run scanner on deployment
2. Discover new personal data fields
3. Generate data inventory report for DPO
4. Track data flows for documentation

## Additional Context

Configuration:
```php
'discovery' => [
    'auto_scan' => true,
    'scan_paths' => [app_path('Models')],
    'field_patterns' => [
        'email' => ['email', 'e_mail'],
        'name' => ['name', 'first_name', 'last_name'],
        'phone' => ['phone', 'telephone', 'mobile'],
    ],
],
```

---

**Related Issues:**
- #022 (HasPersonalData Trait)
- #024 (Privacy Scan Command)
