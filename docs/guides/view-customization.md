# View customization

## Livewire / Blade

Publish the views:

```bash
php artisan vendor:publish --tag=privacy-views
```

They land at `resources/views/vendor/artisanpack-ui/privacy/`. Laravel's view loader checks `resources/views/vendor/...` before falling back to the package's own views, so any file you keep there overrides the package version.

Common overrides:

| File | Purpose |
|---|---|
| `livewire/cookie-banner.blade.php` | Banner markup + styles |
| `livewire/consent-preferences.blade.php` | Modal that toggles per-category |
| `livewire/data-request-form.blade.php` | Submission form |
| `livewire/privacy-dashboard.blade.php` | Per-user dashboard |
| `admin/layout.blade.php` | Admin dashboard outer layout |
| `breach-templates/authority.blade.php` | GDPR Art. 33 authority notice |
| `breach-templates/user.blade.php` | GDPR Art. 34 user notice |

Delete a file from the vendor copy to fall back to the package version.

## React / Vue

The shipped components accept standard className / slot props for styling. To replace markup entirely, fork the component:

```bash
# Copy the package source into your app
cp -R vendor/artisanpack-ui/privacy/resources/js/react/CookieBanner.tsx \
      resources/js/components/CookieBanner.tsx
```

Then import your fork instead of the package version.

## CSS

The base styles live at `resources/css/privacy.css` and can be published:

```bash
php artisan vendor:publish --tag=privacy-css
```

The package targets utility-class hooks (`.ap-privacy-banner`, `.ap-privacy-modal`, …) so a Tailwind project can restyle without writing new CSS.
