---
title: Privacy Documentation
---

# Privacy Documentation

Welcome to the ArtisanPack UI Privacy documentation! This Laravel package provides a comprehensive privacy and data-protection toolkit — cookie consent, data subject rights, breach notification, multi-regulation compliance, and a built-in admin dashboard — with Livewire, React, **and** Vue front-ends.

## Overview

The Privacy package helps Laravel applications comply with modern data protection regulations. It provides:

- **Cookie Consent**: Banner + preferences UI in Livewire, React, and Vue with database-backed audit trail
- **Data Subject Rights**: Access, export, deletion, and rectification workflows — verified, audited, optionally auto-processed
- **Multi-Regulation Engine**: First-class GDPR and CCPA implementations; LGPD and PIPEDA toggles
- **Admin Dashboard**: Consent manager, request manager, compliance reports, breach manager, policy manager
- **Breach Notification**: GDPR Article 33/34 authority + user notification templates with a 72-hour window
- **Policy Management + Re-Consent**: Version policies and re-prompt visitors when terms change
- **Artisan Tooling**: `privacy:install`, `privacy:scan`, `privacy:purge-expired`, `privacy:process-requests`, `privacy:report`
- **Event-Driven**: Every consent change, request, and breach emits an event you can listen for
- **JSON API**: Stateless endpoints behind the same logic as the Livewire components
- **JavaScript API**: `window.PrivacyConsent` global plus React/Vue subpath exports

---

## Getting Started

- [Installation Guide](../INSTALLATION.md) — Setup and `privacy:install` walkthrough
- [Configuration](../CONFIGURATION.md) — Every option in `config/artisanpack/privacy.php`
- [Upgrading](../UPGRADING.md) — Version-to-version upgrade notes

---

## Guides

End-to-end walkthroughs for each major feature of the package.

- [[guides]] — Overview of the guides section
- [[guides/cookie-consent]] — Define categories, mount the banner, persist consent
- [[guides/data-subject-rights]] — Wire up access / export / deletion / rectification requests
- [[guides/multi-regulation]] — GDPR, CCPA, LGPD, PIPEDA and the regulation registry
- [[guides/admin-dashboard]] — The five admin tools, gate, and route mounting
- [[guides/view-customization]] — Override Livewire, React, and Vue components and templates
- [[guides/react-vue]] — Drop-in React and Vue surfaces that share the JSON API

---

## API Reference

- [[api]] — Overview of the API reference
- [[api/services]] — `ConsentService`, `DataRequestService`, `AnonymizationService`, `GeoLocationService`
- [[api/models]] — `Consent`, `DataRequest`, `DataRequestLog`, `PersonalDataMap`, `Policy`, `BreachNotification`
- [[api/events]] — Every event fired by the package and what to listen for
- [[api/helpers]] — Global `privacy*` helper functions
- [[api/blade-directives]] — `@hasConsent`, `@consentRequired`
- [[api/javascript]] — `window.PrivacyConsent`, `useConsent`, `PrivacyClient`

---

## Module Quick Reference

| Area | Purpose | Status |
|------|---------|--------|
| Cookie Consent | Banner + preferences across Livewire, React, Vue | Stable |
| Data Subject Rights | Access, export, deletion, rectification | Stable |
| Regulations | GDPR, CCPA built-in; LGPD, PIPEDA opt-in | Stable |
| Admin Dashboard | Consents, requests, breaches, policies, reports | Stable |
| Breach Notification | Article 33/34 authority + user templates | Stable |
| Policy Management | Versioned policies + re-consent flow | Stable |
| Artisan Commands | Install, scan, purge, process, report | Stable |

---

## Artisan Commands

| Command | Purpose |
|---------|---------|
| `privacy:install` | Publish config, migrations, views, breach templates; run migrations; seed categories |
| `privacy:scan` | Scan the codebase for personal-data sources and update the personal-data map |
| `privacy:purge-expired` | Soft-withdraw expired consents (scheduled daily at 03:00) |
| `privacy:process-requests` | Auto-process pending verified access and export requests |
| `privacy:report` | Generate consent / request / breach compliance reports (JSON or CSV) |

---

## Configuration

The package is configured through `config/artisanpack/privacy.php`:

```php
// config/artisanpack/privacy.php
return [
    'enabled_regulations' => ['gdpr', 'ccpa'],
    'cookie_categories' => [
        'necessary'  => ['name' => 'Strictly Necessary', 'required' => true],
        'functional' => ['name' => 'Functional', 'required' => false],
        'analytics'  => ['name' => 'Analytics', 'required' => false],
        'marketing'  => ['name' => 'Marketing', 'required' => false],
    ],
    // …
];
```

See [Configuration](../CONFIGURATION.md) for every option.

---

## Support

For issues, feature requests, and contributions:

- **GitHub**: https://github.com/ArtisanPack-UI/privacy
- **Documentation**: https://artisanpack.dev/packages/privacy

---

*This documentation covers Privacy v1.0.0*
