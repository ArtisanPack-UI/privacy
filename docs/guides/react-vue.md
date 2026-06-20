# React / Vue integration

Every Livewire component in this package has a React **and** Vue equivalent that calls the same JSON API. Pick the front-end your app uses — the back-end behavior (rate limiting, validation, verification, deadlines) is identical.

## Installation

The components ship inside the Composer package. Add an alias to your bundler so JavaScript imports resolve into `vendor/`:

### Vite

```js
// vite.config.js
import { resolve } from 'path'

export default {
    resolve: {
        alias: {
            '@artisanpack-ui/privacy': resolve(
                __dirname,
                'vendor/artisanpack-ui/privacy/resources/js',
            ),
        },
    },
}
```

> The published npm package mirrors this directory structure, so once it's released to npm you can switch to `npm install @artisanpack-ui/privacy` and drop the alias.

## Subpath imports

Always import via a subpath — the root import is intentionally not provided so optional peer deps (react-apexcharts in the admin charts, for example) don't break test runs.

```ts
// React
import { CookieBanner, ConsentPreferences, useConsent } from '@artisanpack-ui/privacy/react'
import { ConsentManager, ComplianceReport } from '@artisanpack-ui/privacy/react/admin'

// Vue
import CookieBanner from '@artisanpack-ui/privacy/vue/CookieBanner.vue'
import ConsentManager from '@artisanpack-ui/privacy/vue/admin/ConsentManager.vue'
import { useConsent } from '@artisanpack-ui/privacy/vue'
```

## Component API parity

| Livewire | React | Vue |
|---|---|---|
| `<livewire:privacy-cookie-banner />` | `<CookieBanner />` | `<CookieBanner />` |
| `<livewire:privacy-consent-preferences />` | `<ConsentPreferences />` | `<ConsentPreferences />` |
| `<livewire:privacy-data-request-form />` | `<DataRequestForm />` | `<DataRequestForm />` |
| `<livewire:privacy-verify-data-request />` | `<VerifyDataRequest />` | `<VerifyDataRequest />` |
| `<livewire:privacy-dashboard />` | `<PrivacyDashboard />` | `<PrivacyDashboard />` |
| `<livewire:privacy-policy-reconsent-banner />` | `<PolicyReconsentBanner />` | `<PolicyReconsentBanner />` |
| `<livewire:privacy-admin-consent-manager />` | `<ConsentManager />` (admin) | `<ConsentManager />` (admin) |
| `<livewire:privacy-admin-data-request-manager />` | `<DataRequestManager />` (admin) | `<DataRequestManager />` (admin) |
| `<livewire:privacy-admin-compliance-report />` | `<ComplianceReport />` (admin) | `<ComplianceReport />` (admin) |
| `<livewire:privacy-admin-breach-manager />` | `<BreachManager />` (admin) | `<BreachManager />` (admin) |
| `<livewire:privacy-admin-breach-detail />` | `<BreachDetail />` (admin) | `<BreachDetail />` (admin) |
| `<livewire:privacy-admin-breach-report-form />` | `<BreachReportForm />` (admin) | `<BreachReportForm />` (admin) |

## The `useConsent` hook / composable

Both front-ends ship a `useConsent()` hook backed by the JSON API. It returns:

```ts
{
    consent: Record<string, boolean>,    // current consent map
    hasConsent: (category: string) => boolean,
    grant: (category: string) => Promise<void>,
    withdraw: (category: string) => Promise<void>,
    refresh: () => Promise<void>,
}
```

Listen for the cross-cutting `open-consent-preferences` DOM event to open the preferences modal from anywhere.

## CSRF + authentication

The components include the standard Laravel CSRF token header automatically (`X-CSRF-TOKEN` from the `<meta>` tag injected by Blade's `@csrf`). For SPAs running off-domain, use Sanctum and configure `axios.defaults.withCredentials = true` before the components mount.
