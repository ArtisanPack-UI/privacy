# Events

Listen for these in `EventServiceProvider` (or attach a closure in a service provider's `boot()` method) to extend behavior without editing the package.

## Consent

| Event | Payload | When fired |
|---|---|---|
| `ConsentGiven` | `Consent $consent` | Any time `ConsentService::grant()` succeeds |
| `ConsentWithdrawn` | `Consent $consent` | Any time `ConsentService::withdraw()` succeeds or a consent expires via `privacy:purge-expired` |

## Data subject requests

| Event | Payload | When fired |
|---|---|---|
| `DataAccessRequested` | `DataRequest $request` | Verified access request |
| `DataExportRequested` | `DataRequest $request` | Verified export request |
| `DataDeletionRequested` | `DataRequest $request` | Verified deletion request |
| `DataRectificationRequested` | `DataRequest $request` | Verified rectification request |

## Breach

| Event | Payload | When fired |
|---|---|---|
| `DataBreach` | `BreachNotification $breach` | `BreachNotificationService::report()` |

## Policy

| Event | Payload | When fired |
|---|---|---|
| `PolicyReconsentRequired` | `PrivacyPolicy $policy, Model $subject` | On render of `PolicyReconsentBanner` when subject's recorded version is stale |
| `PolicyReconsentGiven` | `PrivacyPolicy $policy, Model $subject` | When the subject re-consents |

## Bundled listeners

Wired by default in `PrivacyServiceProvider::$listen`:

| Listener | Effect |
|---|---|
| `LogConsentActivity` | Writes audit-log row on grant + withdrawal |
| `ProcessDataAccessRequest` | Auto-marks access requests as processing |
| `ProcessDataExportRequest` | Auto-generates the export file |
| `NotifyAdminOfRequest` | Sends `AdminDataRequestNotification` |
| `NotifyDataBreach` | Sends authority + user breach notifications |
| `LogPolicyReconsent` | Audit trail for policy re-consent |
| `SyncConsentOnLogin` | Promotes guest cookie consents to DB rows on login |

## Disabling a built-in listener

Override `PrivacyServiceProvider::$listen` by subclassing and registering your subclass in `bootstrap/providers.php`, or unsubscribe at runtime:

```php
Event::forget(\ArtisanPackUI\Privacy\Events\ConsentGiven::class);
```

(Forgetting clears **all** listeners — re-attach the ones you want to keep.)
