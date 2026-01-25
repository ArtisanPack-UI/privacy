# Implement privacy:install Artisan command

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::9" ~"Area::Backend"

## Problem Statement

Developers need a simple way to set up the privacy package with all required files and configurations.

## Proposed Solution

Create `privacy:install` Artisan command that handles complete package setup.

## Acceptance Criteria

- [ ] Command: `php artisan privacy:install`
- [ ] Publish configuration file
- [ ] Publish migrations
- [ ] Run migrations (with confirmation)
- [ ] Publish views (optional)
- [ ] Seed default consent categories
- [ ] Create authorization gate stub
- [ ] Generate JavaScript assets
- [ ] Display next steps
- [ ] `--force` option to overwrite
- [ ] `--no-interaction` support
- [ ] Clear relevant caches
- [ ] Feature tests for command

## Use Cases

1. Fresh install: `php artisan privacy:install`
2. Force overwrite: `php artisan privacy:install --force`
3. CI/CD: `php artisan privacy:install --no-interaction`

## Additional Context

Output should guide developer through next steps:
- Add `HasPersonalData` trait to User model
- Configure regulations in config
- Add cookie banner to layout
- Set up admin gate

---

**Related Issues:**
- #005 (Configuration System)
- #002 (Database Migrations)
