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
    | Features are organized hierarchically with dependencies automatically
    | managed by the FeatureToggleService.
    |
    */

    'features' => [
        // Core Security Features (always recommended)
        'spam_detection' => env('FORM_SECURITY_SPAM_DETECTION', true),
        'csrf_protection' => env('FORM_SECURITY_CSRF', true),
        'honeypot' => env('FORM_SECURITY_HONEYPOT', true),
        'rate_limiting' => env('FORM_SECURITY_RATE_LIMITING', true),

        // Advanced Security Features
        'ip_reputation' => env('FORM_SECURITY_IP_REPUTATION', false),
        'geolocation' => env('FORM_SECURITY_GEOLOCATION', false),
        'ai_analysis' => env('FORM_SECURITY_AI_ANALYSIS', false),
        'advanced_pattern_matching' => env('FORM_SECURITY_ADVANCED_PATTERNS', false),

        // Performance Features
        'caching' => env('FORM_SECURITY_CACHING', true),
        'query_optimization' => env('FORM_SECURITY_QUERY_OPTIMIZATION', true),
        'lazy_loading' => env('FORM_SECURITY_LAZY_LOADING', true),

        // Monitoring & Logging Features
        'logging' => env('FORM_SECURITY_LOGGING', true),
        'performance_monitoring' => env('FORM_SECURITY_PERFORMANCE_MONITORING', false),
        'analytics' => env('FORM_SECURITY_ANALYTICS', false),
        'debug_mode' => env('FORM_SECURITY_DEBUG', false),

        // Integration Features
        'notifications' => env('FORM_SECURITY_NOTIFICATIONS', false),
        'webhooks' => env('FORM_SECURITY_WEBHOOKS', false),
        'api_access' => env('FORM_SECURITY_API_ACCESS', false),
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
    | Configuration Management
    |--------------------------------------------------------------------------
    |
    | Settings for the configuration management system including caching,
    | validation, and hierarchical loading behavior.
    |
    */

    'configuration' => [
        'cache_enabled' => env('FORM_SECURITY_CONFIG_CACHE', true),
        'cache_ttl' => env('FORM_SECURITY_CONFIG_CACHE_TTL', 3600), // 1 hour
        'validation_enabled' => env('FORM_SECURITY_CONFIG_VALIDATION', true),
        'encryption_enabled' => env('FORM_SECURITY_CONFIG_ENCRYPTION', true),
        'change_tracking' => env('FORM_SECURITY_CONFIG_CHANGE_TRACKING', true),
        'performance_monitoring' => env('FORM_SECURITY_CONFIG_PERFORMANCE', false),

        'sources' => [
            'priority_order' => ['runtime', 'environment', 'database', 'cache', 'file', 'default'],
            'timeout_seconds' => [
                'database' => 10,
                'api' => 30,
                'remote' => 30,
                'file' => 5,
            ],
        ],

        'validation' => [
            'strict_mode' => env('FORM_SECURITY_CONFIG_STRICT', false),
            'business_rules' => env('FORM_SECURITY_CONFIG_BUSINESS_RULES', true),
            'security_constraints' => env('FORM_SECURITY_CONFIG_SECURITY_CONSTRAINTS', true),
            'performance_constraints' => env('FORM_SECURITY_CONFIG_PERFORMANCE_CONSTRAINTS', true),
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
        'configuration_load_target' => 10, // milliseconds
        'cache_warm_on_boot' => env('FORM_SECURITY_CACHE_WARM_BOOT', true),
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
    | Feature Toggle Configuration
    |--------------------------------------------------------------------------
    |
    | Configure feature toggle behavior including dependencies, fallback
    | strategies, and graceful degradation settings.
    |
    */

    'feature_toggles' => [
        'cache_enabled' => env('FORM_SECURITY_FEATURE_CACHE', true),
        'graceful_degradation' => env('FORM_SECURITY_GRACEFUL_DEGRADATION', true),
        'dependency_checking' => env('FORM_SECURITY_DEPENDENCY_CHECKING', true),
        'context_evaluation' => env('FORM_SECURITY_CONTEXT_EVALUATION', true),

        'dependencies' => [
            'ai_analysis' => ['spam_detection'],
            'geolocation' => ['ip_reputation'],
            'advanced_logging' => ['logging'],
            'advanced_pattern_matching' => ['spam_detection'],
            'webhooks' => ['notifications'],
        ],

        'safe_defaults' => [
            'spam_detection' => true,
            'csrf_protection' => true,
            'logging' => true,
            'caching' => true,
        ],

        'fallback_strategies' => [
            'ai_analysis' => 'rule_based_detection',
            'geolocation' => 'unknown_location',
            'ip_reputation' => 'neutral_reputation',
        ],
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
        'configuration_debug' => env('FORM_SECURITY_DEBUG_CONFIG', false),
        'feature_toggle_debug' => env('FORM_SECURITY_DEBUG_FEATURES', false),
    ],
];
