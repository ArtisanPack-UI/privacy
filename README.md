# ArtisanPack UI Privacy

[![CI](https://github.com/ArtisanPack-UI/privacy/actions/workflows/ci.yml/badge.svg)](https://github.com/ArtisanPack-UI/privacy/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/badge/coverage-80%25%2B-brightgreen.svg)](https://github.com/ArtisanPack-UI/privacy/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A comprehensive privacy and data-protection package for Laravel applications. Cookie consent, data subject rights (access / export / deletion / rectification), breach notification, multi-regulation support (GDPR, CCPA, LGPD, PIPEDA), and a built-in admin dashboard — with Livewire **and** React **and** Vue front-ends.

## Highlights

- 🍪 **Cookie consent banner + preferences UI** in Livewire, React, and Vue
- 📋 **Data subject rights** workflow — verified, audited, optionally auto-processed
- 🌍 **Multi-regulation engine** — GDPR, CCPA out of the box; LGPD and PIPEDA toggles
- 📊 **Admin dashboard** — consent manager, request manager, compliance reports, breach manager
- 🛡 **Breach notification** — GDPR Article 33/34 authority + user templates
- 🧰 **Artisan tooling** — `privacy:install`, `privacy:scan`, `privacy:purge-expired`, `privacy:process-requests`, `privacy:report`
- ♻️ **Policy management + re-consent** flow when terms change
- 🔌 **Event-driven** — every consent change, request, and breach emits an event you can listen for

## Requirements

- PHP 8.2+ for Laravel 10, 11, or 12
- PHP 8.3+ for Laravel 13
- Livewire 3 (optional — only required if you use the Livewire components)

## Installation

```bash
composer require artisanpack-ui/privacy
php artisan privacy:install
```

The install command publishes config, migrations, views, breach-notification templates, and the admin layout; runs migrations; seeds default consent categories; clears caches; and prints the admin gate stub plus next steps.

### Non-interactive install (CI/CD)

```bash
php artisan privacy:install --no-interaction --force
```

### React / Vue projects

Components live in the package and are importable through subpath imports:

```ts
// React
import { CookieBanner, ConsentPreferences } from '@artisanpack-ui/privacy/react'
import { ComplianceReport } from '@artisanpack-ui/privacy/react/admin'

// Vue
import CookieBanner from '@artisanpack-ui/privacy/vue/CookieBanner.vue'
```

The React/Vue components hit the same JSON API the Livewire components do, so back-end behavior (rate limits, validation, verification) is identical regardless of the chosen front-end.

See [INSTALLATION.md](INSTALLATION.md) for the full setup walkthrough.

## Quick start

```blade
{{-- Drop the cookie banner into your main layout --}}
<livewire:privacy-cookie-banner />

{{-- Render the privacy dashboard for the authenticated user --}}
<livewire:privacy-dashboard />

{{-- Gate content on consent --}}
@hasConsent('analytics')
    <script src="https://analytics.example.com/tracker.js"></script>
@endhasConsent
```

```php
use ArtisanPackUI\Privacy\Facades\Privacy;

// Record consent programmatically
Privacy::consent()->grant($user, 'analytics');

// Submit a data subject request on the user's behalf
Privacy::dataRequests()->createExportRequest($user);
```

## Documentation

- [INSTALLATION.md](INSTALLATION.md) — full setup including queue/scheduler wiring
- [CONFIGURATION.md](CONFIGURATION.md) — every config key explained
- [docs/](docs/) — feature guides and API reference

### Feature guides

- [Cookie consent setup](docs/guides/cookie-consent.md)
- [Data subject rights](docs/guides/data-subject-rights.md)
- [Admin dashboard customization](docs/guides/admin-dashboard.md)
- [Multi-regulation setup](docs/guides/multi-regulation.md)
- [View customization](docs/guides/view-customization.md)
- [React / Vue integration](docs/guides/react-vue.md)

### API reference

- [Services](docs/api/services.md)
- [Models](docs/api/models.md)
- [Events](docs/api/events.md)
- [Helpers](docs/api/helpers.md)
- [Blade directives](docs/api/blade-directives.md)
- [JavaScript API](docs/api/javascript.md)

## Artisan commands

| Command | Purpose |
|---|---|
| `privacy:install` | Publish assets, migrate, seed default categories, print gate stub |
| `privacy:scan` | Discover personal-data columns in your models |
| `privacy:purge-expired` | Withdraw or prune expired consents |
| `privacy:process-requests` | Auto-process pending access + export requests |
| `privacy:report` | Generate consent / request / breach compliance reports |

Schedule the recurring commands in `bootstrap/app.php` (Laravel 11+):

```php
->withSchedule(function (Schedule $schedule): void {
    $schedule->command('privacy:purge-expired')->daily();
    $schedule->command('privacy:process-requests')->daily();
    $schedule->command('privacy:report --period=month --email=dpo@example.com')
        ->monthlyOn(1, '08:00');
})
```

## Testing

```bash
composer test                                # full suite
./vendor/bin/pest tests/Feature/Console      # one suite
./vendor/bin/pest --filter=cookie            # one test
```

The package ships with 400+ Pest tests covering all services, models, Livewire components, Artisan commands, middleware, events, and listeners.

## Hooks

The package fires the following canonical hooks (`ap.privacy.*` camelCase, per the cross-package hooks convention):

| Hook | Type |
|---|---|
| `ap.privacy.exportData` | filter |
| `ap.privacy.exportFormats` | filter |
| `ap.privacy.formatExport` | filter |
| `ap.privacy.deleteData` | action |
| `ap.privacy.anonymizeData` | filter |
| `ap.privacy.hasData` | filter |
| `ap.privacy.dataSummary` | filter |
| `ap.privacy.consentGranted` | action |
| `ap.privacy.consentRevoked` | action |
| `ap.privacy.consentStatus` | filter |
| `ap.privacy.consentCategories` | filter |

### Deprecated Hooks

The following legacy hook names remain registered as backwards-compatibility aliases via `HookDeprecations`. Subscribers to any of the old names continue to fire — each emits an info-level log the first time it resolves per request. **Alias removal is deferred to the next major.**

| Deprecated | Canonical |
|---|---|
| `privacy.export.data` | `ap.privacy.exportData` |
| `privacy.export-data` | `ap.privacy.exportData` |
| `privacy.export-formats` | `ap.privacy.exportFormats` |
| `privacy.export-format` | `ap.privacy.formatExport` |
| `privacy.delete-data` | `ap.privacy.deleteData` |
| `privacy.anonymize-data` | `ap.privacy.anonymizeData` |
| `privacy.has-data` | `ap.privacy.hasData` |
| `privacy.data-summary` | `ap.privacy.dataSummary` |
| `privacy.consent-granted` | `ap.privacy.consentGranted` |
| `privacy.consent-revoked` | `ap.privacy.consentRevoked` |
| `privacy.consent-status` | `ap.privacy.consentStatus` |
| `privacy.consent-categories` | `ap.privacy.consentCategories` |

## Upgrading

See [UPGRADING.md](UPGRADING.md) for version-to-version migration notes.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## Contributing

As an open source project, this package is open to contributions from anyone. Please [read through the contributing guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.

## License

MIT — see [LICENSE](LICENSE).
