# Implement Blade directives for consent checking

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::2" ~"Area::Frontend"

## Problem Statement

Developers need convenient Blade directives to conditionally render content based on consent status.

## Proposed Solution

Create custom Blade directives for consent checking in templates.

## Acceptance Criteria

- [ ] `@hasConsent('category')` / `@endhasConsent` directive
- [ ] `@consentRequired('category')` / `@else` / `@endconsentRequired` directive
- [ ] Directives registered in service provider
- [ ] Works with all consent categories
- [ ] Handles missing consent gracefully
- [ ] Cacheable (no side effects)
- [ ] Documentation with examples
- [ ] Feature tests for directives

## Use Cases

1. Conditionally load analytics:
   ```blade
   @hasConsent('analytics')
       <script src="analytics.js"></script>
   @endhasConsent
   ```

2. Show alternative content:
   ```blade
   @consentRequired('marketing')
       <p>Enable marketing cookies to see personalized content.</p>
   @else
       <div>Personalized content here</div>
   @endconsentRequired
   ```

## Additional Context

Directives should compile to efficient PHP for production caching.

---

**Related Issues:**
- #004 (ConsentService)
- #007 (Helper Functions)
