# Blade directives

## `@hasConsent('category')` … `@endhasConsent`

Renders the inner block only when the current user (or guest cookie) has granted consent for `category`.

```blade
@hasConsent('analytics')
    <script src="https://www.googletagmanager.com/gtag/js"></script>
@endhasConsent
```

## `@consentRequired('category')` … `@else` … `@endconsentRequired`

Inverse of `@hasConsent` — renders when consent is **missing**. Supports an optional `@else` branch for the consented case.

```blade
@consentRequired('marketing')
    <button @click="$dispatch('open-consent-preferences')">Enable marketing cookies</button>
@else
    <button @click="optOutMarketing">Disable</button>
@endconsentRequired
```

## Notes

- Both directives evaluate against the request's `privacy_consent` attribute (populated by the `privacy.context` middleware) when available, then fall back to the database.
- Inside a queue/CLI context, the request attribute is empty, so both directives effectively read from the database.
- The directives are pure server-side — they emit nothing client-side. For React/Vue, use the `useConsent()` hook instead.
