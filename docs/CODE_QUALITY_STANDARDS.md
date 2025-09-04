# Code Quality Standards

This document outlines the code quality standards and practices for the JTD-FormSecurity package.

## Overview

The JTD-FormSecurity package maintains enterprise-grade code quality standards through comprehensive static analysis, automated formatting, and rigorous testing practices.

## Code Quality Tools

### 1. Laravel Pint (PSR-12 Formatting)

**Configuration**: `pint.json`
**Command**: `composer pint`

- **Standard**: PSR-12 coding standards
- **Enforcement**: Automatic formatting on save (IDE configured)
- **CI/CD**: Automated checks prevent non-compliant code from merging

**Usage**:
```bash
# Format code automatically
composer pint

# Check formatting without modifying files
composer pint --test
```

### 2. PHPStan (Static Analysis)

**Configuration**: `phpstan.neon`
**Command**: `composer phpstan`

- **Level**: Maximum (Level 8+) with strict rules
- **Coverage**: All source code and configuration files
- **Extensions**: Larastan for Laravel-specific analysis
- **Memory**: 2GB limit for complex analysis

**Features Enabled**:
- Strict type checking
- Generic class validation
- Iterable value type checking  
- Dead code detection
- Unused variable detection

**Usage**:
```bash
# Run static analysis
composer phpstan

# Run with specific memory limit
vendor/bin/phpstan analyse --memory-limit=2G
```

### 3. Psalm (Advanced Type Analysis)

**Configuration**: `psalm.xml`
**Command**: `vendor/bin/psalm`

- **Level**: Error Level 1 (strictest)
- **Type Coverage**: Complete type inference
- **Laravel Plugin**: Laravel-specific optimizations
- **Dead Code**: Detects unused code and variables

**Usage**:
```bash
# Run Psalm analysis
vendor/bin/psalm

# Generate baseline for existing code
vendor/bin/psalm --set-baseline=psalm-baseline.xml

# Show type coverage stats
vendor/bin/psalm --show-info=true --stats
```

## Code Standards

### 1. PHP Standards

- **PHP Version**: 8.2+ with modern features
- **Strict Types**: `declare(strict_types=1);` in all files
- **Type Hints**: Comprehensive parameter and return type declarations
- **Property Types**: Typed properties for all class properties

### 2. Laravel Standards

- **Service Providers**: Follow Laravel 12 enhanced patterns
- **Dependency Injection**: Constructor injection preferred
- **Facades**: Used sparingly, prefer dependency injection
- **Collections**: Leverage Laravel collection methods

### 3. Documentation Standards

- **PHPDoc**: Comprehensive documentation for all public methods
- **Type Information**: Detailed `@param` and `@return` annotations
- **Examples**: Code examples in complex method documentation
- **Links**: Cross-references to related classes and methods

### 4. Testing Standards

- **Coverage**: Minimum 90% code coverage required
- **PHPUnit**: Version 12+ with modern assertions
- **Test Groups**: Epic/Sprint-based organization
- **Performance**: Dedicated performance benchmarks

## Quality Gates

All code must pass these quality gates before merging:

### 1. Formatting Gate
```bash
composer pint --test
```
**Requirement**: Zero violations of PSR-12 standards

### 2. Static Analysis Gate
```bash
composer phpstan
```
**Requirement**: Zero errors at maximum level

### 3. Type Analysis Gate  
```bash
vendor/bin/psalm
```
**Requirement**: Zero errors at level 1

### 4. Test Gate
```bash
composer test
```
**Requirement**: 100% test pass rate, 90%+ coverage

### 5. Integration Gate
```bash
vendor/bin/phpunit --group integration
```
**Requirement**: All integration tests pass

## IDE Configuration

### VS Code Settings

The package includes comprehensive VS Code settings:
- **Auto-formatting**: PSR-12 on save
- **Static Analysis**: Real-time PHPStan/Psalm integration
- **IntelliSense**: Full PHP and Laravel support
- **Extensions**: Recommended extensions list

### EditorConfig

Cross-IDE consistency through `.editorconfig`:
- **Indentation**: 4 spaces for PHP, 2 for JSON/YAML
- **Line Endings**: LF (Unix-style)
- **Encoding**: UTF-8
- **Trailing Whitespace**: Automatically trimmed

## Automated Quality Assurance

### GitHub Actions Workflow

**File**: `.github/workflows/code-quality.yml`

**Matrix Testing**:
- PHP 8.2, 8.3
- Laravel 12.x versions
- Multiple operating systems

**Quality Checks**:
1. Laravel Pint formatting validation
2. PHPStan static analysis (max level)
3. Psalm type analysis (level 1)  
4. PHPUnit test suite execution
5. Code coverage reporting

### Pre-commit Hooks

Recommended pre-commit hook configuration:
```bash
#!/bin/sh
# Run quality checks before commit
composer pint
composer phpstan
composer test

# Exit if any check fails
if [ $? -ne 0 ]; then
    echo "Quality checks failed. Please fix issues before committing."
    exit 1
fi
```

## Performance Standards

### Code Performance

- **Service Provider**: <30ms bootstrap time
- **Database Queries**: <100ms for 95% of operations  
- **Cache Operations**: 95%+ hit ratio, <5ms response time
- **Memory Usage**: <50MB for typical operations

### Static Analysis Performance

- **PHPStan**: <60 seconds for full analysis
- **Psalm**: <30 seconds for type analysis
- **Combined**: <2 minutes total for all quality checks

## Quality Metrics

### Tracked Metrics

1. **Code Coverage**: Target 90%+, measured by PHPUnit
2. **Type Coverage**: Target 95%+, measured by Psalm
3. **Cyclomatic Complexity**: Target <10 average, measured by static analysis
4. **Code Duplication**: Target <3%, monitored in reviews
5. **Technical Debt**: Tracked through TODO comments and issue labels

### Reporting

- **Coverage Reports**: Generated automatically in HTML format
- **Static Analysis**: Reports integrated into CI/CD pipeline  
- **Performance Metrics**: Tracked through dedicated benchmarks
- **Quality Dashboard**: Aggregated metrics in README badges

## Best Practices

### 1. Development Workflow

1. **Feature Branch**: Create feature branch from `develop`
2. **Code Implementation**: Follow TDD practices when possible
3. **Quality Check**: Run local quality gates before push
4. **Pull Request**: Comprehensive PR with quality gate results
5. **Review Process**: Code review focusing on quality standards
6. **Merge**: Only after all quality gates pass

### 2. Code Organization

- **Single Responsibility**: Classes and methods have single, clear purpose
- **Dependency Inversion**: Depend on abstractions, not concretions  
- **Open/Closed**: Open for extension, closed for modification
- **Interface Segregation**: Client-specific interfaces
- **Liskov Substitution**: Substitutable subclasses

### 3. Error Handling

- **Exceptions**: Use specific exception types
- **Logging**: Comprehensive error logging with context
- **User Messages**: Clear, actionable error messages
- **Recovery**: Graceful degradation when possible

## Continuous Improvement

### 1. Tool Updates

- **Monthly**: Review and update static analysis tools
- **Quarterly**: Evaluate new quality tools and practices
- **Annually**: Review and update coding standards

### 2. Metric Review

- **Weekly**: Review quality metrics in team meetings
- **Monthly**: Analyze trends and identify improvement areas  
- **Quarterly**: Set new quality targets and standards

### 3. Training

- **Onboarding**: New team members receive quality standards training
- **Regular Updates**: Team training on new tools and practices
- **Best Practices**: Regular sharing of quality improvement techniques

## Resources

### Documentation
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
- [Psalm Documentation](https://psalm.dev/docs/)  
- [Laravel Pint Documentation](https://laravel.com/docs/pint)
- [PSR-12 Coding Standards](https://www.php-fig.org/psr/psr-12/)

### Tools
- [PHPStan Larastan Extension](https://github.com/nunomaduro/larastan)
- [Psalm Laravel Plugin](https://github.com/psalm/psalm-plugin-laravel)
- [VS Code PHP Extensions](https://marketplace.visualstudio.com/items?itemName=bmewburn.vscode-intelephense-client)

This document is living and should be updated as standards evolve and new tools are adopted.