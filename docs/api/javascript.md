# JavaScript API

The package exposes a vanilla browser API on `window.privacy` for non-React/Vue projects, plus a `useConsent()` hook/composable for React and Vue.

## `window.privacy` (vanilla)

Loaded by `resources/js/window-privacy-consent.ts`.

```ts
window.privacy.has('analytics')              // boolean
window.privacy.grant('analytics')            // Promise<void>
window.privacy.withdraw('marketing')         // Promise<void>
window.privacy.all()                         // Record<string, boolean>
window.privacy.openPreferences()             // open the preferences UI
window.privacy.refresh()                     // re-pull from /api/privacy/consent
```

All methods round-trip through the JSON API so server state stays authoritative.

## DOM events

Dispatched on `document`:

| Event | Detail | When |
|---|---|---|
| `privacy:consent-changed` | `{ category, granted }` | Any grant/withdraw |
| `privacy:banner-shown` | `null` | Banner mounts |
| `privacy:banner-dismissed` | `null` | User accepts or rejects |
| `privacy:preferences-opened` | `null` | Preferences modal opens |
| `privacy:policy-reconsent-required` | `{ policy }` | Stale policy detected |

Open the preferences modal from anywhere:

```js
document.dispatchEvent(new CustomEvent('open-consent-preferences'))
```

## React: `useConsent`

```tsx
import { useConsent } from '@artisanpack-ui/privacy/react'

function AnalyticsTracker() {
    const { hasConsent, grant } = useConsent()

    if (!hasConsent('analytics')) return null
    return <script src="/analytics.js" />
}
```

Returns:

```ts
{
    consent: Record<string, boolean>,
    hasConsent: (category: string) => boolean,
    grant: (category: string) => Promise<void>,
    withdraw: (category: string) => Promise<void>,
    refresh: () => Promise<void>,
    loading: boolean,
}
```

Subscribes to `privacy:consent-changed` and re-renders automatically.

## Vue: `useConsent`

```ts
import { useConsent } from '@artisanpack-ui/privacy/vue'

const { consent, hasConsent, grant, withdraw } = useConsent()
```

The returned values are Vue refs, so they're reactive in templates without any extra wiring.

## REST endpoints

The hook/composable wraps these endpoints — useful if you're building your own UI:

| Method + path | Purpose |
|---|---|
| `GET /api/privacy/consent` | Current consent map |
| `POST /api/privacy/consent/grant` | `{ category }` |
| `POST /api/privacy/consent/withdraw` | `{ category }` |
| `GET /api/privacy/data-requests` | List the user's requests |
| `POST /api/privacy/data-requests` | `{ type, reason? }` |
| `POST /api/privacy/data-requests/{id}/verify` | `{ token }` |

All endpoints require a session (or Sanctum) and are rate-limited per `data_requests.api_rate_limit`.
