# Upgrade Guide

## Upgrading to 1.0 (from pre-release)

### React / Vue components are now first-class

The package now ships React and Vue equivalents of every Livewire component (cookie banner, consent preferences, data-request form, verification view, privacy dashboard, policy re-consent banner, and all admin managers). They live under `resources/js/react/` and `resources/js/vue/` and are exported as subpath imports from `@artisanpack-ui/privacy`.

If you were previously importing the root entrypoint, switch to a subpath import:

```ts
// Before
import { CookieBanner } from '@artisanpack-ui/privacy'

// After
import { CookieBanner } from '@artisanpack-ui/privacy/react'
```

### `privacy:install` does more

The install command now publishes views, runs migrations (with confirmation), seeds default consent categories, and clears caches. Re-running it is safe — seeding is skipped when categories already exist, and publishes can be re-applied with `--force`.

New flags: `--skip-views`, `--skip-seed`, `--skip-migrate`.

### New scheduled commands

- `privacy:process-requests` — processes pending verified access + export requests
- `privacy:report` — generates compliance reports in JSON or CSV, optionally emailed

Wire them in `bootstrap/app.php`:

```php
->withSchedule(function (Schedule $schedule): void {
    $schedule->command('privacy:process-requests')->daily();
    $schedule->command('privacy:report --period=month --email=dpo@example.com')
        ->monthlyOn(1, '08:00');
})
```

No breaking changes — existing installs keep working.
