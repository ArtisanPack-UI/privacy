# Models

All models live under `ArtisanPackUI\Privacy\Models`.

## Consent

Stored in `privacy_consents`. Tied to a subject via polymorphic `(consentable_type, consentable_id)`.

| Column | Type | Purpose |
|---|---|---|
| `consentable_type` / `consentable_id` | morph | Subject of the consent |
| `category` | string | Category key (`analytics`, `marketing`, …) |
| `granted` | bool | Current state |
| `regulation` | string\|null | Regulation key when known |
| `expires_at` | datetime\|null | Auto-withdraw threshold |
| `withdrawn_at` | datetime\|null | Soft-withdrawal timestamp |

Scopes: `active()`, `expired()`, `byCategory(string)`, `byRegulation(string)`.

## ConsentCategory

Stored in `privacy_consent_categories`. Seeded from `cookie_categories` config on `privacy:install`.

| Column | Type |
|---|---|
| `key`, `name`, `description` | string |
| `required` | bool |
| `sort_order` | int |
| `active` | bool |
| `regulations` | json |

## DataRequest

Stored in `privacy_data_requests`. Polymorphic via `(requestable_type, requestable_id)`.

Constants:

- `STATUS_PENDING`, `STATUS_PROCESSING`, `STATUS_COMPLETED`, `STATUS_REJECTED`
- `TYPE_ACCESS`, `TYPE_EXPORT`, `TYPE_DELETION`, `TYPE_RECTIFICATION`

Scopes: `pending()`, `processing()`, `completed()`, `rejected()`, `overdue()`, `active()`.

Relations: `requestable()` (MorphTo), `logs()` (HasMany `DataRequestLog`).

## DataRequestLog

Stored in `privacy_data_request_logs`. Append-only audit trail.

| Column |
|---|
| `data_request_id`, `action`, `description`, `metadata` (json), `performed_by` |

## PersonalDataMap

Stored in `privacy_personal_data_maps`. Populated by `privacy:scan` — one row per (model_class, column) that the scanner identified as PII.

## PrivacyPolicy

Stored in `privacy_policies`. Versioned policy content; the latest published row is what `PolicyReconsentBanner` compares against.

## BreachNotification

Stored in `privacy_breach_notifications`. One row per reported breach with severity, scope, affected user list, and authority/user notification timestamps.

## ScheduledDeletion

Stored in `privacy_scheduled_deletions`. Used by `DataDeletionService::schedule()` for future-dated deletions.
