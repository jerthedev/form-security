<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | FormSecurity Package Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for the JTD FormSecurity
    | package. You can customize the behavior of spam detection, validation,
    | and security features according to your application's needs.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Package Status
    |--------------------------------------------------------------------------
    |
    | Enable or disable the entire FormSecurity package. When disabled,
    | all security checks will be bypassed and forms will be processed
    | without any spam detection or validation.
    |
    */

    'enabled' => env('FORM_SECURITY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Control which features of the FormSecurity package are enabled.
    | You can selectively enable or disable features based on your needs.
    |
    */

    'features' => [
        'spam_detection' => env('FORM_SECURITY_SPAM_DETECTION', true),
        'rate_limiting' => env('FORM_SECURITY_RATE_LIMITING', true),
        'ip_reputation' => env('FORM_SECURITY_IP_REPUTATION', false),
        'geolocation' => env('FORM_SECURITY_GEOLOCATION', false),
        'ai_analysis' => env('FORM_SECURITY_AI_ANALYSIS', false),
        'caching' => env('FORM_SECURITY_CACHING', true),
        'logging' => env('FORM_SECURITY_LOGGING', true),
        'honeypot' => env('FORM_SECURITY_HONEYPOT', true),
        'csrf_protection' => env('FORM_SECURITY_CSRF', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam Detection Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the spam detection thresholds and behavior. The spam_threshold
    | determines at what score a submission is considered spam (0.0 = clean,
    | 1.0 = definitely spam).
    |
    */

    'spam_threshold' => env('FORM_SECURITY_SPAM_THRESHOLD', 0.7),

    'spam_action' => env('FORM_SECURITY_SPAM_ACTION', 'block'), // block, flag, log

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting to prevent abuse and spam. These settings
    | control how many submissions are allowed per time window.
    |
    */

    'rate_limit' => [
        'max_attempts' => env('FORM_SECURITY_RATE_LIMIT_ATTEMPTS', 10),
        'window_minutes' => env('FORM_SECURITY_RATE_LIMIT_WINDOW', 60),
        'block_duration_minutes' => env('FORM_SECURITY_BLOCK_DURATION', 1440), // 24 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Address Configuration
    |--------------------------------------------------------------------------
    |
    | Configure IP address handling including blocked IPs, whitelisted IPs,
    | and IP reputation checking settings.
    |
    */

    'ip_settings' => [
        'blocked_ips' => [
            // Add IP addresses to block
        ],
        'whitelisted_ips' => [
            // Add IP addresses to always allow
        ],
        'check_proxies' => env('FORM_SECURITY_CHECK_PROXIES', true),
        'reputation_threshold' => env('FORM_SECURITY_IP_REPUTATION_THRESHOLD', 0.8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Configure custom validation rules and field-specific settings.
    |
    */

    'validation' => [
        'min_content_length' => env('FORM_SECURITY_MIN_CONTENT_LENGTH', 10),
        'max_content_length' => env('FORM_SECURITY_MAX_CONTENT_LENGTH', 5000),
        'max_links_per_submission' => env('FORM_SECURITY_MAX_LINKS', 3),
        'required_fields' => [
            // Add fields that must be present
        ],
        'forbidden_patterns' => [
            // Add regex patterns that should not be allowed
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance-related settings including caching, timeouts,
    | and resource limits.
    |
    */

    'performance' => [
        'cache_ttl' => env('FORM_SECURITY_CACHE_TTL', 3600), // 1 hour
        'analysis_timeout' => env('FORM_SECURITY_ANALYSIS_TIMEOUT', 5), // seconds
        'max_memory_usage' => env('FORM_SECURITY_MAX_MEMORY', 50), // MB
        'enable_query_optimization' => env('FORM_SECURITY_OPTIMIZE_QUERIES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging behavior for security events, spam detection,
    | and performance monitoring.
    |
    */

    'logging' => [
        'enabled' => env('FORM_SECURITY_LOGGING_ENABLED', true),
        'level' => env('FORM_SECURITY_LOG_LEVEL', 'info'),
        'channel' => env('FORM_SECURITY_LOG_CHANNEL', 'default'),
        'log_spam_attempts' => env('FORM_SECURITY_LOG_SPAM', true),
        'log_blocked_ips' => env('FORM_SECURITY_LOG_BLOCKED_IPS', true),
        'log_performance' => env('FORM_SECURITY_LOG_PERFORMANCE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure database-related settings including table names and
    | connection preferences.
    |
    */

    'database' => [
        'connection' => env('FORM_SECURITY_DB_CONNECTION', null),
        'table_prefix' => env('FORM_SECURITY_TABLE_PREFIX', 'form_security_'),
        'enable_migrations' => env('FORM_SECURITY_ENABLE_MIGRATIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configure notifications for security events and spam detection.
    |
    */

    'notifications' => [
        'enabled' => env('FORM_SECURITY_NOTIFICATIONS_ENABLED', false),
        'channels' => ['mail'], // mail, slack, discord, etc.
        'notify_on_spam' => env('FORM_SECURITY_NOTIFY_SPAM', false),
        'notify_on_attacks' => env('FORM_SECURITY_NOTIFY_ATTACKS', true),
        'admin_email' => env('FORM_SECURITY_ADMIN_EMAIL', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    |
    | Configure debugging and development features. These should be disabled
    | in production environments.
    |
    */

    'debug' => [
        'enabled' => env('FORM_SECURITY_DEBUG', false),
        'show_analysis_details' => env('FORM_SECURITY_DEBUG_ANALYSIS', false),
        'log_all_submissions' => env('FORM_SECURITY_DEBUG_LOG_ALL', false),
        'bypass_security' => env('FORM_SECURITY_DEBUG_BYPASS', false),
    ],
];
