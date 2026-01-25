# Implement privacy:process-requests Artisan command

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::9" ~"Area::Backend"

## Problem Statement

Pending data requests that are configured for auto-processing need to be processed on a schedule.

## Proposed Solution

Create `privacy:process-requests` Artisan command for scheduled processing.

## Acceptance Criteria

- [ ] Command: `php artisan privacy:process-requests`
- [ ] Find pending auto-processable requests
- [ ] Process access requests automatically
- [ ] Process export requests automatically
- [ ] Skip deletion requests (require manual review)
- [ ] Update request status
- [ ] Send completion notifications
- [ ] Log processing results
- [ ] `--type=` filter option
- [ ] `--dry-run` option
- [ ] Feature tests for command

## Use Cases

1. Schedule daily: `php artisan privacy:process-requests`
2. Process only exports: `php artisan privacy:process-requests --type=export`
3. Preview processing: `php artisan privacy:process-requests --dry-run`

## Additional Context

Add to scheduler:
```php
Schedule::command('privacy:process-requests')->daily();
```

---

**Related Issues:**
- #017 (DataExportService)
- #018 (DataDeletionService)
