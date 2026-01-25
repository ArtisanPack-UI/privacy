# Implement core Eloquent models

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::1" ~"Area::Backend"

## Problem Statement

The package needs Eloquent models to interact with the privacy database tables.

## Proposed Solution

Create models with proper relationships, casts, scopes, and accessors for all privacy tables.

## Acceptance Criteria

- [ ] `Consent` model with morph relationships
- [ ] `ConsentCategory` model with validation
- [ ] `DataRequest` model with status workflow
- [ ] `DataRequestLog` model for audit trail
- [ ] `PersonalDataMap` model for field mappings
- [ ] `PrivacyPolicy` model with versioning
- [ ] `BreachNotification` model with severity levels
- [ ] All models have proper `$casts` arrays
- [ ] Query scopes for common filters
- [ ] Relationships defined and tested
- [ ] Model factories created for testing

## Use Cases

1. Query user consents: `Consent::forUser($user)->get()`
2. Check active policy: `PrivacyPolicy::active()->first()`
3. Find pending requests: `DataRequest::pending()->get()`

## Additional Context

Models should follow ArtisanPack UI coding standards with proper PHPDoc blocks.

---

**Related Issues:**
- #002 (Database Migrations)
