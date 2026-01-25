# Implement JavaScript consent API

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::2" ~"Area::Frontend"

## Problem Statement

Frontend JavaScript code needs to check consent status before loading third-party scripts (analytics, marketing, etc.).

## Proposed Solution

Create a `window.PrivacyConsent` JavaScript API that provides consent checking and event listening.

## Acceptance Criteria

- [ ] `window.PrivacyConsent.hasConsent(category)` method
- [ ] `window.PrivacyConsent.getConsents()` method
- [ ] `window.PrivacyConsent.openPreferences()` method
- [ ] `privacy:consent-updated` custom event dispatched
- [ ] Consent state synced with Livewire components
- [ ] Script blocking until consent given
- [ ] Script loading helper after consent
- [ ] Works without Livewire for static pages
- [ ] TypeScript definitions file
- [ ] Minified and non-minified versions
- [ ] Published as package asset
- [ ] Documentation with examples

## Use Cases

1. Check before loading Google Analytics:
   ```javascript
   if (window.PrivacyConsent.hasConsent('analytics')) {
       loadGoogleAnalytics();
   }
   ```
2. Listen for consent changes:
   ```javascript
   window.addEventListener('privacy:consent-updated', (e) => {
       if (e.detail.category === 'marketing' && e.detail.granted) {
           loadMarketingPixels();
       }
   });
   ```

## Additional Context

Script should be lightweight (<5KB minified) and have no dependencies.

---

**Related Issues:**
- #008 (CookieBanner Component)
- #010 (Consent Storage)
