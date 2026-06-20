# Data subject rights

The package supports four rights out of the box: **access**, **export**, **deletion**, **rectification**. Disable any of them via `data_requests.allowed_types`.

## End-user flow

1. User submits a request via the `<DataRequestForm />` / `<livewire:privacy-data-request-form />` component
2. The package emails a verification link (when `require_verification = true`)
3. User clicks the link → `VerifyDataRequest` confirms the request and dispatches the `DataAccessRequested` / `DataExportRequested` / `DataDeletionRequested` / `DataRectificationRequested` event
4. Listeners auto-process (access + export) or notify the admin (deletion + rectification)
5. On completion, `DataRequestCompleted` is sent with a download URL when applicable

## Auto-processing

Configure per-type in `config/artisanpack/privacy.php`:

```php
'data_requests' => [
    'auto_process' => [
        'access'   => true,   // mark verified access requests as processing
        'export'   => true,   // generate the export file and email a download link
        'deletion' => false,  // ALWAYS manual — never auto-delete
    ],
],
```

Even with auto-processing on, the package still runs a scheduled sweep to catch any requests that slipped past the listeners (e.g. the queue worker was down):

```bash
php artisan privacy:process-requests
```

## Deadlines

`due_at` is set from `data_requests.response_days` keyed by regulation (`gdpr` defaults to 30 days; `ccpa` to 45). The compliance report tracks deadline compliance — see `privacy:report`.

## Programmatic API

```php
use ArtisanPackUI\Privacy\Facades\Privacy;

$request = Privacy::dataRequests()->createAccessRequest($user, 'Reviewing my data');
$request = Privacy::dataRequests()->createExportRequest($user);
$request = Privacy::dataRequests()->createDeletionRequest($user);
$request = Privacy::dataRequests()->createRectificationRequest($user);
```

## Deletion strategy

`Privacy::deletion()` proxies the `DataDeletionService`. By default it anonymizes via `AnonymizationService` (preserves referential integrity) rather than hard-deleting. Override on a per-model basis with a `deletePersonalData()` method on the model.

## Audit log

Every state change writes a row to `privacy_data_request_logs` (`pending → processing → completed`, plus `auto_processed` for the scheduler). The admin's `DataRequestManager` surfaces this log inline.
