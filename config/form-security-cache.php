<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | FormSecurity Cache Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains caching configuration for the JTD FormSecurity
    | package. Proper caching is essential for performance optimization
    | and reducing database load.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cache Driver
    |--------------------------------------------------------------------------
    |
    | Specify the cache driver to use for FormSecurity operations.
    | If null, the default application cache driver will be used.
    |
    */

    'driver' => env('FORM_SECURITY_CACHE_DRIVER', null),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for all FormSecurity cache keys to avoid conflicts with
    | other application cache entries.
    |
    */

    'prefix' => env('FORM_SECURITY_CACHE_PREFIX', 'form_security'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL Settings
    |--------------------------------------------------------------------------
    |
    | Time-to-live settings for different types of cached data.
    | Values are in seconds.
    |
    */

    'ttl' => [
        'spam_patterns' => env('FORM_SECURITY_CACHE_PATTERNS_TTL', 86400), // 24 hours
        'ip_reputation' => env('FORM_SECURITY_CACHE_IP_TTL', 3600), // 1 hour
        'rate_limits' => env('FORM_SECURITY_CACHE_RATE_LIMIT_TTL', 3600), // 1 hour
        'geolocation' => env('FORM_SECURITY_CACHE_GEO_TTL', 604800), // 1 week
        'configuration' => env('FORM_SECURITY_CACHE_CONFIG_TTL', 1800), // 30 minutes
        'statistics' => env('FORM_SECURITY_CACHE_STATS_TTL', 300), // 5 minutes
        'analysis_results' => env('FORM_SECURITY_CACHE_ANALYSIS_TTL', 1800), // 30 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Tags
    |--------------------------------------------------------------------------
    |
    | Cache tags for efficient cache invalidation. Only supported by
    | cache drivers that support tagging (Redis, Memcached).
    |
    */

    'tags' => [
        'enabled' => env('FORM_SECURITY_CACHE_TAGS_ENABLED', true),
        'global' => 'form-security',
        'patterns' => 'form-security-patterns',
        'ip_data' => 'form-security-ip',
        'statistics' => 'form-security-stats',
        'configuration' => 'form-security-config',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Warming
    |--------------------------------------------------------------------------
    |
    | Configure cache warming to pre-populate frequently accessed data.
    |
    */

    'warming' => [
        'enabled' => env('FORM_SECURITY_CACHE_WARMING_ENABLED', true),
        'schedule' => env('FORM_SECURITY_CACHE_WARMING_SCHEDULE', '0 */6 * * *'), // Every 6 hours
        'items' => [
            'spam_patterns' => true,
            'configuration' => true,
            'ip_reputation_lists' => false,
            'geolocation_data' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Invalidation
    |--------------------------------------------------------------------------
    |
    | Configure automatic cache invalidation rules and triggers.
    |
    */

    'invalidation' => [
        'auto_invalidate' => env('FORM_SECURITY_CACHE_AUTO_INVALIDATE', true),
        'triggers' => [
            'config_change' => true,
            'pattern_update' => true,
            'ip_list_update' => true,
            'feature_toggle' => true,
        ],
        'batch_size' => env('FORM_SECURITY_CACHE_INVALIDATION_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Monitor cache performance and hit ratios for optimization.
    |
    */

    'monitoring' => [
        'enabled' => env('FORM_SECURITY_CACHE_MONITORING_ENABLED', true),
        'track_hit_ratio' => env('FORM_SECURITY_CACHE_TRACK_HIT_RATIO', true),
        'track_response_times' => env('FORM_SECURITY_CACHE_TRACK_RESPONSE_TIMES', true),
        'alert_on_low_hit_ratio' => env('FORM_SECURITY_CACHE_ALERT_LOW_HIT_RATIO', false),
        'min_hit_ratio_threshold' => env('FORM_SECURITY_CACHE_MIN_HIT_RATIO', 0.8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Serialization
    |--------------------------------------------------------------------------
    |
    | Configure how data is serialized for caching.
    |
    */

    'serialization' => [
        'method' => env('FORM_SECURITY_CACHE_SERIALIZATION', 'php'), // php, json, igbinary
        'compress' => env('FORM_SECURITY_CACHE_COMPRESS', false),
        'compression_level' => env('FORM_SECURITY_CACHE_COMPRESSION_LEVEL', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory Management
    |--------------------------------------------------------------------------
    |
    | Configure memory usage limits and cleanup strategies.
    |
    */

    'memory' => [
        'max_memory_usage' => env('FORM_SECURITY_CACHE_MAX_MEMORY', 100), // MB
        'cleanup_threshold' => env('FORM_SECURITY_CACHE_CLEANUP_THRESHOLD', 0.9),
        'cleanup_strategy' => env('FORM_SECURITY_CACHE_CLEANUP_STRATEGY', 'lru'), // lru, lfu, random
    ],
];
