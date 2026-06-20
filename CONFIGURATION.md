# Configuration

All options live in `config/artisanpack/privacy.php` (publish it via `php artisan privacy:install` or `vendor:publish --tag=privacy-config`).

## Top-level

| Key | Default | Purpose |
|---|---|---|
| `enabled` | `env('PRIVACY_ENABLED', true)` | Master switch. Disabling skips migrations, routes, middleware, and Livewire registration. |

## Regulations

Toggle which regulations apply. Disabled regulations are excluded from the regulation registry and from `due_at` deadline math.

```php
'regulations' => [
    'gdpr' => [
        'enabled'                   => true,
        'applies_to'                => ['EU', 'EEA', 'UK'],
        'consent_expiry_days'       => 365,
        'breach_notification_hours' => 72,
    ],
    'ccpa' => [
        'enabled'             => true,
        'applies_to'          => ['US-CA'],
        'opt_out_sale'        => true,
        'financial_threshold' => 25000000,
    ],
    'lgpd'   => ['enabled' => false, 'applies_to' => ['BR']],
    'pipeda' => ['enabled' => false, 'applies_to' => ['CA']],
],
```

## Consent

| Key | Default | Purpose |
|---|---|---|
| `consent.storage` | `both` | `database`, `cookie`, or `both` |
| `consent.cookie_name` | `privacy_consent` | Cookie used for guest persistence |
| `consent.cookie_lifetime` | `365` | Days |
| `consent.require_authentication` | `false` | If true, guests cannot record consent |
| `consent.guest_identifier` | `fingerprint` | `fingerprint`, `ip`, or `session` |

## Cookie categories

These power the consent banner UI **and** seed `privacy_consent_categories` rows on `privacy:install`.

```php
'cookie_categories' => [
    'necessary'  => ['name' => 'Strictly Necessary', 'required' => true, ...],
    'functional' => ['name' => 'Functional', 'required' => false, ...],
    'analytics'  => ['name' => 'Analytics', 'required' => false, ...],
    'marketing'  => ['name' => 'Marketing', 'required' => false, ...],
],
```

## Data subject requests

| Key | Default | Purpose |
|---|---|---|
| `data_requests.enabled` | `true` | Master switch for the request workflow |
| `data_requests.require_verification` | `true` | Force email-token verification before any processing |
| `data_requests.verification_method` | `email` | `email` or `manual` |
| `data_requests.allowed_types` | `[access, export, deletion, rectification]` | Removing a type disables it in the API + UI |
| `data_requests.auto_process.access` | `true` | Auto-mark verified access requests as processing |
| `data_requests.auto_process.export` | `true` | Auto-generate the export file on verification |
| `data_requests.auto_process.deletion` | `false` | **Never** auto-process — requires manual review |
| `data_requests.response_days` | `{gdpr: 30, ccpa: 45, default: 30}` | Sets `due_at` |
| `data_requests.export_format` | `json` | `json`, `csv`, or `xml` |
| `data_requests.notify_admin` | `true` | Email the configured admin on new requests |
| `data_requests.admin_email` | `env('PRIVACY_ADMIN_EMAIL')` | Where admin notifications go |
| `data_requests.api_rate_limit` | `60,1` | `hits,minutes` per user |

## Admin dashboard

| Key | Default | Purpose |
|---|---|---|
| `admin.enabled` | `true` | Mount the admin routes |
| `admin.gate` | `manage-privacy` | Gate that protects every admin route |
| `admin.route_prefix` | `admin/privacy` | URL prefix |
| `admin.middleware` | `['web', 'auth']` | Middleware stack |
| `admin.api_rate_limit` | `120,1` | `hits,minutes` per user |

## Routes

| Key | Default | Purpose |
|---|---|---|
| `routes.enabled` | `true` | Register web + API routes |
| `routes.prefix` | `privacy` | Web prefix |
| `routes.api_prefix` | `api/privacy` | JSON API prefix |
| `routes.middleware` | `['web']` | Web middleware |
| `routes.api_middleware` | `['api']` | API middleware |

## Verification

| Key | Default | Purpose |
|---|---|---|
| `verification.rate_limit` | `6,1` | `hits,minutes` per IP — verification endpoint |
| `verification.token_lifetime` | `60` | Minutes |

## Export

| Key | Default | Purpose |
|---|---|---|
| `export.disk` | `local` | Filesystem disk for generated export files |
| `export.directory` | `privacy-exports` | Subdirectory on the disk |
| `export.url_ttl` | `60` | Minutes that signed download URLs stay valid |
| `export.file_retention_minutes` | `1440` | How long the file lives on disk (24h default) |
| `export.row_cap` | `100000` | Hard cap on rows in collection exports |

## Scheduling

| Key | Default | Purpose |
|---|---|---|
| `scheduling.purge_expired.enabled` | `true` | Auto-register the daily purge job |
| `scheduling.purge_expired.cron` | `0 3 * * *` | Override the cron expression |
| `scheduling.purge_expired.prune` | `false` | Delete expired rows instead of withdrawing |
| `scheduling.purge_expired.timezone` | `null` | Run in a specific timezone |

## Environment variables

| Variable | Maps to |
|---|---|
| `PRIVACY_ENABLED` | `enabled` |
| `PRIVACY_GDPR_ENABLED` / `PRIVACY_CCPA_ENABLED` / `PRIVACY_LGPD_ENABLED` / `PRIVACY_PIPEDA_ENABLED` | per-regulation toggle |
| `PRIVACY_CONSENT_STORAGE` | `consent.storage` |
| `PRIVACY_CONSENT_COOKIE` | `consent.cookie_name` |
| `PRIVACY_CONSENT_COOKIE_LIFETIME` | `consent.cookie_lifetime` |
| `PRIVACY_VERIFICATION_METHOD` | `data_requests.verification_method` |
| `PRIVACY_EXPORT_FORMAT` | `data_requests.export_format` |
| `PRIVACY_ADMIN_EMAIL` | `data_requests.admin_email` |
