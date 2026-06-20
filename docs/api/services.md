# Services

All services are singletons resolved from the container and exposed via the `Privacy` facade.

## ConsentService — `Privacy::consent()`

| Method | Purpose |
|---|---|
| `grant(Model $subject, string $category, ?string $regulation = null): Consent` | Record consent |
| `withdraw(Model $subject, string $category): bool` | Mark withdrawn (audit-preserving) |
| `has(Model $subject, string $category): bool` | Convenience check |
| `for(Model $subject): Collection` | All active consents for a subject |
| `byCategory(string $category): Builder` | Query across all subjects |

## DataRequestService — `Privacy::dataRequests()`

| Method | Purpose |
|---|---|
| `createAccessRequest(Model $subject, ?string $reason = null): DataRequest` | New access request |
| `createExportRequest(Model $subject, ?string $reason = null): DataRequest` | New export request |
| `createDeletionRequest(Model $subject, ?string $reason = null): DataRequest` | New deletion request |
| `createRectificationRequest(Model $subject, ?string $reason = null): DataRequest` | New rectification request |
| `canDelete(Model $subject): bool` | Defer to deletion guards |

## DataExportService — `Privacy::export()`

| Method | Purpose |
|---|---|
| `export(Model $subject, string $format = 'json'): string` | Serialized payload |
| `exportToFile(Model $subject, string $format = 'json'): array{path, url, expires_at}` | Disk + signed URL |
| `getPersonalData(Model $subject): array` | Just the column data |
| `getConsentHistory(Model $subject): array` | All consent rows incl. withdrawn |
| `getActivityLog(Model $subject): array` | Request log entries |

## DataDeletionService — `Privacy::deletion()`

| Method | Purpose |
|---|---|
| `delete(Model $subject): bool` | Hard-delete + anonymize related rows |
| `anonymize(Model $subject): bool` | Run through `AnonymizationService` only |
| `schedule(Model $subject, Carbon $when): ScheduledDeletion` | Schedule future deletion |

## AnonymizationService — `Privacy::anonymization()`

| Method | Purpose |
|---|---|
| `anonymize(Model $subject): bool` | Strip PII columns, preserve FKs |
| `register(string $modelClass, callable $callback): void` | Custom per-model strategy |

## VerificationService — `Privacy::verification()`

| Method | Purpose |
|---|---|
| `find(string $token): ?DataRequest` | Resolve a token to a request |
| `confirm(DataRequest $request): bool` | Mark verified + dispatch event |
| `manuallyVerify(DataRequest $request, int $adminId): bool` | Admin-bypass |
| `refreshToken(DataRequest $request): string` | Regenerate the token |
| `urlFor(DataRequest $request): ?string` | Build a verification URL |

## BreachNotificationService — `Privacy::breach()`

| Method | Purpose |
|---|---|
| `report(array $data): BreachNotification` | Record a breach + start clock |
| `notifyAuthority(BreachNotification $breach): bool` | Send Art. 33 notice |
| `notifyAffectedUsers(BreachNotification $breach): int` | Send Art. 34 notices |
| `deadlineFor(BreachNotification $breach): Carbon` | Resolve from regulation |

## ComplianceReportService — `Privacy::compliance()`

| Method | Purpose |
|---|---|
| `generate(Carbon $from, Carbon $to, ?string $regulation = null): array` | Full report payload |
| `consentStats(...)` | Just consent metrics |
| `requestStats(...)` | Just request metrics |
| `breachStats(Carbon $from, Carbon $to): array` | Just breach metrics |

## ReconsentService — `Privacy::reconsent()`

| Method | Purpose |
|---|---|
| `requiresReconsent(Model $subject, ?PrivacyPolicy $policy = null): bool` | Detect stale consent |
| `markConsented(Model $subject, PrivacyPolicy $policy): void` | Record re-consent |

## GeoLocationService — `Privacy::geolocation()`

| Method | Purpose |
|---|---|
| `detect(Request $request): ?string` | Resolve ISO country code |
| `regulationFor(?string $country): ?string` | Best-fit regulation key |

## PersonalDataScanner — `Privacy::scanner()`

| Method | Purpose |
|---|---|
| `scan(string $modelClass): array` | Inspect a model's columns for PII heuristics |
| `scanAll(): Collection` | Run across the configured model namespace |

## PrivacyPolicyGenerator — `Privacy::policyGenerator()`

| Method | Purpose |
|---|---|
| `generate(array $variables): string` | Render the policy template |
| `availableTemplates(): array` | List built-in templates |
