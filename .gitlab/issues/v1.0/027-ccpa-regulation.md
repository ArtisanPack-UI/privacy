# Implement CCPA/CPRA regulation rules

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::5" ~"Area::Backend"

## Problem Statement

CCPA/CPRA (California Consumer Privacy Act / California Privacy Rights Act) has specific requirements for California residents.

## Proposed Solution

Create `CCPA` regulation class implementing all CCPA/CPRA-specific rules.

## Acceptance Criteria

- [ ] `CCPA` class extending `BaseRegulation`
- [ ] Data rights: know, delete, opt-out of sale, non-discrimination
- [ ] 45-day response deadline (extendable)
- [ ] "Do Not Sell My Personal Information" link support
- [ ] Opt-out of sale/sharing
- [ ] Financial incentive disclosure
- [ ] Geographic detection (California)
- [ ] Business threshold checks (optional)
- [ ] Unit tests for all rules

## Use Cases

1. User from California visits → CCPA applies
2. User clicks "Do Not Sell" → sale consent revoked
3. Data request must be fulfilled within 45 days
4. User cannot be discriminated against for exercising rights

## Additional Context

CCPA applies to businesses that:
- Have gross annual revenue > $25 million, OR
- Buy/sell personal info of 100,000+ consumers, OR
- Derive 50%+ revenue from selling personal info

Configuration:
```php
'ccpa' => [
    'enabled' => true,
    'applies_to' => ['US-CA'],
    'opt_out_sale' => true,
    'response_days' => 45,
],
```

---

**Related Issues:**
- #025 (BaseRegulation Class)
- #028 (Geolocation Service)
