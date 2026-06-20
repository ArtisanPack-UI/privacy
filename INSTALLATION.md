# Installation

This guide walks through a full install of `artisanpack-ui/privacy` for a Laravel 11+ application using Livewire, React, or Vue.

## 1. Composer

```bash
composer require artisanpack-ui/privacy
```

The package's service provider is auto-discovered.

## 2. Run the install command

```bash
php artisan privacy:install
```

This:

1. Publishes `config/artisanpack/privacy.php`
2. Publishes the package migrations to `database/migrations/`
3. Publishes the package views to `resources/views/vendor/artisanpack-ui/privacy/`
4. Publishes breach-notification templates to `resources/views/vendor/artisanpack-ui/privacy/breach-templates/`
5. Publishes the admin layout view
6. Runs `php artisan migrate` (with confirmation)
7. Seeds default consent categories from `cookie_categories` config
8. Clears the config + view caches
9. Prints the admin gate stub for `AuthServiceProvider`
10. Prints next steps

### Options

| Option | Effect |
|---|---|
| `--force` | Overwrite already-published files |
| `--skip-migrations` | Don't publish or run migrations |
| `--skip-migrate` | Publish migrations but skip `migrate` |
| `--skip-views` | Don't publish views |
| `--skip-templates` | Don't publish breach templates |
| `--skip-admin-layout` | Don't publish the admin layout |
| `--skip-seed` | Don't seed default consent categories |
| `--no-interaction` | Skip the migrate confirmation (CI/CD) |

## 3. Add the admin gate

Copy the printed stub into your `App\Providers\AuthServiceProvider::boot()`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('manage-privacy', function ($user) {
    return method_exists($user, 'hasRole') && $user->hasRole('admin');
});
```

Override the gate name via `config('artisanpack.privacy.admin.gate')`.

## 4. Add `HasPersonalData` to your User model

```php
use ArtisanPackUI\Privacy\Concerns\HasPersonalData;

class User extends Authenticatable
{
    use HasPersonalData;

    protected array $personalDataColumns = ['name', 'email', 'phone'];
}
```

## 5. Mount the UI

### Livewire

```blade
<livewire:privacy-cookie-banner />
<livewire:privacy-policy-reconsent-banner />
```

### React

```tsx
import { CookieBanner, PolicyReconsentBanner } from '@artisanpack-ui/privacy/react'

export default function Layout({ children }) {
    return (
        <>
            {children}
            <CookieBanner />
            <PolicyReconsentBanner />
        </>
    )
}
```

### Vue

```vue
<script setup>
import CookieBanner from '@artisanpack-ui/privacy/vue/CookieBanner.vue'
import PolicyReconsentBanner from '@artisanpack-ui/privacy/vue/PolicyReconsentBanner.vue'
</script>

<template>
    <slot />
    <CookieBanner />
    <PolicyReconsentBanner />
</template>
```

## 6. Schedule the recurring commands

In `bootstrap/app.php`:

```php
->withSchedule(function (Schedule $schedule): void {
    $schedule->command('privacy:purge-expired')->daily();
    $schedule->command('privacy:process-requests')->daily();
    $schedule->command('privacy:report --period=month --email=dpo@example.com')
        ->monthlyOn(1, '08:00');
})
```

## 7. (Optional) Customize views

```bash
php artisan vendor:publish --tag=privacy-views
```

Then edit the files under `resources/views/vendor/artisanpack-ui/privacy/`.

## 8. (Optional) Customize breach templates

```bash
php artisan vendor:publish --tag=privacy-breach-templates
```

The templates live at `resources/views/vendor/artisanpack-ui/privacy/breach-templates/`.

## 9. Verify

```bash
php artisan privacy:scan        # discover personal-data columns
php artisan privacy:report      # generate today's compliance report to stdout
```

If both commands succeed, the package is wired correctly.

## Upgrading

See [UPGRADING.md](UPGRADING.md).
