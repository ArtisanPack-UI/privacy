---
title: API Reference
---

# API Reference

Programmatic surface of the Privacy package: services, models, events, helpers, Blade directives, and the JavaScript API. For end-to-end walkthroughs that use these primitives, see the [[guides]].

## Reference Pages

- [[api/services]] — `ConsentService`, `DataRequestService`, `AnonymizationService`, `GeoLocationService` (all resolved as singletons and exposed via the `Privacy` facade)
- [[api/models]] — `Consent`, `DataRequest`, `DataRequestLog`, `PersonalDataMap`, `Policy`, `BreachNotification`
- [[api/events]] — Events emitted on consent changes, request lifecycle, policy updates, and breaches
- [[api/helpers]] — Global `privacy*` helper functions registered via `src/helpers.php`
- [[api/blade-directives]] — `@hasConsent` and `@consentRequired` for conditional rendering
- [[api/javascript]] — `window.PrivacyConsent` global, `PrivacyClient`, and the React / Vue `useConsent` hook/composable

## By Concern

| If you need to… | See |
|---|---|
| Record or check consent in PHP | [[api/services]] (`ConsentService`) |
| File a data request from server code | [[api/services]] (`DataRequestService`) or [[api/helpers]] |
| Query consents or requests in Eloquent | [[api/models]] |
| React to a consent / breach / request | [[api/events]] |
| Gate Blade output on a category | [[api/blade-directives]] |
| Load a third-party tag only after opt-in | [[api/javascript]] (`whenConsented`, `loadScript`) |
| Build a React or Vue UI | [[api/javascript]] (`useConsent`) and the [[guides/react-vue]] guide |

## Conventions

- All services are container singletons. Resolve them via the `Privacy` facade (`Privacy::consent()`, `Privacy::dataRequests()`, etc.) or type-hint them in your own classes.
- All models live under `ArtisanPackUI\Privacy\Models` and use timestamps + soft-delete semantics where audit-preservation matters.
- All events live under `ArtisanPackUI\Privacy\Events` and are dispatched through Laravel's standard event system.
- All global helpers are prefixed `privacy` (e.g. `privacyHasConsent`, `privacyRequestDataExport`) to avoid collisions with other packages.
