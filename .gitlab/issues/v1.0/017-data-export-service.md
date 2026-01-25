# Implement DataExportService

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::3" ~"Area::Backend"

## Problem Statement

Users have the right to receive their personal data in a portable format (GDPR Article 20, CCPA).

## Proposed Solution

Create `DataExportService` to collect and export user data in multiple formats.

## Acceptance Criteria

- [ ] `export(Model $user, string $format)` method
- [ ] `exportToFile(Model $user, string $format)` method
- [ ] `getPersonalData(Model $user)` method
- [ ] `getConsentHistory(Model $user)` method
- [ ] `getActivityLog(Model $user)` method
- [ ] JSON export format
- [ ] CSV export format
- [ ] XML export format (optional)
- [ ] Collect data from models with `HasPersonalData` trait
- [ ] Include consent history
- [ ] Include data request history
- [ ] Secure file storage with expiration
- [ ] Download URL generation
- [ ] Unit tests for export logic
- [ ] Feature tests for full export flow

## Use Cases

1. User requests data export
2. System collects data from all models
3. Export file generated and stored
4. User receives download link via email

## Additional Context

Export should use filter hooks so other packages can contribute data:
```php
addFilter('privacy.export.data', function ($data, $user) {
    $data['analytics'] = Analytics::exportUserData($user);
    return $data;
});
```

---

**Related Issues:**
- #016 (Identity Verification)
- #022 (HasPersonalData Trait)
