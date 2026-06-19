# ArtisanPack UI Privacy Changelog

## Unreleased

### Added

- Event/listener system (#6). Six new events — `DataAccessRequested`, `DataDeletionRequested`, `DataExportRequested`, `DataRequestCompleted`, `DataBreach`, `PrivacyPolicyUpdated` — plus five default listeners (`LogConsentActivity`, `ProcessDataAccessRequest`, `ProcessDataExportRequest`, `NotifyAdminOfRequest`, `NotifyDataBreach`) wired through `PrivacyServiceProvider`. Listener bindings are configurable by registering your own handlers in the application service provider.
- Global helper functions (#7). `privacyHasConsent()`, `privacyGetRegulation()`, `privacyRequestDataExport()`, `privacyRequestDataDeletion()`, `privacyAnonymize()`, `privacyCanDelete()` — autoloaded via Composer and delegating to the new `DataRequestService` and `AnonymizationService`.
- `CookieBanner` Livewire component (#8). Accept all / reject all / customise actions, inline preferences panel with required-category lock, dispatches `privacy:consent-updated` and `privacy:banner-closed`, supports `header`/`description`/`footer` slots and position/style props.
- `ConsentPreferences` Livewire component (#9). Per-category toggles with dirty/saved tracking, optional description and cookie-list visibility, accessible toggle controls, save action.
- Database + cookie consent storage (#10). `ConsentService::syncCookieToDatabase()` materialises guest cookie consents into database rows; `SyncConsentOnLogin` listener does it automatically on the framework `Login` event. New `resolveGuestIdentifier()` supports fingerprint (default), session, and IP strategies.
- JSON API. `GET /api/privacy/consent`, `POST /api/privacy/consent` (single or bulk), `GET /api/privacy/categories`. `UpdateConsentRequest` performs cross-field validation. Default middleware is `web` so session and cookies remain available for stateful applications.
- Vanilla JS client (`@artisanpack-ui/privacy`). `PrivacyClient` with `load`/`getState`/`hasConsent`/`setConsent`/`setConsents`/`onChange`, plus a `window.ArtisanPackPrivacy` singleton install.
- React subpath (`@artisanpack-ui/privacy/react`). `useConsent` hook, `CookieBanner` and `ConsentPreferences` components backed by the JSON API.
- Vue subpath (`@artisanpack-ui/privacy/vue`). `useConsent` composable, `CookieBanner` and `ConsentPreferences` single-file components backed by the JSON API.
- `package.json` and `tsconfig.json` declaring `.`, `./react`, `./vue` subpath exports with React/Vue as optional peer dependencies.
- Laravel 13 support. The `illuminate/support` constraint now allows `^13.0` alongside the existing `^10.0|^11.0|^12.0` range. Laravel 13 requires PHP 8.3+; the PHP floor for users staying on older Laravel versions is unchanged.

### Changed

- `livewire/livewire ^3.5` is now a required dependency (the package ships Livewire components).
