<?php

declare(strict_types=1);

/**
 * Configuration File: form-security-performance.php
 *
 * EPIC: EPIC-006-analytics-monitoring
 * SPEC: SPEC-013-performance-monitoring-profiling
 * SPRINT: Sprint-005-performance-monitoring
 * TICKET: 2053-performance-monitoring-configuration
 *
 * Description: Comprehensive performance monitoring configuration for
 * metrics collection, profiling, alerting, and reporting capabilities.
 *
 * @see docs/Planning/Epics/EPIC-006-analytics-monitoring.md
 * @see docs/Planning/Specs/Analytics-Monitoring/SPEC-013-performance-monitoring-profiling.md
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Enable or disable performance monitoring features. When enabled, the
    | system will collect detailed metrics about database queries, cache
    | operations, memory usage, and execution times.
    |
    */

    'enabled' => env('FORM_SECURITY_PERFORMANCE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Monitoring Features
    |--------------------------------------------------------------------------
    |
    | Control which performance monitoring features are active. Features
    | can be individually enabled/disabled based on your needs and
    | performance requirements.
    |
    */

    'features' => [
        'query_monitoring' => env('FORM_SECURITY_QUERY_MONITORING', true),
        'memory_tracking' => env('FORM_SECURITY_MEMORY_TRACKING', true),
        'profiling' => env('FORM_SECURITY_PROFILING', true),
        'cache_metrics' => env('FORM_SECURITY_CACHE_METRICS', true),
        'alerting' => env('FORM_SECURITY_PERFORMANCE_ALERTING', true),
        'detailed_logging' => env('FORM_SECURITY_DETAILED_LOGGING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    |
    | Define performance thresholds for alerting and monitoring. Values
    | are in milliseconds for time-based metrics and bytes for memory.
    |
    */

    'thresholds' => [
        // Database query thresholds (milliseconds)
        'slow_query_threshold' => (int) env('FORM_SECURITY_SLOW_QUERY_THRESHOLD', 100),
        'critical_query_threshold' => (int) env('FORM_SECURITY_CRITICAL_QUERY_THRESHOLD', 500),

        // Memory usage thresholds (bytes)
        'memory_warning_threshold' => (int) env('FORM_SECURITY_MEMORY_WARNING_THRESHOLD', 52428800), // 50MB
        'memory_critical_threshold' => (int) env('FORM_SECURITY_MEMORY_CRITICAL_THRESHOLD', 104857600), // 100MB

        // Operation duration thresholds (milliseconds)
        'operation_slow_threshold' => (int) env('FORM_SECURITY_OPERATION_SLOW_THRESHOLD', 200),
        'operation_critical_threshold' => (int) env('FORM_SECURITY_OPERATION_CRITICAL_THRESHOLD', 1000),

        // Cache performance thresholds
        'cache_hit_ratio_threshold' => (float) env('FORM_SECURITY_CACHE_HIT_RATIO_THRESHOLD', 0.85), // 85%
        'cache_miss_ratio_threshold' => (float) env('FORM_SECURITY_CACHE_MISS_RATIO_THRESHOLD', 0.20), // 20%
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Configure how long performance monitoring data is retained. Longer
    | retention periods provide better analysis but consume more storage.
    |
    */

    'retention' => [
        // Cache TTL for performance data (seconds)
        'metrics_ttl' => (int) env('FORM_SECURITY_METRICS_TTL', 3600), // 1 hour
        'alerts_ttl' => (int) env('FORM_SECURITY_ALERTS_TTL', 7200), // 2 hours
        'profiling_ttl' => (int) env('FORM_SECURITY_PROFILING_TTL', 7200), // 2 hours
        'query_stats_ttl' => (int) env('FORM_SECURITY_QUERY_STATS_TTL', 3600), // 1 hour

        // Maximum number of records to keep
        'max_metrics' => (int) env('FORM_SECURITY_MAX_METRICS', 1000),
        'max_alerts' => (int) env('FORM_SECURITY_MAX_ALERTS', 100),
        'max_slow_queries' => (int) env('FORM_SECURITY_MAX_SLOW_QUERIES', 100),
        'max_profiling_sessions' => (int) env('FORM_SECURITY_MAX_PROFILING_SESSIONS', 50),
        'max_operations_per_session' => (int) env('FORM_SECURITY_MAX_OPERATIONS_PER_SESSION', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiling Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the performance profiler behavior, including call stack
    | depth, memory tracking, and session management.
    |
    */

    'profiling' => [
        'enabled' => env('FORM_SECURITY_PROFILING_ENABLED', true),
        'memory_tracking' => env('FORM_SECURITY_PROFILING_MEMORY_TRACKING', true),
        'call_stack_depth' => (int) env('FORM_SECURITY_CALL_STACK_DEPTH', 10),
        'auto_start' => env('FORM_SECURITY_PROFILING_AUTO_START', false),
        'sample_rate' => (float) env('FORM_SECURITY_PROFILING_SAMPLE_RATE', 1.0), // 100% by default

        // Operations to automatically profile
        'auto_profile_operations' => [
            'database_queries',
            'cache_operations',
            'service_provider_boot',
            'middleware_execution',
            'console_commands',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance alerting behavior, including notification
    | methods and escalation thresholds.
    |
    */

    'alerting' => [
        'enabled' => env('FORM_SECURITY_ALERTING_ENABLED', true),

        // Alert channels (log, mail, slack, etc.)
        'channels' => [
            'log' => [
                'enabled' => true,
                'level' => env('FORM_SECURITY_ALERT_LOG_LEVEL', 'warning'),
            ],
            'mail' => [
                'enabled' => env('FORM_SECURITY_ALERT_MAIL_ENABLED', false),
                'to' => env('FORM_SECURITY_ALERT_MAIL_TO'),
                'subject' => 'Form Security Performance Alert',
            ],
            'slack' => [
                'enabled' => env('FORM_SECURITY_ALERT_SLACK_ENABLED', false),
                'webhook_url' => env('FORM_SECURITY_ALERT_SLACK_WEBHOOK'),
                'channel' => env('FORM_SECURITY_ALERT_SLACK_CHANNEL', '#alerts'),
            ],
        ],

        // Alert throttling to prevent spam
        'throttling' => [
            'enabled' => true,
            'interval' => (int) env('FORM_SECURITY_ALERT_THROTTLE_INTERVAL', 300), // 5 minutes
            'max_alerts_per_interval' => (int) env('FORM_SECURITY_MAX_ALERTS_PER_INTERVAL', 10),
        ],

        // Escalation rules
        'escalation' => [
            'enabled' => env('FORM_SECURITY_ALERT_ESCALATION_ENABLED', false),
            'critical_threshold_multiplier' => 2.0, // Alert becomes critical at 2x threshold
            'repeat_interval' => (int) env('FORM_SECURITY_ALERT_REPEAT_INTERVAL', 1800), // 30 minutes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance reporting behavior, including automated reports
    | and export formats.
    |
    */

    'reporting' => [
        'enabled' => env('FORM_SECURITY_REPORTING_ENABLED', true),

        // Default report periods
        'default_period' => env('FORM_SECURITY_DEFAULT_REPORT_PERIOD', '1h'),
        'available_periods' => ['1m', '5m', '15m', '30m', '1h', '6h', '12h', '24h'],

        // Export formats
        'formats' => [
            'json' => true,
            'html' => true,
            'csv' => false,
            'pdf' => false,
        ],

        // Automated reporting
        'automated' => [
            'enabled' => env('FORM_SECURITY_AUTOMATED_REPORTS_ENABLED', false),
            'schedule' => env('FORM_SECURITY_AUTOMATED_REPORT_SCHEDULE', 'daily'),
            'recipients' => env('FORM_SECURITY_REPORT_RECIPIENTS'),
            'storage_path' => env('FORM_SECURITY_REPORT_STORAGE_PATH', 'performance-reports'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for performance monitoring data. Proper
    | caching is essential for the performance of the monitoring system itself.
    |
    */

    'cache' => [
        'store' => env('FORM_SECURITY_PERFORMANCE_CACHE_STORE', 'default'),
        'prefix' => env('FORM_SECURITY_PERFORMANCE_CACHE_PREFIX', 'form_security_perf'),

        // Cache tags for better invalidation
        'use_tags' => env('FORM_SECURITY_PERFORMANCE_CACHE_TAGS', true),
        'tags' => [
            'performance',
            'monitoring',
            'metrics',
            'profiling',
        ],

        // Cache warming
        'warming' => [
            'enabled' => env('FORM_SECURITY_CACHE_WARMING_ENABLED', false),
            'schedule' => env('FORM_SECURITY_CACHE_WARMING_SCHEDULE', '*/15 * * * *'), // Every 15 minutes
            'keys_to_warm' => [
                'query_stats',
                'cache_stats',
                'thresholds',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development & Testing
    |--------------------------------------------------------------------------
    |
    | Special configuration options for development and testing environments.
    |
    */

    'development' => [
        'enabled_in_testing' => env('FORM_SECURITY_PERFORMANCE_TESTING', false),
        'debug_mode' => env('FORM_SECURITY_PERFORMANCE_DEBUG', false),
        'verbose_logging' => env('FORM_SECURITY_PERFORMANCE_VERBOSE', false),

        // Mock data for testing
        'use_mock_data' => env('FORM_SECURITY_USE_MOCK_PERFORMANCE_DATA', false),
        'mock_data_seed' => env('FORM_SECURITY_MOCK_DATA_SEED', 12345),

        // Performance testing
        'benchmark_mode' => env('FORM_SECURITY_BENCHMARK_MODE', false),
        'load_testing' => env('FORM_SECURITY_LOAD_TESTING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Settings
    |--------------------------------------------------------------------------
    |
    | Configure integration with external monitoring and analytics services.
    |
    */

    'integrations' => [
        // Application Performance Monitoring (APM) services
        'apm' => [
            'enabled' => env('FORM_SECURITY_APM_ENABLED', false),
            'service' => env('FORM_SECURITY_APM_SERVICE'), // newrelic, datadog, etc.
        ],

        // Metrics collection services
        'metrics' => [
            'enabled' => env('FORM_SECURITY_METRICS_SERVICE_ENABLED', false),
            'service' => env('FORM_SECURITY_METRICS_SERVICE'), // prometheus, influxdb, etc.
            'endpoint' => env('FORM_SECURITY_METRICS_ENDPOINT'),
        ],

        // Log aggregation services
        'logging' => [
            'enabled' => env('FORM_SECURITY_LOG_AGGREGATION_ENABLED', false),
            'service' => env('FORM_SECURITY_LOG_SERVICE'), // elasticsearch, splunk, etc.
            'index' => env('FORM_SECURITY_LOG_INDEX', 'form-security-performance'),
        ],
    ],
];
