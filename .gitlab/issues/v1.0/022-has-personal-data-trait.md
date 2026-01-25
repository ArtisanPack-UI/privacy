# Implement HasPersonalData model trait

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::4" ~"Area::Backend"

## Problem Statement

Developers need a way to define which fields in their models contain personal data and how to handle them.

## Proposed Solution

Create a `HasPersonalData` trait that models can use to declare personal data fields.

## Acceptance Criteria

- [ ] Trait created at `src/Traits/HasPersonalData.php`
- [ ] `personalDataFields()` method for field definitions
- [ ] `personalDataRelations()` method for related models
- [ ] `getPersonalData()` method to retrieve data
- [ ] `anonymizePersonalData()` method
- [ ] `deletePersonalData()` method
- [ ] Integration with DataExportService
- [ ] Integration with DataDeletionService
- [ ] Field metadata (type, sensitivity, strategy)
- [ ] Relationship cascade support
- [ ] Unit tests for trait methods
- [ ] Documentation with examples

## Use Cases

```php
class User extends Authenticatable
{
    use HasPersonalData;

    protected function personalDataFields(): array
    {
        return [
            'email' => [
                'type' => 'email',
                'sensitivity' => 'normal',
                'deletion_strategy' => 'anonymize',
            ],
            'name' => [
                'type' => 'name',
                'sensitivity' => 'normal',
                'deletion_strategy' => 'anonymize',
            ],
        ];
    }

    protected function personalDataRelations(): array
    {
        return ['profile', 'orders', 'comments'];
    }
}
```

## Additional Context

The trait should work with any Eloquent model, not just User.

---

**Related Issues:**
- #017 (DataExportService)
- #018 (DataDeletionService)
