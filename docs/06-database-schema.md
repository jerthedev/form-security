# Database Schema & Models

## Overview

The JTD-FormSecurity package includes comprehensive database schema for tracking spam attempts, IP reputation, geolocation data, and user security information. The schema is designed for performance, analytics, and compliance with data retention policies.

## Core Tables

### blocked_submissions Table

Tracks all blocked form submissions for analysis and monitoring.

```php
// Migration: create_blocked_submissions_table.php
Schema::create('blocked_submissions', function (Blueprint $table) {
    $table->id();
    
    // Submission data
    $table->string('form_type')->index(); // 'registration', 'contact', 'comment', etc.
    $table->string('route_name')->nullable();
    $table->string('request_uri');
    $table->string('http_method', 10)->default('POST');
    
    // User data (sanitized)
    $table->string('name')->nullable();
    $table->string('email')->nullable();
    $table->ipAddress('ip_address')->index();
    $table->string('user_agent', 500)->nullable();
    $table->string('referer', 500)->nullable();
    
    // Geolocation data
    $table->string('country_code', 2)->nullable()->index();
    $table->string('country_name')->nullable();
    $table->string('region')->nullable();
    $table->string('city')->nullable();
    $table->string('isp')->nullable();
    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    
    // Spam analysis results
    $table->unsignedTinyInteger('spam_score')->index();
    $table->json('spam_indicators')->nullable();
    $table->unsignedTinyInteger('spam_threshold');
    
    // Analysis metadata
    $table->json('validation_fields')->nullable(); // Fields that were validated
    $table->boolean('ai_analysis_used')->default(false)->index();
    $table->string('ai_model')->nullable();
    $table->decimal('ai_confidence', 5, 2)->nullable();
    
    // Request context
    $table->json('form_data')->nullable(); // Sanitized form data
    $table->json('request_headers')->nullable(); // Selected headers
    $table->string('session_id')->nullable();
    $table->unsignedBigInteger('user_id')->nullable()->index(); // If authenticated
    
    // Timestamps
    $table->timestamp('blocked_at')->index();
    $table->timestamps();
    
    // Indexes for analytics
    $table->index(['form_type', 'blocked_at']);
    $table->index(['country_code', 'blocked_at']);
    $table->index(['spam_score', 'blocked_at']);
    $table->index(['ai_analysis_used', 'blocked_at']);
    
    // Foreign key constraints
    $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
});
```

### ip_reputation Table

Caches IP reputation data from external services.

```php
// Migration: create_ip_reputation_table.php
Schema::create('ip_reputation', function (Blueprint $table) {
    $table->id();
    $table->ipAddress('ip_address')->unique();
    
    // AbuseIPDB data
    $table->unsignedTinyInteger('abuse_confidence')->default(0)->index();
    $table->unsignedInteger('total_reports')->default(0);
    $table->boolean('is_whitelisted')->default(false)->index();
    $table->string('usage_type')->nullable(); // 'Data Center', 'ISP', etc.
    $table->string('country_code', 2)->nullable()->index();
    $table->string('country_name')->nullable();
    
    // Calculated spam score
    $table->unsignedTinyInteger('spam_score')->default(0)->index();
    $table->json('spam_indicators')->nullable();
    
    // Raw API response data
    $table->json('raw_abuseipdb_data')->nullable();
    $table->json('raw_geolocation_data')->nullable();
    
    // Cache management
    $table->timestamp('last_checked_at')->nullable()->index();
    $table->timestamp('expires_at')->nullable()->index();
    $table->unsignedInteger('check_count')->default(1);
    
    $table->timestamps();
    
    // Indexes for performance
    $table->index(['abuse_confidence', 'total_reports']);
    $table->index(['expires_at', 'last_checked_at']);
    $table->index(['country_code', 'abuse_confidence']);
});
```

### spam_patterns Table

Stores configurable spam detection patterns.

```php
// Migration: create_spam_patterns_table.php
Schema::create('spam_patterns', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('type')->index(); // 'name', 'email', 'content', 'ip'
    $table->string('category')->index(); // 'promotional', 'gibberish', 'malicious'
    $table->text('pattern'); // Regex pattern or keyword list
    $table->unsignedTinyInteger('score_weight')->default(10);
    $table->boolean('is_active')->default(true)->index();
    $table->text('description')->nullable();
    
    // Pattern metadata
    $table->unsignedInteger('match_count')->default(0);
    $table->timestamp('last_matched_at')->nullable();
    $table->decimal('accuracy_rate', 5, 2)->nullable(); // % of true positives
    
    // Management
    $table->unsignedBigInteger('created_by_user_id')->nullable();
    $table->unsignedBigInteger('updated_by_user_id')->nullable();
    
    $table->timestamps();
    
    $table->index(['type', 'is_active']);
    $table->index(['category', 'is_active']);
    $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
    $table->foreign('updated_by_user_id')->references('id')->on('users')->onDelete('set null');
});
```

### geolite2_locations Table

Stores MaxMind GeoLite2 location data.

```php
// Migration: create_geolite2_locations_table.php
Schema::create('geolite2_locations', function (Blueprint $table) {
    $table->id();
    $table->unsignedInteger('geoname_id')->unique();
    $table->string('locale_code', 2)->default('en');
    $table->string('continent_code', 2)->nullable();
    $table->string('continent_name')->nullable();
    $table->string('country_iso_code', 2)->nullable()->index();
    $table->string('country_name')->nullable();
    $table->string('subdivision_1_iso_code', 3)->nullable();
    $table->string('subdivision_1_name')->nullable();
    $table->string('subdivision_2_iso_code', 3)->nullable();
    $table->string('subdivision_2_name')->nullable();
    $table->string('city_name')->nullable();
    $table->string('metro_code', 10)->nullable();
    $table->string('time_zone')->nullable();
    $table->boolean('is_in_european_union')->default(false);
    
    // Computed fields
    $table->string('full_location')->nullable(); // "City, Region, Country"
    
    $table->timestamps();
    
    $table->index(['country_iso_code', 'subdivision_1_iso_code']);
    $table->index('city_name');
});
```

### geolite2_ipv4_blocks Table

Stores MaxMind GeoLite2 IPv4 block data.

```php
// Migration: create_geolite2_ipv4_blocks_table.php
Schema::create('geolite2_ipv4_blocks', function (Blueprint $table) {
    $table->id();
    $table->string('network', 18)->index(); // CIDR notation
    $table->unsignedBigInteger('network_start_int')->index();
    $table->unsignedBigInteger('network_end_int')->index();
    $table->unsignedInteger('geoname_id')->nullable();
    $table->unsignedInteger('registered_country_geoname_id')->nullable();
    $table->unsignedInteger('represented_country_geoname_id')->nullable();
    $table->boolean('is_anonymous_proxy')->default(false)->index();
    $table->boolean('is_satellite_provider')->default(false)->index();
    $table->string('postal_code', 10)->nullable();
    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    $table->unsignedSmallInteger('accuracy_radius')->nullable();
    $table->boolean('is_anycast')->default(false);
    
    // No timestamps for GeoLite2 data
    
    $table->index(['network_start_int', 'network_end_int']);
    $table->foreign('geoname_id')->references('geoname_id')->on('geolite2_locations')->onDelete('set null');
});
```

## User Table Extensions

### Additional User Fields

```php
// Migration: add_form_security_fields_to_users_table.php
Schema::table('users', function (Blueprint $table) {
    // Registration tracking
    $table->ipAddress('registration_ip')->nullable()->after('email_verified_at');
    $table->string('registration_country_code', 2)->nullable()->after('registration_ip');
    $table->string('registration_country_name')->nullable()->after('registration_country_code');
    $table->string('registration_region')->nullable()->after('registration_country_name');
    $table->string('registration_city')->nullable()->after('registration_region');
    $table->string('registration_isp')->nullable()->after('registration_city');
    
    // Spam scoring
    $table->unsignedTinyInteger('spam_score')->default(0)->after('registration_isp');
    $table->json('spam_indicators')->nullable()->after('spam_score');
    
    // AI analysis tracking
    $table->boolean('ai_analysis_pending')->default(false)->after('spam_indicators');
    $table->timestamp('ai_analysis_failed_at')->nullable()->after('ai_analysis_pending');
    $table->text('ai_analysis_error')->nullable()->after('ai_analysis_failed_at');
    
    // Blocking functionality
    $table->timestamp('blocked_at')->nullable()->after('ai_analysis_error');
    $table->string('blocked_reason')->nullable()->after('blocked_at');
    $table->unsignedBigInteger('blocked_by_user_id')->nullable()->after('blocked_reason');
    
    // Performance indexes
    $table->index(['registration_ip', 'created_at']);
    $table->index('spam_score');
    $table->index('blocked_at');
    $table->index('ai_analysis_pending');
    
    $table->foreign('blocked_by_user_id')->references('id')->on('users')->onDelete('set null');
});
```

## Model Classes

### BlockedSubmission Model

```php
class BlockedSubmission extends Model
{
    protected $fillable = [
        'form_type', 'route_name', 'request_uri', 'http_method',
        'name', 'email', 'ip_address', 'user_agent', 'referer',
        'country_code', 'country_name', 'region', 'city', 'isp',
        'latitude', 'longitude', 'spam_score', 'spam_indicators',
        'spam_threshold', 'validation_fields', 'ai_analysis_used',
        'ai_model', 'ai_confidence', 'form_data', 'request_headers',
        'session_id', 'user_id', 'blocked_at'
    ];
    
    protected $casts = [
        'spam_indicators' => 'array',
        'validation_fields' => 'array',
        'form_data' => 'array',
        'request_headers' => 'array',
        'ai_analysis_used' => 'boolean',
        'blocked_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'ai_confidence' => 'decimal:2',
    ];
    
    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    // Scopes
    public function scopeByFormType($query, string $type)
    {
        return $query->where('form_type', $type);
    }
    
    public function scopeHighRisk($query, int $threshold = 80)
    {
        return $query->where('spam_score', '>=', $threshold);
    }
    
    public function scopeRecentBlocks($query, int $hours = 24)
    {
        return $query->where('blocked_at', '>=', now()->subHours($hours));
    }
}
```

### IpReputation Model

```php
class IpReputation extends Model
{
    protected $table = 'ip_reputation';
    
    protected $fillable = [
        'ip_address', 'abuse_confidence', 'total_reports', 'is_whitelisted',
        'usage_type', 'country_code', 'country_name', 'spam_score',
        'spam_indicators', 'raw_abuseipdb_data', 'raw_geolocation_data',
        'last_checked_at', 'expires_at', 'check_count'
    ];
    
    protected $casts = [
        'is_whitelisted' => 'boolean',
        'spam_indicators' => 'array',
        'raw_abuseipdb_data' => 'array',
        'raw_geolocation_data' => 'array',
        'last_checked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
    
    // Helper methods
    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }
    
    public function isHighRisk(): bool
    {
        return $this->abuse_confidence >= 75 || $this->total_reports >= 20;
    }
    
    public function isSuspicious(): bool
    {
        return $this->abuse_confidence >= 25 || $this->total_reports >= 5;
    }
}
```

## Data Retention & Cleanup

### Automated Cleanup Policies

```php
// config/form-security.php
'data_retention' => [
    'blocked_submissions' => [
        'retention_days' => 90,
        'cleanup_schedule' => 'daily',
        'archive_before_delete' => true,
    ],
    'ip_reputation' => [
        'retention_days' => 365,
        'refresh_interval_days' => 30,
        'cleanup_expired' => true,
    ],
    'spam_patterns' => [
        'inactive_retention_days' => 180,
        'unused_pattern_days' => 60,
    ],
],
```

### Database Management Commands

```php
// Clean up old blocked submissions
php artisan form-security:cleanup --type=blocked-submissions --days=90

// Refresh expired IP reputation data
php artisan form-security:refresh-ip-reputation

// Archive old data before cleanup
php artisan form-security:archive --type=all --days=365

// GeoLite2 Database Import (Memory-Efficient Chunked Processing)
php artisan geolite2:import-chunked --limit=100000 --batch-size=1000
php artisan geolite2:import-chunked --skip=100000 --limit=100000  # Resume import
php artisan form-security:import-geolite2-locations
php artisan form-security:verify-geolite2
```

### GeoLite2 Import Performance

The `geolite2:import-chunked` command is specifically designed for memory-efficient processing of large GeoLite2 datasets:

- **Chunked Processing** - Processes data in configurable batch sizes (default: 1,000 records)
- **Memory Management** - Includes garbage collection to prevent memory exhaustion
- **Resumable Imports** - Can resume from any point using the `--skip` parameter
- **Progress Tracking** - Real-time progress bar with performance metrics
- **Error Recovery** - Graceful handling of import errors with detailed logging

This approach allows importing millions of GeoLite2 records without running out of memory, even on resource-constrained servers.

This database schema provides comprehensive tracking and analytics capabilities while maintaining performance through proper indexing and data retention policies.
