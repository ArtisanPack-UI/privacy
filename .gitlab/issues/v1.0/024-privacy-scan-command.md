# Implement privacy:scan Artisan command

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::4" ~"Area::Backend"

## Problem Statement

Developers need an easy way to run the personal data scanner and optionally save results.

## Proposed Solution

Create `privacy:scan` Artisan command for personal data discovery.

## Acceptance Criteria

- [ ] Command: `php artisan privacy:scan`
- [ ] `--model=` option to scan specific model
- [ ] `--save` option to persist mappings
- [ ] `--format=` option (table, json, csv)
- [ ] Display discovered fields with types
- [ ] Show sensitivity classifications
- [ ] Highlight unmapped fields
- [ ] Comparison with existing mappings
- [ ] Interactive mode for field configuration
- [ ] Success/warning/error output formatting
- [ ] Unit tests for command

## Use Cases

1. Initial setup: `php artisan privacy:scan --save`
2. Check specific model: `php artisan privacy:scan --model=App\\Models\\User`
3. Export for documentation: `php artisan privacy:scan --format=json > inventory.json`

## Additional Context

Output example:
```
Scanning models in app/Models...

App\Models\User
├── email (email) - normal sensitivity - auto-detected
├── name (name) - normal sensitivity - auto-detected
├── phone (phone) - normal sensitivity - auto-detected
└── ssn (ssn) - HIGH sensitivity - auto-detected ⚠️

Found 4 personal data fields in 1 model.
```

---

**Related Issues:**
- #023 (PersonalDataScanner)
