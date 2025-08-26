# Database Schema & Models Specification

**Spec ID**: SPEC-001-database-schema-models  
**Date Created**: 2025-01-27  
**Last Updated**: 2025-01-27  
**Status**: Draft  
**Priority**: High  
**Related Epic**: EPIC-001 - JTD-FormSecurity Foundation Infrastructure

## Title
Database Schema & Models - Complete database design for comprehensive form security tracking

## Feature Overview
This specification defines the complete database schema and Eloquent models for the JTD-FormSecurity package. The database design provides comprehensive tracking of spam attempts, IP reputation data, geolocation information, spam patterns, and user security extensions. The schema is optimized for performance, analytics, and compliance with data retention policies.

Key components include:
- Blocked submissions tracking with detailed metadata
- IP reputation caching system with external service integration
- Configurable spam pattern management
- GeoLite2 geolocation data storage with memory-efficient import
- User model extensions for spam protection
- Comprehensive indexing strategy for analytics and performance

## Purpose & Rationale
### Business Justification
- **Comprehensive Tracking**: Enables detailed analysis of spam attempts and attack patterns for continuous improvement
- **Performance Optimization**: Caches external API data to reduce costs and improve response times
- **Compliance Support**: Provides audit trails and data retention capabilities for regulatory compliance
- **Analytics Foundation**: Creates data foundation for reporting, monitoring, and business intelligence

### Technical Justification
- **Scalable Architecture**: Designed to handle high-volume form submissions with proper indexing
- **Data Integrity**: Implements foreign key constraints and validation at the database level
- **Memory Efficiency**: Includes chunked import strategies for large datasets like GeoLite2
- **Performance Optimization**: Strategic indexing for common query patterns and analytics

## Detailed Requirements

### Functional Requirements
- [ ] **FR-001**: Create blocked_submissions table to track all blocked form submissions with comprehensive metadata
- [ ] **FR-002**: Implement ip_reputation table for caching external IP reputation data with TTL management
- [ ] **FR-003**: Design spam_patterns table for configurable spam detection rules and pattern management
- [ ] **FR-004**: Create geolite2_locations and geolite2_ipv4_blocks tables for geolocation data
- [ ] **FR-005**: Extend users table with spam protection fields and registration tracking
- [ ] **FR-006**: Implement comprehensive Eloquent models with relationships and business logic
- [ ] **FR-007**: Create database indexes optimized for analytics queries and performance
- [ ] **FR-008**: Implement data retention and cleanup policies with configurable retention periods

### Non-Functional Requirements
- [ ] **NFR-001**: Database queries must execute within 100ms for 95% of requests under normal load
- [ ] **NFR-002**: Support concurrent writes to blocked_submissions table up to 1000 submissions/minute
- [ ] **NFR-003**: GeoLite2 import process must handle datasets up to 10 million records without memory exhaustion
- [ ] **NFR-004**: Database schema must support horizontal scaling through proper partitioning strategies
- [ ] **NFR-005**: All sensitive data must be encrypted at rest and properly sanitized before storage

### Business Rules
- [ ] **BR-001**: Blocked submissions data must be retained for minimum 90 days for analysis purposes
- [ ] **BR-002**: IP reputation data expires after 30 days and must be refreshed from external sources
- [ ] **BR-003**: Spam patterns must track accuracy metrics and can be automatically disabled if accuracy falls below 70%
- [ ] **BR-004**: User spam scores must be recalculated when new spam indicators are detected
- [ ] **BR-005**: GeoLite2 data must be updated monthly to maintain location accuracy

## Technical Architecture

### System Components
- **Migration System**: Laravel migrations for all database schema changes with rollback support
- **Model Layer**: Eloquent models with relationships, scopes, and business logic methods
- **Index Strategy**: Comprehensive indexing for performance optimization and analytics queries
- **Data Management**: Automated cleanup and retention policies with configurable schedules

### Data Architecture
#### Database Schema
```sql
-- Core blocked submissions tracking
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
    
    INDEX idx_form_type (form_type),
    INDEX idx_ip_address (ip_address),
    INDEX idx_country_code (country_code),
    INDEX idx_spam_score (spam_score),
    INDEX idx_blocked_at (blocked_at),
    INDEX idx_user_id (user_id),
    INDEX idx_ai_analysis_used (ai_analysis_used),
    INDEX idx_form_type_blocked_at (form_type, blocked_at),
    INDEX idx_country_code_blocked_at (country_code, blocked_at),
    INDEX idx_spam_score_blocked_at (spam_score, blocked_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- IP reputation caching
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
    
    INDEX idx_abuse_confidence (abuse_confidence),
    INDEX idx_is_whitelisted (is_whitelisted),
    INDEX idx_country_code (country_code),
    INDEX idx_spam_score (spam_score),
    INDEX idx_last_checked_at (last_checked_at),
    INDEX idx_expires_at (expires_at),
    INDEX idx_abuse_confidence_total_reports (abuse_confidence, total_reports),
    INDEX idx_expires_at_last_checked_at (expires_at, last_checked_at),
    INDEX idx_country_code_abuse_confidence (country_code, abuse_confidence)
);
```

#### Data Models
- **BlockedSubmission**: Comprehensive tracking model with relationships to User and scoped queries
- **IpReputation**: IP reputation caching model with expiration logic and risk assessment methods
- **SpamPattern**: Configurable spam detection patterns with accuracy tracking
- **GeoLite2Location**: MaxMind location data with hierarchical relationships
- **GeoLite2IPv4Block**: IP block ranges with geolocation mapping

### Integration Requirements
- **Internal Integrations**: Seamless integration with Laravel's Eloquent ORM and migration system
- **External Integrations**: Support for AbuseIPDB API data caching and GeoLite2 database imports
- **Event System**: Model events for cache invalidation and data synchronization
- **Queue/Job Requirements**: Background jobs for data cleanup, IP reputation refresh, and GeoLite2 imports

## Performance Requirements
- [ ] **Response Time**: Database queries must complete within 100ms for 95% of requests
- [ ] **Throughput**: Support 1000+ concurrent blocked submission inserts per minute
- [ ] **Resource Usage**: GeoLite2 import process must use less than 512MB memory regardless of dataset size
- [ ] **Caching Strategy**: IP reputation data cached for 30 days with automatic refresh on expiration
- [ ] **Database Performance**: All analytics queries must use proper indexes and complete within 500ms

## Security Considerations
- [ ] **Data Protection**: All PII data sanitized before storage with configurable retention policies
- [ ] **Input Validation**: All model attributes validated at database and application level
- [ ] **Audit Logging**: Comprehensive logging of all data modifications and access patterns
- [ ] **Access Control**: Database access restricted through Laravel's built-in security mechanisms

## Dependencies & Prerequisites
### Internal Dependencies
- [ ] Laravel framework 12.x with enhanced Eloquent ORM
- [ ] Database system (MySQL 8.0+ or PostgreSQL 12+) with JSON support
- [ ] Laravel 12 migration system for schema management

### External Dependencies
- [ ] MaxMind GeoLite2 database files for geolocation data
- [ ] AbuseIPDB API access for IP reputation data
- [ ] Sufficient database storage for retention policies (estimated 1GB per 100K blocked submissions)

## Success Criteria & Acceptance
- [ ] All database tables created with proper schema and indexes
- [ ] Eloquent models implemented with full functionality and relationships
- [ ] GeoLite2 import process successfully handles 10M+ records without memory issues
- [ ] Database performance meets specified response time requirements
- [ ] Data retention and cleanup policies function correctly
- [ ] All foreign key constraints and data integrity rules enforced

### Definition of Done
- [ ] All migrations created and tested with rollback capability
- [ ] All Eloquent models implemented with comprehensive test coverage
- [ ] Database indexes optimized for expected query patterns
- [ ] GeoLite2 chunked import system fully functional
- [ ] Data retention policies implemented and scheduled
- [ ] Performance benchmarks met under expected load
- [ ] Security requirements validated and documented
- [ ] Code reviewed and approved by senior developers

## Related Documentation
- [ ] [Epic EPIC-001] - JTD-FormSecurity Foundation Infrastructure
- [ ] [Database Design Document] - Detailed schema specifications and relationships
- [ ] [Performance Testing Results] - Benchmark data and optimization recommendations
- [ ] [Security Audit Report] - Data protection and compliance validation

## Notes
This specification forms the foundation for all other JTD-FormSecurity features. The database schema must be implemented first as other components depend on these data structures. Special attention should be paid to the GeoLite2 import performance as these datasets can be extremely large (10M+ records).

## Spec Completion Checklist
- [x] All requirements clearly defined and measurable
- [x] Technical architecture fully specified
- [x] Database schema designed and documented
- [x] Performance requirements specified with metrics
- [x] Security requirements identified and planned
- [x] Dependencies mapped and validated
- [x] Success criteria and acceptance criteria defined
- [ ] Stakeholder review completed and approved
