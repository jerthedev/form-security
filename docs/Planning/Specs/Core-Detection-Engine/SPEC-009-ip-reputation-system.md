# IP Reputation System Specification

**Spec ID**: SPEC-009-ip-reputation-system  
**Date Created**: 2025-01-27  
**Last Updated**: 2025-01-27  
**Status**: Draft  
**Priority**: High  
**Related Epic**: EPIC-002 - JTD-FormSecurity Core Features

## Title
IP Reputation System - AbuseIPDB integration for IP-based risk assessment with local caching

## Feature Overview
This specification defines a comprehensive IP reputation system that integrates with external services like AbuseIPDB to provide real-time IP risk assessment for spam detection. The system includes local caching, intelligent refresh strategies, rate limiting, and comprehensive risk scoring algorithms. It serves as a critical component of the spam detection engine, providing IP-based risk assessment that significantly enhances the accuracy of spam detection.

The system balances real-time accuracy with performance and cost considerations through intelligent caching strategies, API rate limiting, and efficient data management. It provides both synchronous and asynchronous IP reputation checking capabilities with graceful degradation when external services are unavailable.

Key capabilities include:
- AbuseIPDB API integration with comprehensive data extraction
- Local IP reputation caching with intelligent TTL management
- Risk scoring algorithms with configurable thresholds
- API rate limiting and cost management
- Bulk IP analysis capabilities for batch processing
- Geolocation integration for enhanced risk assessment
- Whitelist management for trusted IP addresses

## Purpose & Rationale
### Business Justification
- **Enhanced Accuracy**: IP reputation data significantly improves spam detection accuracy
- **Cost Optimization**: Local caching reduces external API costs while maintaining effectiveness
- **Real-time Protection**: Provides immediate risk assessment for incoming form submissions
- **Attack Pattern Recognition**: Historical IP data enables identification of coordinated attacks

### Technical Justification
- **Performance**: Local caching provides sub-millisecond IP reputation lookups
- **Reliability**: Graceful degradation ensures system functionality when external services fail
- **Scalability**: Efficient caching and rate limiting support high-volume operations
- **Data Quality**: Comprehensive data validation ensures accurate risk assessments

## Detailed Requirements

### Functional Requirements
- [ ] **FR-001**: Implement AbuseIPDB API integration with comprehensive data extraction
- [ ] **FR-002**: Create local IP reputation caching system with intelligent TTL management
- [ ] **FR-003**: Develop risk scoring algorithms with configurable thresholds and weights
- [ ] **FR-004**: Implement API rate limiting and cost management controls
- [ ] **FR-005**: Provide bulk IP analysis capabilities for batch processing
- [ ] **FR-006**: Create IP whitelist management system for trusted addresses
- [ ] **FR-007**: Implement geolocation integration for enhanced risk assessment
- [ ] **FR-008**: Provide comprehensive logging and monitoring for IP reputation activities

### Non-Functional Requirements
- [ ] **NFR-001**: Cached IP reputation lookups must complete within 5ms for 95% of requests
- [ ] **NFR-002**: API calls to external services must complete within 2 seconds with timeout handling
- [ ] **NFR-003**: Support concurrent IP reputation checks up to 500 requests per minute
- [ ] **NFR-004**: Cache hit ratio must be maintained above 80% for frequently checked IPs
- [ ] **NFR-005**: System must gracefully handle API rate limits and service outages

### Business Rules
- [ ] **BR-001**: IP reputation data must be cached for minimum 1 hour, maximum 24 hours
- [ ] **BR-002**: High-risk IPs (abuse confidence >75%) must be rechecked more frequently
- [ ] **BR-003**: Whitelisted IPs must bypass all reputation checks and scoring
- [ ] **BR-004**: API rate limits must be respected with intelligent request queuing
- [ ] **BR-005**: Expired reputation data must trigger background refresh when possible

## Technical Architecture

### System Components
- **IpReputationService**: Core service for IP reputation management and analysis
- **AbuseIPDBClient**: Specialized client for AbuseIPDB API integration
- **ReputationCache**: Multi-level caching system for IP reputation data
- **RiskScoreCalculator**: Configurable risk scoring algorithms
- **RateLimitManager**: API rate limiting and request queuing system
- **WhitelistManager**: Trusted IP address management system

### Data Architecture
#### IP Reputation Data Structure
```php
// IP reputation record structure
[
    'ip_address' => '192.168.1.1',
    'abuse_confidence' => 85,           // 0-100 AbuseIPDB confidence score
    'total_reports' => 15,              // Number of abuse reports
    'is_whitelisted' => false,          // Manual whitelist status
    'usage_type' => 'Data Center',      // ISP, Data Center, etc.
    'country_code' => 'US',             // ISO country code
    'country_name' => 'United States',  // Full country name
    'spam_score' => 42,                 // Calculated spam score (0-50)
    'spam_indicators' => [              // Risk factors identified
        'High abuse confidence (85%)',
        'Multiple recent reports',
        'Data center IP address'
    ],
    'raw_abuseipdb_data' => [...],      // Complete API response
    'raw_geolocation_data' => [...],    // Geolocation API response
    'last_checked_at' => '2025-01-27 10:00:00',
    'expires_at' => '2025-01-27 11:00:00',
    'check_count' => 3,                 // Number of times checked
]
```

#### Risk Scoring Algorithm
```php
// IP reputation risk scoring
$riskScore = 0;

// AbuseIPDB confidence scoring (0-30 points)
if ($abuseConfidence >= 90) $riskScore += 30;
elseif ($abuseConfidence >= 75) $riskScore += 25;
elseif ($abuseConfidence >= 50) $riskScore += 15;
elseif ($abuseConfidence >= 25) $riskScore += 8;

// Report count scoring (0-10 points)
if ($totalReports >= 50) $riskScore += 10;
elseif ($totalReports >= 20) $riskScore += 7;
elseif ($totalReports >= 5) $riskScore += 4;

// Usage type scoring (0-10 points)
$usageTypeScores = [
    'Data Center' => 8,
    'Hosting' => 6,
    'VPN' => 5,
    'Proxy' => 7,
    'ISP' => 0,
    'Mobile' => 1,
];
$riskScore += $usageTypeScores[$usageType] ?? 0;

return min(50, $riskScore); // Cap at 50 points
```

### API Specifications

#### Core IP Reputation Interface
```php
interface IpReputationServiceInterface
{
    // Primary reputation checking
    public function checkIpReputation(string $ip): array;
    public function getIpRiskScore(string $ip): int;
    public function isHighRiskIp(string $ip): bool;
    public function isSuspiciousIp(string $ip): bool;
    
    // Bulk operations
    public function checkMultipleIps(array $ips): array;
    public function refreshExpiredReputations(): int;
    public function preloadIpReputations(array $ips): void;
    
    // Whitelist management
    public function addToWhitelist(string $ip, string $reason = ''): bool;
    public function removeFromWhitelist(string $ip): bool;
    public function isWhitelisted(string $ip): bool;
    
    // Cache management
    public function clearIpCache(string $ip): bool;
    public function getCacheStats(): array;
    public function warmCache(array $ips): void;
}

// Usage examples
$reputationService = app(IpReputationServiceInterface::class);

// Check single IP
$reputation = $reputationService->checkIpReputation('192.168.1.1');
$riskScore = $reputationService->getIpRiskScore('192.168.1.1');

// Bulk checking
$ips = ['192.168.1.1', '10.0.0.1', '172.16.0.1'];
$reputations = $reputationService->checkMultipleIps($ips);

// Whitelist management
$reputationService->addToWhitelist('192.168.1.100', 'Office IP');
```

#### AbuseIPDB Client Interface
```php
interface AbuseIPDBClientInterface
{
    public function checkIp(string $ip): array;
    public function checkIpBulk(array $ips): array;
    public function reportIp(string $ip, array $categories, string $comment): bool;
    public function getApiUsage(): array;
    public function isRateLimited(): bool;
}
```

### Integration Requirements
- **Internal Integrations**: Integration with spam detection service and caching system
- **External Integrations**: AbuseIPDB API, geolocation services, and monitoring systems
- **Event System**: IP reputation events (HighRiskIpDetected, IpWhitelisted, ApiLimitReached)
- **Queue/Job Requirements**: Background IP reputation refresh and bulk analysis jobs

## Performance Requirements
- [ ] **Cache Performance**: Cached lookups complete within 5ms for 95% of requests
- [ ] **API Performance**: External API calls complete within 2 seconds with proper timeout handling
- [ ] **Throughput**: Support 500+ concurrent IP reputation checks per minute
- [ ] **Cache Efficiency**: Maintain 80%+ cache hit ratio for frequently checked IPs
- [ ] **Bulk Processing**: Process bulk IP analysis efficiently without blocking other operations

## Security Considerations
- [ ] **API Security**: AbuseIPDB API keys securely stored and transmitted
- [ ] **Data Protection**: IP reputation data handled in compliance with privacy regulations
- [ ] **Access Control**: IP reputation management restricted to authorized users
- [ ] **Audit Logging**: All IP reputation activities logged for security monitoring
- [ ] **Rate Limiting**: Proper rate limiting prevents abuse of external APIs

## Testing Requirements

### Unit Testing
- [ ] IP reputation service functionality with various IP types and scenarios
- [ ] Risk scoring algorithms with different abuse confidence levels
- [ ] Cache management and TTL handling
- [ ] Whitelist management functionality

### Integration Testing
- [ ] AbuseIPDB API integration with real API responses
- [ ] Cache integration with Redis/Memcached
- [ ] Database integration for IP reputation storage
- [ ] Rate limiting and API quota management

### Performance Testing
- [ ] High-volume concurrent IP reputation checking
- [ ] Cache performance and hit ratio optimization
- [ ] API timeout and error handling
- [ ] Bulk IP analysis performance

## Implementation Guidelines

### Development Standards
- [ ] Follow Laravel service container patterns for dependency injection
- [ ] Implement comprehensive error handling and graceful degradation
- [ ] Use proper HTTP client configuration with timeouts and retries
- [ ] Maintain efficient caching strategies with appropriate TTL values

### API Management
- [ ] Implement proper API key rotation and management
- [ ] Monitor API usage and costs with alerting
- [ ] Handle API rate limits gracefully with request queuing
- [ ] Provide fallback mechanisms when external services are unavailable

## Dependencies & Prerequisites
### Internal Dependencies
- [ ] Database schema (SPEC-001) for IP reputation table structure
- [ ] Caching system (SPEC-003) for multi-level IP reputation caching
- [ ] Configuration management (SPEC-002) for API keys and settings

### External Dependencies
- [ ] AbuseIPDB API access with valid API key
- [ ] HTTP client library for API communication
- [ ] Cache system (Redis/Memcached) for performance optimization

## Success Criteria & Acceptance
- [ ] AbuseIPDB integration provides accurate IP reputation data
- [ ] Local caching system achieves target performance and hit ratios
- [ ] Risk scoring algorithms provide meaningful spam detection enhancement
- [ ] API rate limiting prevents service disruption and cost overruns
- [ ] System gracefully handles external service outages
- [ ] Performance requirements met under expected load

### Definition of Done
- [ ] Complete IP reputation service with AbuseIPDB integration
- [ ] Local caching system with intelligent TTL management
- [ ] Risk scoring algorithms with configurable thresholds
- [ ] API rate limiting and cost management controls
- [ ] Whitelist management system for trusted IPs
- [ ] Bulk IP analysis capabilities for batch processing
- [ ] Comprehensive test suite with >90% code coverage
- [ ] Performance optimization meeting all specified requirements
- [ ] Security review completed for API key management and data protection

## Related Documentation
- [ ] [Epic EPIC-002] - JTD-FormSecurity Core Features
- [ ] [SPEC-003] - Multi-Level Caching System integration
- [ ] [SPEC-004] - Pattern-Based Spam Detection System integration
- [ ] [AbuseIPDB API Documentation] - External service integration guide

## Notes
The IP Reputation System is a critical component that significantly enhances spam detection accuracy. The system must balance real-time accuracy with performance and cost considerations. Special attention should be paid to API rate limiting and caching strategies to ensure cost-effective operation while maintaining high detection accuracy.

## Spec Completion Checklist
- [x] All requirements clearly defined and measurable
- [x] Technical architecture fully specified
- [x] API specifications complete with examples
- [x] Performance requirements specified with metrics
- [x] Security requirements identified and planned
- [x] Dependencies mapped and validated
- [x] Success criteria and acceptance criteria defined
- [ ] Stakeholder review completed and approved
