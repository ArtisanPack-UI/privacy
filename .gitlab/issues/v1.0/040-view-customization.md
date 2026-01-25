# Implement view customization system

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::9" ~"Area::Frontend"

## Problem Statement

Developers need multiple ways to customize the package's UI components to match their application's design.

## Proposed Solution

Create a comprehensive view customization system with publishing, props, slots, and CSS variables.

## Acceptance Criteria

### View Publishing
- [ ] `php artisan vendor:publish --tag=privacy-views` command
- [ ] Views published to `resources/views/vendor/artisanpack-ui/privacy/`
- [ ] All component views publishable
- [ ] Email templates publishable
- [ ] Admin views publishable

### Component Props
- [ ] `class` prop for custom CSS classes
- [ ] `button-classes` prop for button styling
- [ ] `labels` prop for custom text
- [ ] Props documented for each component

### Slots
- [ ] `header` slot in banner/modal components
- [ ] `description` slot for custom content
- [ ] `footer` slot for additional content
- [ ] Slots documented for each component

### CSS Variables
- [ ] CSS variables for all themeable properties
- [ ] Variables documented
- [ ] Dark mode variables
- [ ] daisyUI integration

### Configuration
- [ ] `animation` option (slide-up, fade, none)
- [ ] `z_index` option
- [ ] `custom_css_class` option
- [ ] `use_daisyui` option

## Use Cases

1. Publish views for full customization
2. Add custom class to banner
3. Override button text via labels prop
4. Inject custom header via slot
5. Override colors via CSS variables

## Additional Context

See `IMPLEMENTATION_PLAN.md` View Customization section for full details.

---

**Related Issues:**
- #008 (CookieBanner Component)
- #009 (ConsentPreferences Component)
