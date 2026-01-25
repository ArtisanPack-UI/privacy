# Implement consent middleware

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::2" ~"Area::Backend"

## Problem Statement

Some routes should only be accessible if specific consent has been given, and consent status should be available in all views.

## Proposed Solution

Create middleware for consent enforcement and consent status injection.

## Acceptance Criteria

### EnsureConsentGiven Middleware
- [ ] Block routes requiring specific consent
- [ ] Configurable redirect or abort behavior
- [ ] Support for multiple required categories
- [ ] Middleware parameter syntax: `privacy.consent:analytics,marketing`

### CheckCookieConsent Middleware
- [ ] Inject `$privacyConsent` variable into all views
- [ ] Make consent status available to JavaScript
- [ ] Lightweight (no database queries if cookie exists)

### Tests
- [ ] Middleware blocks unauthorized access
- [ ] Middleware allows authorized access
- [ ] View variable injection works
- [ ] Middleware registered in service provider

## Use Cases

1. Protect analytics dashboard:
   ```php
   Route::middleware('privacy.consent:analytics')->group(function () {
       Route::get('/my-analytics', AnalyticsController::class);
   });
   ```

2. Access consent in any view:
   ```blade
   @if($privacyConsent['analytics'])
       <!-- Analytics enabled -->
   @endif
   ```

## Additional Context

Middleware should be opt-in, not applied globally by default.

---

**Related Issues:**
- #004 (ConsentService)
- #010 (Consent Storage)
