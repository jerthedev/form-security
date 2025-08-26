# User Model Extensions (HasSpamProtection) Specification

**Spec ID**: SPEC-007-user-model-extensions  
**Date Created**: 2025-01-27  
**Last Updated**: 2025-01-27  
**Status**: Draft  
**Priority**: High  
**Related Epic**: EPIC-002 - JTD-FormSecurity Core Features

## Title
User Model Extensions (HasSpamProtection) - Trait and database fields for user spam tracking and management

## Feature Overview
This specification defines comprehensive extensions to Laravel's User model that provide spam protection, tracking, and management capabilities. The system includes database schema extensions, a powerful HasSpamProtection trait, and specialized analysis methods for user registration data. These extensions enable comprehensive user spam scoring, geolocation tracking, AI analysis management, and user blocking functionality.

The extensions are designed to be non-intrusive and backward-compatible with existing User models while providing powerful spam protection capabilities. The system automatically tracks registration metadata, maintains spam scores, manages AI analysis workflows, and provides administrative tools for user management.

Key components include:
- Database schema extensions for spam tracking fields
- HasSpamProtection trait with comprehensive spam management methods
- User registration analysis and velocity checking
- Geolocation tracking and IP reputation integration
- AI analysis workflow management
- Administrative blocking and unblocking capabilities

## Purpose & Rationale
### Business Justification
- **User Quality**: Maintains high-quality user base by identifying and managing spam registrations
- **Operational Efficiency**: Automated spam scoring reduces manual moderation workload
- **Security Enhancement**: Comprehensive tracking enables identification of attack patterns
- **Compliance Support**: Detailed logging supports security audits and compliance requirements

### Technical Justification
- **Data Integrity**: Centralized spam tracking ensures consistent data across the application
- **Performance**: Indexed fields enable efficient queries for spam analysis and reporting
- **Extensibility**: Trait-based approach allows easy integration with existing User models
- **Maintainability**: Clean separation of spam-related functionality from core user logic

## Detailed Requirements

### Functional Requirements
- [ ] **FR-001**: Create database migration to extend users table with spam protection fields
- [ ] **FR-002**: Implement HasSpamProtection trait with comprehensive spam management methods
- [ ] **FR-003**: Provide user spam scoring and indicator tracking functionality
- [ ] **FR-004**: Implement user blocking and unblocking capabilities with audit trails
- [ ] **FR-005**: Create geolocation tracking for user registration data
- [ ] **FR-006**: Implement AI analysis workflow management for users
- [ ] **FR-007**: Provide velocity checking and registration pattern analysis
- [ ] **FR-008**: Create administrative methods for bulk user management

### Non-Functional Requirements
- [ ] **NFR-001**: Database queries using spam fields must execute within 50ms for 95% of requests
- [ ] **NFR-002**: User model extensions must not impact existing application performance
- [ ] **NFR-003**: Spam score updates must be atomic and consistent across concurrent requests
- [ ] **NFR-004**: Migration must be backward-compatible and reversible
- [ ] **NFR-005**: Trait methods must be memory-efficient for bulk operations

### Business Rules
- [ ] **BR-001**: Spam scores must be constrained between 0-100 with automatic clamping
- [ ] **BR-002**: User blocking must invalidate all active sessions and tokens
- [ ] **BR-003**: Geolocation data must be populated during registration process
- [ ] **BR-004**: AI analysis pending flag must be cleared after successful analysis
- [ ] **BR-005**: Blocked users must not be able to authenticate or perform actions

## Technical Architecture

### System Components
- **Database Migration**: Schema extension for users table with spam protection fields
- **HasSpamProtection Trait**: Core trait providing spam management functionality
- **UserSpamAnalyzer**: Specialized analyzer for user registration data
- **VelocityChecker**: Registration velocity analysis and rate limiting
- **GeolocationTracker**: IP-based geolocation data collection and storage
- **UserBlockingService**: Administrative user blocking and management service

### Data Architecture
#### Database Schema Extensions
```sql
-- Users table extensions
ALTER TABLE users ADD COLUMN registration_ip VARCHAR(45) NULL AFTER email_verified_at;
ALTER TABLE users ADD COLUMN registration_country_code VARCHAR(2) NULL AFTER registration_ip;
ALTER TABLE users ADD COLUMN registration_country_name VARCHAR(255) NULL AFTER registration_country_code;
ALTER TABLE users ADD COLUMN registration_region VARCHAR(255) NULL AFTER registration_country_name;
ALTER TABLE users ADD COLUMN registration_city VARCHAR(255) NULL AFTER registration_region;
ALTER TABLE users ADD COLUMN registration_isp VARCHAR(255) NULL AFTER registration_city;
ALTER TABLE users ADD COLUMN spam_score TINYINT UNSIGNED DEFAULT 0 AFTER registration_isp;
ALTER TABLE users ADD COLUMN spam_indicators JSON NULL AFTER spam_score;
ALTER TABLE users ADD COLUMN ai_analysis_pending BOOLEAN DEFAULT FALSE AFTER spam_indicators;
ALTER TABLE users ADD COLUMN ai_analysis_failed_at TIMESTAMP NULL AFTER ai_analysis_pending;
ALTER TABLE users ADD COLUMN ai_analysis_error TEXT NULL AFTER ai_analysis_failed_at;
ALTER TABLE users ADD COLUMN blocked_at TIMESTAMP NULL AFTER ai_analysis_error;
ALTER TABLE users ADD COLUMN blocked_reason VARCHAR(255) NULL AFTER blocked_at;
ALTER TABLE users ADD COLUMN blocked_by_user_id BIGINT UNSIGNED NULL AFTER blocked_reason;

-- Performance indexes
CREATE INDEX idx_users_registration_ip_created_at ON users(registration_ip, created_at);
CREATE INDEX idx_users_spam_score ON users(spam_score);
CREATE INDEX idx_users_blocked_at ON users(blocked_at);
CREATE INDEX idx_users_ai_analysis_pending ON users(ai_analysis_pending);

-- Foreign key constraint
ALTER TABLE users ADD CONSTRAINT fk_users_blocked_by_user_id 
    FOREIGN KEY (blocked_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
```

#### User Model Structure
```php
class User extends Authenticatable
{
    use HasSpamProtection;
    
    protected $fillable = [
        'name', 'email', 'password',
        'registration_ip', 'registration_country_code', 'registration_country_name',
        'registration_region', 'registration_city', 'registration_isp',
        'spam_score', 'spam_indicators',
        'ai_analysis_pending', 'ai_analysis_failed_at', 'ai_analysis_error',
        'blocked_at', 'blocked_reason', 'blocked_by_user_id',
    ];
    
    protected $casts = [
        'spam_indicators' => 'array',
        'ai_analysis_pending' => 'boolean',
        'ai_analysis_failed_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];
}
```

### API Specifications

#### HasSpamProtection Trait Methods
```php
trait HasSpamProtection
{
    // Spam status checking
    public function isLikelySpam(): bool;
    public function shouldBeBlocked(): bool;
    public function isBlocked(): bool;
    public function getSpamRiskLevel(): string; // 'low', 'medium', 'high'
    
    // Spam score management
    public function updateSpamScore(int $score, array $indicators = []): void;
    public function incrementSpamScore(int $increment, array $newIndicators = []): void;
    public function resetSpamScore(): void;
    
    // User blocking management
    public function blockUser(string $reason = 'High spam score', ?int $blockedByUserId = null): void;
    public function unblockUser(): void;
    public function getBlockingHistory(): Collection;
    
    // AI analysis management
    public function markForAiAnalysis(?string $error = null): void;
    public function clearAiAnalysisPending(): void;
    public function hasFailedAiAnalysis(): bool;
    
    // Geolocation methods
    public function getRegistrationLocation(): ?array;
    public function isFromHighRiskRegion(): bool;
    public function getRegistrationDistance(float $lat, float $lng): ?float;
    
    // Query scopes
    public function scopeSpammy(Builder $query, int $threshold = 70): Builder;
    public function scopeBlocked(Builder $query): Builder;
    public function scopePendingAiAnalysis(Builder $query): Builder;
    public function scopeFromIP(Builder $query, string $ip): Builder;
    public function scopeFromCountry(Builder $query, string $countryCode): Builder;
}
```

#### Usage Examples
```php
// Check user spam status
if ($user->isLikelySpam()) {
    // Handle spam user
}

// Update spam score
$user->updateSpamScore(85, [
    'Suspicious email pattern',
    'High-risk IP address',
    'Rapid registration velocity'
]);

// Block user
$user->blockUser('Confirmed spam account', auth()->id());

// Query spam users
$spamUsers = User::spammy(80)->get();
$blockedUsers = User::blocked()->get();
$pendingAnalysis = User::pendingAiAnalysis()->get();
```

### Integration Requirements
- **Internal Integrations**: Integration with spam detection service and validation rules
- **External Integrations**: Laravel authentication system and database layer
- **Event System**: User spam events (UserSpamScoreUpdated, UserBlocked, UserUnblocked)
- **Queue/Job Requirements**: Background AI analysis and geolocation processing

## Performance Requirements
- [ ] **Database Performance**: Spam-related queries execute within 50ms for 95% of requests
- [ ] **Memory Efficiency**: Trait methods use minimal memory for bulk operations
- [ ] **Index Optimization**: All spam-related queries utilize proper database indexes
- [ ] **Concurrent Safety**: Spam score updates are atomic and handle concurrent modifications
- [ ] **Bulk Operations**: Support efficient bulk operations for administrative tasks

## Security Considerations
- [ ] **Data Protection**: User spam data handled securely with appropriate access controls
- [ ] **Privacy Compliance**: Geolocation and tracking data complies with privacy regulations
- [ ] **Audit Logging**: All spam score changes and blocking actions logged with attribution
- [ ] **Access Control**: Administrative functions restricted to authorized users
- [ ] **Session Management**: User blocking invalidates all active sessions and tokens

## Testing Requirements

### Unit Testing
- [ ] HasSpamProtection trait methods with various spam scenarios
- [ ] Spam score calculation and clamping logic
- [ ] User blocking and unblocking functionality
- [ ] Geolocation tracking and analysis methods

### Integration Testing
- [ ] Database migration and rollback functionality
- [ ] User model integration with existing authentication system
- [ ] Event system integration for spam-related events
- [ ] Performance testing with large user datasets

### Security Testing
- [ ] Access control for administrative spam management functions
- [ ] Data protection and privacy compliance validation
- [ ] Session invalidation during user blocking
- [ ] Audit logging accuracy and completeness

## Implementation Guidelines

### Development Standards
- [ ] Follow Laravel model and trait conventions
- [ ] Implement comprehensive error handling for all trait methods
- [ ] Use database transactions for atomic spam score updates
- [ ] Maintain backward compatibility with existing User models

### Migration Strategy
- [ ] Create reversible migration with proper rollback functionality
- [ ] Implement data seeding for existing users if needed
- [ ] Provide migration testing with various database configurations
- [ ] Document migration impact and requirements

## Dependencies & Prerequisites
### Internal Dependencies
- [ ] Database schema (SPEC-001) for related tables and constraints
- [ ] Configuration management system (SPEC-002) for spam thresholds
- [ ] Spam detection service (SPEC-004) for analysis functionality

### External Dependencies
- [ ] Laravel framework 12.x with Eloquent ORM and authentication
- [ ] Database system supporting JSON columns and proper indexing
- [ ] PHP 8.2+ for modern language features and performance

## Success Criteria & Acceptance
- [ ] Database migration successfully extends users table with all required fields
- [ ] HasSpamProtection trait provides comprehensive spam management functionality
- [ ] User spam scoring and tracking works accurately across all scenarios
- [ ] Administrative blocking and unblocking functions operate correctly
- [ ] Performance requirements met for all spam-related database operations
- [ ] Integration with existing User models requires minimal code changes

### Definition of Done
- [ ] Complete database migration with all spam protection fields
- [ ] HasSpamProtection trait implemented with all specified methods
- [ ] User spam analysis and velocity checking functionality
- [ ] Administrative user management tools and interfaces
- [ ] Comprehensive test suite with >90% code coverage
- [ ] Performance optimization and database indexing
- [ ] Security review completed for user data handling
- [ ] Documentation with integration examples and best practices
- [ ] Migration guide for existing applications

## Related Documentation
- [ ] [Epic EPIC-002] - JTD-FormSecurity Core Features
- [ ] [SPEC-001] - Database Schema & Models for related table structures
- [ ] [SPEC-006] - Specialized Validation Rules integration
- [ ] [User Model Integration Guide] - Complete integration instructions and examples

## Notes
The User Model Extensions provide the foundation for user-based spam protection and tracking. The HasSpamProtection trait should be designed for easy integration with existing User models while providing comprehensive spam management capabilities. Special attention should be paid to performance optimization and backward compatibility.

## Spec Completion Checklist
- [x] All requirements clearly defined and measurable
- [x] Technical architecture fully specified
- [x] API specifications complete with examples
- [x] Performance requirements specified with metrics
- [x] Security requirements identified and planned
- [x] Dependencies mapped and validated
- [x] Success criteria and acceptance criteria defined
- [ ] Stakeholder review completed and approved
