# Database Schema & Models Planning

**Ticket ID**: Research-Audit/1004-database-schema-models-planning
**Date Created**: 2025-01-27
**Status**: Complete

## Title
Database Schema & Models Planning - Detailed database design, migration strategy, and Eloquent model architecture

## Description
This ticket involves detailed planning of the database schema, migration strategy, and Eloquent model architecture for the JTD-FormSecurity foundation infrastructure. The planning will focus on performance optimization, scalability, data integrity, and Laravel 12 compatibility for handling high-volume form submissions and analytics queries.

**What needs to be accomplished:**
- Design detailed database schema for all four core tables (blocked_submissions, ip_reputation, spam_patterns, geolocation_data)
- Plan comprehensive indexing strategy for analytics and transactional queries
- Design Eloquent model relationships and query optimization
- Plan migration strategy with rollback procedures and conflict detection
- Design data retention and archival strategies
- Plan database seeding approach for initial data
- Design performance monitoring and optimization strategies
- Plan Laravel 12 migration features and database improvements utilization

**Why this work is necessary:**
- Establishes performant database foundation supporting 10,000+ daily submissions
- Ensures optimal query performance for both transactional and analytical workloads
- Provides scalable data architecture supporting package growth
- Establishes data integrity and consistency patterns
- Enables efficient data retention and compliance management

**Current state vs desired state:**
- Current: High-level database specifications and schema concepts
- Desired: Detailed implementation-ready database design with performance optimization

**Dependencies:**
- Architecture and design planning (1003) for integration patterns
- Technology research (1002) for database optimization techniques
- Laravel 12 database and migration improvements

## Related Documentation
- [ ] docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md - Database specifications
- [ ] docs/06-database-schema.md - Database schema documentation
- [ ] docs/Planning/Specs/Specialized-Features/SPEC-019-geolite2-database-management.md - GeoLite2 integration
- [ ] Laravel 12 Migration Documentation - Modern migration features
- [ ] Laravel 12 Eloquent Documentation - Model improvements and performance
- [ ] Database Performance Best Practices - Indexing and optimization strategies

## Related Files
- [ ] database/migrations/create_blocked_submissions_table.php - Core tracking table (needs creation)
- [ ] database/migrations/create_ip_reputation_table.php - IP reputation caching (needs creation)
- [ ] database/migrations/create_spam_patterns_table.php - Pattern storage (needs creation)
- [ ] database/migrations/create_geolocation_data_table.php - Location data (needs creation)
- [ ] database/seeders/SpamPatternsSeeder.php - Initial pattern data (needs creation)
- [ ] src/Models/BlockedSubmission.php - Primary tracking model (needs creation)
- [ ] src/Models/IpReputation.php - IP reputation model (needs creation)
- [ ] src/Models/SpamPattern.php - Pattern management model (needs creation)

## Related Tests
- [ ] tests/Unit/Models/ - Model unit tests for relationships and scopes
- [ ] tests/Feature/Database/ - Migration and seeding integration tests
- [ ] tests/Performance/DatabasePerformanceTest.php - Query performance benchmarking
- [ ] tests/Unit/DatabaseSchemaTest.php - Schema validation and integrity tests

## Acceptance Criteria
- [x] Complete database schema design with all tables, columns, and constraints
- [x] Comprehensive indexing strategy with performance benchmarks
- [x] Eloquent model architecture with optimized relationships and query scopes
- [x] Migration strategy with rollback procedures and conflict detection
- [x] Data retention and archival strategy with automated cleanup procedures
- [x] Database seeding plan with initial spam patterns and test data
- [x] Performance optimization plan targeting sub-100ms query times
- [x] Laravel 12 database feature utilization plan
- [x] Database monitoring and maintenance procedures

## AI Prompt
```
You are a Laravel AI package development expert. Please read this ticket fully: docs/Planning/Tickets/Foundation-Infrastructure/Research-Audit/1004-database-schema-models-planning.md, including the title, description, related documentation, files, and tests listed above.

TICKET DIRECTORY STRUCTURE:
- Template: docs/Planning/Tickets/template.md
- Major Features: Epics defined in docs/Planning/Epics/
- Phases: Research/Audit, Implementation, Test Implementation, Code Cleanup
- Format: docs/Planning/Tickets/<Major Feature>/<Phase>/####-short-description.md
- Numbering: 1000s for first major feature, 2000s for second, etc.

Based on this ticket:
1. Create a comprehensive task list breaking down all work needed to complete this ticket
2. Design detailed database schema optimized for high-volume form submissions
3. Plan indexing strategy for both transactional and analytical query performance
4. Design Eloquent model relationships leveraging Laravel 12 improvements
5. Plan migration strategy with Laravel 12 migration features
6. Plan the creation of subsequent Implementation phase tickets based on database design
7. Pause and wait for my review before proceeding with implementation

Please be thorough and consider Laravel 12 database improvements, modern indexing strategies, and high-performance Eloquent patterns.
```

## Phase Descriptions
- Research/Audit: 
  - Gather requirements and design detailed database architecture
  - Research Laravel 12 database and Eloquent improvements
  - Analyze performance requirements and plan optimization strategies
  - Generate tickets for Implementation, Test Implementation, and Code Cleanup based on database design
- Implementation: Develop migrations, models, and seeders
- Test Implementation: Write tests, verify functionality, performance, data integrity
- Code Cleanup: Refactor, optimize, remove technical debt (optional, only if needed)

## Notes
Database design is critical for package performance and scalability. Focus on Laravel 12 database improvements, modern indexing strategies, and query optimization for high-volume applications.

## Estimated Effort
Large (1-2 days)

## Dependencies
- [x] 1002-technology-best-practices-research - Database optimization techniques
- [x] 1003-architecture-design-planning - Integration architecture patterns
- [x] Laravel 12 database and migration documentation

## Research Findings & Analysis

### Database Requirements Analysis

Based on comprehensive analysis of SPEC-001-database-schema-models.md, SPEC-019-geolite2-database-management.md, and project guidelines, the following database requirements have been identified:

#### Core Tables Required
1. **blocked_submissions** - Primary tracking table for all blocked form submissions
2. **ip_reputation** - IP reputation caching with external service integration
3. **spam_patterns** - Configurable spam detection patterns with accuracy tracking
4. **geolite2_locations** - MaxMind location data with hierarchical relationships
5. **geolite2_ipv4_blocks** - IP block ranges with geolocation mapping

#### Performance Requirements
- Support 10,000+ daily form submissions with sub-100ms query times
- Handle concurrent writes up to 1,000 submissions/minute
- GeoLite2 import must handle 10M+ records without memory exhaustion
- Database queries must execute within 100ms for 95% of requests
- Cache hit ratio must exceed 90% for IP reputation and geolocation lookups

#### Data Retention & Compliance
- Blocked submissions retained for minimum 90 days
- IP reputation data expires after 30 days with automatic refresh
- GeoLite2 data updated monthly for accuracy
- All PII data sanitized before storage with configurable retention policies

### Laravel 12 Database Features Analysis

#### Migration Improvements
Laravel 12 maintains backward compatibility with existing migration features while providing:
- Enhanced migration events (MigrationsStarted, MigrationsEnded, MigrationStarted, MigrationEnded)
- Improved schema dumping with `schema:dump` command
- Better migration isolation with `--isolated` flag using cache-based locking
- Conditional migration execution with `shouldRun()` method
- Enhanced foreign key constraint management

#### Column Types & Modifiers
Laravel 12 continues to support all standard column types with enhanced modifiers:
- JSON columns with default expressions using `new Expression('(JSON_ARRAY())')`
- Enhanced indexing with composite indexes and full-text search
- Improved foreign key constraints with cascading options
- Better column modification with explicit attribute retention

#### Performance Features
- Optimized query builder with better index utilization
- Enhanced database connection management
- Improved migration performance with batch processing
- Better memory management for large dataset operations

### Database Schema Design

#### 1. blocked_submissions Table
```sql
CREATE TABLE blocked_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_type VARCHAR(255) NOT NULL,
    route_name VARCHAR(255) NULL,
    request_uri VARCHAR(255) NOT NULL,
    http_method VARCHAR(10) DEFAULT 'POST',
    name VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NULL,
    referer VARCHAR(500) NULL,
    country_code VARCHAR(2) NULL,
    country_name VARCHAR(255) NULL,
    region VARCHAR(255) NULL,
    city VARCHAR(255) NULL,
    isp VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    spam_score TINYINT UNSIGNED NOT NULL,
    spam_indicators JSON NULL,
    spam_threshold TINYINT UNSIGNED NOT NULL,
    validation_fields JSON NULL,
    ai_analysis_used BOOLEAN DEFAULT FALSE,
    ai_model VARCHAR(255) NULL,
    ai_confidence DECIMAL(5,2) NULL,
    form_data JSON NULL,
    request_headers JSON NULL,
    session_id VARCHAR(255) NULL,
    user_id BIGINT UNSIGNED NULL,
    blocked_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Performance-optimized indexes
    INDEX idx_form_type (form_type),
    INDEX idx_ip_address (ip_address),
    INDEX idx_country_code (country_code),
    INDEX idx_spam_score (spam_score),
    INDEX idx_blocked_at (blocked_at),
    INDEX idx_user_id (user_id),
    INDEX idx_ai_analysis_used (ai_analysis_used),

    -- Composite indexes for analytics queries
    INDEX idx_form_type_blocked_at (form_type, blocked_at),
    INDEX idx_country_code_blocked_at (country_code, blocked_at),
    INDEX idx_spam_score_blocked_at (spam_score, blocked_at),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

**Design Rationale:**
- BIGINT primary key supports high-volume applications
- JSON columns for flexible metadata storage
- Comprehensive indexing strategy for analytics queries
- Foreign key to users table with SET NULL for data integrity
- Timestamp fields for audit trails and data retention

#### 2. ip_reputation Table
```sql
CREATE TABLE ip_reputation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) UNIQUE NOT NULL,
    abuse_confidence TINYINT UNSIGNED DEFAULT 0,
    total_reports INT UNSIGNED DEFAULT 0,
    is_whitelisted BOOLEAN DEFAULT FALSE,
    usage_type VARCHAR(255) NULL,
    country_code VARCHAR(2) NULL,
    country_name VARCHAR(255) NULL,
    spam_score TINYINT UNSIGNED DEFAULT 0,
    spam_indicators JSON NULL,
    raw_abuseipdb_data JSON NULL,
    raw_geolocation_data JSON NULL,
    last_checked_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    check_count INT UNSIGNED DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Performance indexes for lookups
    INDEX idx_abuse_confidence (abuse_confidence),
    INDEX idx_is_whitelisted (is_whitelisted),
    INDEX idx_country_code (country_code),
    INDEX idx_spam_score (spam_score),
    INDEX idx_last_checked_at (last_checked_at),
    INDEX idx_expires_at (expires_at),

    -- Composite indexes for complex queries
    INDEX idx_abuse_confidence_total_reports (abuse_confidence, total_reports),
    INDEX idx_expires_at_last_checked_at (expires_at, last_checked_at),
    INDEX idx_country_code_abuse_confidence (country_code, abuse_confidence)
);
```

**Design Rationale:**
- Unique constraint on ip_address for efficient lookups
- TTL management with expires_at for automatic cleanup
- JSON storage for raw API responses
- Comprehensive indexing for reputation scoring queries

#### 3. spam_patterns Table
```sql
CREATE TABLE spam_patterns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    pattern_type ENUM('regex', 'keyword', 'email_domain', 'ip_range', 'user_agent') NOT NULL,
    pattern_value TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    score_weight TINYINT UNSIGNED DEFAULT 10,
    accuracy_rate DECIMAL(5,2) DEFAULT 0.00,
    total_matches INT UNSIGNED DEFAULT 0,
    false_positives INT UNSIGNED DEFAULT 0,
    last_matched_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Performance indexes
    INDEX idx_pattern_type (pattern_type),
    INDEX idx_is_active (is_active),
    INDEX idx_severity (severity),
    INDEX idx_accuracy_rate (accuracy_rate),
    INDEX idx_last_matched_at (last_matched_at),

    -- Composite indexes for pattern matching
    INDEX idx_pattern_type_active (pattern_type, is_active),
    INDEX idx_severity_active (severity, is_active)
);
```

**Design Rationale:**
- ENUM types for controlled pattern types and severity levels
- Accuracy tracking for pattern effectiveness monitoring
- Flexible pattern storage with TEXT field
- Performance indexes for pattern matching queries

#### 4. geolite2_locations Table
```sql
CREATE TABLE geolite2_locations (
    geoname_id INT UNSIGNED PRIMARY KEY,
    locale_code VARCHAR(2) DEFAULT 'en',
    continent_code VARCHAR(2) NULL,
    continent_name VARCHAR(255) NULL,
    country_iso_code VARCHAR(2) NULL,
    country_name VARCHAR(255) NULL,
    subdivision_1_iso_code VARCHAR(3) NULL,
    subdivision_1_name VARCHAR(255) NULL,
    subdivision_2_iso_code VARCHAR(3) NULL,
    subdivision_2_name VARCHAR(255) NULL,
    city_name VARCHAR(255) NULL,
    metro_code INT UNSIGNED NULL,
    time_zone VARCHAR(255) NULL,
    is_in_european_union BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Geographic lookup indexes
    INDEX idx_country_iso_code (country_iso_code),
    INDEX idx_continent_code (continent_code),
    INDEX idx_subdivision_1_iso_code (subdivision_1_iso_code),
    INDEX idx_city_name (city_name),
    INDEX idx_time_zone (time_zone),

    -- Composite indexes for hierarchical queries
    INDEX idx_country_subdivision (country_iso_code, subdivision_1_iso_code),
    INDEX idx_continent_country (continent_code, country_iso_code)
);
```

#### 5. geolite2_ipv4_blocks Table
```sql
CREATE TABLE geolite2_ipv4_blocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    network VARCHAR(18) NOT NULL,
    geoname_id INT UNSIGNED NULL,
    registered_country_geoname_id INT UNSIGNED NULL,
    represented_country_geoname_id INT UNSIGNED NULL,
    is_anonymous_proxy BOOLEAN DEFAULT FALSE,
    is_satellite_provider BOOLEAN DEFAULT FALSE,
    postal_code VARCHAR(10) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    accuracy_radius INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Network lookup indexes
    INDEX idx_network (network),
    INDEX idx_geoname_id (geoname_id),
    INDEX idx_registered_country_geoname_id (registered_country_geoname_id),
    INDEX idx_is_anonymous_proxy (is_anonymous_proxy),
    INDEX idx_is_satellite_provider (is_satellite_provider),

    -- Foreign key constraints
    FOREIGN KEY (geoname_id) REFERENCES geolite2_locations(geoname_id) ON DELETE SET NULL,
    FOREIGN KEY (registered_country_geoname_id) REFERENCES geolite2_locations(geoname_id) ON DELETE SET NULL,
    FOREIGN KEY (represented_country_geoname_id) REFERENCES geolite2_locations(geoname_id) ON DELETE SET NULL
);
```

### Eloquent Model Architecture

#### 1. BlockedSubmission Model
```php
<?php

namespace JTD\FormSecurity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

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
        'ai_confidence' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'blocked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    // Query Scopes
    public function scopeByFormType(Builder $query, string $formType): Builder
    {
        return $query->where('form_type', $formType);
    }

    public function scopeByCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeHighSpamScore(Builder $query, int $threshold = 80): Builder
    {
        return $query->where('spam_score', '>=', $threshold);
    }

    public function scopeWithAiAnalysis(Builder $query): Builder
    {
        return $query->where('ai_analysis_used', true);
    }

    public function scopeRecentBlocks(Builder $query, int $days = 30): Builder
    {
        return $query->where('blocked_at', '>=', Carbon::now()->subDays($days));
    }

    // Business Logic Methods
    public function isHighRisk(): bool
    {
        return $this->spam_score >= 80;
    }

    public function getSpamIndicatorsAttribute($value): array
    {
        return $value ? json_decode($value, true) : [];
    }

    public function getFormDataAttribute($value): array
    {
        return $value ? json_decode($value, true) : [];
    }
}
```

#### 2. IpReputation Model
```php
<?php

namespace JTD\FormSecurity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class IpReputation extends Model
{
    protected $table = 'ip_reputation';

    protected $fillable = [
        'ip_address', 'abuse_confidence', 'total_reports',
        'is_whitelisted', 'usage_type', 'country_code',
        'country_name', 'spam_score', 'spam_indicators',
        'raw_abuseipdb_data', 'raw_geolocation_data',
        'last_checked_at', 'expires_at', 'check_count'
    ];

    protected $casts = [
        'abuse_confidence' => 'integer',
        'total_reports' => 'integer',
        'is_whitelisted' => 'boolean',
        'spam_score' => 'integer',
        'spam_indicators' => 'array',
        'raw_abuseipdb_data' => 'array',
        'raw_geolocation_data' => 'array',
        'last_checked_at' => 'datetime',
        'expires_at' => 'datetime',
        'check_count' => 'integer',
    ];

    // Query Scopes
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', Carbon::now());
    }

    public function scopeWhitelisted(Builder $query): Builder
    {
        return $query->where('is_whitelisted', true);
    }

    public function scopeHighRisk(Builder $query, int $threshold = 75): Builder
    {
        return $query->where('abuse_confidence', '>=', $threshold);
    }

    public function scopeByCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    // Business Logic Methods
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isHighRisk(): bool
    {
        return $this->abuse_confidence >= 75;
    }

    public function needsRefresh(): bool
    {
        return $this->isExpired() ||
               ($this->last_checked_at && $this->last_checked_at->diffInDays() > 7);
    }

    public function calculateRiskScore(): int
    {
        $score = $this->abuse_confidence;

        if ($this->is_whitelisted) {
            return 0;
        }

        // Adjust score based on total reports
        if ($this->total_reports > 100) {
            $score += 10;
        } elseif ($this->total_reports > 50) {
            $score += 5;
        }

        return min($score, 100);
    }
}
```

### Migration Strategy

#### Migration File Structure
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('form_type')->index();
            $table->string('route_name')->nullable();
            $table->string('request_uri');
            $table->string('http_method', 10)->default('POST');

            // User data (sanitized)
            $table->string('name')->nullable();
            $table->string('email')->nullable();

            // Request metadata
            $table->string('ip_address', 45)->index();
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

            // Spam detection data
            $table->tinyInteger('spam_score')->unsigned()->index();
            $table->json('spam_indicators')->nullable();
            $table->tinyInteger('spam_threshold')->unsigned();
            $table->json('validation_fields')->nullable();

            // AI analysis data
            $table->boolean('ai_analysis_used')->default(false)->index();
            $table->string('ai_model')->nullable();
            $table->decimal('ai_confidence', 5, 2)->nullable();

            // Request data (sanitized)
            $table->json('form_data')->nullable();
            $table->json('request_headers')->nullable();
            $table->string('session_id')->nullable();

            // User relationship
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // Timestamps
            $table->timestamp('blocked_at')->index();
            $table->timestamps();

            // Composite indexes for analytics
            $table->index(['form_type', 'blocked_at']);
            $table->index(['country_code', 'blocked_at']);
            $table->index(['spam_score', 'blocked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_submissions');
    }
};
```

#### Rollback Strategy
- All migrations include proper `down()` methods
- Foreign key constraints use `onDelete('set null')` to prevent cascade issues
- Index dropping handled automatically by Laravel
- Data backup procedures before major schema changes

### Performance Optimization Strategy

#### Indexing Strategy
1. **Primary Indexes**: All frequently queried columns (ip_address, form_type, country_code, spam_score)
2. **Composite Indexes**: Analytics queries (form_type + blocked_at, country_code + blocked_at)
3. **JSON Indexes**: MySQL 8.0+ functional indexes on JSON columns for specific queries
4. **Partial Indexes**: PostgreSQL partial indexes for active records only

#### Query Optimization
1. **Eager Loading**: Preload relationships to avoid N+1 queries
2. **Query Scopes**: Reusable query logic in model scopes
3. **Database Chunking**: Process large datasets in chunks to avoid memory issues
4. **Connection Pooling**: Use persistent connections for high-volume operations

#### Caching Strategy
1. **Model Caching**: Cache frequently accessed IP reputation data
2. **Query Result Caching**: Cache expensive analytics queries
3. **Geolocation Caching**: Cache GeoLite2 lookups with appropriate TTL
4. **Cache Invalidation**: Automatic cache clearing on data updates

### Data Retention & Cleanup Strategy

#### Automated Cleanup Procedures
```php
// Console command for data cleanup
class CleanupBlockedSubmissions extends Command
{
    protected $signature = 'form-security:cleanup {--days=90}';

    public function handle()
    {
        $days = $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        $deleted = BlockedSubmission::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} blocked submissions older than {$days} days");
    }
}
```

#### Archival Strategy
1. **Cold Storage**: Move old data to separate archive tables
2. **Compression**: Use database compression for archived data
3. **Backup Integration**: Automated backups before cleanup operations
4. **Compliance**: GDPR-compliant data retention and deletion

### Laravel 12 Feature Utilization

#### Enhanced Migration Features
1. **Migration Events**: Listen to migration events for custom logic
2. **Conditional Migrations**: Use `shouldRun()` for feature-flag based migrations
3. **Schema Dumping**: Use `schema:dump` for faster test database setup
4. **Migration Isolation**: Use `--isolated` flag for production deployments

#### Modern PHP 8.2+ Features
1. **Readonly Properties**: Use for immutable model attributes
2. **Enums**: Replace string constants with typed enums
3. **Union Types**: Better type hints for flexible parameters
4. **Match Expressions**: Cleaner conditional logic in models

### Implementation Roadmap

#### Phase 1: Core Tables (Week 1)
1. Create blocked_submissions migration and model
2. Create ip_reputation migration and model
3. Implement basic relationships and scopes
4. Add comprehensive test coverage

#### Phase 2: Pattern Management (Week 1)
1. Create spam_patterns migration and model
2. Implement pattern matching logic
3. Add accuracy tracking functionality
4. Create pattern management commands

#### Phase 3: GeoLite2 Integration (Week 2)
1. Create geolite2_locations migration and model
2. Create geolite2_ipv4_blocks migration and model
3. Implement chunked import system
4. Add geolocation lookup functionality

#### Phase 4: Optimization & Cleanup (Week 2)
1. Implement data retention policies
2. Add performance monitoring
3. Optimize queries and indexes
4. Complete documentation and testing

### Risk Mitigation

#### High-Risk Areas
1. **GeoLite2 Import Memory Usage**: Implement chunked processing with memory monitoring
2. **Database Performance**: Comprehensive indexing and query optimization
3. **Data Integrity**: Foreign key constraints and validation at multiple levels
4. **Migration Conflicts**: Thorough testing on multiple database systems

#### Mitigation Strategies
1. **Performance Testing**: Benchmark all queries under expected load
2. **Memory Monitoring**: Track memory usage during large imports
3. **Rollback Procedures**: Test all migration rollbacks
4. **Data Validation**: Multi-layer validation (request, service, model, database)

### Success Metrics

#### Performance Targets
- Database queries complete within 100ms for 95% of requests
- Support 1,000+ concurrent blocked submission inserts per minute
- GeoLite2 import uses less than 512MB memory regardless of dataset size
- Cache hit ratio exceeds 90% for IP reputation lookups

#### Quality Targets
- 90%+ test coverage for all models and migrations
- Zero migration rollback failures
- All foreign key constraints properly enforced
- Complete data integrity validation

This comprehensive database schema and models planning provides a solid foundation for the JTD-FormSecurity package, leveraging Laravel 12 features while maintaining high performance and scalability for enterprise-grade applications.
