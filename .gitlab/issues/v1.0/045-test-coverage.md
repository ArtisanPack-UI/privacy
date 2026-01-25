# Achieve 80%+ test coverage

/label ~"Type::Enhancement" ~"Status::Backlog" ~"Priority::High" ~"Phase::9" ~"Area::Backend"

## Current Behavior

Tests are written alongside features but overall coverage may not meet target.

## Proposed Improvement

Ensure comprehensive test coverage across all package code.

## Benefits

- Catch regressions early
- Document expected behavior
- Enable confident refactoring
- Meet quality standards

## Acceptance Criteria

- [ ] 80%+ line coverage achieved
- [ ] All services have unit tests
- [ ] All models have unit tests
- [ ] All Livewire components have feature tests
- [ ] All Artisan commands have feature tests
- [ ] All middleware have feature tests
- [ ] All events/listeners have unit tests
- [ ] Integration tests for critical workflows
- [ ] Coverage report generated in CI
- [ ] Coverage badge in README

## Backwards Compatibility

- [x] This is backwards compatible

## Additional Context

Use Pest for all tests. Follow existing test patterns in ArtisanPack UI packages.

Critical workflows to test:
1. Complete consent flow (banner → preferences → storage)
2. Data request flow (submit → verify → process → complete)
3. Breach notification flow
4. Policy update → re-consent flow

---

**Related Issues:**
All previous issues
