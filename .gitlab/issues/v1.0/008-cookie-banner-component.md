# Implement CookieBanner Livewire component

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Frontend"

## Problem Statement

Users need a visible, accessible cookie consent banner to grant or deny consent for different cookie categories.

## Proposed Solution

Create a `CookieBanner` Livewire component with customizable appearance and behavior.

## Acceptance Criteria

- [ ] Livewire component created at `src/Livewire/CookieBanner.php`
- [ ] Component view with responsive design
- [ ] `acceptAll()` method
- [ ] `rejectAll()` method
- [ ] `acceptSelected(array $categories)` method
- [ ] `openPreferences()` method to show detailed settings
- [ ] Props: `position`, `style`, `categories`
- [ ] Events dispatched: `privacy:consent-updated`, `privacy:banner-closed`
- [ ] Slots support: header, description, footer
- [ ] CSS variable theming support
- [ ] Dark mode support
- [ ] Keyboard accessible (focus management)
- [ ] Screen reader compatible
- [ ] Banner hidden after consent given
- [ ] Animation support (configurable)
- [ ] Feature tests for component

## Use Cases

1. Show banner on first visit
2. User clicks "Accept All" - all categories granted
3. User clicks "Reject All" - only necessary cookies
4. User clicks "Customize" - opens preferences modal

## Additional Context

```blade
<livewire:privacy-cookie-banner
    position="bottom"
    style="bar"
/>
```

---

**Related Issues:**
- #004 (ConsentService)
- #009 (ConsentPreferences Component)
