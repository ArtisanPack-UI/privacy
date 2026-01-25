# Implement GeoLocationService for regulation detection

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::5" ~"Area::Backend"

## Problem Statement

To apply the correct regulation, the system needs to determine the user's geographic location.

## Proposed Solution

Create `GeoLocationService` with multiple provider support for IP-based geolocation.

## Acceptance Criteria

- [ ] `GeoLocationService` class
- [ ] `getLocation(string $ip)` method
- [ ] `getCountryCode(string $ip)` method
- [ ] `getRegionCode(string $ip)` method
- [ ] IP-API provider implementation
- [ ] MaxMind provider implementation (optional)
- [ ] Cloudflare header support
- [ ] Result caching (configurable duration)
- [ ] Fallback region configuration
- [ ] Rate limiting awareness
- [ ] Unit tests with mocked responses
- [ ] Integration tests (optional, requires API key)

## Use Cases

1. User visits from IP → determine country
2. Country matches GDPR region → apply GDPR
3. Cache result to avoid repeated API calls
4. Fallback to config if geolocation fails

## Additional Context

Configuration:
```php
'geolocation' => [
    'enabled' => true,
    'provider' => 'ip-api', // ip-api, maxmind, cloudflare
    'cache_duration' => 1440, // minutes
    'fallback_region' => null,
],
```

The `GeolocateUser` middleware should use this service.

---

**Related Issues:**
- #026 (GDPR Regulation)
- #027 (CCPA Regulation)
