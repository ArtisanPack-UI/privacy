# Implement ConsentPreferences Livewire component

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Frontend"

## Problem Statement

Users need a detailed interface to manage individual cookie category preferences.

## Proposed Solution

Create a `ConsentPreferences` Livewire component that displays all categories with toggle controls.

## Acceptance Criteria

- [ ] Livewire component created at `src/Livewire/ConsentPreferences.php`
- [ ] Component view with category list
- [ ] `save()` method to persist preferences
- [ ] `toggleCategory(string $category)` method
- [ ] Props: `showDescriptions`, `showCookieList`
- [ ] Toggle disabled for required categories
- [ ] Category descriptions displayed
- [ ] Cookie list per category (optional)
- [ ] Accessible toggle controls
- [ ] Changes tracked before save
- [ ] Success feedback on save
- [ ] Can be used standalone or in modal
- [ ] Feature tests for component

## Use Cases

1. User opens preferences from banner
2. User toggles individual categories on/off
3. User sees which cookies each category includes
4. User saves preferences and banner closes

## Additional Context

```blade
<x-artisanpack-modal wire:model="showPreferences" title="Cookie Preferences">
    <livewire:privacy-consent-preferences />
</x-artisanpack-modal>
```

---

**Related Issues:**
- #008 (CookieBanner Component)
- #004 (ConsentService)
