# Implement admin routes and authorization

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::6" ~"Area::Backend"

## Problem Statement

Admin functionality must be protected with proper authorization to prevent unauthorized access.

## Proposed Solution

Create admin routes, middleware, and authorization gates for privacy administration.

## Acceptance Criteria

- [ ] Admin routes registered at configurable prefix
- [ ] `manage-privacy` authorization gate
- [ ] Middleware applied to admin routes
- [ ] Install command creates gate definition
- [ ] Gate check in all admin components
- [ ] 403 response for unauthorized access
- [ ] Documentation for gate customization
- [ ] Feature tests for authorization

## Use Cases

1. Only users with `manage-privacy` ability can access admin
2. Developers can customize gate logic in AuthServiceProvider
3. Install command adds gate stub

## Additional Context

Configuration:
```php
'admin' => [
    'enabled' => true,
    'route_prefix' => 'admin/privacy',
    'middleware' => ['web', 'auth'],
    'gate' => 'manage-privacy',
],
```

Gate definition (added by install):
```php
Gate::define('manage-privacy', function (User $user) {
    return $user->hasRole('admin'); // Customize as needed
});
```

---

**Related Issues:**
- #029 (Admin ConsentManager)
- #030 (Admin DataRequestManager)
