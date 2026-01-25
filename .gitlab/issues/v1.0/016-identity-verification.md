# Implement identity verification for data requests

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::3" ~"Area::Backend"

## Problem Statement

Data subject requests must verify the requester's identity to prevent unauthorized access to personal data.

## Proposed Solution

Create an identity verification system with configurable methods (email, SMS, manual).

## Acceptance Criteria

- [ ] Email verification with secure token
- [ ] Token generation and storage
- [ ] Verification link/code generation
- [ ] Verification endpoint/route
- [ ] Configurable verification method
- [ ] Token expiration (configurable)
- [ ] Rate limiting on verification attempts
- [ ] `verified_at` timestamp on DataRequest
- [ ] Notification sent on verification request
- [ ] Manual verification option for admins
- [ ] Unit tests for verification flow
- [ ] Feature tests for email verification

## Use Cases

1. User submits request, receives email with link
2. User clicks link, identity verified
3. Request moves to processing status
4. Admin can manually verify if needed

## Additional Context

Configuration:
```php
'data_requests' => [
    'require_verification' => true,
    'verification_method' => 'email', // email, sms, manual
],
```

---

**Related Issues:**
- #015 (DataRequestForm Component)
- #017 (DataExportService)
