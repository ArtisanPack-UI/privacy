# Implement consent storage (database and cookies)

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Backend"

## Problem Statement

Consent must be stored persistently, with support for both database storage (for authenticated users) and cookie storage (for guests and compliance proof).

## Proposed Solution

Extend `ConsentService` to support configurable storage backends: database, cookie, or both.

## Acceptance Criteria

- [ ] Database storage implementation
- [ ] Cookie storage implementation
- [ ] Configurable storage mode (`'storage' => 'both'`)
- [ ] Guest identifier support (fingerprint, session, IP)
- [ ] Cookie encryption for security
- [ ] Cookie lifetime configuration
- [ ] Sync cookie to database on authentication
- [ ] Handle cookie consent for returning visitors
- [ ] Storage abstraction for future backends
- [ ] Unit tests for each storage type
- [ ] Integration tests for dual storage

## Use Cases

1. Guest visits, consents stored in cookie
2. Guest creates account, cookie consent synced to database
3. Returning visitor's consent loaded from cookie
4. GDPR audit retrieves consent from database

## Additional Context

Configuration:
```php
'consent' => [
    'storage' => 'both', // database, cookie, both
    'cookie_name' => 'privacy_consent',
    'cookie_lifetime' => 365,
    'guest_identifier' => 'fingerprint',
],
```

---

**Related Issues:**
- #004 (ConsentService)
- #005 (Configuration System)
