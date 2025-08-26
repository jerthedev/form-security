# Configuration System

## Overview

The JTD-FormSecurity package provides extensive configuration options to customize spam detection behavior, thresholds, integrations, and performance settings. The configuration system is designed to be **fully modular** - each protection layer can be independently enabled or disabled, allowing you to use only the features you need while maintaining full functionality.

## Modular Architecture

The package is designed with complete modularity in mind. You can enable or disable any combination of features:

- **Pattern-Based Detection Only** - Use basic spam patterns without external services
- **IP Reputation Only** - Focus on IP-based blocking without content analysis
- **AI Analysis Only** - Use AI-powered detection as the primary method
- **Geolocation Only** - Geographic risk assessment without other features
- **Full Stack** - All features enabled for maximum protection
- **Custom Combinations** - Mix and match any features to suit your needs

Each layer gracefully handles the absence of other layers, ensuring the system remains functional regardless of configuration.

## Main Configuration File

### config/form-security.php

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Form Security General Settings
    |--------------------------------------------------------------------------
    */
    'enabled' => env('FORM_SECURITY_ENABLED', true),
    'debug_mode' => env('FORM_SECURITY_DEBUG', false),
    'log_level' => env('FORM_SECURITY_LOG_LEVEL', 'info'),

    /*
    |--------------------------------------------------------------------------
    | Feature Toggles - Modular Protection Layers
    |--------------------------------------------------------------------------
    | Each protection layer can be independently enabled/disabled.
    | The system will gracefully function with any combination of features.
    */
    'features' => [
        'pattern_detection' => env('FORM_SECURITY_PATTERN_DETECTION', true),
        'ip_reputation' => env('FORM_SECURITY_IP_REPUTATION', true),
        'geolocation' => env('FORM_SECURITY_GEOLOCATION', true),
        'ai_analysis' => env('FORM_SECURITY_AI_ANALYSIS', false),
        'velocity_checking' => env('FORM_SECURITY_VELOCITY_CHECKING', true),
        'global_middleware' => env('FORM_SECURITY_GLOBAL_MIDDLEWARE', false),
        'user_registration_enhancement' => env('FORM_SECURITY_USER_REGISTRATION', true),
        'monitoring' => env('FORM_SECURITY_MONITORING', true),
        'caching' => env('FORM_SECURITY_CACHING', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Spam Detection Thresholds
    |--------------------------------------------------------------------------
    | Scores range from 0-100, with higher scores indicating higher spam likelihood.
    */
    'thresholds' => [
        'user_registration' => [
            'block' => env('FORM_SECURITY_USER_BLOCK_THRESHOLD', 90),
            'flag' => env('FORM_SECURITY_USER_FLAG_THRESHOLD', 70),
            'review' => env('FORM_SECURITY_USER_REVIEW_THRESHOLD', 50),
        ],
        'contact' => [
            'block' => env('FORM_SECURITY_CONTACT_BLOCK_THRESHOLD', 85),
            'flag' => env('FORM_SECURITY_CONTACT_FLAG_THRESHOLD', 65),
            'review' => env('FORM_SECURITY_CONTACT_REVIEW_THRESHOLD', 45),
        ],
        'comment' => [
            'block' => env('FORM_SECURITY_COMMENT_BLOCK_THRESHOLD', 95),
            'flag' => env('FORM_SECURITY_COMMENT_FLAG_THRESHOLD', 75),
            'review' => env('FORM_SECURITY_COMMENT_REVIEW_THRESHOLD', 55),
        ],
        'newsletter' => [
            'block' => env('FORM_SECURITY_NEWSLETTER_BLOCK_THRESHOLD', 80),
            'flag' => env('FORM_SECURITY_NEWSLETTER_FLAG_THRESHOLD', 60),
            'review' => env('FORM_SECURITY_NEWSLETTER_REVIEW_THRESHOLD', 40),
        ],
        'generic' => [
            'block' => env('FORM_SECURITY_GENERIC_BLOCK_THRESHOLD', 85),
            'flag' => env('FORM_SECURITY_GENERIC_FLAG_THRESHOLD', 65),
            'review' => env('FORM_SECURITY_GENERIC_REVIEW_THRESHOLD', 45),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | AI Analysis Configuration
    |--------------------------------------------------------------------------
    */
    'ai_analysis' => [
        'enabled' => env('FORM_SECURITY_AI_ENABLED', false),
        'provider' => env('FORM_SECURITY_AI_PROVIDER', 'xai'), // 'xai', 'openai', 'custom'
        'model' => env('FORM_SECURITY_AI_MODEL', 'grok-3-mini-fast'),
        'api_key' => env('FORM_SECURITY_AI_API_KEY'),
        'api_url' => env('FORM_SECURITY_AI_API_URL'),
        'max_tokens' => env('FORM_SECURITY_AI_MAX_TOKENS', 1000),
        'temperature' => env('FORM_SECURITY_AI_TEMPERATURE', 0.1),
        'timeout' => env('FORM_SECURITY_AI_TIMEOUT', 10),
        'retry_attempts' => env('FORM_SECURITY_AI_RETRY_ATTEMPTS', 2),
        'cost_limit_daily' => env('FORM_SECURITY_AI_COST_LIMIT', 10.00),
        
        // Conditional AI analysis
        'trigger_conditions' => [
            'score_range' => [30, 70], // Only analyze borderline cases
            'form_types' => ['contact', 'comment'], // Specific form types
            'high_risk_ips' => true,
            'repeat_offenders' => true,
        ],
        
        // Form-specific AI settings
        'form_specific' => [
            'user_registration' => [
                'enabled' => env('FORM_SECURITY_AI_REGISTRATION_ENABLED', false),
                'max_score' => 40,
            ],
            'contact' => [
                'enabled' => env('FORM_SECURITY_AI_CONTACT_ENABLED', true),
                'max_score' => 40,
            ],
            'comment' => [
                'enabled' => env('FORM_SECURITY_AI_COMMENT_ENABLED', true),
                'max_score' => 50,
            ],
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | IP Reputation & Geolocation
    |--------------------------------------------------------------------------
    */
    'ip_reputation' => [
        'enabled' => env('FORM_SECURITY_IP_REPUTATION_ENABLED', true),
        'provider' => env('FORM_SECURITY_IP_PROVIDER', 'abuseipdb'), // 'abuseipdb', 'custom'
        'api_key' => env('ABUSEIPDB_API_KEY'),
        'cache_ttl' => env('FORM_SECURITY_IP_CACHE_TTL', 3600), // seconds
        'max_age_days' => env('FORM_SECURITY_IP_MAX_AGE_DAYS', 90),
        'daily_limit' => env('FORM_SECURITY_IP_DAILY_LIMIT', 1000),
        'rate_limit_per_minute' => env('FORM_SECURITY_IP_RATE_LIMIT', 60),
    ],
    
    'geolocation' => [
        'enabled' => env('FORM_SECURITY_GEOLOCATION_ENABLED', true),
        'provider' => env('FORM_SECURITY_GEO_PROVIDER', 'geolite2'), // 'geolite2', 'abuseipdb'
        'database_path' => env('FORM_SECURITY_GEOLITE2_PATH', storage_path('app/GeoLite2-City-CSV_20250722')),
        'update_schedule' => env('FORM_SECURITY_GEO_UPDATE_SCHEDULE', 'monthly'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Global Protection Middleware
    |--------------------------------------------------------------------------
    */
    'global_protection' => [
        'enabled' => env('FORM_SECURITY_GLOBAL_ENABLED', false),
        'auto_detect_forms' => env('FORM_SECURITY_AUTO_DETECT', true),
        'block_threshold' => env('FORM_SECURITY_GLOBAL_BLOCK_THRESHOLD', 85),
        'flag_threshold' => env('FORM_SECURITY_GLOBAL_FLAG_THRESHOLD', 65),
        'log_all_submissions' => env('FORM_SECURITY_LOG_ALL', false),
        'log_blocked_only' => env('FORM_SECURITY_LOG_BLOCKED_ONLY', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Route & User Exclusions
    |--------------------------------------------------------------------------
    */
    'exclusions' => [
        'routes' => [
            'login', 'logout', 'password.*', 'verification.*',
            'api/auth/*', 'api/oauth/*', 'admin/*', 'dashboard/*',
            'webhooks/*', 'callbacks/*',
        ],
        'user_roles' => ['admin', 'moderator'],
        'user_permissions' => ['bypass-spam-protection'],
        'user_ids' => [], // Specific user IDs
        'email_domains' => [], // ['@yourcompany.com']
        'ip_addresses' => [
            '127.0.0.1', '::1', // Localhost
            // Add your office IP ranges
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | User Registration Enhancement
    |--------------------------------------------------------------------------
    */
    'registration' => [
        'enabled' => env('FORM_SECURITY_REGISTRATION_ENABLED', true),
        'auto_populate_fields' => env('FORM_SECURITY_AUTO_POPULATE_FIELDS', true),
        'block_temporary_emails' => env('FORM_SECURITY_BLOCK_TEMP_EMAILS', true),
        'check_ip_reputation' => env('FORM_SECURITY_CHECK_IP_REPUTATION', true),
        'check_geolocation' => env('FORM_SECURITY_CHECK_GEOLOCATION', true),
        
        'velocity_checking' => [
            'enabled' => env('FORM_SECURITY_VELOCITY_ENABLED', true),
            'max_per_ip' => env('FORM_SECURITY_MAX_REGISTRATIONS_PER_IP', 5),
            'window_hours' => env('FORM_SECURITY_VELOCITY_WINDOW_HOURS', 24),
            'block_duration_hours' => env('FORM_SECURITY_VELOCITY_BLOCK_HOURS', 24),
        ],
        
        'notifications' => [
            'enabled' => env('FORM_SECURITY_REGISTRATION_NOTIFICATIONS', true),
            'channels' => ['slack'], // ['slack', 'email', 'discord']
            'threshold' => env('FORM_SECURITY_NOTIFICATION_THRESHOLD', 80),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Pattern Detection Settings
    |--------------------------------------------------------------------------
    */
    'patterns' => [
        'name_analysis' => [
            'max_length' => env('FORM_SECURITY_MAX_NAME_LENGTH', 50),
            'min_vowels' => env('FORM_SECURITY_MIN_VOWELS', 1),
            'max_consonant_ratio' => env('FORM_SECURITY_MAX_CONSONANT_RATIO', 0.8),
            'promotional_keywords_weight' => env('FORM_SECURITY_PROMOTIONAL_WEIGHT', 15),
        ],
        
        'email_analysis' => [
            'check_temporary_domains' => env('FORM_SECURITY_CHECK_TEMP_DOMAINS', true),
            'check_disposable_domains' => env('FORM_SECURITY_CHECK_DISPOSABLE_DOMAINS', true),
            'min_domain_age_days' => env('FORM_SECURITY_MIN_DOMAIN_AGE', 30),
        ],
        
        'content_analysis' => [
            'max_links_allowed' => env('FORM_SECURITY_MAX_LINKS', 2),
            'max_uppercase_ratio' => env('FORM_SECURITY_MAX_UPPERCASE_RATIO', 0.5),
            'min_content_length' => env('FORM_SECURITY_MIN_CONTENT_LENGTH', 10),
            'max_content_length' => env('FORM_SECURITY_MAX_CONTENT_LENGTH', 2000),
            'repeated_word_threshold' => env('FORM_SECURITY_REPEATED_WORD_THRESHOLD', 3),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    */
    'error_messages' => [
        'user_registration' => 'Registration failed spam verification. Please use different information or contact support if you believe this is an error.',
        'contact' => 'Your message was flagged as potential spam. Please revise your content and try again.',
        'comment' => 'Your comment was flagged as potential spam. Please revise your content and try again.',
        'newsletter' => 'Subscription failed verification. Please try again or contact support if you believe this is an error.',
        'generic' => 'Your submission was flagged as potential spam. Please revise your content and try again.',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    */
    'caching' => [
        'driver' => env('FORM_SECURITY_CACHE_DRIVER', 'redis'), // 'redis', 'memcached', 'database'
        'prefix' => env('FORM_SECURITY_CACHE_PREFIX', 'form_security'),
        'ttl' => [
            'ip_reputation' => env('FORM_SECURITY_CACHE_IP_TTL', 3600), // 1 hour
            'geolocation' => env('FORM_SECURITY_CACHE_GEO_TTL', 86400), // 24 hours
            'ai_analysis' => env('FORM_SECURITY_CACHE_AI_TTL', 1800), // 30 minutes
            'spam_patterns' => env('FORM_SECURITY_CACHE_PATTERNS_TTL', 300), // 5 minutes
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Monitoring & Analytics
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'enabled' => env('FORM_SECURITY_MONITORING_ENABLED', true),
        'alert_threshold' => env('FORM_SECURITY_ALERT_THRESHOLD', 100), // blocked attempts per hour
        'notification_channels' => ['slack'], // ['slack', 'email', 'discord']
        'dashboard_enabled' => env('FORM_SECURITY_DASHBOARD_ENABLED', true),
        'metrics_retention_days' => env('FORM_SECURITY_METRICS_RETENTION', 30),
        
        'slack' => [
            'webhook_url' => env('FORM_SECURITY_SLACK_WEBHOOK'),
            'channel' => env('FORM_SECURITY_SLACK_CHANNEL', '#security'),
            'username' => env('FORM_SECURITY_SLACK_USERNAME', 'Form Security Bot'),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Data Retention & Cleanup
    |--------------------------------------------------------------------------
    */
    'data_retention' => [
        'blocked_submissions' => [
            'retention_days' => env('FORM_SECURITY_BLOCKED_RETENTION_DAYS', 90),
            'cleanup_schedule' => env('FORM_SECURITY_CLEANUP_SCHEDULE', 'daily'),
            'archive_before_delete' => env('FORM_SECURITY_ARCHIVE_BEFORE_DELETE', true),
        ],
        'ip_reputation' => [
            'retention_days' => env('FORM_SECURITY_IP_RETENTION_DAYS', 365),
            'refresh_interval_days' => env('FORM_SECURITY_IP_REFRESH_DAYS', 30),
            'cleanup_expired' => env('FORM_SECURITY_CLEANUP_EXPIRED_IP', true),
        ],
        'spam_patterns' => [
            'inactive_retention_days' => env('FORM_SECURITY_PATTERN_RETENTION_DAYS', 180),
            'unused_pattern_days' => env('FORM_SECURITY_UNUSED_PATTERN_DAYS', 60),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Development & Testing
    |--------------------------------------------------------------------------
    */
    'development' => [
        'enabled' => env('APP_DEBUG', false),
        'bypass_protection' => env('FORM_SECURITY_BYPASS_PROTECTION', false),
        'log_all_requests' => env('FORM_SECURITY_LOG_ALL_REQUESTS', false),
        'show_debug_info' => env('FORM_SECURITY_SHOW_DEBUG', false),
        'test_mode' => env('FORM_SECURITY_TEST_MODE', false),
        'fake_ai_responses' => env('FORM_SECURITY_FAKE_AI', false),
    ],
];
```

## Environment Variables

### .env Configuration Template

```bash
# Form Security General Settings
FORM_SECURITY_ENABLED=true
FORM_SECURITY_DEBUG=false
FORM_SECURITY_LOG_LEVEL=info

# Spam Detection Thresholds
FORM_SECURITY_USER_BLOCK_THRESHOLD=90
FORM_SECURITY_USER_FLAG_THRESHOLD=70
FORM_SECURITY_CONTACT_BLOCK_THRESHOLD=85
FORM_SECURITY_CONTACT_FLAG_THRESHOLD=65
FORM_SECURITY_COMMENT_BLOCK_THRESHOLD=95
FORM_SECURITY_COMMENT_FLAG_THRESHOLD=75

# AI Analysis
FORM_SECURITY_AI_ENABLED=false
FORM_SECURITY_AI_PROVIDER=xai
FORM_SECURITY_AI_MODEL=grok-3-mini-fast
FORM_SECURITY_AI_API_KEY=your_ai_api_key_here
FORM_SECURITY_AI_COST_LIMIT=10.00

# IP Reputation & Geolocation
FORM_SECURITY_IP_REPUTATION_ENABLED=true
ABUSEIPDB_API_KEY=your_abuseipdb_api_key_here
FORM_SECURITY_GEOLOCATION_ENABLED=true
FORM_SECURITY_GEOLITE2_PATH=/path/to/geolite2/database

# Global Protection
FORM_SECURITY_GLOBAL_ENABLED=false
FORM_SECURITY_AUTO_DETECT=true

# Registration Enhancement
FORM_SECURITY_REGISTRATION_ENABLED=true
FORM_SECURITY_BLOCK_TEMP_EMAILS=true
FORM_SECURITY_MAX_REGISTRATIONS_PER_IP=5
FORM_SECURITY_VELOCITY_WINDOW_HOURS=24

# Monitoring & Notifications
FORM_SECURITY_MONITORING_ENABLED=true
FORM_SECURITY_SLACK_WEBHOOK=your_slack_webhook_url_here
FORM_SECURITY_ALERT_THRESHOLD=100

# Caching
FORM_SECURITY_CACHE_DRIVER=redis
FORM_SECURITY_CACHE_IP_TTL=3600
FORM_SECURITY_CACHE_GEO_TTL=86400

# Data Retention
FORM_SECURITY_BLOCKED_RETENTION_DAYS=90
FORM_SECURITY_IP_RETENTION_DAYS=365
```

## Configuration Publishing

### Artisan Commands

```bash
# Publish main configuration
php artisan vendor:publish --provider="JTD\FormSecurity\FormSecurityServiceProvider" --tag="config"

# Publish all configuration files
php artisan vendor:publish --provider="JTD\FormSecurity\FormSecurityServiceProvider" --tag="form-security-config"

# Publish specific configuration sections
php artisan vendor:publish --provider="JTD\FormSecurity\FormSecurityServiceProvider" --tag="spam-patterns"
php artisan vendor:publish --provider="JTD\FormSecurity\FormSecurityServiceProvider" --tag="ai-models"
```

## Dynamic Configuration

### Runtime Configuration Updates

```php
use JTD\FormSecurity\Facades\FormSecurity;

// Update thresholds at runtime
FormSecurity::setThreshold('contact', 'block', 80);
FormSecurity::setThreshold('user_registration', 'flag', 65);

// Enable/disable features
FormSecurity::enableAiAnalysis();
FormSecurity::disableGlobalProtection();

// Update pattern weights
FormSecurity::updatePatternWeight('promotional_keywords', 20);
```

### Configuration Validation

```php
class ConfigurationValidator
{
    public function validate(): array
    {
        $errors = [];
        
        // Validate thresholds
        if (config('form-security.thresholds.user_registration.block') <= 
            config('form-security.thresholds.user_registration.flag')) {
            $errors[] = 'Block threshold must be higher than flag threshold';
        }
        
        // Validate API keys
        if (config('form-security.ai_analysis.enabled') && 
            !config('form-security.ai_analysis.api_key')) {
            $errors[] = 'AI analysis enabled but API key not configured';
        }
        
        return $errors;
    }
}
```

## Modular Configuration Examples

### Minimal Setup (Pattern Detection Only)
```bash
# Enable only basic pattern detection
FORM_SECURITY_ENABLED=true
FORM_SECURITY_PATTERN_DETECTION=true
FORM_SECURITY_IP_REPUTATION=false
FORM_SECURITY_GEOLOCATION=false
FORM_SECURITY_AI_ANALYSIS=false
FORM_SECURITY_VELOCITY_CHECKING=false
```

### IP-Focused Protection
```bash
# Focus on IP reputation and geolocation
FORM_SECURITY_ENABLED=true
FORM_SECURITY_PATTERN_DETECTION=false
FORM_SECURITY_IP_REPUTATION=true
FORM_SECURITY_GEOLOCATION=true
ABUSEIPDB_API_KEY=your_api_key_here
```

### AI-Powered Detection
```bash
# Use AI as primary detection method
FORM_SECURITY_ENABLED=true
FORM_SECURITY_PATTERN_DETECTION=false
FORM_SECURITY_AI_ANALYSIS=true
FORM_SECURITY_AI_API_KEY=your_ai_api_key_here
```

### Full Protection Stack
```bash
# Enable all features for maximum protection
FORM_SECURITY_ENABLED=true
FORM_SECURITY_PATTERN_DETECTION=true
FORM_SECURITY_IP_REPUTATION=true
FORM_SECURITY_GEOLOCATION=true
FORM_SECURITY_AI_ANALYSIS=true
FORM_SECURITY_VELOCITY_CHECKING=true
FORM_SECURITY_GLOBAL_MIDDLEWARE=true
FORM_SECURITY_MONITORING=true
```

### Registration-Only Protection
```bash
# Protect only user registration forms
FORM_SECURITY_ENABLED=true
FORM_SECURITY_USER_REGISTRATION=true
FORM_SECURITY_GLOBAL_MIDDLEWARE=false
FORM_SECURITY_VELOCITY_CHECKING=true
```

## Feature Independence

Each feature is designed to work independently:

- **Pattern Detection** works without external APIs
- **IP Reputation** functions without geolocation data
- **AI Analysis** operates independently of other detection methods
- **Geolocation** provides value even without IP reputation data
- **Velocity Checking** works with minimal configuration
- **Monitoring** can track any enabled features

This modular approach ensures you can start with basic protection and gradually add more sophisticated features as needed, or disable expensive features (like AI analysis) while maintaining core protection.

This comprehensive configuration system provides fine-grained control over all aspects of the form security package while maintaining ease of use and environment-specific customization.
