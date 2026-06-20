<?php

/**
 * Privacy package configuration.
 *
 * Controls regulation coverage, consent storage, cookie categories, data
 * subject request handling, deletion/anonymization strategies, geolocation,
 * UI presentation, routing, and admin dashboard access.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

return [

	/*
	|--------------------------------------------------------------------------
	| Package Toggle
	|--------------------------------------------------------------------------
	|
	| Master switch for the package. Disabling skips migration loading,
	| route registration, and middleware aliasing in the service provider.
	|
	*/
	'enabled' => env( 'PRIVACY_ENABLED', true ),

	/*
	|--------------------------------------------------------------------------
	| Enabled Regulations
	|--------------------------------------------------------------------------
	|
	| Specify which privacy regulations the application needs to comply with.
	| This affects consent requirements, user rights, and notification rules.
	|
	*/
	'regulations' => [
		'gdpr' => [
			'enabled'                   => env( 'PRIVACY_GDPR_ENABLED', true ),
			'applies_to'                => [ 'EU', 'EEA', 'UK' ],
			'consent_expiry_days'       => 365,
			'breach_notification_hours' => 72,
		],
		'ccpa' => [
			'enabled'             => env( 'PRIVACY_CCPA_ENABLED', true ),
			'applies_to'          => [ 'US-CA' ],
			'opt_out_sale'        => true,
			'financial_threshold' => 25000000,
		],
		'lgpd' => [
			'enabled'    => env( 'PRIVACY_LGPD_ENABLED', false ),
			'applies_to' => [ 'BR' ],
		],
		'pipeda' => [
			'enabled'    => env( 'PRIVACY_PIPEDA_ENABLED', false ),
			'applies_to' => [ 'CA' ],
		],
	],

	/*
	|--------------------------------------------------------------------------
	| Consent Storage
	|--------------------------------------------------------------------------
	|
	| Configure how user consent is persisted. The `storage` driver chooses
	| between database-only, cookie-only, or both for layered persistence.
	|
	*/
	'consent' => [
		'storage'                => env( 'PRIVACY_CONSENT_STORAGE', 'both' ),
		'cookie_name'            => env( 'PRIVACY_CONSENT_COOKIE', 'privacy_consent' ),
		'cookie_lifetime'        => (int) env( 'PRIVACY_CONSENT_COOKIE_LIFETIME', 365 ),
		'require_authentication' => false,
		'guest_identifier'       => 'fingerprint',
	],

	/*
	|--------------------------------------------------------------------------
	| Cookie Categories
	|--------------------------------------------------------------------------
	|
	| Define the cookie consent categories presented to users. The `necessary`
	| category should always remain required.
	|
	*/
	'cookie_categories' => [
		'necessary' => [
			'name'        => 'Strictly Necessary',
			'description' => 'Essential cookies required for the website to function properly.',
			'required'    => true,
			'cookies'     => [ 'session', 'csrf', 'privacy_consent' ],
		],
		'functional' => [
			'name'        => 'Functional',
			'description' => 'Cookies that enable enhanced functionality and personalization.',
			'required'    => false,
			'cookies'     => [ 'language', 'timezone', 'preferences' ],
		],
		'analytics' => [
			'name'        => 'Analytics',
			'description' => 'Cookies that help us understand how visitors use our website.',
			'required'    => false,
			'cookies'     => [ '_ga', '_gid', '_gat' ],
		],
		'marketing' => [
			'name'        => 'Marketing',
			'description' => 'Cookies used to deliver relevant advertisements.',
			'required'    => false,
			'cookies'     => [ '_fbp', 'fr' ],
		],
	],

	/*
	|--------------------------------------------------------------------------
	| Data Subject Rights
	|--------------------------------------------------------------------------
	|
	| Configure how data subject requests (access, deletion, export,
	| rectification) are received and processed.
	|
	*/
	'data_requests' => [
		'enabled'              => true,
		'require_verification' => true,
		'verification_method'  => env( 'PRIVACY_VERIFICATION_METHOD', 'email' ),

		/*
		| Request types the package will accept on the JSON endpoint and the
		| Livewire/React/Vue forms. Remove a type from this list to disable
		| it everywhere — the JSON validator enforces the same allowlist as
		| the UI, so clients cannot bypass a UI restriction via curl.
		*/
		'allowed_types' => [ 'access', 'export', 'deletion', 'rectification' ],

		'auto_process' => [
			'access'   => true,
			'export'   => true,
			'deletion' => false,
		],
		'response_days' => [
			'gdpr'    => 30,
			'ccpa'    => 45,
			'default' => 30,
		],
		'export_format' => env( 'PRIVACY_EXPORT_FORMAT', 'json' ),
		'notify_admin'  => true,
		'admin_email'   => env( 'PRIVACY_ADMIN_EMAIL' ),

		/*
		| Per-user rate limit applied to the JSON `/data-requests` endpoints
		| (both index and store) as `hits,minutes`. Keyed by authenticated
		| user when present, otherwise by IP. Tune down for tighter
		| enumeration protection; tune up for high-traffic dashboard
		| polling.
		*/
		'api_rate_limit' => env( 'PRIVACY_DATA_REQUESTS_API_RATE_LIMIT', '60,1' ),
	],

	/*
	|--------------------------------------------------------------------------
	| Notifications
	|--------------------------------------------------------------------------
	|
	| Channels and queue used by the package's data-request notifications.
	| Each notification key (received/verification/processing/completed/
	| rejected/admin) may override the channel list independently.
	|
	*/
	'notifications' => [
		'queue'    => env( 'PRIVACY_NOTIFICATIONS_QUEUE', 'default' ),
		'channels' => [ 'mail', 'database' ],

		'received'     => [ 'channels' => [ 'mail', 'database' ] ],
		'verification' => [ 'channels' => [ 'mail' ] ],
		'processing'   => [ 'channels' => [ 'database' ] ],
		'completed'    => [ 'channels' => [ 'mail', 'database' ] ],
		'rejected'     => [ 'channels' => [ 'mail', 'database' ] ],
		'admin'        => [ 'channels' => [ 'mail' ] ],
	],

	/*
	|--------------------------------------------------------------------------
	| Data Export
	|--------------------------------------------------------------------------
	|
	| File storage and signed-URL settings for `DataExportService::exportToFile()`.
	|
	*/
	'export' => [
		'disk'                    => env( 'PRIVACY_EXPORT_DISK', 'local' ),
		'directory'               => 'privacy-exports',
		'url_ttl'                 => (int) env( 'PRIVACY_EXPORT_URL_TTL', 60 ),
		'file_retention_minutes'  => (int) env( 'PRIVACY_EXPORT_RETENTION_MINUTES', 1440 ),

		/*
		| Per-section row cap for export collection (consent history,
		| activity log, data-request summary). Prevents OOM for very
		| high-volume subjects. Raise if your compliance posture requires
		| a complete export regardless of size.
		*/
		'row_cap' => (int) env( 'PRIVACY_EXPORT_ROW_CAP', 10000 ),
	],

	/*
	|--------------------------------------------------------------------------
	| Identity Verification
	|--------------------------------------------------------------------------
	|
	| Settings for the data subject identity-verification flow that gates
	| access/deletion/export requests. The token is stored on the request
	| row and confirmed via the configured channel.
	|
	*/
	'verification' => [
		'token_ttl_minutes' => (int) env( 'PRIVACY_VERIFICATION_TTL', 60 ),
		'rate_limit'        => env( 'PRIVACY_VERIFICATION_RATE_LIMIT', '6,1' ),
	],

	/*
	|--------------------------------------------------------------------------
	| Data Deletion Strategies
	|--------------------------------------------------------------------------
	|
	| Defaults applied when the package handles a deletion request without
	| a more specific per-field strategy.
	|
	*/
	'deletion' => [
		'default_strategy'  => env( 'PRIVACY_DELETION_STRATEGY', 'anonymize' ),
		'cascade'           => true,
		'preserve_audit'    => true,
		'grace_period_days' => (int) env( 'PRIVACY_DELETION_GRACE_DAYS', 30 ),
	],

	/*
	|--------------------------------------------------------------------------
	| Anonymization Settings
	|--------------------------------------------------------------------------
	|
	| Per-field strategies used when anonymizing personal data. Strategy
	| names map to AnonymizationService methods.
	|
	*/
	'anonymization' => [
		'strategies' => [
			'email'         => 'mask',
			'name'          => 'pseudonymize',
			'phone'         => 'redact',
			'address'       => 'generalize',
			'ip_address'    => 'truncate',
			'date_of_birth' => 'generalize',
		],
		'hash_algorithm'          => 'sha256',
		'pseudonymization_prefix' => 'Anon_',

		/*
		| Emit an audit-log entry (Log::info on the `privacy.anonymization`
		| channel) every time a model has at least one field anonymized.
		| Records only model class, primary key, and column names — never
		| the original or transformed values.
		*/
		'audit' => true,
	],

	/*
	|--------------------------------------------------------------------------
	| Personal Data Discovery
	|--------------------------------------------------------------------------
	|
	| Settings for the personal data scanner that maps which models hold
	| which sensitive fields.
	|
	*/
	'discovery' => [
		'auto_scan'  => true,
		'scan_paths' => [
			app_path( 'Models' ),
		],
		'exclude_models' => [],
		'field_patterns' => [
			'email'   => [ 'email', 'e_mail', 'email_address' ],
			'name'    => [ 'name', 'first_name', 'last_name', 'full_name' ],
			'phone'   => [ 'phone', 'telephone', 'mobile', 'cell' ],
			'address' => [ 'address', 'street', 'city', 'zip', 'postal' ],
			'ip'      => [ 'ip', 'ip_address', 'ipaddress' ],
			'ssn'     => [ 'ssn', 'social_security', 'tax_id' ],
		],
	],

	/*
	|--------------------------------------------------------------------------
	| Geolocation
	|--------------------------------------------------------------------------
	|
	| Used by the regulation resolver to select the applicable regulation
	| based on the visitor's region.
	|
	*/
	'geolocation' => [
		'enabled'         => env( 'PRIVACY_GEOLOCATION_ENABLED', true ),
		'provider'        => env( 'PRIVACY_GEOLOCATION_PROVIDER', 'ip-api' ),
		'cache_duration'  => 1440,
		'fallback_region' => env( 'PRIVACY_FALLBACK_REGION' ),
	],

	/*
	|--------------------------------------------------------------------------
	| UI Settings
	|--------------------------------------------------------------------------
	|
	| Controls the appearance of the cookie banner and preferences UI.
	|
	*/
	'ui' => [
		'cookie_banner' => [
			'position'        => 'bottom',
			'style'           => 'bar',
			'show_reject_all' => true,
			'show_customize'  => true,
			'blur_background' => false,
		],
		'theme' => 'auto',
	],

	/*
	|--------------------------------------------------------------------------
	| Routes
	|--------------------------------------------------------------------------
	|
	| Routing configuration for both the public web routes and the JSON API.
	|
	*/
	'routes' => [
		'enabled'    => true,
		'prefix'     => 'privacy',
		'middleware' => [ 'web' ],
		'api_prefix' => 'api/privacy',

		/*
		| The consent endpoints rely on session and cookie middleware so
		| Auth::user() resolves and the consent cookie is readable, so
		| `web` is the right default for stateful applications. Swap to
		| `api` (or your own stack) only if you've layered session/cookie
		| handling in elsewhere — for example via Sanctum's stateful
		| middleware on an SPA route.
		*/
		'api_middleware' => [ 'web' ],
	],

	/*
	|--------------------------------------------------------------------------
	| Scheduling
	|--------------------------------------------------------------------------
	|
	| Schedule entries the package adds to Laravel's scheduler automatically.
	| Set `enabled` to false to opt out and run the command from your own
	| `app/Console/Kernel.php` instead.
	|
	*/
	'scheduling' => [
		'purge_expired' => [
			'enabled'  => true,
			'cron'     => '0 3 * * *',
			'prune'    => false,
			'timezone' => null,
		],
	],

	/*
	|--------------------------------------------------------------------------
	| Middleware
	|--------------------------------------------------------------------------
	|
	| Behaviour for the `privacy.consent` route guard. `action` decides what
	| happens when a required category is missing: `abort` returns the HTTP
	| status below; `redirect` sends the visitor to `redirect_route` (when
	| set) or `redirect_url`.
	|
	*/
	'middleware' => [
		'ensure_consent' => [
			'action'         => env( 'PRIVACY_CONSENT_MIDDLEWARE_ACTION', 'abort' ),
			'status'         => 403,
			'message'        => 'Consent required.',
			'redirect_route' => null,
			'redirect_url'   => '/',
		],
	],

	/*
	|--------------------------------------------------------------------------
	| Admin Dashboard
	|--------------------------------------------------------------------------
	|
	| Authorization for the admin dashboard. Override the `gate` in your
	| application's AuthServiceProvider to grant access.
	|
	*/
	'admin' => [
		'enabled'      => true,
		'route_prefix' => 'admin/privacy',
		'middleware'   => [ 'web', 'auth' ],
		'gate'         => 'manage-privacy',
	],

];
