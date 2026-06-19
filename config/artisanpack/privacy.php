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
		'auto_process'         => [
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
		'enabled'        => true,
		'prefix'         => 'privacy',
		'middleware'     => [ 'web' ],
		'api_prefix'     => 'api/privacy',
		'api_middleware' => [ 'api' ],
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
