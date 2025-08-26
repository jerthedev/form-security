# Core Spam Detection Engine

## Overview

The SpamDetectionService is the heart of JTD-FormSecurity, providing sophisticated multi-layered spam detection through pattern analysis, behavioral analysis, and optional AI-powered detection.

## SpamDetectionService Class

### Core Methods

```php
class SpamDetectionService
{
    /**
     * Calculate spam score for user registration data
     */
    public function calculateUserSpamScore(array $userData, ?string $ip = null): array;
    
    /**
     * Calculate spam score for contact form submissions
     */
    public function calculateContactSpamScore(array $contactData, ?string $ip = null): array;
    
    /**
     * Calculate spam score for comment content
     */
    public function calculateCommentSpamScore(string $content, User $user): array;
    
    /**
     * Generic spam score calculation for any form type
     */
    public function calculateGenericSpamScore(array $formData, ?string $ip = null): array;
    
    /**
     * Check name patterns for spam indicators
     */
    public function checkNamePatterns(string $name): array;
    
    /**
     * Check email patterns for spam indicators
     */
    public function checkEmailPatterns(string $email): array;
    
    /**
     * Check message content patterns for spam indicators
     */
    public function checkMessagePatterns(string $message): array;
    
    /**
     * Check IP patterns and reputation
     */
    public function checkIpPatterns(string $ip): array;
}
```

## Pattern-Based Detection Algorithms

### Name Pattern Analysis

**Scoring System:** 0-100 points
**Key Indicators:**
- Random character sequences (excessive consonants, no vowel patterns)
- Promotional keywords ("win", "free", "money", "cash", "prize")
- Suspicious number/letter combinations
- Excessive length (>50 characters)
- Special character patterns (asterisks, hash symbols)
- Cyrillic characters in non-Cyrillic contexts

```php
// Example scoring breakdown
$nameAnalysis = [
    'score' => 85,
    'indicators' => [
        'Name has excessive consonants (likely random)',
        'Contains promotional keywords: free, win',
        'Name exceeds maximum length',
        'Suspicious number/letter pattern'
    ]
];
```

### Email Pattern Analysis

**Scoring System:** 0-50 points
**Key Indicators:**
- Temporary email domains (10minutemail.com, guerrillamail.com)
- Random username patterns (excessive consonants, no vowels)
- Long number sequences in email address
- Suspicious domain patterns
- Known spam email patterns

```php
// Temporary email domains list (configurable)
$tempDomains = [
    'tempmail.org',
    '10minutemail.com', 
    'guerrillamail.com',
    'mailinator.com',
    'throwaway.email'
];
```

### Message Content Analysis

**Scoring System:** 0-100 points
**Key Indicators:**
- Excessive promotional keywords
- Multiple URLs/links (>2)
- Excessive capitalization (>50% uppercase)
- Very short messages (<10 characters)
- Very long messages (>2000 characters)
- Repetitive patterns
- Gibberish detection

```php
// Promotional keywords (configurable)
$promotionalKeywords = [
    'buy', 'sale', 'discount', 'offer', 'deal', 'cheap',
    'free', 'win', 'prize', 'money', 'cash', 'earn',
    'profit', 'bonus', 'gift', 'now', 'urgent', 'limited'
];
```

### IP Reputation Analysis

**Scoring System:** 0-50 points
**Data Sources:**
- AbuseIPDB API integration
- Local IP reputation database
- GeoLite2 geolocation data
- Historical blocking patterns

**Risk Factors:**
- High abuse confidence (>75%)
- Multiple abuse reports (>20)
- Anonymous proxy/VPN detection
- High-risk geographic regions
- Data center/hosting provider IPs

## AI-Powered Detection

### AI Analysis Integration

```php
class AiSpamAnalysisService
{
    /**
     * Analyze content using AI model
     */
    public function analyzeContent(string $content, string $type = 'generic'): array;
    
    /**
     * Analyze user registration data
     */
    public function analyzeUserRegistration(array $userData): array;
    
    /**
     * Analyze contact form submission
     */
    public function analyzeContactSubmission(array $contactData): array;
}
```

### AI Model Configuration

```php
// config/form-security.php
'ai_analysis' => [
    'enabled' => env('FORM_SECURITY_AI_ENABLED', false),
    'model' => env('FORM_SECURITY_AI_MODEL', 'grok-3-mini-fast'),
    'max_tokens' => 1000,
    'temperature' => 0.1,
    'score_range' => [30, 70], // Only use AI for borderline cases
    'timeout' => 10, // seconds
    'retry_attempts' => 2,
    'fallback_enabled' => true,
],
```

### AI Scoring System

- **User Registration:** 0-40 points
- **Contact Forms:** 0-40 points  
- **Comments:** 0-50 points
- **Generic Content:** 0-40 points

AI analysis is only triggered for borderline cases (scores in configurable range) to optimize costs and performance.

## Geolocation Integration

### GeoLite2Service Integration

```php
class GeolocationService
{
    /**
     * Get geolocation data for IP address
     */
    public function lookupIp(string $ip): array;
    
    /**
     * Check if IP is from high-risk region
     */
    public function isHighRiskRegion(string $ip): bool;
    
    /**
     * Get ISP information
     */
    public function getIspInfo(string $ip): array;
}
```

### Geographic Risk Assessment

**Risk Factors:**
- Countries with high spam activity
- Anonymous proxy/VPN detection
- Data center/hosting provider networks
- Satellite internet providers
- Known high-risk ISPs

## Caching Strategy

### Multi-Level Caching

1. **Database Cache** - Long-term storage of IP reputation data
2. **Memory Cache** - Redis/Memcached for frequently accessed data
3. **Request Cache** - Single-request caching to avoid duplicate API calls

```php
// Cache configuration
'caching' => [
    'ip_reputation_ttl' => 3600, // 1 hour
    'geolocation_ttl' => 86400, // 24 hours
    'ai_analysis_ttl' => 1800, // 30 minutes
    'pattern_cache_ttl' => 300, // 5 minutes
],
```

## Performance Optimization

### Efficient Processing

- **Early Exit Strategy** - Stop processing when threshold is exceeded
- **Lazy Loading** - Only load expensive services when needed
- **Batch Processing** - Process multiple items efficiently
- **Background Processing** - Queue expensive operations

### API Rate Limiting

```php
'api_limits' => [
    'abuseipdb' => [
        'daily_limit' => 1000,
        'rate_limit' => 60, // per minute
        'burst_limit' => 10,
    ],
    'ai_service' => [
        'daily_limit' => 500,
        'rate_limit' => 30, // per minute
        'cost_limit' => 10.00, // USD per day
    ],
],
```

## Error Handling & Resilience

### Graceful Degradation

- **Service Failures** - Continue with pattern-based detection if AI fails
- **API Timeouts** - Use cached data or skip external checks
- **Database Issues** - Log errors but don't block legitimate users
- **Configuration Errors** - Use safe defaults

### Monitoring & Alerting

```php
'monitoring' => [
    'log_blocked_attempts' => true,
    'log_api_failures' => true,
    'alert_on_high_volume' => true,
    'alert_threshold' => 100, // blocked attempts per hour
    'notification_channels' => ['slack', 'email'],
],
```

## Extensibility

### Custom Pattern Providers

```php
interface SpamPatternProvider
{
    public function getPatterns(): array;
    public function updatePatterns(array $patterns): void;
    public function analyzeContent(string $content): array;
}
```

### Plugin Architecture

```php
class SpamDetectionService
{
    protected array $plugins = [];
    
    public function addPlugin(SpamDetectionPlugin $plugin): void;
    public function removePlugin(string $name): void;
    public function getPlugins(): array;
}
```

This core detection engine provides the foundation for all spam protection features in the package, with emphasis on accuracy, performance, and extensibility.
