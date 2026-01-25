# Implement privacy:report Artisan command

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Low" ~"Phase::9" ~"Area::Backend"

## Problem Statement

Organizations need to generate compliance reports from the command line for automation and audit purposes.

## Proposed Solution

Create `privacy:report` Artisan command for report generation.

## Acceptance Criteria

- [ ] Command: `php artisan privacy:report`
- [ ] `--period=` option (day, week, month, quarter, year, custom)
- [ ] `--start=` and `--end=` for custom date range
- [ ] `--format=` option (pdf, csv, json)
- [ ] `--output=` option for file path
- [ ] Consent statistics included
- [ ] Request metrics included
- [ ] Deadline compliance included
- [ ] Email option to send report
- [ ] Feature tests for command

## Use Cases

1. Monthly report: `php artisan privacy:report --period=month --format=pdf`
2. Custom range: `php artisan privacy:report --start=2026-01-01 --end=2026-01-31`
3. Email to DPO: `php artisan privacy:report --email=dpo@example.com`

## Additional Context

Can be scheduled for automatic monthly reports:
```php
Schedule::command('privacy:report --period=month --email=dpo@example.com')
    ->monthlyOn(1, '08:00');
```

---

**Related Issues:**
- #031 (Admin ComplianceReport)
