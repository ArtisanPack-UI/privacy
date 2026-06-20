# Helper functions

Global helpers registered by `src/helpers.php`.

| Function | Purpose |
|---|---|
| `privacy(): Privacy` | The package's facade target / singleton |
| `privacyHasConsent(string $category, ?Model $user = null): bool` | Check consent for the current or given user |
| `privacyGetRegulation(): ?string` | The active regulation key for the current request |
| `privacyRegulations(): RegulationRegistry` | The full registry |
| `privacyApplicableRegulation(?Request $request = null): ?PrivacyRegulation` | Best-fit regulation object |
| `privacyRequestDataExport(Model $user, ?string $reason = null): DataRequest` | Shortcut for the export flow |
| `privacyRequestDataDeletion(Model $user, ?string $reason = null): DataRequest` | Shortcut for the deletion flow |
| `privacyAnonymize(Model $model): bool` | Run anonymization on any Eloquent model |
| `privacyCanDelete(Model $user): bool` | Defer to deletion guards |

All helpers are wrapped in `function_exists()` guards, so they're safe to redeclare in your application if you need to override behavior.
