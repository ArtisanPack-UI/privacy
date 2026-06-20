# Cookie consent setup

## 1. Define categories

Categories are configured in `config/artisanpack/privacy.php` under `cookie_categories`. Every key becomes a row in `privacy_consent_categories` on `privacy:install`.

```php
'cookie_categories' => [
    'necessary'  => ['name' => 'Strictly Necessary', 'required' => true,  'cookies' => ['session', 'csrf']],
    'functional' => ['name' => 'Functional',         'required' => false, 'cookies' => ['preferences']],
    'analytics'  => ['name' => 'Analytics',          'required' => false, 'cookies' => ['_ga']],
    'marketing'  => ['name' => 'Marketing',          'required' => false, 'cookies' => ['_fbp']],
],
```

`required: true` categories are always on and cannot be opted out of (e.g. session cookies).

## 2. Mount the banner

### Livewire

```blade
<livewire:privacy-cookie-banner />
```

### React

```tsx
import { CookieBanner } from '@artisanpack-ui/privacy/react'

<CookieBanner />
```

### Vue

```vue
<script setup>
import CookieBanner from '@artisanpack-ui/privacy/vue/CookieBanner.vue'
</script>

<template><CookieBanner /></template>
```

## 3. Gate content on consent

### Blade

```blade
@hasConsent('analytics')
    <script src="https://www.googletagmanager.com/gtag/js?id=GA_ID"></script>
@endhasConsent

@consentRequired('marketing')
    <p>Marketing cookies disabled. <a href="#" @click="openPreferences">Enable</a></p>
@endconsentRequired
```

### React

```tsx
import { useConsent } from '@artisanpack-ui/privacy/react'

const { hasConsent } = useConsent()
if (hasConsent('analytics')) { /* load tracker */ }
```

### Vue

```ts
import { useConsent } from '@artisanpack-ui/privacy/vue'

const { hasConsent } = useConsent()
```

## 4. Re-open the preferences UI

Dispatch the global event from any element:

```html
<button @click="$dispatch('open-consent-preferences')">Manage cookies</button>
```

All three front-ends listen for `open-consent-preferences` and open the preferences modal/component.

## 5. Programmatic API

```php
use ArtisanPackUI\Privacy\Facades\Privacy;

Privacy::consent()->grant($user, 'analytics');
Privacy::consent()->withdraw($user, 'marketing');
Privacy::consent()->has($user, 'analytics'); // bool
```

## Storage model

The `consent.storage` config controls persistence:

- `database` — rows in `privacy_consents` only (authenticated users)
- `cookie` — encrypted JSON in `privacy_consent` cookie only (guests)
- `both` — write to both for layered persistence

`both` is the default and recommended.

## Expiry & re-consent

Consents inherit `consent_expiry_days` from the matching regulation (`gdpr` defaults to 365). The `privacy:purge-expired` scheduled command marks expired rows as withdrawn and triggers the `ConsentWithdrawn` event, which causes the banner to re-appear on the user's next visit.
