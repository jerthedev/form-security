# JTD-FormSecurity Models Directory

This directory contains all Eloquent models for the JTD-FormSecurity package. All models follow Laravel 12 best practices and implement PHP 8.2+ features for optimal performance and type safety.

## Model Architecture

### Base Classes and Interfaces

- **BaseModel.php** - Abstract base class that all models extend
- **Contracts/ModelInterface.php** - Base interface for all models
- **Contracts/AnalyticsModelInterface.php** - Interface for models with analytics capabilities
- **Contracts/CacheableModelInterface.php** - Interface for models with caching support

### Core Models

1. **BlockedSubmission.php** - Tracks blocked form submissions
   - Primary table: `blocked_submissions`
   - Implements: `AnalyticsModelInterface`
   - Features: Risk scoring, geolocation, analytics scopes

2. **IpReputation.php** - Caches IP reputation data
   - Primary table: `ip_reputation`
   - Implements: `CacheableModelInterface`, `AnalyticsModelInterface`
   - Features: Threat intelligence, reputation scoring, cache management

3. **SpamPattern.php** - Manages spam detection patterns
   - Primary table: `spam_patterns`
   - Implements: `CacheableModelInterface`
   - Features: Pattern matching, accuracy tracking, performance optimization

4. **GeoLite2Location.php** - Geographic location data
   - Primary table: `geolite2_locations`
   - Features: Location lookups, coordinate-based queries

5. **GeoLite2IpBlock.php** - IP block ranges for geolocation
   - Primary table: `geolite2_ipv4_blocks`
   - Features: IP range lookups, geolocation mapping

## Model Conventions

### PHP 8.2+ Features
- Strict typing with `declare(strict_types=1)`
- Readonly properties where appropriate
- Enum usage for status fields
- Union types for flexible parameters
- Match expressions for cleaner conditionals

### Laravel 12 Features
- Enhanced Eloquent relationships
- Improved query builder methods
- Modern factory patterns
- Advanced casting capabilities

### Naming Conventions
- **Classes**: PascalCase (e.g., `BlockedSubmission`)
- **Methods**: camelCase (e.g., `getLocationString`)
- **Properties**: camelCase (e.g., `$ipAddress`)
- **Database columns**: snake_case (e.g., `ip_address`)
- **Scopes**: Prefixed with `scope` (e.g., `scopeByCountry`)

### Documentation Standards
- Comprehensive PHPDoc blocks for all public methods
- Property type hints using `@property` annotations
- Method parameter and return type documentation
- Usage examples in complex methods

### Performance Considerations
- Proper indexing strategies for high-volume queries
- Eager loading relationships to prevent N+1 queries
- Query scopes for common filtering operations
- Caching strategies for frequently accessed data
- Target: <100ms query response times

### Testing Requirements
- Unit tests for all model methods and scopes
- Integration tests for model relationships
- Performance tests for query optimization
- Factory definitions for test data generation
- PHPUnit 12 attributes for test grouping

## Relationships

### Model Relationships Overview
- `BlockedSubmission` ↔ `IpReputation` (via ip_address)
- `GeoLite2IpBlock` → `GeoLite2Location` (via geoname_id)
- `BlockedSubmission` uses geolocation data from GeoLite2 models
- `SpamPattern` is referenced by block_reason in BlockedSubmission

### Foreign Key Constraints
- Proper foreign key relationships defined in migrations
- Cascade delete policies where appropriate
- Null handling for optional relationships

## Query Scopes

### Common Scopes (Available in BaseModel)
- Date range filtering
- Recent record filtering
- Timestamp-based queries

### Model-Specific Scopes
- **BlockedSubmission**: byFormIdentifier, byBlockReason, byCountry, highRisk
- **IpReputation**: byReputationStatus, byThreatType, expired
- **SpamPattern**: active, byPatternType, byRiskScore
- **GeoLite2**: byCountry, byCoordinates, byPostalCode

## Business Logic Methods

### Risk Assessment
- Spam scoring algorithms
- Reputation calculation
- Threat level determination

### Analytics
- Aggregation queries
- Reporting methods
- Statistical calculations

### Caching
- Cache key generation
- Expiration management
- Cache invalidation

## Usage Examples

```php
// Get high-risk submissions from the last 24 hours
$highRiskSubmissions = BlockedSubmission::highRisk()
    ->recentBlocks(24)
    ->with(['ipReputation'])
    ->get();

// Find IP reputation with caching
$reputation = IpReputation::getCached('192.168.1.1');

// Get active spam patterns for email validation
$emailPatterns = SpamPattern::active()
    ->byPatternType('email_pattern')
    ->orderBy('priority')
    ->get();
```

## Performance Targets

- Query response times: <100ms for standard operations
- Cache hit ratio: >90% for frequently accessed data
- Memory usage: <50MB for typical operations
- Database connection efficiency: Connection pooling enabled

## Testing Strategy

All models include comprehensive test coverage:
- **Unit Tests**: Individual method testing
- **Integration Tests**: Relationship and complex query testing
- **Performance Tests**: Query optimization validation
- **Factory Tests**: Test data generation verification

Test files are located in `tests/Unit/Models/` and `tests/Integration/Models/`.
