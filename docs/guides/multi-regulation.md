# Multi-regulation setup

The package ships with first-class GDPR and CCPA implementations and toggles for LGPD and PIPEDA. Each regulation is a class that implements `ArtisanPackUI\Privacy\Regulations\RegulationContract` and is registered in `RegulationRegistry`.

## Enable a regulation

```php
'regulations' => [
    'gdpr' => ['enabled' => true,  'applies_to' => ['EU', 'EEA', 'UK']],
    'ccpa' => ['enabled' => true,  'applies_to' => ['US-CA']],
    'lgpd' => ['enabled' => true,  'applies_to' => ['BR']],
],
```

`applies_to` is a list of ISO 3166-1 alpha-2 country codes (or sub-codes like `US-CA`). The `GeoLocationService` resolves the visitor's country and the regulation registry picks the matching regulation(s).

## How geolocation works

`CheckCookieConsent` (the `privacy.context` middleware) calls `GeoLocationService::detect()` on every request. The default driver resolves country from `CF-IPCountry`, then `X-Country-Code`, then a configurable callback. Override the resolver:

```php
$this->app->bind(
    \ArtisanPackUI\Privacy\Contracts\GeoLocationResolver::class,
    \App\Privacy\MaxMindResolver::class,
);
```

## Per-regulation behavior

Each regulation contributes:

- `consent_expiry_days` — used when persisting new consent rows
- `breach_notification_hours` — `BreachNotificationService` enforces this for required-disclosure breaches
- `data_requests.response_days.<key>` — sets `due_at` on new requests
- Custom UI strings (banner copy, opt-out language)

## Add a custom regulation

```php
namespace App\Privacy;

use ArtisanPackUI\Privacy\Contracts\RegulationContract;

class Pdpa implements RegulationContract
{
    public function key(): string { return 'pdpa'; }
    public function appliesTo(string $country): bool { return 'SG' === $country; }
    public function consentExpiryDays(): int { return 365; }
    public function breachNotificationHours(): int { return 72; }
    public function responseDays(string $requestType): int { return 30; }
}
```

Register it in `AppServiceProvider::boot()`:

```php
app(\ArtisanPackUI\Privacy\Regulations\RegulationRegistry::class)
    ->registerClass('pdpa', \App\Privacy\Pdpa::class);
```

## Priority

When a visitor's country matches multiple regulations, the most-protective wins (shortest deadline, longest consent expiry). The registry's `bestFor($country)` returns the resolved regulation.
