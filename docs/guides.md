---
title: Guides
---

# Guides

End-to-end walkthroughs for the major features of the Privacy package. Each guide assumes you have already run `privacy:install` and have the package wired into a Laravel application.

## Available Guides

- [[guides/cookie-consent]] — Define cookie categories, mount the banner across Livewire / React / Vue, and persist consent to the database
- [[guides/data-subject-rights]] — Wire up access, export, deletion, and rectification request flows with verification, audit logs, and optional auto-processing
- [[guides/multi-regulation]] — Configure GDPR, CCPA, LGPD, and PIPEDA; geolocate users and resolve which regulation applies
- [[guides/admin-dashboard]] — The five admin tools (consents, requests, breaches, policies, reports), the `manage-privacy` gate, and route mounting
- [[guides/view-customization]] — Override Livewire components, Blade partials, React components, and Vue single-file components
- [[guides/react-vue]] — Drop-in React and Vue surfaces that share the same JSON API as the Livewire components

## When to Read What

| If you are… | Start with |
|---|---|
| Wiring up the cookie banner for the first time | [[guides/cookie-consent]] |
| Adding a "Download my data" form to a user dashboard | [[guides/data-subject-rights]] |
| Operating in California, Brazil, or Canada | [[guides/multi-regulation]] |
| Building the operator-facing admin area | [[guides/admin-dashboard]] |
| Skinning the package to match your design system | [[guides/view-customization]] |
| Building a React or Vue front-end | [[guides/react-vue]] |

For the underlying classes, methods, events, and helpers used by each guide, see the [[api]] reference.
