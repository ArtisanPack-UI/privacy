# Admin dashboard customization

The admin dashboard is mounted at `admin/privacy` (configurable) and is protected by the `manage-privacy` gate (configurable). It includes five tools:

| Component | Route | Purpose |
|---|---|---|
| `ConsentManager` | `/admin/privacy/consents` | Filter, search, and revoke consents |
| `DataRequestManager` | `/admin/privacy/requests` | Review, approve, reject, or process requests |
| `ComplianceReport` | `/admin/privacy/reports` | On-screen reports with filtering |
| `BreachManager` | `/admin/privacy/breaches` | List + filter breaches |
| `BreachDetail` / `BreachReportForm` | `/admin/privacy/breaches/{id}` and `/new` | Manage individual breach records |

## Authorize the dashboard

Define the gate in `AuthServiceProvider::boot()`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('manage-privacy', function ($user) {
    return $user->is_admin || $user->hasRole('dpo');
});
```

Change the gate name:

```php
// config/artisanpack/privacy.php
'admin' => ['gate' => 'view-privacy-admin'],
```

## Change the URL prefix

```php
'admin' => ['route_prefix' => 'tools/privacy'],
```

## Customize the layout

```bash
php artisan vendor:publish --tag=privacy-admin-layout
```

The file lands at `resources/views/vendor/artisanpack-ui/privacy/admin/layout.blade.php`. Edit it to match your design system — extend your app layout, add your nav, etc.

## React / Vue dashboards

Every admin Livewire component has a React + Vue equivalent under `@artisanpack-ui/privacy/react/admin` and `@artisanpack-ui/privacy/vue/admin`. They call the same admin JSON API (`api/privacy/admin/*`) so authorization is enforced server-side via the same gate.

```tsx
import { ConsentManager, DataRequestManager } from '@artisanpack-ui/privacy/react/admin'
```

## Disable the dashboard entirely

```php
'admin' => ['enabled' => false],
```

(Useful if you're building your own admin against the JSON API.)
