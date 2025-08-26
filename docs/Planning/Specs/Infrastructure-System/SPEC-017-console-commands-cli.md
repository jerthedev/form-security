# Console Commands & CLI Tools Specification

**Spec ID**: SPEC-017-console-commands-cli-tools  
**Date Created**: 2025-01-27  
**Last Updated**: 2025-01-27  
**Status**: Draft  
**Priority**: Nice to Have  
**Related Epic**: EPIC-004 - JTD-FormSecurity Advanced Features

## Title
Console Commands & CLI Tools - Artisan commands for package management and maintenance

## Feature Overview
This specification defines a comprehensive suite of Laravel Artisan console commands and CLI tools for managing, maintaining, and operating the JTD-FormSecurity package. The command suite provides administrators and developers with powerful tools for installation, configuration, testing, monitoring, maintenance, and troubleshooting of the spam protection system.

The CLI tools are designed to support both interactive and automated operations, with comprehensive logging, progress reporting, and error handling. They include installation wizards, diagnostic tools, maintenance commands, and operational utilities that enable efficient management of the spam protection system.

Key capabilities include:
- Installation and setup automation with guided configuration
- Comprehensive testing and diagnostic tools
- Database management and maintenance commands
- Analytics and reporting generation tools
- System health monitoring and troubleshooting utilities
- Data import/export and migration tools
- Performance optimization and cache management
- Automated maintenance and cleanup operations

## Purpose & Rationale
### Business Justification
- **Operational Efficiency**: Automated tools reduce manual maintenance overhead
- **System Reliability**: Diagnostic tools enable proactive issue identification and resolution
- **Deployment Automation**: Installation commands streamline deployment processes
- **Maintenance Automation**: Scheduled maintenance commands ensure optimal system performance

### Technical Justification
- **Administrative Control**: CLI tools provide comprehensive system management capabilities
- **Troubleshooting Support**: Diagnostic commands enable rapid issue identification and resolution
- **Performance Optimization**: Maintenance commands ensure optimal system performance
- **Data Management**: Import/export tools support data migration and backup operations

## Detailed Requirements

### Functional Requirements
- [ ] **FR-001**: Implement installation and setup automation with guided configuration
- [ ] **FR-002**: Create comprehensive testing and diagnostic tools
- [ ] **FR-003**: Develop database management and maintenance commands
- [ ] **FR-004**: Implement analytics and reporting generation tools
- [ ] **FR-005**: Create system health monitoring and troubleshooting utilities
- [ ] **FR-006**: Provide data import/export and migration tools
- [ ] **FR-007**: Implement performance optimization and cache management commands
- [ ] **FR-008**: Create automated maintenance and cleanup operations

### Non-Functional Requirements
- [ ] **NFR-001**: Commands must provide clear progress indicators for long-running operations
- [ ] **NFR-002**: All commands must include comprehensive help documentation and examples
- [ ] **NFR-003**: Commands must handle errors gracefully with informative error messages
- [ ] **NFR-004**: Long-running commands must be interruptible and resumable where appropriate
- [ ] **NFR-005**: Commands must log all operations for audit and troubleshooting purposes

### Business Rules
- [ ] **BR-001**: Destructive operations must require explicit confirmation
- [ ] **BR-002**: All commands must respect data retention and privacy policies
- [ ] **BR-003**: Maintenance commands must be safe to run during production operations
- [ ] **BR-004**: Import/export operations must include data validation and integrity checks
- [ ] **BR-005**: Commands must provide appropriate exit codes for automation integration

## Technical Architecture

### System Components
- **InstallationCommands**: Automated installation and setup commands
- **DiagnosticCommands**: System testing and diagnostic utilities
- **MaintenanceCommands**: Database and system maintenance operations
- **AnalyticsCommands**: Report generation and analytics tools
- **DataManagementCommands**: Import/export and migration utilities
- **CacheCommands**: Cache management and optimization tools

### Data Architecture
#### Command Categories Structure
```php
// Installation & Setup Commands
'installation' => [
    'form-security:install' => 'Complete package installation and setup',
    'form-security:configure' => 'Interactive configuration wizard',
    'form-security:verify-installation' => 'Verify installation integrity',
],

// Testing & Diagnostics
'diagnostics' => [
    'form-security:test-detection' => 'Test spam detection functionality',
    'form-security:analyze-ip' => 'Analyze IP reputation and geolocation',
    'form-security:health-check' => 'Comprehensive system health check',
    'form-security:validate-config' => 'Validate configuration settings',
],

// Database Management
'database' => [
    'form-security:migrate-fresh' => 'Fresh migration with data seeding',
    'form-security:cleanup' => 'Clean up old data based on retention policies',
    'form-security:optimize-db' => 'Optimize database tables and indexes',
    'form-security:backup-data' => 'Backup spam protection data',
],

// Analytics & Reporting
'analytics' => [
    'form-security:report' => 'Generate comprehensive analytics reports',
    'form-security:export-data' => 'Export data for external analysis',
    'form-security:trend-analysis' => 'Analyze spam trends and patterns',
],

// Maintenance & Operations
'maintenance' => [
    'form-security:refresh-cache' => 'Refresh all caches',
    'form-security:update-patterns' => 'Update spam detection patterns',
    'form-security:refresh-ip-cache' => 'Refresh IP reputation cache',
    'form-security:warm-cache' => 'Pre-warm caches with common data',
],
```

### API Specifications

#### Core Command Interface
```php
abstract class FormSecurityCommand extends Command
{
    // Common functionality
    protected function validateConfiguration(): bool;
    protected function logOperation(string $operation, array $data = []): void;
    protected function confirmDestructiveOperation(string $message): bool;
    protected function showProgressBar(int $total): ProgressBar;
    protected function handleError(Exception $e, string $context): void;
}

// Installation Commands
class InstallCommand extends FormSecurityCommand
{
    protected $signature = 'form-security:install 
                           {--force : Force installation without confirmation}
                           {--config-only : Only publish configuration files}
                           {--migrate : Run migrations after installation}';
    
    protected $description = 'Install and setup JTD-FormSecurity package';
    
    public function handle(): int;
    protected function publishAssets(): void;
    protected function runMigrations(): void;
    protected function seedDefaultData(): void;
    protected function validateEnvironment(): bool;
}

// Diagnostic Commands
class TestDetectionCommand extends FormSecurityCommand
{
    protected $signature = 'form-security:test-detection 
                           {--type=all : Test type (all, patterns, ip, ai)}
                           {--samples=10 : Number of test samples}
                           {--verbose : Show detailed results}';
    
    protected $description = 'Test spam detection functionality';
    
    public function handle(): int;
    protected function testPatternDetection(): array;
    protected function testIpReputation(): array;
    protected function testAiAnalysis(): array;
    protected function generateTestData(): array;
}
```

#### Command Examples and Usage
```bash
# Installation and Setup
php artisan form-security:install --migrate
php artisan form-security:configure --interactive
php artisan form-security:verify-installation

# Testing and Diagnostics
php artisan form-security:test-detection --type=patterns --verbose
php artisan form-security:analyze-ip 192.168.1.1 --detailed
php artisan form-security:health-check --fix-issues
php artisan form-security:validate-config --environment=production

# Database Management
php artisan form-security:cleanup --days=90 --dry-run
php artisan form-security:optimize-db --analyze-only
php artisan form-security:backup-data --format=json --compress

# Analytics and Reporting
php artisan form-security:report --days=30 --format=pdf --email=admin@company.com
php artisan form-security:export-data --table=blocked_submissions --format=csv
php artisan form-security:trend-analysis --period=weekly --chart

# Maintenance Operations
php artisan form-security:refresh-cache --all
php artisan form-security:update-patterns --source=remote --backup
php artisan form-security:warm-cache --ips-file=common-ips.txt

# GeoLite2 Management
php artisan geolite2:import-chunked --limit=100000 --batch-size=1000 --resume
php artisan form-security:verify-geolite2 --repair-missing
php artisan form-security:update-geolite2 --auto-download
```

### Integration Requirements
- **Internal Integrations**: Integration with all package components for comprehensive management
- **External Integrations**: Laravel Artisan framework, database systems, and external services
- **Event System**: Command events (CommandStarted, CommandCompleted, CommandFailed)
- **Queue/Job Requirements**: Background processing for long-running operations

## Performance Requirements
- [ ] **Command Responsiveness**: Interactive commands respond within 1 second
- [ ] **Progress Reporting**: Long-running operations provide progress updates every 5 seconds
- [ ] **Memory Efficiency**: Commands use memory efficiently for large dataset operations
- [ ] **Interruption Handling**: Commands handle interruption gracefully with cleanup
- [ ] **Resume Capability**: Long-running operations support resume functionality

## Security Considerations
- [ ] **Access Control**: Commands respect Laravel's authorization and permission systems
- [ ] **Data Protection**: Commands handle sensitive data securely with appropriate sanitization
- [ ] **Audit Logging**: All command operations logged with user attribution and timestamps
- [ ] **Configuration Security**: Commands validate and secure configuration changes
- [ ] **Backup Security**: Data backup operations include encryption and secure storage

## Testing Requirements

### Unit Testing
- [ ] Individual command functionality with various parameters and options
- [ ] Error handling and validation logic for all commands
- [ ] Progress reporting and user interaction components
- [ ] Data validation and integrity checking mechanisms

### Integration Testing
- [ ] End-to-end command workflows with real system components
- [ ] Database operations and migration commands
- [ ] External service integration commands
- [ ] Command chaining and automation scenarios

### User Experience Testing
- [ ] Command help documentation accuracy and completeness
- [ ] Interactive command usability and error handling
- [ ] Progress reporting clarity and accuracy
- [ ] Error message helpfulness and actionability

## Implementation Guidelines

### Development Standards
- [ ] Follow Laravel Artisan command conventions and patterns
- [ ] Implement comprehensive error handling with user-friendly messages
- [ ] Use Laravel's built-in progress bars and output formatting
- [ ] Maintain consistent command naming and parameter conventions

### Command Design Principles
- [ ] Design commands to be idempotent where possible
- [ ] Provide dry-run options for destructive operations
- [ ] Include comprehensive help documentation and examples
- [ ] Implement proper exit codes for automation integration

## Dependencies & Prerequisites
### Internal Dependencies
- [ ] All package components for comprehensive management capabilities
- [ ] Database schema (SPEC-001) for data management operations
- [ ] Configuration system (SPEC-002) for configuration management

### External Dependencies
- [ ] Laravel framework 12.x with enhanced Artisan command system
- [ ] Database system for data management operations
- [ ] File system access for import/export operations

## Success Criteria & Acceptance
- [ ] Complete suite of commands covers all major package management needs
- [ ] Installation commands enable automated deployment and setup
- [ ] Diagnostic commands provide comprehensive system health insights
- [ ] Maintenance commands ensure optimal system performance
- [ ] Commands provide clear documentation and user-friendly interfaces
- [ ] All commands handle errors gracefully with informative messages

### Definition of Done
- [ ] Complete installation and setup automation commands
- [ ] Comprehensive testing and diagnostic tools
- [ ] Database management and maintenance commands
- [ ] Analytics and reporting generation tools
- [ ] System health monitoring and troubleshooting utilities
- [ ] Data import/export and migration tools
- [ ] Performance optimization and cache management commands
- [ ] Automated maintenance and cleanup operations
- [ ] Comprehensive help documentation for all commands
- [ ] Complete test suite with >90% code coverage
- [ ] User experience validation with real-world usage scenarios
- [ ] Security review completed for all command operations

## Related Documentation
- [ ] [Epic EPIC-004] - JTD-FormSecurity Advanced Features
- [ ] [SPEC-002] - Configuration Management System integration
- [ ] [SPEC-001] - Database Schema & Models for data operations
- [ ] [CLI Tools User Guide] - Complete command reference and usage examples

## Notes
The Console Commands & CLI Tools provide essential operational capabilities for the JTD-FormSecurity package. Commands must be designed with both interactive use and automation in mind, providing clear feedback and comprehensive error handling. Special attention should be paid to data safety and security when implementing destructive operations.

## Spec Completion Checklist
- [x] All requirements clearly defined and measurable
- [x] Technical architecture fully specified
- [x] API specifications complete with examples
- [x] Performance requirements specified with metrics
- [x] Security requirements identified and planned
- [x] Dependencies mapped and validated
- [x] Success criteria and acceptance criteria defined
- [ ] Stakeholder review completed and approved
