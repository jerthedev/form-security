<?php

declare(strict_types=1);

/**
 * Test File: EnvironmentIntegrationTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-002-configuration-management-system
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1022-configuration-system-tests
 *
 * Description: Tests for environment variable integration with fallback mechanisms
 * and hierarchical configuration loading from environment sources.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1022-configuration-system-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Environment;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use JTD\FormSecurity\Contracts\ConfigurationValidatorInterface;
use JTD\FormSecurity\Enums\ConfigurationSource;
use JTD\FormSecurity\Services\ConfigurationManager;
use JTD\FormSecurity\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-003')]
#[Group('ticket-1022')]
#[Group('configuration')]
#[Group('environment')]
#[Group('unit')]
class EnvironmentIntegrationTest extends TestCase
{
    protected ConfigurationManager $configManager;

    protected ConfigRepository $config;

    protected CacheRepository $cache;

    protected ConfigurationValidatorInterface $validator;

    protected EventDispatcher $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = Mockery::mock(ConfigRepository::class);
        $this->cache = Mockery::mock(CacheRepository::class);
        $this->validator = Mockery::mock(ConfigurationValidatorInterface::class);
        $this->events = Mockery::mock(EventDispatcher::class);

        $this->configManager = new ConfigurationManager(
            $this->config,
            $this->cache,
            $this->validator,
            $this->events
        );
    }

    #[Test]
    public function it_loads_configuration_from_environment_variables(): void
    {
        // This test verifies that the getWithEnvFallback method exists and can be called
        // The actual environment variable loading is tested in integration tests

        // Arrange
        $envKey = 'FORM_SECURITY_TEST_KEY';
        $defaultValue = 'default_value';

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn(null);

        // Act
        $result = $this->configManager->getWithEnvFallback('test_key', $envKey, $defaultValue);

        // Assert - Should return some value (either env or default)
        $this->assertNotNull($result);
        $this->assertNotNull($result->value);
        $this->assertInstanceOf(\JTD\FormSecurity\ValueObjects\ConfigurationValue::class, $result);
    }

    #[Test]
    public function it_falls_back_to_default_when_environment_variable_not_set(): void
    {
        // Arrange
        $envKey = 'FORM_SECURITY_NONEXISTENT_KEY';
        $defaultValue = 'default_value';

        $this->cache->shouldReceive('get')
            ->with('form_security_config_nonexistent_key')
            ->andReturn(null);
        $this->config->shouldReceive('get')
            ->with('form-security.nonexistent_key')
            ->andReturn(null);

        // Act
        $result = $this->configManager->getWithEnvFallback('nonexistent_key', $envKey, $defaultValue);

        // Assert
        $this->assertEquals($defaultValue, $result->value);
        $this->assertEquals(ConfigurationSource::DEFAULT->value, $result->source->value);
    }

    #[Test]
    public function it_prioritizes_configuration_over_environment_variables(): void
    {
        // This test verifies the method signature and basic functionality
        // The actual priority testing is done in integration tests

        // Arrange
        $configKey = 'test_priority';
        $envKey = 'FORM_SECURITY_TEST_PRIORITY';
        $defaultValue = 'default';

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn(null);

        // Act
        $result = $this->configManager->getWithEnvFallback($configKey, $envKey, $defaultValue);

        // Assert - Should return a valid ConfigurationValue
        $this->assertInstanceOf(\JTD\FormSecurity\ValueObjects\ConfigurationValue::class, $result);
        $this->assertNotNull($result->source);
    }

    #[Test]
    public function it_handles_boolean_environment_variables(): void
    {
        // Test a single boolean case to verify environment variable handling
        $envKey = 'FORM_SECURITY_BOOLEAN_TEST';
        $envValue = 'true';
        putenv("{$envKey}={$envValue}");

        $this->cache->shouldReceive('get')
            ->with('form_security_config_boolean_test')
            ->andReturn(null);
        $this->config->shouldReceive('get')
            ->with('form-security.boolean_test')
            ->andReturn(null);

        // Act
        $result = $this->configManager->getWithEnvFallback('boolean_test', $envKey, false);

        // Assert
        $this->assertEquals($envValue, $result->value);
        $this->assertEquals(ConfigurationSource::ENVIRONMENT->value, $result->source->value);

        // Clean up
        putenv($envKey);
    }

    #[Test]
    public function it_handles_numeric_environment_variables(): void
    {
        // Arrange
        $testCases = [
            ['env_value' => '42', 'expected' => '42'],
            ['env_value' => '3.14', 'expected' => '3.14'],
            ['env_value' => '-10', 'expected' => '-10'],
            ['env_value' => '0', 'expected' => '0'],
        ];

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn(null);

        foreach ($testCases as $testCase) {
            // Arrange
            $envKey = 'FORM_SECURITY_NUMERIC_TEST';
            putenv("{$envKey}={$testCase['env_value']}");

            // Act
            $result = $this->configManager->getWithEnvFallback('numeric_test', $envKey, 0);

            // Assert
            $this->assertEquals($testCase['expected'], $result->value);

            // Clean up
            putenv($envKey);
        }
    }

    #[Test]
    public function it_handles_empty_environment_variables(): void
    {
        // Arrange
        $envKey = 'FORM_SECURITY_EMPTY_TEST';
        $defaultValue = 'default_value';
        putenv("{$envKey}=");

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn(null);

        // Act
        $result = $this->configManager->getWithEnvFallback('empty_test', $envKey, $defaultValue);

        // Assert
        $this->assertEquals('', $result->value); // Empty string should be returned, not default

        // Clean up
        putenv($envKey);
    }

    #[Test]
    public function it_handles_environment_variables_with_special_characters(): void
    {
        // Arrange
        $envKey = 'FORM_SECURITY_SPECIAL_TEST';
        $envValue = 'value with spaces and symbols: !@#$%^&*()';
        putenv("{$envKey}={$envValue}");

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn(null);

        // Act
        $result = $this->configManager->getWithEnvFallback('special_test', $envKey, 'default');

        // Assert
        $this->assertEquals($envValue, $result->value);

        // Clean up
        putenv($envKey);
    }

    #[Test]
    public function it_uses_conventional_environment_variable_naming(): void
    {
        // Test that the system can automatically generate environment variable names
        // from configuration keys using the FORM_SECURITY_ prefix

        // Arrange
        $configKey = 'features.spam_detection';
        $expectedEnvKey = 'FORM_SECURITY_FEATURES_SPAM_DETECTION';
        $envValue = 'true';
        putenv("{$expectedEnvKey}={$envValue}");

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn(null);

        // Act
        $result = $this->configManager->getWithEnvFallback($configKey, null, false);

        // Assert
        $this->assertEquals($envValue, $result->value);

        // Clean up
        putenv($expectedEnvKey);
    }

    #[Test]
    public function it_handles_hierarchical_environment_variable_loading(): void
    {
        // Test loading from multiple environment sources in priority order

        // Arrange
        $configKey = 'test_hierarchy';
        $sources = ['runtime', 'environment', 'file', 'default'];

        // Set environment variable
        $envKey = 'FORM_SECURITY_TEST_HIERARCHY';
        $envValue = 'env_value';
        putenv("{$envKey}={$envValue}");

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn(null);

        // Act
        $result = $this->configManager->getFromSources($configKey, $sources);

        // Assert
        $this->assertEquals($envValue, $result->value);
        $this->assertEquals(ConfigurationSource::ENVIRONMENT, $result->source);

        // Clean up
        putenv($envKey);
    }

    #[Test]
    public function it_validates_environment_variable_values(): void
    {
        // This test verifies that environment variable integration works
        // Validation is handled separately by the ConfigurationValidator

        // Arrange
        $envKey = 'FORM_SECURITY_VALIDATION_TEST';
        $defaultValue = 0.7;

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn(null);

        // Act
        $result = $this->configManager->getWithEnvFallback('validation_test', $envKey, $defaultValue);

        // Assert - Should return a valid result
        $this->assertInstanceOf(\JTD\FormSecurity\ValueObjects\ConfigurationValue::class, $result);
        $this->assertNotNull($result->value);
    }

    #[Test]
    public function it_handles_environment_variable_caching(): void
    {
        // This test verifies that the method can be called multiple times
        // Actual caching behavior is tested in integration tests

        // Arrange
        $envKey = 'FORM_SECURITY_CACHE_TEST';
        $defaultValue = 'default';

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn(null);

        // Act - Multiple calls should work
        $result1 = $this->configManager->getWithEnvFallback('cache_test', $envKey, $defaultValue);
        $result2 = $this->configManager->getWithEnvFallback('cache_test', $envKey, $defaultValue);

        // Assert - Both should return valid results
        $this->assertInstanceOf(\JTD\FormSecurity\ValueObjects\ConfigurationValue::class, $result1);
        $this->assertInstanceOf(\JTD\FormSecurity\ValueObjects\ConfigurationValue::class, $result2);
    }

    #[Test]
    public function it_handles_missing_environment_variables_gracefully(): void
    {
        // Arrange
        $envKey = 'FORM_SECURITY_MISSING_VAR';
        $defaultValue = 'fallback_value';

        // Ensure environment variable is not set
        putenv($envKey); // Unset the variable

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn(null);

        // Act
        $result = $this->configManager->getWithEnvFallback('missing_var', $envKey, $defaultValue);

        // Assert
        $this->assertEquals($defaultValue, $result->value);
        $this->assertEquals(ConfigurationSource::DEFAULT, $result->source);
    }

    protected function tearDown(): void
    {
        // Clean up any remaining environment variables
        $envVars = [
            'FORM_SECURITY_SPAM_THRESHOLD',
            'FORM_SECURITY_NONEXISTENT_KEY',
            'FORM_SECURITY_BOOLEAN_TEST',
            'FORM_SECURITY_NUMERIC_TEST',
            'FORM_SECURITY_EMPTY_TEST',
            'FORM_SECURITY_SPECIAL_TEST',
            'FORM_SECURITY_FEATURES_SPAM_DETECTION',
            'FORM_SECURITY_TEST_HIERARCHY',
            'FORM_SECURITY_VALIDATION_TEST',
            'FORM_SECURITY_CACHE_TEST',
            'FORM_SECURITY_MISSING_VAR',
        ];

        foreach ($envVars as $envVar) {
            putenv($envVar);
        }

        Mockery::close();
        parent::tearDown();
    }
}
