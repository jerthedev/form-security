<?php

/**
 * Configuration File: form-security-spam-detection.php
 *
 * EPIC: EPIC-002-core-spam-detection-engine
 * SPEC: SPEC-004-pattern-based-spam-detection
 * SPRINT: Sprint-007-epic-002-foundation-setup
 * TICKET: 2012-core-spam-detection-service
 *
 * Description: Configuration for spam detection service including hybrid algorithm
 * weights, thresholds, rate limits, and detection method settings.
 *
 * @see docs/Planning/Epics/EPIC-002-core-spam-detection-engine.md
 * @see docs/Planning/Tickets/Core-Spam-Detection-Engine/Implementation/2012-core-spam-detection-service.md
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Spam Detection Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the hybrid spam detection algorithm including method
    | weights, thresholds, and performance optimization settings.
    |
    */

    // Enable/disable spam detection entirely
    'enabled' => env('FORM_SECURITY_SPAM_DETECTION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Hybrid Detection Algorithm Weights
    |--------------------------------------------------------------------------
    |
    | Configure the weights for each detection method in the hybrid algorithm.
    | Weights should sum to approximately 1.0 for optimal scoring.
    |
    | Default algorithm: Bayesian 40%, Regex 30%, Behavioral 20%, AI 10%
    |
    */

    'method_weights' => [
        'bayesian' => env('SPAM_WEIGHT_BAYESIAN', 0.40),      // 40% - Statistical analysis
        'regex' => env('SPAM_WEIGHT_REGEX', 0.30),            // 30% - Pattern matching
        'behavioral' => env('SPAM_WEIGHT_BEHAVIORAL', 0.20),   // 20% - Behavior analysis
        'ai' => env('SPAM_WEIGHT_AI', 0.10),                  // 10% - AI analysis
        'keyword' => env('SPAM_WEIGHT_KEYWORD', 0.15),        // Supplementary methods
        'pattern' => env('SPAM_WEIGHT_PATTERN', 0.25),
        'rate_limit' => env('SPAM_WEIGHT_RATE_LIMIT', 0.35),
        'ip_reputation' => env('SPAM_WEIGHT_IP_REPUTATION', 0.20),
        'content_analysis' => env('SPAM_WEIGHT_CONTENT', 0.15),
        'geolocation' => env('SPAM_WEIGHT_GEOLOCATION', 0.10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Detection Thresholds
    |--------------------------------------------------------------------------
    |
    | Configure score thresholds for spam classification and actions.
    |
    */

    'spam_threshold' => env('SPAM_DETECTION_THRESHOLD', 0.5),        // Overall spam threshold
    'high_confidence_threshold' => env('SPAM_HIGH_CONFIDENCE', 0.8), // High confidence threshold
    'review_threshold' => env('SPAM_REVIEW_THRESHOLD', 0.4),         // Manual review threshold
    'captcha_threshold' => env('SPAM_CAPTCHA_THRESHOLD', 0.7),       // CAPTCHA trigger threshold

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for form submissions to prevent spam flooding.
    |
    */

    'rate_limits' => [
        'per_minute' => env('SPAM_RATE_LIMIT_MINUTE', 5),      // Max 5 per minute
        'per_hour' => env('SPAM_RATE_LIMIT_HOUR', 50),         // Max 50 per hour
        'per_day' => env('SPAM_RATE_LIMIT_DAY', 200),          // Max 200 per day
        'burst_limit' => env('SPAM_RATE_LIMIT_BURST', 10),     // Burst protection
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    |
    | Settings for performance optimization including early exit conditions
    | and processing time targets.
    |
    */

    'performance' => [
        'target_processing_time_ms' => env('SPAM_TARGET_TIME_MS', 50),      // <50ms target
        'max_processing_time_ms' => env('SPAM_MAX_TIME_MS', 200),           // Hard limit
        'enable_early_exit' => env('SPAM_ENABLE_EARLY_EXIT', true),         // Early exit optimization
        'early_exit_threshold' => env('SPAM_EARLY_EXIT_THRESHOLD', 0.8),    // Early exit score
        'minimum_methods_before_exit' => env('SPAM_MIN_METHODS_EXIT', 2),   // Min methods before exit
        'enable_caching' => env('SPAM_ENABLE_CACHING', true),               // Result caching
        'cache_ttl_seconds' => env('SPAM_CACHE_TTL', 3600),                 // Cache TTL
    ],

    /*
    |--------------------------------------------------------------------------
    | Early Exit Methods
    |--------------------------------------------------------------------------
    |
    | Detection methods that can trigger early exit when they return
    | high confidence scores.
    |
    */

    'early_exit_methods' => [
        'regex',
        'pattern',
        'rate_limit',
        'ip_reputation',
    ],

    'method_early_exit_threshold' => env('SPAM_METHOD_EXIT_THRESHOLD', 0.9),

    /*
    |--------------------------------------------------------------------------
    | Context Adjustments
    |--------------------------------------------------------------------------
    |
    | Score adjustments based on submission context and risk factors.
    |
    */

    'context_adjustments' => [
        'high_frequency_submission' => ['type' => 'add', 'value' => 0.2],
        'missing_user_agent' => ['type' => 'add', 'value' => 0.1],
        'tor_network' => ['type' => 'add', 'value' => 0.3],
        'vpn_detected' => ['type' => 'add', 'value' => 0.1],
        'authenticated_user' => ['type' => 'subtract', 'value' => 0.1],
        'repeated_content' => ['type' => 'add', 'value' => 0.3],
        'suspicious_timing' => ['type' => 'add', 'value' => 0.2],
        'bot_detected' => ['type' => 'add', 'value' => 0.4],
        'missing_referer' => ['type' => 'add', 'value' => 0.05],
        'mobile_device' => ['type' => 'subtract', 'value' => 0.05],
    ],

    /*
    |--------------------------------------------------------------------------
    | Detection Method Configuration
    |--------------------------------------------------------------------------
    |
    | Enable/disable specific detection methods and configure their settings.
    |
    */

    // Enable advanced detection methods
    'enable_bayesian' => env('SPAM_ENABLE_BAYESIAN', false),          // Requires training data
    'enable_ai' => env('SPAM_ENABLE_AI', false),                      // Requires AI service
    'enable_geolocation' => env('SPAM_ENABLE_GEOLOCATION', true),     // GeoIP analysis
    'enable_ip_reputation' => env('SPAM_ENABLE_IP_REPUTATION', true), // IP reputation check

    // Bayesian spam filtering configuration
    'bayesian' => [
        'min_word_length' => 3,
        'max_word_length' => 20,
        'min_word_frequency' => 2,
        'ham_probability' => 0.4,    // Default probability for unknown words
        'spam_probability' => 0.6,   // Default spam probability
    ],

    // Bayesian word probabilities (simplified training data)
    'bayesian_words' => [
        // High spam probability words
        'free' => 0.85,
        'money' => 0.80,
        'offer' => 0.75,
        'click' => 0.70,
        'buy' => 0.65,
        'sale' => 0.65,
        'win' => 0.75,
        'prize' => 0.80,
        'casino' => 0.90,
        'viagra' => 0.95,
        'pharmacy' => 0.85,
        'loan' => 0.75,
        'credit' => 0.70,
        'mortgage' => 0.75,
        'investment' => 0.70,
        'earn' => 0.65,
        'income' => 0.65,
        'rich' => 0.70,
        'wealthy' => 0.70,
        'millionaire' => 0.85,
        'guaranteed' => 0.80,
        'promise' => 0.65,
        'amazing' => 0.60,
        'incredible' => 0.65,
        'fantastic' => 0.60,
        'opportunity' => 0.70,
        'limited' => 0.65,
        'urgent' => 0.70,
        'act' => 0.60,
        'now' => 0.55,
        'today' => 0.50,
        'immediately' => 0.70,
        'instant' => 0.65,
        'fast' => 0.55,
        'quick' => 0.55,
        'easy' => 0.60,
        'simple' => 0.50,
        'effortless' => 0.70,
        'automatic' => 0.65,
        'system' => 0.55,
        'method' => 0.55,
        'secret' => 0.75,
        'hidden' => 0.70,
        'exclusive' => 0.65,
        'private' => 0.60,
        'confidential' => 0.65,
        'insider' => 0.75,
        'breakthrough' => 0.70,
        'revolutionary' => 0.65,
        'miracle' => 0.80,
        'magic' => 0.70,
        'formula' => 0.65,
        'solution' => 0.55,
        'system' => 0.55,
        'software' => 0.50,
        'program' => 0.50,
        'download' => 0.60,
        'install' => 0.55,
        'access' => 0.50,
        'membership' => 0.60,
        'subscription' => 0.55,
        'trial' => 0.65,
        'demo' => 0.50,
        'sample' => 0.50,
        'test' => 0.45,

        // Low spam probability words (common legitimate words)
        'thank' => 0.15,
        'thanks' => 0.15,
        'please' => 0.20,
        'help' => 0.25,
        'question' => 0.20,
        'information' => 0.25,
        'about' => 0.15,
        'regarding' => 0.20,
        'concerning' => 0.20,
        'contact' => 0.30,
        'email' => 0.35,
        'phone' => 0.30,
        'address' => 0.25,
        'name' => 0.15,
        'company' => 0.25,
        'business' => 0.30,
        'service' => 0.35,
        'product' => 0.40,
        'website' => 0.30,
        'web' => 0.30,
        'online' => 0.40,
        'internet' => 0.35,
        'computer' => 0.25,
        'technology' => 0.25,
        'support' => 0.25,
        'customer' => 0.25,
        'client' => 0.25,
        'user' => 0.20,
        'account' => 0.35,
        'login' => 0.30,
        'password' => 0.30,
        'security' => 0.30,
        'privacy' => 0.25,
        'policy' => 0.25,
        'terms' => 0.25,
        'conditions' => 0.25,
        'agreement' => 0.30,
        'legal' => 0.30,
        'copyright' => 0.20,
        'trademark' => 0.20,
        'reserved' => 0.20,
        'rights' => 0.25,
        'permission' => 0.25,
        'license' => 0.30,
        'authorized' => 0.25,
        'official' => 0.25,
        'legitimate' => 0.20,
        'genuine' => 0.25,
        'authentic' => 0.25,
        'real' => 0.30,
        'actual' => 0.25,
        'true' => 0.25,
        'correct' => 0.20,
        'accurate' => 0.20,
        'precise' => 0.20,
        'exact' => 0.20,
        'specific' => 0.20,
        'detailed' => 0.20,
        'complete' => 0.25,
        'full' => 0.25,
        'entire' => 0.20,
        'total' => 0.30,
        'whole' => 0.20,
        'all' => 0.15,
        'every' => 0.20,
        'each' => 0.15,
        'individual' => 0.20,
        'personal' => 0.30,
        'private' => 0.35,
        'confidential' => 0.40,
    ],

    /*
    |--------------------------------------------------------------------------
    | High-Risk Countries/Regions
    |--------------------------------------------------------------------------
    |
    | Country codes that are considered higher risk for spam submissions.
    |
    */

    'high_risk_countries' => [
        // Common spam source countries (ISO 3166-1 alpha-2)
        'CN', // China
        'RU', // Russia
        'IN', // India
        'BR', // Brazil
        'VN', // Vietnam
        'PH', // Philippines
        'ID', // Indonesia
        'TH', // Thailand
        'MY', // Malaysia
        'BD', // Bangladesh
        'PK', // Pakistan
        'NG', // Nigeria
        'GH', // Ghana
        'KE', // Kenya
        'EG', // Egypt
        'IR', // Iran
        'IQ', // Iraq
        'AF', // Afghanistan
        'SY', // Syria
        'YE', // Yemen
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging and Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for spam detection logging and monitoring.
    |
    */

    'logging' => [
        'enable_analysis_logging' => env('SPAM_ENABLE_LOGGING', true),
        'log_clean_submissions' => env('SPAM_LOG_CLEAN', false),        // Log non-spam too
        'log_detailed_analysis' => env('SPAM_LOG_DETAILED', false),     // Detailed analysis logs
        'log_performance_metrics' => env('SPAM_LOG_PERFORMANCE', true), // Performance logging
        'log_pattern_matches' => env('SPAM_LOG_PATTERNS', true),        // Pattern match logs
        'retention_days' => env('SPAM_LOG_RETENTION', 30),              // Log retention period
    ],

    /*
    |--------------------------------------------------------------------------
    | Statistics and Analytics
    |--------------------------------------------------------------------------
    |
    | Configuration for detection statistics and analytics collection.
    |
    */

    'analytics' => [
        'enable_statistics' => env('SPAM_ENABLE_STATS', true),
        'enable_method_stats' => env('SPAM_ENABLE_METHOD_STATS', true),
        'enable_hourly_trends' => env('SPAM_ENABLE_HOURLY_TRENDS', true),
        'stats_retention_hours' => env('SPAM_STATS_RETENTION', 168),    // 7 days
        'enable_performance_monitoring' => env('SPAM_ENABLE_PERF_MONITOR', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Caching settings specific to spam detection.
    |
    */

    'cache' => [
        'enable_result_caching' => env('SPAM_CACHE_RESULTS', true),
        'enable_pattern_caching' => env('SPAM_CACHE_PATTERNS', true),
        'enable_rate_limit_caching' => env('SPAM_CACHE_RATE_LIMITS', true),
        'result_cache_ttl' => env('SPAM_CACHE_RESULT_TTL', 1800),       // 30 minutes
        'pattern_cache_ttl' => env('SPAM_CACHE_PATTERN_TTL', 3600),     // 1 hour
        'rate_limit_cache_ttl' => env('SPAM_CACHE_RATE_LIMIT_TTL', 3600), // 1 hour
        'stats_cache_ttl' => env('SPAM_CACHE_STATS_TTL', 300),          // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Settings
    |--------------------------------------------------------------------------
    |
    | Settings for integration with external services and Epic-001 infrastructure.
    |
    */

    'integration' => [
        'use_epic001_caching' => env('SPAM_USE_EPIC001_CACHE', true),
        'use_epic001_logging' => env('SPAM_USE_EPIC001_LOGGING', true),
        'use_epic001_config' => env('SPAM_USE_EPIC001_CONFIG', true),
        'enable_database_patterns' => env('SPAM_USE_DB_PATTERNS', true),
        'fallback_to_defaults' => env('SPAM_FALLBACK_DEFAULTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security and Privacy
    |--------------------------------------------------------------------------
    |
    | Security settings for spam detection processing.
    |
    */

    'security' => [
        'anonymize_ip_addresses' => env('SPAM_ANONYMIZE_IPS', false),
        'encrypt_sensitive_data' => env('SPAM_ENCRYPT_DATA', false),
        'enable_content_hashing' => env('SPAM_HASH_CONTENT', true),
        'max_content_length' => env('SPAM_MAX_CONTENT_LENGTH', 10000),  // Max content size
        'max_fields_per_form' => env('SPAM_MAX_FIELDS', 50),           // Max form fields
    ],

    /*
    |--------------------------------------------------------------------------
    | Development and Testing
    |--------------------------------------------------------------------------
    |
    | Settings for development and testing environments.
    |
    */

    'development' => [
        'enable_debug_mode' => env('SPAM_DEBUG_MODE', false),
        'bypass_rate_limits' => env('SPAM_BYPASS_RATE_LIMITS', false),
        'force_spam_detection' => env('SPAM_FORCE_DETECTION', null),    // Force result for testing
        'enable_test_patterns' => env('SPAM_ENABLE_TEST_PATTERNS', false),
        'log_debug_info' => env('SPAM_LOG_DEBUG', false),
    ],

];
