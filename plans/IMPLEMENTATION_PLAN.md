# ArtisanPack UI Privacy Package - Implementation Plan

## Overview

The `artisanpack-ui/privacy` package provides comprehensive privacy compliance tools for Laravel applications, helping developers conform to global privacy regulations including GDPR, CCPA, LGPD, PIPEDA, and others.

**Version**: 1.0.0
**Status**: Planning
**Last Updated**: January 2026

---

## Table of Contents

1. [Goals & Objectives](#goals--objectives)
2. [Supported Regulations](#supported-regulations)
3. [Architecture Overview](#architecture-overview)
4. [Core Features](#core-features)
5. [Database Schema](#database-schema)
6. [Configuration](#configuration)
7. [Livewire Components](#livewire-components)
8. [Service Classes](#service-classes)
9. [Artisan Commands](#artisan-commands)
10. [Events & Listeners](#events--listeners)
11. [Middleware](#middleware)
12. [API Endpoints](#api-endpoints)
13. [View Customization](#view-customization)
14. [Analytics Package Integration](#analytics-package-integration)
15. [Integration Points](#integration-points)
16. [Testing Strategy](#testing-strategy)
17. [Documentation](#documentation)
18. [Implementation Phases](#implementation-phases)

---

## Goals & Objectives

### Primary Goals

1. **Simplify Privacy Compliance**: Provide out-of-the-box tools to handle common privacy requirements
2. **Multi-Regulation Support**: Support multiple privacy regulations from a single, unified API
3. **Developer-Friendly**: Easy integration with existing Laravel applications
4. **Extensible**: Allow customization for specific business requirements
5. **Audit-Ready**: Maintain comprehensive logs for compliance audits

### Non-Goals

- Legal advice or certification (developers must consult legal counsel)
- Automatic detection of regulation applicability (developers configure which regulations apply)
- Third-party cookie scanning (integrate with external services if needed)

---

## Supported Regulations

### Tier 1 (MVP)

| Regulation | Region | Key Requirements |
|------------|--------|------------------|
| **GDPR** | European Union | Consent, data subject rights, DPO, breach notification (72h), data portability |
| **CCPA/CPRA** | California, USA | Right to know, delete, opt-out of sale, non-discrimination |

### Tier 2 (Post-MVP)

| Regulation | Region | Key Requirements |
|------------|--------|------------------|
| **LGPD** | Brazil | Similar to GDPR, data subject rights, DPO requirement |
| **PIPEDA** | Canada | Consent, access, correction, accountability |
| **POPIA** | South Africa | Consent, data subject rights, information officer |

### Tier 3 (Future)

| Regulation | Region | Key Requirements |
|------------|--------|------------------|
| **VCDPA** | Virginia, USA | Similar to CCPA with variations |
| **CPA** | Colorado, USA | Universal opt-out, data minimization |
| **CTDPA** | Connecticut, USA | Similar to other US state laws |
| **APPI** | Japan | Consent for sensitive data, cross-border transfers |
| **PDPA** | Singapore | Consent, access, correction |

---

## Architecture Overview

### Directory Structure

```
src/
├── Commands/
│   ├── InstallCommand.php
│   ├── ProcessDataRequestsCommand.php
│   ├── PurgeExpiredConsentsCommand.php
│   ├── GeneratePrivacyReportCommand.php
│   └── ScanPersonalDataCommand.php
├── Config/
│   └── privacy.php
├── Contracts/
│   ├── ConsentManager.php
│   ├── DataSubjectRightsHandler.php
│   ├── PersonalDataAware.php
│   ├── Anonymizable.php
│   └── PrivacyRegulation.php
├── Events/
│   ├── ConsentGiven.php
│   ├── ConsentWithdrawn.php
│   ├── DataAccessRequested.php
│   ├── DataDeletionRequested.php
│   ├── DataExportRequested.php
│   ├── DataBreach.php
│   └── PrivacyPolicyUpdated.php
├── Facades/
│   └── Privacy.php
├── Http/
│   ├── Controllers/
│   │   ├── ConsentController.php
│   │   ├── DataRequestController.php
│   │   ├── PrivacyPolicyController.php
│   │   └── Api/
│   │       ├── ConsentApiController.php
│   │       └── DataRequestApiController.php
│   └── Middleware/
│       ├── EnsureConsentGiven.php
│       ├── CheckCookieConsent.php
│       └── GeolocateUser.php
├── Listeners/
│   ├── ProcessDataAccessRequest.php
│   ├── ProcessDataDeletionRequest.php
│   ├── NotifyDataBreach.php
│   └── LogConsentActivity.php
├── Livewire/
│   ├── CookieBanner.php
│   ├── ConsentPreferences.php
│   ├── DataRequestForm.php
│   ├── PrivacyDashboard.php
│   └── Admin/
│       ├── ConsentManager.php
│       ├── DataRequestManager.php
│       └── ComplianceReport.php
├── Models/
│   ├── Consent.php
│   ├── ConsentCategory.php
│   ├── DataRequest.php
│   ├── DataRequestLog.php
│   ├── PersonalDataMap.php
│   ├── PrivacyPolicy.php
│   └── BreachNotification.php
├── Notifications/
│   ├── DataRequestReceived.php
│   ├── DataRequestCompleted.php
│   ├── DataBreachNotification.php
│   └── ConsentExpiringNotification.php
├── Regulations/
│   ├── BaseRegulation.php
│   ├── GDPR.php
│   ├── CCPA.php
│   ├── LGPD.php
│   └── PIPEDA.php
├── Services/
│   ├── ConsentService.php
│   ├── DataExportService.php
│   ├── DataDeletionService.php
│   ├── AnonymizationService.php
│   ├── PersonalDataScanner.php
│   ├── PrivacyPolicyGenerator.php
│   ├── BreachNotificationService.php
│   └── GeoLocationService.php
├── Traits/
│   ├── HasPersonalData.php
│   ├── Anonymizable.php
│   └── TracksConsent.php
├── Privacy.php
├── PrivacyServiceProvider.php
└── helpers.php
```

### Component Relationships

```
┌─────────────────────────────────────────────────────────────────┐
│                         User Interface                          │
├─────────────────────────────────────────────────────────────────┤
│  Cookie Banner  │  Consent Preferences  │  Data Request Form   │
│  (Livewire)     │  (Livewire)           │  (Livewire)          │
└────────┬────────┴──────────┬────────────┴──────────┬───────────┘
         │                   │                       │
         ▼                   ▼                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                         Services Layer                          │
├─────────────────────────────────────────────────────────────────┤
│  ConsentService  │  DataExportService  │  DataDeletionService  │
│  AnonymizationService  │  PrivacyPolicyGenerator               │
└────────┬────────────────────┬───────────────────────┬──────────┘
         │                    │                       │
         ▼                    ▼                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Regulations Layer                          │
├─────────────────────────────────────────────────────────────────┤
│       GDPR       │       CCPA        │        LGPD             │
│     (rules)      │      (rules)      │       (rules)           │
└────────┬────────────────────┬───────────────────────┬──────────┘
         │                    │                       │
         ▼                    ▼                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                         Data Layer                              │
├─────────────────────────────────────────────────────────────────┤
│  Consent  │  DataRequest  │  PersonalDataMap  │  PrivacyPolicy │
└─────────────────────────────────────────────────────────────────┘
```

---

## Core Features

### 1. Cookie Consent Management

**Purpose**: Allow users to consent to different categories of cookies/tracking

**Features**:
- Configurable cookie categories (necessary, functional, analytics, marketing)
- Granular consent per category
- Cookie banner with customizable UI
- Consent preferences center
- Consent expiration and re-consent prompts
- "Accept All" / "Reject All" / "Customize" options
- Remember consent across sessions
- JavaScript API for checking consent before loading scripts

**Cookie Categories** (Default):
```php
[
    'necessary' => [
        'name' => 'Strictly Necessary',
        'description' => 'Essential for the website to function',
        'required' => true,
    ],
    'functional' => [
        'name' => 'Functional',
        'description' => 'Enhance functionality and personalization',
        'required' => false,
    ],
    'analytics' => [
        'name' => 'Analytics',
        'description' => 'Help us understand how visitors use our site',
        'required' => false,
    ],
    'marketing' => [
        'name' => 'Marketing',
        'description' => 'Used to deliver relevant advertisements',
        'required' => false,
    ],
]
```

### 2. Data Subject Rights

**Purpose**: Enable users to exercise their privacy rights

**Rights Supported**:

| Right | GDPR | CCPA | LGPD | Description |
|-------|------|------|------|-------------|
| Access | ✅ | ✅ | ✅ | View personal data held |
| Rectification | ✅ | ❌ | ✅ | Correct inaccurate data |
| Erasure | ✅ | ✅ | ✅ | Delete personal data |
| Portability | ✅ | ✅ | ✅ | Export data in machine-readable format |
| Restriction | ✅ | ❌ | ✅ | Limit processing |
| Objection | ✅ | ✅ | ✅ | Opt-out of processing |
| Non-Discrimination | ❌ | ✅ | ❌ | Equal service regardless of privacy choices |

**Request Workflow**:
1. User submits request via form
2. System verifies identity (configurable verification methods)
3. Request logged and assigned status
4. Admin notified (optional)
5. Request processed (manual or automatic)
6. User notified of completion
7. Audit trail maintained

### 3. Privacy Policy Management

**Purpose**: Generate and manage privacy policies

**Features**:
- Template-based policy generation
- Multi-regulation support (generates compliant text per regulation)
- Version history tracking
- User notification on policy updates
- Consent re-collection when policy changes significantly
- Markdown and HTML output
- Customizable sections

### 4. Data Breach Notification

**Purpose**: Manage data breach reporting requirements

**Features**:
- Breach logging and documentation
- Automatic notification timing (GDPR: 72 hours)
- Notification templates for authorities
- Notification templates for affected users
- Breach severity assessment
- Remediation tracking
- Audit trail

### 5. Personal Data Discovery & Mapping

**Purpose**: Identify and document where personal data is stored

**Features**:
- Auto-scan Eloquent models for personal data fields
- Manual field mapping via model trait
- Data flow documentation
- Third-party data processor tracking
- Data retention policy assignment
- Personal data inventory report

### 6. Data Anonymization & Pseudonymization

**Purpose**: Transform personal data to protect identity

**Features**:
- Configurable anonymization strategies per field type
- Pseudonymization with reversible encryption
- Batch anonymization for old records
- Anonymization audit logging

**Anonymization Strategies**:
```php
[
    'name' => 'hash',           // SHA-256 hash
    'email' => 'mask',          // j***@e***.com
    'phone' => 'redact',        // [REDACTED]
    'address' => 'generalize',  // City only
    'ip' => 'truncate',         // Remove last octet
    'date' => 'generalize',     // Year only
]
```

---

## Database Schema

### Tables

#### `privacy_consents`
```php
Schema::create('privacy_consents', function (Blueprint $table) {
    $table->id();
    $table->morphs('consentable');              // user_id or guest identifier
    $table->string('category');                  // necessary, analytics, etc.
    $table->boolean('granted')->default(false);
    $table->string('regulation')->nullable();    // gdpr, ccpa, etc.
    $table->string('ip_address')->nullable();
    $table->string('user_agent')->nullable();
    $table->json('metadata')->nullable();        // Additional context
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('withdrawn_at')->nullable();
    $table->timestamps();

    $table->index(['consentable_type', 'consentable_id', 'category']);
});
```

#### `privacy_consent_categories`
```php
Schema::create('privacy_consent_categories', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();            // analytics, marketing, etc.
    $table->string('name');
    $table->text('description')->nullable();
    $table->boolean('required')->default(false);
    $table->integer('sort_order')->default(0);
    $table->boolean('active')->default(true);
    $table->json('regulations')->nullable();     // Which regulations require this
    $table->timestamps();
});
```

#### `privacy_data_requests`
```php
Schema::create('privacy_data_requests', function (Blueprint $table) {
    $table->id();
    $table->morphs('requestable');              // User making request
    $table->string('type');                      // access, deletion, export, rectification
    $table->string('status')->default('pending'); // pending, processing, completed, rejected
    $table->string('regulation')->nullable();    // Which regulation this falls under
    $table->text('reason')->nullable();          // User's stated reason
    $table->json('data')->nullable();            // Request-specific data
    $table->string('verification_token')->nullable();
    $table->timestamp('verified_at')->nullable();
    $table->timestamp('due_at')->nullable();     // Regulatory deadline
    $table->timestamp('completed_at')->nullable();
    $table->foreignId('processed_by')->nullable()->constrained('users');
    $table->text('admin_notes')->nullable();
    $table->timestamps();

    $table->index(['status', 'due_at']);
});
```

#### `privacy_data_request_logs`
```php
Schema::create('privacy_data_request_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('data_request_id')->constrained('privacy_data_requests')->cascadeOnDelete();
    $table->string('action');                    // created, verified, processing, completed, etc.
    $table->text('description')->nullable();
    $table->json('metadata')->nullable();
    $table->foreignId('performed_by')->nullable()->constrained('users');
    $table->timestamps();
});
```

#### `privacy_personal_data_maps`
```php
Schema::create('privacy_personal_data_maps', function (Blueprint $table) {
    $table->id();
    $table->string('model');                     // App\Models\User
    $table->string('field');                     // email, phone, etc.
    $table->string('data_type');                 // email, phone, name, address, etc.
    $table->string('sensitivity')->default('normal'); // normal, sensitive, special_category
    $table->string('retention_period')->nullable(); // 1 year, 3 years, forever
    $table->string('deletion_strategy');         // delete, anonymize, pseudonymize
    $table->string('export_format')->nullable(); // How to format in exports
    $table->boolean('auto_discovered')->default(false);
    $table->timestamps();

    $table->unique(['model', 'field']);
});
```

#### `privacy_policies`
```php
Schema::create('privacy_policies', function (Blueprint $table) {
    $table->id();
    $table->string('version');
    $table->string('regulation')->nullable();    // null = general, or specific regulation
    $table->string('locale')->default('en');
    $table->text('content');                     // Markdown content
    $table->json('sections')->nullable();        // Structured sections
    $table->boolean('active')->default(false);
    $table->boolean('requires_reconsent')->default(false);
    $table->timestamp('published_at')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->timestamps();

    $table->index(['active', 'regulation', 'locale']);
});
```

#### `privacy_breach_notifications`
```php
Schema::create('privacy_breach_notifications', function (Blueprint $table) {
    $table->id();
    $table->string('reference_number')->unique();
    $table->timestamp('discovered_at');
    $table->timestamp('occurred_at')->nullable();
    $table->string('severity');                  // low, medium, high, critical
    $table->text('description');
    $table->json('data_types_affected');         // email, passwords, payment, etc.
    $table->integer('records_affected')->nullable();
    $table->json('affected_users')->nullable();  // User IDs or count
    $table->text('cause')->nullable();
    $table->text('remediation')->nullable();
    $table->json('notifications_sent')->nullable(); // Authorities, users notified
    $table->timestamp('authority_notified_at')->nullable();
    $table->timestamp('users_notified_at')->nullable();
    $table->string('status')->default('investigating'); // investigating, contained, resolved
    $table->foreignId('reported_by')->nullable()->constrained('users');
    $table->timestamps();
});
```

---

## Configuration

### Main Configuration File (`config/artisanpack/privacy.php`)

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Enabled Regulations
    |--------------------------------------------------------------------------
    |
    | Specify which privacy regulations your application needs to comply with.
    | This affects consent requirements, user rights, and notification rules.
    |
    */
    'regulations' => [
        'gdpr' => [
            'enabled' => env('PRIVACY_GDPR_ENABLED', true),
            'applies_to' => ['EU', 'EEA', 'UK'], // Country codes
            'consent_expiry_days' => 365,
            'breach_notification_hours' => 72,
        ],
        'ccpa' => [
            'enabled' => env('PRIVACY_CCPA_ENABLED', true),
            'applies_to' => ['US-CA'],
            'opt_out_sale' => true,
            'financial_threshold' => 25000000, // Annual revenue threshold
        ],
        'lgpd' => [
            'enabled' => env('PRIVACY_LGPD_ENABLED', false),
            'applies_to' => ['BR'],
        ],
        'pipeda' => [
            'enabled' => env('PRIVACY_PIPEDA_ENABLED', false),
            'applies_to' => ['CA'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent Storage
    |--------------------------------------------------------------------------
    |
    | Configure how consent is stored and retrieved.
    |
    */
    'consent' => [
        'storage' => env('PRIVACY_CONSENT_STORAGE', 'both'), // database, cookie, both
        'cookie_name' => 'privacy_consent',
        'cookie_lifetime' => 365, // days
        'require_authentication' => false, // Require login for consent
        'guest_identifier' => 'fingerprint', // fingerprint, session, ip
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookie Categories
    |--------------------------------------------------------------------------
    |
    | Define the cookie consent categories available to users.
    |
    */
    'cookie_categories' => [
        'necessary' => [
            'name' => 'Strictly Necessary',
            'description' => 'Essential cookies required for the website to function properly.',
            'required' => true,
            'cookies' => ['session', 'csrf', 'privacy_consent'],
        ],
        'functional' => [
            'name' => 'Functional',
            'description' => 'Cookies that enable enhanced functionality and personalization.',
            'required' => false,
            'cookies' => ['language', 'timezone', 'preferences'],
        ],
        'analytics' => [
            'name' => 'Analytics',
            'description' => 'Cookies that help us understand how visitors use our website.',
            'required' => false,
            'cookies' => ['_ga', '_gid', '_gat'],
        ],
        'marketing' => [
            'name' => 'Marketing',
            'description' => 'Cookies used to deliver relevant advertisements.',
            'required' => false,
            'cookies' => ['_fbp', 'fr'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Subject Rights
    |--------------------------------------------------------------------------
    |
    | Configure how data subject requests are handled.
    |
    */
    'data_requests' => [
        'enabled' => true,
        'require_verification' => true,
        'verification_method' => 'email', // email, sms, manual
        'auto_process' => [
            'access' => true,   // Automatically process access requests
            'export' => true,   // Automatically process export requests
            'deletion' => false, // Require manual review for deletions
        ],
        'response_days' => [
            'gdpr' => 30,
            'ccpa' => 45,
            'default' => 30,
        ],
        'export_format' => 'json', // json, csv, xml
        'notify_admin' => true,
        'admin_email' => env('PRIVACY_ADMIN_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Deletion Strategies
    |--------------------------------------------------------------------------
    |
    | Configure how data is handled when deletion is requested.
    |
    */
    'deletion' => [
        'default_strategy' => 'anonymize', // delete, anonymize, pseudonymize
        'cascade' => true, // Delete/anonymize related records
        'preserve_audit' => true, // Keep audit logs with anonymized data
        'grace_period_days' => 30, // Days before permanent deletion
    ],

    /*
    |--------------------------------------------------------------------------
    | Anonymization Settings
    |--------------------------------------------------------------------------
    |
    | Configure how personal data is anonymized.
    |
    */
    'anonymization' => [
        'strategies' => [
            'email' => 'mask',        // j***@e***.com
            'name' => 'pseudonymize', // User_12345
            'phone' => 'redact',      // [REDACTED]
            'address' => 'generalize', // City, Country
            'ip_address' => 'truncate', // Remove last octet
            'date_of_birth' => 'generalize', // Year only
        ],
        'hash_algorithm' => 'sha256',
        'pseudonymization_prefix' => 'Anon_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Personal Data Discovery
    |--------------------------------------------------------------------------
    |
    | Configure automatic personal data discovery.
    |
    */
    'discovery' => [
        'auto_scan' => true,
        'scan_paths' => [
            app_path('Models'),
        ],
        'exclude_models' => [
            // Models to exclude from scanning
        ],
        'field_patterns' => [
            'email' => ['email', 'e_mail', 'email_address'],
            'name' => ['name', 'first_name', 'last_name', 'full_name'],
            'phone' => ['phone', 'telephone', 'mobile', 'cell'],
            'address' => ['address', 'street', 'city', 'zip', 'postal'],
            'ip' => ['ip', 'ip_address', 'ipaddress'],
            'ssn' => ['ssn', 'social_security', 'tax_id'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Geolocation
    |--------------------------------------------------------------------------
    |
    | Configure geolocation for determining applicable regulations.
    |
    */
    'geolocation' => [
        'enabled' => true,
        'provider' => 'ip-api', // ip-api, maxmind, cloudflare
        'cache_duration' => 1440, // minutes
        'fallback_region' => null, // Default region if geolocation fails
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    |
    | Configure the appearance of privacy UI components.
    |
    */
    'ui' => [
        'cookie_banner' => [
            'position' => 'bottom', // top, bottom, bottom-left, bottom-right
            'style' => 'bar', // bar, modal, floating
            'show_reject_all' => true,
            'show_customize' => true,
            'blur_background' => false,
        ],
        'theme' => 'auto', // auto, light, dark
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Configure routing for privacy endpoints.
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'privacy',
        'middleware' => ['web'],
        'api_prefix' => 'api/privacy',
        'api_middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard
    |--------------------------------------------------------------------------
    |
    | Configure the admin dashboard.
    |
    */
    'admin' => [
        'enabled' => true,
        'route_prefix' => 'admin/privacy',
        'middleware' => ['web', 'auth'],
        'gate' => 'manage-privacy', // Authorization gate name
    ],
];
```

---

## Livewire Components

### 1. CookieBanner

**File**: `src/Livewire/CookieBanner.php`

**Purpose**: Display cookie consent banner to users

**Props**:
- `position` (string): Banner position
- `style` (string): Visual style
- `categories` (array): Cookie categories to display

**Methods**:
- `acceptAll()`: Accept all cookie categories
- `rejectAll()`: Reject non-essential categories
- `acceptSelected(array $categories)`: Accept specific categories
- `openPreferences()`: Open detailed preferences

**Events Dispatched**:
- `privacy:consent-updated`
- `privacy:banner-closed`

**Usage**:
```blade
<livewire:privacy-cookie-banner
    position="bottom"
    style="bar"
/>
```

### 2. ConsentPreferences

**File**: `src/Livewire/ConsentPreferences.php`

**Purpose**: Detailed consent management interface

**Props**:
- `showDescriptions` (bool): Show category descriptions
- `showCookieList` (bool): Show individual cookies per category

**Methods**:
- `save()`: Save consent preferences
- `toggleCategory(string $category)`: Toggle category consent

**Usage**:
```blade
<livewire:privacy-consent-preferences />

{{-- Or as modal --}}
<x-artisanpack-modal wire:model="showPreferences" title="Cookie Preferences">
    <livewire:privacy-consent-preferences />
</x-artisanpack-modal>
```

### 3. DataRequestForm

**File**: `src/Livewire/DataRequestForm.php`

**Purpose**: Allow users to submit data subject requests

**Props**:
- `requestTypes` (array): Available request types
- `requireReason` (bool): Require reason for request

**Methods**:
- `submit()`: Submit data request
- `verifyIdentity()`: Initiate identity verification

**Usage**:
```blade
<livewire:privacy-data-request-form
    :request-types="['access', 'export', 'deletion']"
/>
```

### 4. PrivacyDashboard (User)

**File**: `src/Livewire/PrivacyDashboard.php`

**Purpose**: User-facing privacy dashboard

**Features**:
- View current consent status
- Update consent preferences
- Submit data requests
- View request history
- Download exported data

**Usage**:
```blade
<livewire:privacy-dashboard />
```

### 5. Admin Components

#### ConsentManager

**File**: `src/Livewire/Admin/ConsentManager.php`

**Purpose**: Admin view of consent records

**Features**:
- View all consent records
- Filter by category, status, date
- Export consent audit log
- Bulk operations

#### DataRequestManager

**File**: `src/Livewire/Admin/DataRequestManager.php`

**Purpose**: Process data subject requests

**Features**:
- View pending requests
- Process requests (approve, reject)
- Add admin notes
- Track deadlines
- Generate compliance reports

#### ComplianceReport

**File**: `src/Livewire/Admin/ComplianceReport.php`

**Purpose**: Generate compliance reports

**Features**:
- Consent statistics
- Request processing metrics
- Deadline compliance
- Export for audits

---

## Service Classes

### ConsentService

**File**: `src/Services/ConsentService.php`

**Methods**:
```php
class ConsentService
{
    // Check consent status
    public function hasConsent(string $category, ?Model $user = null): bool;
    public function getConsents(?Model $user = null): Collection;

    // Grant/revoke consent
    public function grantConsent(string $category, ?Model $user = null, array $metadata = []): Consent;
    public function revokeConsent(string $category, ?Model $user = null): bool;
    public function grantAllConsents(?Model $user = null): Collection;
    public function revokeAllConsents(?Model $user = null): bool;

    // Cookie operations
    public function getConsentCookie(): ?array;
    public function setConsentCookie(array $consents): void;

    // Utility
    public function isConsentExpired(Consent $consent): bool;
    public function getApplicableRegulation(): ?string;
}
```

### DataExportService

**File**: `src/Services/DataExportService.php`

**Methods**:
```php
class DataExportService
{
    // Generate exports
    public function export(Model $user, string $format = 'json'): string;
    public function exportToFile(Model $user, string $format = 'json'): string;

    // Get exportable data
    public function getPersonalData(Model $user): array;
    public function getConsentHistory(Model $user): Collection;
    public function getActivityLog(Model $user): Collection;
}
```

### DataDeletionService

**File**: `src/Services/DataDeletionService.php`

**Methods**:
```php
class DataDeletionService
{
    // Delete user data
    public function delete(Model $user, array $options = []): bool;
    public function anonymize(Model $user): bool;
    public function pseudonymize(Model $user): bool;

    // Cascade operations
    public function deleteRelated(Model $user, array $relations): bool;

    // Scheduling
    public function scheduleForDeletion(Model $user, int $gracePeriodDays = 30): void;
    public function cancelScheduledDeletion(Model $user): bool;
}
```

### AnonymizationService

**File**: `src/Services/AnonymizationService.php`

**Methods**:
```php
class AnonymizationService
{
    // Anonymize data
    public function anonymize(Model $model, array $fields = []): Model;
    public function anonymizeField(string $value, string $strategy): string;

    // Strategies
    public function mask(string $value): string;      // j***@e***.com
    public function redact(string $value): string;    // [REDACTED]
    public function hash(string $value): string;      // SHA-256 hash
    public function truncate(string $value): string;  // Partial value
    public function generalize(string $value, string $type): string; // City only, Year only
    public function pseudonymize(string $value): string; // Reversible with key
}
```

### PersonalDataScanner

**File**: `src/Services/PersonalDataScanner.php`

**Methods**:
```php
class PersonalDataScanner
{
    // Scan models
    public function scan(): Collection;
    public function scanModel(string $modelClass): array;

    // Register mappings
    public function registerField(string $model, string $field, array $config): void;
    public function getFieldMappings(string $model): array;

    // Generate report
    public function generateDataInventory(): array;
}
```

### BreachNotificationService

**File**: `src/Services/BreachNotificationService.php`

**Methods**:
```php
class BreachNotificationService
{
    // Log breach
    public function reportBreach(array $data): BreachNotification;

    // Notifications
    public function notifyAuthority(BreachNotification $breach): void;
    public function notifyAffectedUsers(BreachNotification $breach): void;

    // Compliance
    public function isWithinNotificationWindow(BreachNotification $breach): bool;
    public function getNotificationDeadline(BreachNotification $breach): Carbon;
}
```

---

## Artisan Commands

### InstallCommand

```bash
php artisan privacy:install
```

**Actions**:
- Publish configuration
- Publish migrations
- Run migrations
- Publish views (optional)
- Seed default consent categories
- Create admin gate

### ProcessDataRequestsCommand

```bash
php artisan privacy:process-requests
```

**Actions**:
- Find pending auto-processable requests
- Process access/export requests automatically
- Send notifications
- Update request status

**Scheduling**: Daily

### PurgeExpiredConsentsCommand

```bash
php artisan privacy:purge-expired
```

**Actions**:
- Remove expired consent records
- Optionally notify users to re-consent

**Scheduling**: Weekly

### GeneratePrivacyReportCommand

```bash
php artisan privacy:report {--period=month} {--format=pdf}
```

**Actions**:
- Generate compliance report
- Include consent statistics
- Include request metrics
- Export to PDF/CSV

### ScanPersonalDataCommand

```bash
php artisan privacy:scan {--model=} {--save}
```

**Actions**:
- Scan models for personal data fields
- Generate data inventory
- Optionally save mappings to database

---

## Events & Listeners

### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `ConsentGiven` | `Consent $consent` | User granted consent |
| `ConsentWithdrawn` | `Consent $consent` | User revoked consent |
| `DataAccessRequested` | `DataRequest $request` | Access request submitted |
| `DataDeletionRequested` | `DataRequest $request` | Deletion request submitted |
| `DataExportRequested` | `DataRequest $request` | Export request submitted |
| `DataRequestCompleted` | `DataRequest $request` | Request processing finished |
| `DataBreach` | `BreachNotification $breach` | Breach reported |
| `PrivacyPolicyUpdated` | `PrivacyPolicy $policy` | New policy published |

### Default Listeners

| Listener | Event | Action |
|----------|-------|--------|
| `LogConsentActivity` | `ConsentGiven`, `ConsentWithdrawn` | Log to audit trail |
| `ProcessDataAccessRequest` | `DataAccessRequested` | Auto-process if enabled |
| `ProcessDataExportRequest` | `DataExportRequested` | Generate export file |
| `NotifyAdminOfRequest` | All data requests | Email admin |
| `NotifyDataBreach` | `DataBreach` | Start notification workflow |

---

## Middleware

### EnsureConsentGiven

**File**: `src/Http/Middleware/EnsureConsentGiven.php`

**Purpose**: Block routes that require specific consent

**Usage**:
```php
Route::middleware('privacy.consent:analytics')->group(function () {
    // Routes requiring analytics consent
});
```

### CheckCookieConsent

**File**: `src/Http/Middleware/CheckCookieConsent.php`

**Purpose**: Inject cookie consent status into views/JavaScript

**Usage**:
```php
// Applied globally, makes $privacyConsent available
```

### GeolocateUser

**File**: `src/Http/Middleware/GeolocateUser.php`

**Purpose**: Determine user's location for regulation detection

**Usage**:
```php
Route::middleware('privacy.geolocate')->group(function () {
    // Routes needing location awareness
});
```

---

## API Endpoints

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/privacy/consent` | Get current consent status |
| `POST` | `/api/privacy/consent` | Update consent preferences |
| `GET` | `/api/privacy/categories` | Get consent categories |
| `GET` | `/api/privacy/policy` | Get current privacy policy |

### Authenticated Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/privacy/requests` | List user's data requests |
| `POST` | `/api/privacy/requests` | Submit new data request |
| `GET` | `/api/privacy/requests/{id}` | Get request status |
| `GET` | `/api/privacy/export/{id}` | Download export file |

### Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/privacy/admin/requests` | List all requests |
| `PATCH` | `/api/privacy/admin/requests/{id}` | Update request status |
| `GET` | `/api/privacy/admin/consents` | List consent records |
| `GET` | `/api/privacy/admin/reports` | Get compliance reports |

---

## View Customization

Developers can customize the package's UI in several ways:

### 1. Publishing Views

Publish all views to your application for full customization:

```bash
php artisan vendor:publish --tag=privacy-views
```

This publishes views to `resources/views/vendor/artisanpack-ui/privacy/`:

```
resources/views/vendor/artisanpack-ui/privacy/
├── components/
│   ├── cookie-banner.blade.php
│   ├── consent-preferences.blade.php
│   ├── data-request-form.blade.php
│   └── privacy-dashboard.blade.php
├── admin/
│   ├── consent-manager.blade.php
│   ├── data-request-manager.blade.php
│   └── compliance-report.blade.php
├── emails/
│   ├── data-request-received.blade.php
│   ├── data-request-completed.blade.php
│   └── data-breach-notification.blade.php
└── policy/
    ├── show.blade.php
    └── partials/
        ├── gdpr.blade.php
        └── ccpa.blade.php
```

### 2. Component Customization via Props

Livewire components accept customization props:

```blade
{{-- Custom CSS classes --}}
<livewire:privacy-cookie-banner
    class="my-custom-banner"
    :button-classes="['accept' => 'btn-primary', 'reject' => 'btn-outline']"
/>

{{-- Custom text/labels --}}
<livewire:privacy-cookie-banner
    :labels="[
        'title' => 'We value your privacy',
        'accept' => 'Accept All',
        'reject' => 'Reject All',
        'customize' => 'Manage Preferences',
    ]"
/>
```

### 3. Slot-Based Content Injection

Components support slots for custom content:

```blade
<livewire:privacy-cookie-banner>
    <x-slot:header>
        <img src="/logo.svg" alt="Logo" class="h-8" />
        <h3>Cookie Settings</h3>
    </x-slot:header>

    <x-slot:description>
        We use cookies to enhance your experience. Read our
        <a href="/privacy">privacy policy</a> for more details.
    </x-slot:description>

    <x-slot:footer>
        <small>By continuing, you agree to our terms.</small>
    </x-slot:footer>
</livewire:privacy-cookie-banner>
```

### 4. CSS Variables for Theming

The default views use CSS variables that can be overridden:

```css
:root {
    /* Cookie Banner */
    --privacy-banner-bg: theme('colors.base-100');
    --privacy-banner-text: theme('colors.base-content');
    --privacy-banner-shadow: 0 -4px 6px rgba(0, 0, 0, 0.1);

    /* Buttons */
    --privacy-btn-accept-bg: theme('colors.primary');
    --privacy-btn-accept-text: theme('colors.primary-content');
    --privacy-btn-reject-bg: theme('colors.base-300');
    --privacy-btn-reject-text: theme('colors.base-content');

    /* Toggle switches */
    --privacy-toggle-on: theme('colors.success');
    --privacy-toggle-off: theme('colors.base-300');
}
```

### 5. Configuration-Based Customization

The `config/artisanpack/privacy.php` file includes UI settings:

```php
'ui' => [
    'cookie_banner' => [
        'position' => 'bottom',           // top, bottom, bottom-left, bottom-right
        'style' => 'bar',                 // bar, modal, floating
        'show_reject_all' => true,
        'show_customize' => true,
        'blur_background' => false,
        'animation' => 'slide-up',        // slide-up, fade, none
        'z_index' => 9999,
    ],
    'theme' => 'auto',                    // auto, light, dark
    'custom_css_class' => '',             // Additional CSS class for all components
    'use_daisyui' => true,                // Use daisyUI component classes
],
```

### 6. Extending Components

Create custom Livewire components that extend the package's components:

```php
namespace App\Livewire;

use ArtisanPackUI\Privacy\Livewire\CookieBanner as BaseCookieBanner;

class CustomCookieBanner extends BaseCookieBanner
{
    public function render()
    {
        return view('livewire.custom-cookie-banner', $this->getViewData());
    }

    // Override methods as needed
    public function acceptAll()
    {
        parent::acceptAll();

        // Custom logic after acceptance
        $this->dispatch('custom-consent-event');
    }
}
```

Register your custom component in a service provider:

```php
use Livewire\Livewire;

Livewire::component('privacy-cookie-banner', \App\Livewire\CustomCookieBanner::class);
```

---

## Analytics Package Integration

The `artisanpack-ui/privacy` package integrates seamlessly with `artisanpack-ui/analytics` when both are installed. This integration ensures a unified consent experience and prevents duplicate consent UI.

### Integration Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     Privacy Package (Primary)                   │
├─────────────────────────────────────────────────────────────────┤
│  Cookie Banner  │  Consent Service  │  Consent Events          │
│                 │  (Single Source)  │                          │
└────────┬────────┴────────┬──────────┴───────────┬──────────────┘
         │                 │                      │
         ▼                 ▼                      ▼
┌─────────────────────────────────────────────────────────────────┐
│                     Analytics Package (Consumer)                 │
├─────────────────────────────────────────────────────────────────┤
│  Listens for     │  Checks Privacy  │  Defers Consent UI       │
│  Consent Events  │  Consent Status  │  to Privacy Package      │
└─────────────────────────────────────────────────────────────────┘
```

### How It Works

**1. Automatic Detection**

When both packages are installed, Analytics detects Privacy and defers to it:

```php
// In Analytics package AnalyticsServiceProvider
public function boot(): void
{
    if ($this->privacyPackageInstalled()) {
        // Don't register our own consent banner
        // Listen for Privacy package events instead
        $this->registerPrivacyIntegration();
    }
}

protected function privacyPackageInstalled(): bool
{
    return class_exists(\ArtisanPackUI\Privacy\PrivacyServiceProvider::class);
}
```

**2. Event Integration**

Analytics listens for Privacy consent events:

```php
// In Analytics package
use ArtisanPackUI\Privacy\Events\ConsentGiven;
use ArtisanPackUI\Privacy\Events\ConsentWithdrawn;

Event::listen(ConsentGiven::class, function ($event) {
    if ($event->consent->category === 'analytics') {
        // Start tracking for this user
        Analytics::enableTracking($event->consent->consentable);
    }
});

Event::listen(ConsentWithdrawn::class, function ($event) {
    if ($event->consent->category === 'analytics') {
        // Stop tracking for this user
        Analytics::disableTracking($event->consent->consentable);
        // Optionally delete existing data
        Analytics::handleOptOut($event->consent->consentable);
    }
});
```

**3. Consent Check Integration**

Analytics uses Privacy's consent service:

```php
// In Analytics TrackingService
public function shouldTrack(): bool
{
    // When Privacy package is installed, defer to it
    if ($this->privacyIntegration) {
        return privacyHasConsent('analytics');
    }

    // Fallback to Analytics' own consent check
    return $this->hasOwnConsent();
}
```

**4. JavaScript API Integration**

Analytics JavaScript defers to Privacy's consent state:

```javascript
// In analytics.js
(function() {
    const analytics = {
        track: function(event, data) {
            // Check Privacy package consent first
            if (window.PrivacyConsent && !window.PrivacyConsent.hasConsent('analytics')) {
                return; // Don't track without consent
            }

            // Proceed with tracking
            this._sendEvent(event, data);
        }
    };

    // Listen for Privacy consent changes
    window.addEventListener('privacy:consent-updated', function(e) {
        if (e.detail.category === 'analytics') {
            if (e.detail.granted) {
                analytics.enable();
            } else {
                analytics.disable();
            }
        }
    });

    window.Analytics = analytics;
})();
```

### Configuration

**Privacy Package Configuration**

Ensure the `analytics` category is defined in Privacy config:

```php
// config/artisanpack/privacy.php
'cookie_categories' => [
    'necessary' => [
        'name' => 'Strictly Necessary',
        'required' => true,
    ],
    'analytics' => [
        'name' => 'Analytics',
        'description' => 'Help us understand how visitors use our site.',
        'required' => false,
        'cookies' => ['_ap_sid', '_ap_vid'], // Analytics package cookies
    ],
    // ...
],
```

**Analytics Package Configuration**

When Privacy is installed, Analytics auto-configures:

```php
// config/artisanpack/analytics.php
'privacy' => [
    // Automatically set to true when Privacy package is detected
    'use_privacy_package' => true,

    // Analytics' own consent is disabled when Privacy handles it
    'consent_required' => false,

    // Category name to check in Privacy package
    'consent_category' => 'analytics',
],
```

### Blade Directive Behavior

When both packages are installed:

```blade
{{-- Use Privacy's banner (Analytics won't show its own) --}}
<livewire:privacy-cookie-banner />

{{-- This becomes a no-op when Privacy is installed --}}
@analyticsConsentBanner

{{-- Both work, but Privacy is the source of truth --}}
@hasConsent('analytics')
    {{-- This content only shows if Privacy consent is granted --}}
    @analyticsScripts
@endhasConsent
```

### Filter Hooks for Integration

Privacy provides hooks that Analytics uses:

```php
// Privacy package provides these hooks
addFilter('privacy.consent.categories', function ($categories) {
    // Analytics can add its category if not present
    if (!isset($categories['analytics'])) {
        $categories['analytics'] = [
            'name' => 'Analytics',
            'description' => 'Website analytics and performance tracking',
            'required' => false,
        ];
    }
    return $categories;
});

// Analytics can register its cookies with Privacy
addAction('privacy.cookies.register', function ($cookieRegistry) {
    $cookieRegistry->register('analytics', [
        '_ap_sid' => 'Session identifier for analytics',
        '_ap_vid' => 'Visitor identifier for analytics',
    ]);
});
```

### Data Subject Rights Integration

When a user exercises data rights through Privacy:

```php
// Privacy package fires event
Event::dispatch(new DataDeletionRequested($user));

// Analytics listens and deletes its data
Event::listen(DataDeletionRequested::class, function ($event) {
    Analytics::deleteUserData($event->user);
});

// For data export
Event::listen(DataExportRequested::class, function ($event) {
    // Add analytics data to export
    $event->addData('analytics', Analytics::exportUserData($event->user));
});
```

### Summary: Package Behavior

| Scenario | Privacy Installed | Analytics Behavior |
|----------|-------------------|-------------------|
| Consent UI | Yes | Analytics doesn't show its banner |
| Consent Check | Yes | Defers to `privacyHasConsent('analytics')` |
| Consent Storage | Yes | Privacy stores, Analytics reads |
| Consent Events | Yes | Listens for Privacy events |
| Data Deletion | Yes | Responds to Privacy deletion events |
| Data Export | Yes | Contributes data to Privacy exports |
| Consent UI | No | Shows its own consent banner |
| Consent Check | No | Uses its own consent mechanism |

---

## Integration Points

### Model Trait: HasPersonalData

```php
use ArtisanPackUI\Privacy\Traits\HasPersonalData;

class User extends Authenticatable
{
    use HasPersonalData;

    /**
     * Define personal data fields and their handling.
     */
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
            'phone' => [
                'type' => 'phone',
                'sensitivity' => 'normal',
                'deletion_strategy' => 'delete',
            ],
        ];
    }

    /**
     * Define related models with personal data.
     */
    protected function personalDataRelations(): array
    {
        return ['profile', 'orders', 'comments'];
    }
}
```

### Blade Directives

```blade
{{-- Check consent before loading script --}}
@hasConsent('analytics')
    <script src="analytics.js"></script>
@endhasConsent

{{-- Show content based on consent --}}
@consentRequired('marketing')
    <p>Enable marketing cookies to see personalized content.</p>
@else
    <div>Personalized content here</div>
@endconsentRequired
```

### JavaScript API

```javascript
// Check consent
if (window.PrivacyConsent.hasConsent('analytics')) {
    // Load analytics
}

// Listen for consent changes
window.addEventListener('privacy:consent-updated', (e) => {
    const { category, granted } = e.detail;
    if (category === 'analytics' && granted) {
        loadAnalytics();
    }
});

// Programmatically open preferences
window.PrivacyConsent.openPreferences();
```

### Helper Functions

```php
// Check consent
if (privacyHasConsent('analytics')) {
    // Track event
}

// Get user's applicable regulation
$regulation = privacyGetRegulation(); // 'gdpr', 'ccpa', null

// Submit data request programmatically
privacyRequestDataExport($user);
privacyRequestDataDeletion($user, 'User requested account deletion');

// Anonymize a model
privacyAnonymize($user);

// Check if user can be deleted
if (privacyCanDelete($user)) {
    // No blocking relationships
}
```

---

## Testing Strategy

### Unit Tests

- ConsentService methods
- AnonymizationService strategies
- PersonalDataScanner detection
- Regulation rule implementations

### Feature Tests

- Cookie banner flow
- Data request submission and processing
- Admin dashboard operations
- API endpoints
- Middleware behavior

### Browser Tests (Dusk)

- Cookie banner interaction
- Consent preferences modal
- Data request form submission

### Test Helpers

```php
// Trait for test classes
use ArtisanPackUI\Privacy\Testing\PrivacyTestHelpers;

class MyTest extends TestCase
{
    use PrivacyTestHelpers;

    public function test_something()
    {
        // Grant consent for test
        $this->grantConsent($user, 'analytics');

        // Assert consent state
        $this->assertHasConsent($user, 'analytics');

        // Simulate GDPR user
        $this->asGdprUser();

        // Simulate CCPA user
        $this->asCcpaUser();
    }
}
```

---

## Documentation

### Required Documentation

1. **README.md**: Quick start guide
2. **INSTALLATION.md**: Detailed installation steps
3. **CONFIGURATION.md**: All configuration options
4. **COMPONENTS.md**: Livewire component documentation
5. **API.md**: API endpoint documentation
6. **REGULATIONS.md**: Regulation-specific guidance
7. **UPGRADING.md**: Version upgrade notes

### Wiki Pages

- Getting Started
- Configuration Deep Dive
- Cookie Consent Setup
- Data Subject Rights Implementation
- Admin Dashboard Customization
- Extending the Package
- Troubleshooting

---

## Implementation Phases

### Phase 1: Core Infrastructure (Weeks 1-2)

**Goals**: Establish package foundation

**Tasks**:
- [ ] Set up package structure following package-blueprint
- [ ] Create database migrations
- [ ] Implement core models (Consent, DataRequest, etc.)
- [ ] Create ConsentService with basic operations
- [ ] Implement configuration system
- [ ] Create Privacy facade
- [ ] Write helper functions
- [ ] Set up events system
- [ ] Basic unit tests

**Deliverables**:
- Working package scaffold
- Database schema implemented
- Core service layer functional

### Phase 2: Cookie Consent (Weeks 3-4)

**Goals**: Complete cookie consent functionality

**Tasks**:
- [ ] Create CookieBanner Livewire component
- [ ] Create ConsentPreferences Livewire component
- [ ] Implement cookie storage
- [ ] Implement database storage
- [ ] Create JavaScript API
- [ ] Add Blade directives
- [ ] Implement consent expiration
- [ ] Add middleware (CheckCookieConsent)
- [ ] Feature tests for consent flow
- [ ] Browser tests for UI

**Deliverables**:
- Fully functional cookie consent system
- Configurable banner and preferences UI

### Phase 3: Data Subject Rights (Weeks 5-6)

**Goals**: Implement data access, export, and deletion

**Tasks**:
- [ ] Create DataRequestForm Livewire component
- [ ] Implement DataExportService
- [ ] Implement DataDeletionService
- [ ] Implement AnonymizationService
- [ ] Create identity verification flow
- [ ] Implement request processing workflow
- [ ] Add notifications (user and admin)
- [ ] Create PrivacyDashboard (user-facing)
- [ ] Feature tests for data requests

**Deliverables**:
- Users can submit data requests
- Automatic processing for access/export
- Manual review workflow for deletions

### Phase 4: Personal Data Discovery (Week 7)

**Goals**: Implement data mapping and discovery

**Tasks**:
- [ ] Create HasPersonalData trait
- [ ] Implement PersonalDataScanner
- [ ] Create field pattern detection
- [ ] Implement data inventory report
- [ ] Create privacy:scan command
- [ ] Tests for scanner

**Deliverables**:
- Automatic detection of personal data fields
- Manual override capabilities
- Data inventory generation

### Phase 5: Regulations Layer (Week 8)

**Goals**: Implement regulation-specific logic

**Tasks**:
- [ ] Create BaseRegulation class
- [ ] Implement GDPR regulation
- [ ] Implement CCPA regulation
- [ ] Add geolocation service
- [ ] Implement regulation detection
- [ ] Add regulation-specific consent requirements
- [ ] Tests for regulation rules

**Deliverables**:
- Multi-regulation support
- Automatic regulation detection
- Compliant workflows per regulation

### Phase 6: Admin Dashboard (Week 9)

**Goals**: Create basic admin interface

**Tasks**:
- [ ] Create admin ConsentManager component
- [ ] Create admin DataRequestManager component
- [ ] Create admin ComplianceReport component
- [ ] Implement admin routes
- [ ] Add authorization gates
- [ ] Tests for admin features

**Deliverables**:
- Basic admin dashboard
- Request management
- Compliance reporting

### Phase 7: Breach Notification (Week 10)

**Goals**: Implement breach handling

**Tasks**:
- [ ] Create BreachNotification model
- [ ] Implement BreachNotificationService
- [ ] Create breach reporting workflow
- [ ] Add authority notification templates
- [ ] Add user notification templates
- [ ] Create admin breach management UI
- [ ] Tests for breach workflow

**Deliverables**:
- Breach logging and tracking
- Notification workflow
- Compliance with notification deadlines

### Phase 8: Privacy Policy (Week 11)

**Goals**: Policy generation and management

**Tasks**:
- [ ] Create PrivacyPolicy model
- [ ] Implement PrivacyPolicyGenerator
- [ ] Create policy templates per regulation
- [ ] Add version tracking
- [ ] Implement re-consent workflow
- [ ] Create policy display routes/views
- [ ] Tests for policy generation

**Deliverables**:
- Template-based policy generation
- Version history
- Re-consent on major changes

### Phase 9: Polish & Documentation (Week 12)

**Goals**: Production readiness

**Tasks**:
- [ ] Complete all documentation
- [ ] Add comprehensive test coverage
- [ ] Performance optimization
- [ ] Code style compliance
- [ ] Security audit
- [ ] Create example application
- [ ] Write Wiki documentation
- [ ] Prepare CHANGELOG

**Deliverables**:
- Complete documentation
- 80%+ test coverage
- Production-ready release

---

## Dependencies

### Required

- `php`: ^8.2
- `illuminate/support`: ^10.0|^11.0|^12.0
- `artisanpack-ui/core`: ^1.0
- `artisanpack-ui/livewire-ui-components`: ^2.0

### Optional (Suggested)

- `guzzlehttp/guzzle`: For geolocation API calls
- `dompdf/dompdf` or `barryvdh/laravel-dompdf`: For PDF report generation

### Dev Dependencies

- `pestphp/pest`: ^3.8
- `orchestra/testbench`: ^10.2
- `artisanpack-ui/code-style`: ^1.1
- `artisanpack-ui/code-style-pint`: ^1.1

---

## Success Metrics

### Technical

- [ ] 80%+ code coverage
- [ ] All code style checks pass
- [ ] Zero known security vulnerabilities
- [ ] Compatible with Laravel 10, 11, 12
- [ ] Sub-100ms consent check performance

### Compliance

- [ ] GDPR requirements checklist complete
- [ ] CCPA requirements checklist complete
- [ ] Audit trail meets regulatory requirements
- [ ] Data export format meets portability requirements

### User Experience

- [ ] Cookie banner loads in <500ms
- [ ] Data export completes in <30s for typical user
- [ ] Admin dashboard responsive on mobile
- [ ] Accessibility (WCAG 2.1 AA) compliance

---

## Open Questions

1. **Third-party cookie scanning**: Should we integrate with external services (CookieBot, OneTrust) or provide our own scanner?

2. **Multi-tenant support**: Should we support multi-tenancy out of the box, or leave that to package consumers?

3. **Queue processing**: Should data requests be processed via queues by default, or synchronously?

4. **Audit log retention**: How long should audit logs be retained? Configurable?

5. **Privacy policy AI generation**: Should we integrate with AI for policy generation, or stick to templates?

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 0.1 | Jan 2026 | Jacob Martella | Initial plan draft |
| 0.2 | Jan 2026 | Jacob Martella | Added View Customization section; Added Analytics Package Integration section |
