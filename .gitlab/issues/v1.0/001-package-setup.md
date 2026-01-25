# Set up package structure following package-blueprint

/label ~"Type::Setup" ~"Status::Backlog" ~"Priority::High" ~"Phase::1"

## Task Description

Set up the initial package structure for `artisanpack-ui/privacy` following the `artisanpack-ui/package-blueprint` template.

## Acceptance Criteria

- [ ] Package directory structure created following blueprint
- [ ] `composer.json` configured with correct dependencies
- [ ] Service provider created (`PrivacyServiceProvider.php`)
- [ ] Facade created (`Privacy.php`)
- [ ] Configuration file structure in place (`config/privacy.php`)
- [ ] Base test case configured with Orchestra Testbench
- [ ] Code style files configured (`.php-cs-fixer.dist.php`, `phpcs.xml`)
- [ ] GitLab CI pipeline configured
- [ ] `composer install` runs without errors
- [ ] `composer test` runs (even if no tests yet)

## Context

This is the foundational task for Phase 1. All other tasks depend on this being complete.

## Notes

### Directory Structure
```
src/
├── Commands/
├── Config/
├── Contracts/
├── Events/
├── Facades/
├── Http/
├── Listeners/
├── Livewire/
├── Models/
├── Notifications/
├── Regulations/
├── Services/
├── Traits/
├── Privacy.php
├── PrivacyServiceProvider.php
└── helpers.php
```

### Dependencies
- `php`: ^8.2
- `illuminate/support`: ^10.0|^11.0|^12.0
- `artisanpack-ui/core`: ^1.0
- `artisanpack-ui/livewire-ui-components`: ^2.0
