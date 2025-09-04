# Code Review Checklist

This checklist ensures consistent, high-quality code reviews for the JTD-FormSecurity package.

## Pre-Review Checklist (Author)

Before requesting a code review, ensure:

### ✅ Code Quality Gates
- [ ] `composer pint` passes (PSR-12 formatting)
- [ ] `composer phpstan` passes (zero errors at max level)
- [ ] `vendor/bin/psalm` passes (zero errors at level 1)
- [ ] `composer test` passes (100% success rate)
- [ ] Code coverage meets 90% minimum threshold

### ✅ Documentation
- [ ] All public methods have comprehensive PHPDoc comments
- [ ] Complex algorithms have inline comments explaining logic
- [ ] README.md updated if public API changes
- [ ] CHANGELOG.md updated with changes
- [ ] Ticket/Epic references included in commit messages

### ✅ Testing
- [ ] Unit tests cover all new/modified functionality
- [ ] Integration tests validate end-to-end scenarios
- [ ] Performance tests included for optimization changes
- [ ] Test names are descriptive and follow naming conventions
- [ ] Tests use proper Epic/Sprint/Ticket grouping attributes

## Review Checklist (Reviewer)

### 🏗️ Architecture & Design

#### Design Patterns
- [ ] **Single Responsibility**: Classes/methods have one clear purpose
- [ ] **Open/Closed**: Code is open for extension, closed for modification
- [ ] **Dependency Inversion**: Dependencies on abstractions, not concretions
- [ ] **Interface Segregation**: Interfaces are client-specific
- [ ] **Liskov Substitution**: Subclasses are substitutable for base classes

#### Laravel Best Practices
- [ ] **Service Providers**: Proper registration and boot methods
- [ ] **Dependency Injection**: Constructor injection used over facades
- [ ] **Eloquent**: Relationships and scopes properly defined
- [ ] **Middleware**: Applied appropriately for cross-cutting concerns
- [ ] **Events/Listeners**: Used for decoupled communication

### 🔧 Code Quality

#### PHP Standards
- [ ] **Strict Types**: `declare(strict_types=1);` present
- [ ] **Type Hints**: All parameters and return types declared
- [ ] **Property Types**: Class properties have type declarations
- [ ] **Modern PHP**: Uses PHP 8.2+ features appropriately
- [ ] **Error Handling**: Proper exception handling and types

#### Code Structure
- [ ] **Naming**: Variables, methods, classes use descriptive names
- [ ] **Constants**: Magic numbers/strings replaced with named constants
- [ ] **Method Length**: Methods are concise and focused
- [ ] **Class Size**: Classes don't exceed reasonable size limits
- [ ] **Nesting**: Excessive nesting avoided (max 3-4 levels)

### 📊 Performance

#### Database
- [ ] **N+1 Queries**: No N+1 query problems introduced
- [ ] **Eager Loading**: Relationships loaded efficiently
- [ ] **Indexing**: Database queries use appropriate indexes
- [ ] **Query Optimization**: Complex queries are optimized
- [ ] **Pagination**: Large datasets use proper pagination

#### Caching
- [ ] **Cache Strategy**: Appropriate caching implemented
- [ ] **Cache Keys**: Proper cache key structure and TTL
- [ ] **Invalidation**: Cache invalidation logic is correct
- [ ] **Hit Ratios**: Changes maintain target hit ratios

#### Memory & Performance
- [ ] **Memory Usage**: No memory leaks or excessive usage
- [ ] **Loop Optimization**: Efficient loops and iterations
- [ ] **Resource Cleanup**: Proper resource disposal
- [ ] **Performance Targets**: Meets Epic performance requirements

### 🛡️ Security

#### Input Validation
- [ ] **Validation Rules**: All inputs properly validated
- [ ] **Sanitization**: User input sanitized before processing
- [ ] **Type Casting**: Proper type casting for security
- [ ] **Mass Assignment**: Protected against mass assignment
- [ ] **SQL Injection**: No raw SQL without proper binding

#### Data Protection
- [ ] **Sensitive Data**: No secrets or keys in code
- [ ] **Encryption**: Sensitive data properly encrypted
- [ ] **Access Control**: Proper authorization checks
- [ ] **OWASP**: Follows OWASP security guidelines
- [ ] **Rate Limiting**: Appropriate rate limiting implemented

### 🧪 Testing

#### Test Coverage
- [ ] **Unit Tests**: New/modified code has unit tests
- [ ] **Integration Tests**: End-to-end scenarios tested
- [ ] **Edge Cases**: Boundary conditions and edge cases covered
- [ ] **Error Scenarios**: Error conditions properly tested
- [ ] **Performance Tests**: Performance requirements validated

#### Test Quality
- [ ] **AAA Pattern**: Arrange, Act, Assert structure followed
- [ ] **Descriptive Names**: Test method names are descriptive
- [ ] **Single Assertion**: Tests focus on single behavior
- [ ] **Test Data**: Uses factories/fixtures appropriately
- [ ] **Mocking**: External dependencies properly mocked

### 📚 Documentation

#### Code Documentation
- [ ] **PHPDoc**: All public methods have comprehensive documentation
- [ ] **Examples**: Complex methods include usage examples
- [ ] **Parameters**: All parameters documented with types
- [ ] **Return Values**: Return types and meanings documented
- [ ] **Exceptions**: Thrown exceptions documented

#### External Documentation
- [ ] **API Changes**: Public API changes documented
- [ ] **Migration Guides**: Breaking changes have migration guides  
- [ ] **Configuration**: New configuration options documented
- [ ] **Usage Examples**: Complex features have usage examples
- [ ] **Troubleshooting**: Common issues and solutions documented

## Specialized Reviews

### 🚀 Performance Review

For performance-critical changes:

- [ ] **Benchmarks**: Performance benchmarks included and meet targets
- [ ] **Profiling**: Code has been profiled for bottlenecks
- [ ] **Resource Usage**: Memory and CPU usage optimized
- [ ] **Scalability**: Changes handle increased load appropriately
- [ ] **Monitoring**: Performance metrics and monitoring added

### 🔧 Infrastructure Review

For infrastructure changes:

- [ ] **Configuration**: New configurations are documented
- [ ] **Migrations**: Database migrations are reversible
- [ ] **Dependencies**: New dependencies are justified and secure
- [ ] **Deployment**: Changes don't break deployment process
- [ ] **Backward Compatibility**: Maintains backward compatibility

### 📦 Package Review

For package-level changes:

- [ ] **Public API**: Changes maintain API stability
- [ ] **Semantic Versioning**: Version changes follow SemVer
- [ ] **Composer**: composer.json updated appropriately
- [ ] **Installation**: Package installs cleanly in fresh projects
- [ ] **Integration**: Works with supported Laravel versions

## Review Process

### 1. Initial Review
- [ ] Code compiles and runs without errors
- [ ] All CI/CD checks pass
- [ ] PR description clearly explains changes
- [ ] Changes match ticket/Epic requirements

### 2. Detailed Review
- [ ] Go through each file methodically
- [ ] Check for adherence to coding standards
- [ ] Validate test coverage and quality
- [ ] Review documentation updates

### 3. Testing Review
- [ ] Manually test critical functionality changes
- [ ] Validate performance claims with benchmarks
- [ ] Test edge cases and error scenarios
- [ ] Verify integration with existing features

### 4. Final Approval
- [ ] All review comments addressed
- [ ] Code quality standards met
- [ ] Documentation complete and accurate
- [ ] Confident in production readiness

## Common Issues Checklist

### ❌ Avoid These Common Problems

#### Code Issues
- [ ] **God Classes**: Avoid classes that do too much
- [ ] **Long Methods**: Methods should be focused and concise
- [ ] **Deep Nesting**: Excessive if/for nesting makes code hard to read
- [ ] **Magic Numbers**: Use named constants instead of magic numbers
- [ ] **Dead Code**: Remove commented out or unused code

#### Laravel Issues  
- [ ] **N+1 Queries**: Watch for N+1 query problems in relationships
- [ ] **Mass Assignment**: Don't forget `$fillable` or `$guarded` properties
- [ ] **Facade Overuse**: Prefer dependency injection over facades
- [ ] **Route Model Binding**: Use route model binding when appropriate
- [ ] **Validation**: Don't skip input validation

#### Testing Issues
- [ ] **Test Independence**: Tests should not depend on each other
- [ ] **Database State**: Clean up test database state properly
- [ ] **Time Dependencies**: Don't use actual time/dates in tests
- [ ] **External Dependencies**: Mock external services appropriately
- [ ] **Assertion Quality**: Use specific assertions, not just `assertTrue`

#### Security Issues
- [ ] **Input Validation**: All user inputs must be validated
- [ ] **Output Escaping**: Escape output to prevent XSS
- [ ] **SQL Injection**: Use query builder or prepared statements
- [ ] **CSRF Protection**: CSRF protection for state-changing operations
- [ ] **Authentication**: Verify authentication for protected operations

## Tools Integration

### Automated Checks
- **GitHub Actions**: Automated quality gate validation
- **PHPStan**: Static analysis integration in PR checks
- **Psalm**: Type analysis in CI/CD pipeline
- **Code Coverage**: Coverage reporting in PR comments

### Manual Tools
- **IDE Integration**: VS Code settings for real-time quality feedback
- **Local Scripts**: Pre-commit hooks for quality validation
- **Performance Profiling**: Built-in profiling tools for optimization

## Review Efficiency Tips

### For Authors
1. **Self-Review**: Review your own code first
2. **Small PRs**: Keep PRs focused and reasonably sized
3. **Context**: Provide clear PR descriptions with context
4. **Quality Gates**: Ensure all automated checks pass

### For Reviewers
1. **Focus**: Review for correctness, maintainability, and performance
2. **Constructive**: Provide constructive, actionable feedback
3. **Education**: Use reviews as learning opportunities
4. **Priorities**: Focus on significant issues over minor style preferences

This checklist should be adapted based on team experience and project needs. Regular retrospectives can help identify areas where the review process can be improved.