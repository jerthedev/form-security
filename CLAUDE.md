# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**JTD-FormSecurity** is a comprehensive Laravel 12 package for form security and spam prevention with enterprise-grade features. This is a public open-source package published to Packagist as `jerthedev/form-security`.

**Package Identity:**
- **Namespace**: JTD\FormSecurity
- **GitHub Repository**: jerthedev/form-security
- **Packagist Package**: jerthedev/form-security
- **License**: MIT
- **Target**: Enterprise-grade form security with modular architecture, high performance, and seamless Laravel 12 integration

**Key Technologies:**
- Laravel 12.x framework
- PHP 8.2+ with modern features (readonly properties, enums, union types)
- PHPUnit 12.x for testing with PHP 8 attributes
- PSR-12 coding standards enforced by Laravel Pint
- PHPStan Level 8 + Larastan for maximum type safety

## Development Commands

### Testing Commands
```bash
# Run all tests
composer test
# or: vendor/bin/phpunit

# Run tests with coverage (90%+ required)
composer test:coverage
# or: vendor/bin/phpunit --coverage-html coverage

# Run tests by Epic/Sprint grouping
composer test:epic-001          # Epic 1 - Foundation Infrastructure
composer test:epic-002          # Epic 2 - Spam Detection Algorithms
composer test:foundation        # Foundation infrastructure tests
composer test:integration       # Integration tests
composer test:performance       # Performance benchmarks (no coverage)
composer test:current-sprint    # Current sprint tests (sprint-002)

# Run specific test groups
vendor/bin/phpunit --group spam-detection
vendor/bin/phpunit --group database
vendor/bin/phpunit --group cache
vendor/bin/phpunit --group cli
vendor/bin/phpunit --group ticket-1015     # Specific ticket tests

# Advanced test filtering
vendor/bin/phpunit --group epic-001,database       # Multiple groups (AND)
vendor/bin/phpunit --exclude-group performance     # Exclude groups
```

### Code Quality Commands
```bash
# Format code with Laravel Pint (PSR-12) - REQUIRED before commits
composer pint
# or: vendor/bin/pint

# Static analysis with PHPStan Level 8 + Larastan - ZERO ERRORS REQUIRED
composer phpstan  
# or: vendor/bin/phpstan analyse

# Run all quality gates (required before merge)
composer quality
# Runs: pint + phpstan + test (all must pass)

# Pre-commit quality check workflow
composer run pint                    # Format code
composer run phpstan                 # Static analysis  
composer run test                    # Full test suite
composer run test:coverage          # Generate coverage report
```

## Architecture Overview

### Core Architecture Principles
1. **Laravel 12 Native Integration**: Leverage enhanced service container, console commands, caching, and database features
2. **Modular Design with Graceful Degradation**: Each feature independently toggleable, core functionality works even if optional features disabled
3. **Performance-First Architecture**: Target sub-100ms query responses for 10,000+ daily submissions, 90%+ cache hit ratio
4. **Security by Design**: OWASP guidelines, multi-layer input validation, Laravel 12 security features
5. **Developer Experience Excellence**: Comprehensive CLI tools, detailed documentation, helpful error messages

### Service Provider Pattern
The package uses Laravel 12's enhanced service provider with:
- **Conditional Service Registration**: Services registered based on feature flags in configuration
- **Multi-tier Caching**: Request → Memory → Database caching with intelligent invalidation
- **Deferred Loading**: Performance optimization through lazy loading of services
- **Feature Flags**: A/B testing and gradual rollouts support

### File Organization Structure
```
src/
├── FormSecurityServiceProvider.php     # Main service provider
├── Contracts/                          # Service contracts and interfaces
├── Services/                          # Business logic services
│   ├── ConfigurationService           # Configuration management
│   ├── SpamDetectionService          # Core spam detection algorithms
│   ├── FormSecurityService           # Main security orchestration
│   ├── CacheService                  # Multi-level caching system
│   ├── FeatureToggleService          # Feature flag management
│   ├── AiAnalysisService             # AI-powered analysis (optional)
│   └── GeolocationService            # Geolocation features (optional)
├── Models/                            # Eloquent models
├── Console/Commands/                  # Artisan commands
├── Middleware/                        # HTTP middleware
├── Rules/                             # Validation rules
├── Events/                            # Event classes
├── Listeners/                         # Event listeners
├── Observers/                         # Model observers
├── Exceptions/                        # Custom exceptions
├── Traits/                            # Reusable traits
├── ValueObjects/                      # Value objects
├── Enums/                             # PHP 8.2+ enums
├── Casts/                             # Eloquent attribute casting
└── Facades/                           # Laravel facades
```

### Configuration System
Three-tier configuration with hierarchical feature dependencies:
- `config/form-security.php` - Main configuration with feature flags
- `config/form-security-cache.php` - Caching configuration  
- `config/form-security-patterns.php` - Spam pattern definitions

**Key Configuration Features:**
- Feature flags for modular functionality
- Environment-based configuration overrides
- Conditional service registration based on enabled features
- Performance tuning parameters (cache TTL, rate limits, etc.)

### Database Architecture
Uses Laravel migrations with performance-optimized indexing for high-volume applications:
- `blocked_submissions` - Spam/blocked form submissions
- `ip_reputation` - IP address reputation tracking
- `geolocation_cache` - Cached geolocation data

**Database Optimization:**
- Proper indexing strategies for high-volume queries
- Query optimization and monitoring
- Database connection pooling for high-load scenarios
- Proper pagination for large datasets

## Testing Standards

### PHPUnit 12 Configuration
- **Test Framework**: PHPUnit 12.x with modern features
- **Test Database**: SQLite in-memory for fast execution
- **Coverage Target**: Minimum 90% code coverage (enforced in CI)
- **Performance Testing**: Dedicated performance benchmarks

### Test Structure and Organization
```
tests/
├── Unit/                             # Isolated unit tests
│   ├── Models/                       # Model tests
│   ├── Services/                     # Service class tests
│   └── Rules/                        # Validation rule tests
├── Feature/                          # Integration tests
│   ├── Console/                      # Command tests
│   ├── Http/                         # HTTP endpoint tests
│   └── Database/                     # Database integration tests
├── Performance/                      # Performance benchmarks
│   ├── DatabasePerformanceTest.php
│   └── CachePerformanceTest.php
└── TestCase.php                      # Base test case with common setup
```

### Test Organization with PHPUnit 12 Attributes
Tests follow Epic-based organization using PHP 8 attributes (not annotations):
```php
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]                    # Epic-level grouping
#[Group('foundation-infrastructure')]   # Feature-level grouping
#[Group('sprint-001')]                  # Sprint-level grouping  
#[Group('ticket-1015')]                 # Ticket-level grouping
#[Group('spam-detection')]              # Component-level grouping
class SpamDetectionServiceTest extends TestCase
{
    #[Test]
    public function spam_detection_blocks_high_score_submissions(): void
    {
        // AAA Pattern: Arrange, Act, Assert
    }
}
```

### **CRITICAL** Test File Headers
Every test file MUST include traceability header:
```php
/**
 * Test File: SpamDetectionServiceTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-002-spam-detection-algorithms
 * SPRINT: Sprint-001-Core-Infrastructure
 * TICKET: 1015-implement-spam-detection-service
 *
 * Description: Tests for the core spam detection service functionality
 * including pattern matching, scoring algorithms, and threshold validation.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Core-Detection/SPEC-002-spam-detection-algorithms.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1015-implement-spam-detection-service.md
 */
```

### Test Grouping Strategy
**Epic-Level Groups:**
- `#[Group('epic-001')]` - Foundation Infrastructure
- `#[Group('epic-002')]` - Spam Detection Algorithms
- `#[Group('epic-003')]` - Form Validation System

**Feature-Level Groups:**
- `#[Group('foundation-infrastructure')]`
- `#[Group('spam-detection')]`
- `#[Group('form-validation')]`
- `#[Group('user-registration')]`

**Sprint-Level Groups:**
- `#[Group('sprint-001')]` - Core Infrastructure Sprint
- `#[Group('sprint-002')]` - Detection Algorithms Sprint

**Component-Level Groups:**
- `#[Group('database')]` - Database-related tests
- `#[Group('cache')]` - Caching functionality tests
- `#[Group('cli')]` - Console command tests
- `#[Group('integration')]` - Integration tests
- `#[Group('performance')]` - Performance benchmark tests

### PHPUnit 12 Best Practices
- **Attributes over Annotations**: Use PHP 8 attributes instead of PHPDoc annotations
- **Data Providers**: Use data providers for parameterized tests
- **Test Doubles**: Leverage PHPUnit 12's improved mock and stub separation
- **Assertions**: Use specific assertions (`assertSame` vs `assertEquals`)
- **AAA Pattern**: Arrange, Act, Assert structure for all tests
- **Database Transactions**: Use database transactions for test isolation
- **Factory Usage**: Use model factories for test data generation
- **Mock External Services**: Mock all external API calls and services

### Performance Requirements
- Target: Sub-100ms query response times for 10,000+ daily submissions
- Cache hit ratio target: 90%+
- Memory usage: Keep under 50MB for typical operations
- Test coverage: Minimum 90% required (enforced in CI)
- Performance benchmarks must not regress

## CLI Commands Suite

The package includes comprehensive CLI commands using Laravel 12's enhanced console command features:
```bash
# Installation and setup
php artisan form-security:install              # Complete package installation
php artisan form-security:config:publish       # Publish configuration files
php artisan form-security:config:validate      # Validate configuration

# Cache management (multi-level caching system)
php artisan form-security:cache:clear          # Clear all caches
php artisan form-security:cache:warm           # Warm critical caches
php artisan form-security:cache               # Cache management commands
php artisan form-security:optimize            # Performance optimization

# Maintenance and monitoring
php artisan form-security:cleanup             # Clean old data/logs
php artisan form-security:health-check        # System health check
php artisan form-security:report              # Generate security reports

# Feature management
php artisan form-security:feature-toggle      # Toggle feature flags

# Import external data
php artisan form-security:import-geolite2     # Import GeoLite2 database
```

**CLI Command Features:**
- Progress bars for long-running operations
- Proper argument and option definitions
- Comprehensive error handling and user feedback
- Supports Laravel 12's improved command testing utilities

## Development Workflow & Standards

### Code Standards (Strictly Enforced)
- **PSR-12**: Strict adherence to PSR-12 coding standards enforced by Laravel Pint
- **Laravel Pint**: Automatic code formatting (run before every commit)
- **PHPStan Level 8**: Maximum static analysis level for type safety
- **Larastan**: Laravel-specific PHPStan rules and extensions
- **PHP 8.2+ Features**: Leverage modern PHP features (readonly properties, enums, union types)

### Naming Conventions
- **Classes**: PascalCase (e.g., `SpamDetectionService`)
- **Methods**: camelCase (e.g., `analyzeFormSubmission`)
- **Variables**: camelCase (e.g., `$spamScore`)
- **Constants**: SCREAMING_SNAKE_CASE (e.g., `MAX_SPAM_SCORE`)
- **Database Tables**: snake_case (e.g., `blocked_submissions`)
- **Configuration Keys**: snake_case (e.g., `enable_ai_analysis`)

### Documentation Standards
- **PHPDoc**: Comprehensive PHPDoc blocks for all public methods
- **Type Hints**: Use PHP 8.2+ type hints for all parameters and return types
- **README**: Maintain comprehensive README with installation and usage examples
- **CHANGELOG**: Follow Keep a Changelog format
- **API Documentation**: Generate API docs using phpDocumentor

### Quality Gates (All Must Pass Before Merge)
1. **Laravel Pint formatting** (zero violations)
2. **PHPStan Level 8 + Larastan analysis** (zero errors)  
3. **PHPUnit test suite** (90%+ coverage required)
4. **Epic-specific test validation**
5. **Security vulnerability scanning**
6. **Dependency vulnerability checks**

### Git Workflow (GitHub: jerthedev/form-security)
- **Branch Protection**: Main branch protected, requires PR reviews and passing CI
- **Feature Branches**: All development in feature branches (e.g., `feature/spam-detection-service`)
- **Conventional Commits**: Follow conventional commit format for automated changelog
- **Pull Request Requirements**:
  - All CI checks must pass
  - Code review required from maintainer
  - No merge until all quality gates pass
- **Commit Quality**: Squash commits before merging to maintain clean history

### Continuous Integration (GitHub Actions)
**Required CI Checks (All Must Pass):**
- PHPUnit 12 test suite (90%+ coverage required)
  - Full test suite execution  
  - Epic-specific test validation
  - Integration test verification
  - Performance benchmark validation
- Laravel Pint code formatting (zero violations)
- PHPStan Level 8 static analysis (zero errors)
- Larastan Laravel-specific analysis (zero errors)
- Security vulnerability scanning
- Dependency vulnerability checks
- Package installation testing on fresh Laravel 12 projects

**Testing Matrix:**
- **PHP Versions**: 8.2, 8.3, 8.4 (when available)
- **Laravel Versions**: 12.x (latest and LTS versions)
- **Database Matrix**: MySQL 8.0+, PostgreSQL 12+, SQLite 3.8+

### Feature Development Principles
- **All features toggleable** via configuration flags
- **Graceful degradation** when features disabled
- **Performance-first** with multi-level caching strategies
- **Security by design** following OWASP guidelines
- **Modular architecture** with conditional service registration

### Laravel 12 Specific Guidelines
**Service Provider Best Practices:**
- Use Laravel 12's enhanced service provider features
- Implement conditional service registration based on configuration
- Leverage automatic package discovery
- Use deferred providers for performance optimization

**Database and Eloquent:**
- Use Laravel 12's enhanced migration features
- Implement proper database indexing strategies
- Use Eloquent relationships efficiently
- Leverage Laravel 12's query builder improvements

**Caching Strategy:**
- Use Laravel 12's improved cache tagging and invalidation
- Implement cache warming strategies  
- Use Redis for production (recommended)
- Implement cache fallback mechanisms
- Monitor cache hit ratios (target: 90%+)

### Security Guidelines
**Input Validation:**
- Validate all input at multiple layers (request, service, model)
- Use Laravel's validation rules with custom rules for spam detection
- Implement rate limiting for form submissions
- Sanitize output to prevent XSS attacks

**Data Protection:**
- Encrypt sensitive data using Laravel's encryption
- Implement proper data retention policies
- Use secure random number generation
- Follow GDPR compliance guidelines for data handling

**Authentication and Authorization:**
- Implement proper authentication for admin features
- Use Laravel's authorization policies
- Implement role-based access control where needed
- Secure API endpoints with proper authentication

### Performance Guidelines
**Database Optimization:**
- Use proper indexing strategies for high-volume queries
- Implement query optimization and monitoring
- Use database connection pooling for high-load scenarios
- Implement proper pagination for large datasets

**Memory Management:**
- Keep memory usage under 50MB for typical operations
- Use generators for processing large datasets
- Implement proper resource cleanup
- Monitor memory usage in production

### Release Process (Public Package)
- **Semantic Versioning**: Follow SemVer strictly for public package compatibility
- **Packagist Publishing**: Automatic publishing via GitHub releases
- **Changelog**: Maintain comprehensive CHANGELOG.md
- **Breaking Changes**: Clear documentation and migration guides for major versions
- **Installation Testing**: Verify `composer require jerthedev/form-security` works on fresh Laravel 12 projects

## Important Patterns & Architecture

### Modular Feature Design with Graceful Degradation
Features are independently toggleable and gracefully degrade:
```php
// Feature flags control service registration
if (config('form-security.features.spam_detection', false)) {
    // Spam detection logic
}

// Optional AI analysis service registration
if (config('form-security.features.ai_analysis', false)) {
    $this->app->singleton('form-security.ai-analyzer', function (Application $app) {
        return new Services\AiAnalysisService($app->make(ConfigurationContract::class));
    });
}
```

### Multi-level Caching with Intelligent Invalidation
Three-tier caching system (target: 90%+ hit ratio):
1. **Request-level** (per-request cache)
2. **Memory-level** (application cache)  
3. **Database-level** (persistent cache)

**Cache Management:**
- Intelligent cache invalidation on configuration changes
- Cache warming strategies for critical data
- Cache fallback mechanisms for resilience
- Laravel 12's improved cache tagging and invalidation

### Event-Driven Architecture
Configuration changes trigger cache invalidation:
```php
// Event-listener pattern for cache management
Events\ConfigurationChanged::class → Listeners\InvalidateConfigurationCache::class
```

### Service Container Patterns
```php
// Conditional service registration based on feature flags
$this->app->when(FormSecurityContract::class)
    ->needs('$config')
    ->give(function (Application $app) {
        return $app->make(ConfigurationContract::class);
    });
```

### Performance Optimization Patterns
- **Lazy Loading**: Deferred service providers for performance optimization
- **Database Connection Pooling**: For high-load scenarios
- **Query Optimization**: Proper indexing and query monitoring
- **Memory Management**: Generators for large datasets, proper resource cleanup

## Development Environment Setup

### Required Configuration Files
- `pint.json` - Laravel Pint configuration for PSR-12 compliance
- `phpstan.neon` - PHPStan Level 8 configuration
- `phpunit.xml` - PHPUnit 12 configuration with coverage settings and group definitions
- `.github/workflows/ci.yml` - GitHub Actions CI pipeline

### Required Development Dependencies
```json
{
  "require-dev": {
    "laravel/pint": "^1.0",
    "phpstan/phpstan": "^2.0", 
    "larastan/larastan": "^3.0",
    "phpunit/phpunit": "^12.0",
    "orchestra/testbench": "^10.0",
    "mockery/mockery": "^1.6"
  }
}
```

### Environment Setup Options
- **Docker**: Consistent development environment configuration
- **Laravel Sail**: Easy setup for Laravel development
- Document all required PHP extensions and system dependencies
- Setup scripts for quick environment initialization

## Test Traceability Requirements

### Every Test Must Include:
- **Epic Reference**: Which Epic this test validates
- **Spec Reference**: Which Specification this test implements  
- **Sprint Reference**: Which Sprint this was developed in
- **Ticket Reference**: Which specific ticket this addresses
- **Planning Document Links**: Links to relevant planning documents

### Test Failure Context
- Test failures should reference originating planning documents
- Coverage reports grouped by Epic and Sprint for progress tracking
- Failed tests include context about which Epic/Sprint/Ticket is affected

## Package Publication Details

This is a **public open-source package** on Packagist:
- **Namespace**: `JTD\FormSecurity`
- **Package**: `jerthedev/form-security` 
- **Repository**: https://github.com/jerthedev/form-security
- **License**: MIT
- **Semantic Versioning**: Strict SemVer compliance for public compatibility
- **Community Standards**: Follow Laravel community package standards
- **Installation**: `composer require jerthedev/form-security`

### Community Considerations
- Comprehensive README with installation and usage examples
- Backward compatibility maintained within major versions
- GitHub issue templates for bug reports and features
- Clear CONTRIBUTING.md for community contributions
- CODE_OF_CONDUCT.md for community guidelines
- Automatic Packagist publishing via GitHub releases

## Project Planning Documentation Structure

The project uses a comprehensive 4-tier planning structure in `docs/Planning/` that provides complete traceability from high-level epics down to specific implementation tickets.

### Planning Hierarchy

```
docs/Planning/
├── Epics/                              # Strategic planning level
│   ├── EPIC-001-foundation-infrastructure.md
│   ├── EPIC-002-core-spam-detection-engine.md
│   ├── EPIC-003-form-protection-validation-system.md
│   ├── EPIC-004-user-registration-security-enhancement.md
│   ├── EPIC-005-external-services-integration.md
│   ├── EPIC-006-analytics-monitoring.md
│   ├── EPIC-007-quality-assurance-testing-framework.md
│   └── EPIC-008-documentation-deployment.md
├── Specs/                              # Technical specification level
│   ├── Core-Detection-Engine/
│   ├── Data-Management-Analytics/
│   ├── Form-Validation-Protection/
│   ├── Infrastructure-System/
│   ├── Integration-External-Services/
│   ├── Specialized-Features/
│   └── User-Registration-Enhancement/
├── Sprints/                            # Sprint planning level
│   ├── Done/                          # Completed sprints
│   ├── 004-caching-cli-integration.md
│   └── 005-code-cleanup-optimization.md
└── Tickets/                            # Implementation level
    ├── Foundation-Infrastructure/
    │   ├── Research-Audit/
    │   ├── Implementation/
    │   ├── Test-Implementation/
    │   └── Code-Cleanup/
    ├── Core-Spam-Detection-Engine/
    ├── Form-Protection-Validation-System/
    ├── User-Registration-Security-Enhancement/
    ├── External-Services-Integration/
    ├── Analytics-Monitoring/
    ├── Quality-Assurance-Testing-Framework/
    └── Documentation-Deployment/
```

### Planning Document Levels

**1. Epics (Strategic Level)**
- **Purpose**: High-level business capabilities and strategic objectives
- **Example**: `EPIC-001-foundation-infrastructure.md`
- **Content**: Epic overview, goals, scope, success criteria, dependencies
- **Timeframe**: Multiple sprints/months
- **Usage in Tests**: `#[Group('epic-001')]`

**2. Specs (Technical Specification Level)**  
- **Purpose**: Detailed technical specifications for major features
- **Example**: `SPEC-004-pattern-based-spam-detection.md`
- **Content**: Technical requirements, architecture, implementation details
- **Organized by**: Feature domain (Core-Detection-Engine, Infrastructure-System, etc.)
- **Usage in Tests**: Referenced in test file headers with `@see` links

**3. Sprints (Sprint Planning Level)**
- **Purpose**: Time-boxed development cycles with specific deliverables
- **Example**: `004-caching-cli-integration.md`
- **Content**: Sprint goals, tickets included, timeline, deliverables
- **Status Tracking**: Active sprints in main directory, completed in `Done/`
- **Usage in Tests**: `#[Group('sprint-004')]`

**4. Tickets (Implementation Level)**
- **Purpose**: Granular implementation tasks and specific deliverables
- **Organization**: Grouped by Epic area, subdivided by task type
- **Task Types**:
  - `Research-Audit/` - Investigation and analysis tasks
  - `Implementation/` - Core development tasks  
  - `Test-Implementation/` - Test development tasks
  - `Code-Cleanup/` - Refactoring and optimization tasks
- **Usage in Tests**: `#[Group('ticket-1015')]`

### Documentation Integration

**Test Traceability Integration:**
Every test file header must reference the relevant planning documents:
```php
/**
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-004-pattern-based-spam-detection  
 * SPRINT: Sprint-004-Core-Infrastructure
 * TICKET: 1015-implement-spam-detection-service
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Core-Detection-Engine/SPEC-004-pattern-based-spam-detection.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1015-implement-spam-detection-service.md
 */
```

**Planning Documentation Features:**
- **Templates**: Standardized templates for each planning level ensure consistency
- **Cross-References**: Documents link to related epics, specs, and tickets
- **Status Tracking**: Progress tracking at each level (Not Started, In Progress, Completed)
- **Dependencies**: Clear dependency mapping between planning documents
- **Lessons Learned**: `Lessons-Learned/` and `Retrospectives/` capture insights

**Development Workflow Integration:**
- **Epic Planning**: Strategic planning and feature scoping
- **Spec Development**: Technical architecture and detailed requirements
- **Sprint Execution**: Time-boxed development with clear deliverables  
- **Ticket Implementation**: Granular task completion with test validation

This comprehensive planning structure ensures complete traceability from business objectives down to individual test cases, supporting the project's enterprise-grade development standards.