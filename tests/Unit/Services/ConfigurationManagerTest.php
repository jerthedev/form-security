<?php

declare(strict_types=1);

/**
 * Test File: ConfigurationManagerTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-002-configuration-management-system
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1013-configuration-management-system
 *
 * Description: Tests for the ConfigurationManager service functionality
 * including hierarchical loading, caching, validation, and runtime updates.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1013-configuration-management-system.md
 */

namespace JTD\FormSecurity\Tests\Unit\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use JTD\FormSecurity\Contracts\ConfigurationValidatorInterface;
use JTD\FormSecurity\Enums\ConfigurationSource;
use JTD\FormSecurity\Events\ConfigurationChanged;
use JTD\FormSecurity\Services\ConfigurationManager;
use JTD\FormSecurity\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-003')]
#[Group('ticket-1013')]
#[Group('configuration')]
#[Group('unit')]
class ConfigurationManagerTest extends TestCase
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
    public function it_can_get_configuration_value(): void
    {
        // Arrange
        $key = 'spam_threshold';
        $value = 0.7;

        $this->cache->shouldReceive('get')
            ->with("form_security_config_{$key}")
            ->andReturn(null);

        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($value);

        // Act
        $result = $this->configManager->get($key);

        // Assert
        $this->assertEquals($value, $result);
    }

    #[Test]
    public function it_returns_default_when_key_not_found(): void
    {
        // Arrange
        $key = 'nonexistent_key';
        $default = 'default_value';

        $this->cache->shouldReceive('get')
            ->with("form_security_config_{$key}")
            ->andReturn(null);

        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn(null);

        // Act
        $result = $this->configManager->get($key, $default);

        // Assert
        $this->assertEquals($default, $result);
    }

    #[Test]
    public function it_can_set_configuration_value_with_validation(): void
    {
        // Arrange
        $key = 'spam_threshold';
        $value = 0.8;

        $this->validator->shouldReceive('validateValue')
            ->with($key, $value)
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->events->shouldReceive('dispatch')
            ->with(Mockery::type(ConfigurationChanged::class))
            ->once();

        // Act
        $result = $this->configManager->set($key, $value);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_rejects_invalid_configuration_values(): void
    {
        // Arrange
        $key = 'spam_threshold';
        $value = 1.5; // Invalid: should be between 0 and 1

        $this->validator->shouldReceive('validateValue')
            ->with($key, $value)
            ->andReturn([
                'valid' => false,
                'errors' => ['Value must be between 0.0 and 1.0'],
            ]);

        // Act
        $result = $this->configManager->set($key, $value);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_can_check_feature_toggles(): void
    {
        // Arrange
        $feature = 'spam_detection';

        $this->cache->shouldReceive('get')
            ->with("form_security_config_features.{$feature}")
            ->andReturn(null);

        $this->config->shouldReceive('get')
            ->with("form-security.features.{$feature}")
            ->andReturn(true);

        // Act
        $result = $this->configManager->isFeatureEnabled($feature);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_can_toggle_features(): void
    {
        // Arrange
        $feature = 'ai_analysis';
        $enabled = true;

        $this->validator->shouldReceive('validateValue')
            ->with("features.{$feature}", $enabled)
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->events->shouldReceive('dispatch')
            ->with(Mockery::type(ConfigurationChanged::class))
            ->once();

        // Act
        $result = $this->configManager->toggleFeature($feature, $enabled);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_uses_cache_when_available(): void
    {
        // Arrange
        $key = 'cached_value';
        $cachedData = [
            'value' => 'cached_result',
            'type' => 'string',
            'source' => 'cache',
            'scope' => 'application',
            'is_encrypted' => false,
            'metadata' => [],
        ];

        $this->cache->shouldReceive('get')
            ->with("form_security_config_{$key}")
            ->andReturn($cachedData);

        // Act
        $result = $this->configManager->getValue($key);

        // Assert
        $this->assertEquals('cached_result', $result->value);
        $this->assertEquals(ConfigurationSource::CACHE, $result->source);
    }

    #[Test]
    public function it_caches_configuration_values(): void
    {
        // Arrange
        $key = 'test_key';
        $value = 'test_value';

        $this->cache->shouldReceive('get')
            ->with("form_security_config_{$key}")
            ->andReturn(null);

        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($value);

        $this->cache->shouldReceive('put')
            ->with("form_security_config_{$key}", Mockery::type('array'), 3600)
            ->andReturn(true);

        // Act
        $result = $this->configManager->cacheConfiguration($key);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_invalidates_cache_on_configuration_change(): void
    {
        // Arrange
        $key = 'test_key';
        $value = 'new_value';

        $this->validator->shouldReceive('validateValue')
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->cache->shouldReceive('forget')
            ->with("form_security_config_{$key}")
            ->andReturn(true);

        $this->events->shouldReceive('dispatch')
            ->with(Mockery::type(ConfigurationChanged::class))
            ->once();

        // Act
        $result = $this->configManager->setValue($key, $value);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_validates_entire_configuration(): void
    {
        // Arrange
        $config = [
            'enabled' => true,
            'spam_threshold' => 0.7,
            'features' => ['spam_detection' => true],
        ];

        $this->validator->shouldReceive('validateConfiguration')
            ->with($config)
            ->andReturn([
                'valid' => true,
                'errors' => [],
                'summary' => ['total_keys' => 3, 'valid_keys' => 3, 'invalid_keys' => 0],
            ]);

        // Act
        $result = $this->configManager->validateConfig($config);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function it_gets_enabled_features(): void
    {
        // Arrange
        $features = [
            'spam_detection' => true,
            'ai_analysis' => false,
            'rate_limiting' => true,
            'logging' => true,
        ];

        $this->cache->shouldReceive('get')
            ->with('form_security_config_features')
            ->andReturn(null);

        $this->config->shouldReceive('get')
            ->with('form-security.features')
            ->andReturn($features);

        // Act
        $result = $this->configManager->getEnabledFeatures();

        // Assert
        $expected = ['spam_detection', 'rate_limiting', 'logging'];
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_refreshes_configuration(): void
    {
        // Arrange
        $this->config->shouldReceive('set')
            ->with('form-security', Mockery::type('array'))
            ->once();

        $this->config->shouldReceive('get')
            ->with('form-security', [])
            ->andReturn([]);

        // Act
        $result = $this->configManager->refresh();

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_exports_configuration(): void
    {
        // Arrange
        $configData = [
            'enabled' => true,
            'spam_threshold' => 0.7,
            'features' => ['spam_detection' => true],
        ];

        $this->config->shouldReceive('get')
            ->with('form-security', [])
            ->andReturn($configData);

        $this->config->shouldReceive('get')
            ->with('form-security.features')
            ->andReturn(['spam_detection' => true]);

        // Mock cache calls for each key
        $this->cache->shouldReceive('get')
            ->andReturn(null);

        // Act
        $result = $this->configManager->exportConfiguration();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('enabled', $result);
        $this->assertArrayHasKey('spam_threshold', $result);
        $this->assertArrayHasKey('features', $result);
    }

    #[Test]
    public function it_imports_configuration_with_validation(): void
    {
        // Arrange
        $config = [
            'enabled' => true,
            'spam_threshold' => 0.8,
        ];

        $this->validator->shouldReceive('validateConfiguration')
            ->with($config)
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->validator->shouldReceive('validateValue')
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->events->shouldReceive('dispatch')
            ->with(Mockery::type(ConfigurationChanged::class))
            ->twice();

        // Act
        $result = $this->configManager->importConfiguration($config);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_tracks_performance_metrics(): void
    {
        // Arrange
        $this->cache->shouldReceive('get')
            ->with('form_security_config_test_key')
            ->andReturn(null);

        $this->config->shouldReceive('get')
            ->andReturn('test_value');

        // Act
        $this->configManager->get('test_key');
        $metrics = $this->configManager->getPerformanceMetrics();

        // Assert
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('cache_hits', $metrics);
        $this->assertArrayHasKey('cache_misses', $metrics);
        $this->assertArrayHasKey('cache_hit_ratio', $metrics);
        $this->assertArrayHasKey('average_load_time', $metrics);
    }

    #[Test]
    public function it_warms_cache_for_common_keys(): void
    {
        // Arrange
        $keys = ['enabled', 'spam_threshold'];

        $this->config->shouldReceive('get')
            ->andReturn('test_value');

        $this->cache->shouldReceive('put')
            ->andReturn(true);

        // Act
        $result = $this->configManager->warmCache($keys);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_hierarchical_source_loading(): void
    {
        // Arrange
        $key = 'hierarchical_test';
        $sources = ['runtime', 'environment', 'file', 'default'];

        // Mock runtime storage (should be checked first)
        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn(null);

        // Act
        $result = $this->configManager->getFromSources($key, $sources);

        // Assert
        $this->assertInstanceOf(\JTD\FormSecurity\ValueObjects\ConfigurationValue::class, $result);
    }

    #[Test]
    public function it_handles_configuration_source_priority(): void
    {
        // Arrange
        $key = 'priority_test';
        $fileValue = 'file_value';

        // Mock file config (lower priority)
        $this->cache->shouldReceive('get')
            ->with("form_security_config_{$key}")
            ->andReturn(null);
        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($fileValue);

        // Act
        $result = $this->configManager->get($key);

        // Assert
        $this->assertEquals($fileValue, $result);
    }

    #[Test]
    public function it_handles_configuration_with_null_values(): void
    {
        // Arrange
        $key = 'null_test';

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn(null);

        // Act
        $result = $this->configManager->get($key, 'default_value');

        // Assert
        $this->assertEquals('default_value', $result);
    }

    #[Test]
    public function it_handles_configuration_with_empty_string_values(): void
    {
        // Arrange
        $key = 'empty_test';
        $emptyValue = '';

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($emptyValue);

        // Act
        $result = $this->configManager->get($key, 'default_value');

        // Assert
        $this->assertEquals($emptyValue, $result); // Empty string should be returned, not default
    }

    #[Test]
    public function it_handles_configuration_with_zero_values(): void
    {
        // Arrange
        $key = 'zero_test';
        $zeroValue = 0;

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($zeroValue);

        // Act
        $result = $this->configManager->get($key, 999);

        // Assert
        $this->assertEquals($zeroValue, $result); // Zero should be returned, not default
    }

    #[Test]
    public function it_handles_configuration_with_false_values(): void
    {
        // Arrange
        $key = 'false_test';
        $falseValue = false;

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($falseValue);

        // Act
        $result = $this->configManager->get($key, true);

        // Assert
        $this->assertEquals($falseValue, $result); // False should be returned, not default
    }

    #[Test]
    public function it_handles_nested_configuration_keys(): void
    {
        // Arrange
        $key = 'features.nested.deep.value';
        $nestedValue = 'deep_nested_value';

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($nestedValue);

        // Act
        $result = $this->configManager->get($key);

        // Assert
        $this->assertEquals($nestedValue, $result);
    }

    #[Test]
    public function it_handles_configuration_key_case_sensitivity(): void
    {
        // Arrange
        $key = 'KEY_WITH_UNDERSCORES';
        $value = 'case_sensitive_value';

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($value);

        // Act
        $result = $this->configManager->get($key);

        // Assert
        $this->assertEquals($value, $result);
    }

    #[Test]
    public function it_handles_configuration_array_values(): void
    {
        // Arrange
        $key = 'array_test';
        $arrayValue = ['item1', 'item2', 'item3'];

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($arrayValue);

        // Act
        $result = $this->configManager->get($key);

        // Assert
        $this->assertEquals($arrayValue, $result);
    }

    #[Test]
    public function it_handles_configuration_object_values(): void
    {
        // Arrange
        $key = 'object_test';
        $objectValue = (object) ['property' => 'value'];

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($objectValue);

        // Act
        $result = $this->configManager->get($key);

        // Assert
        $this->assertEquals($objectValue, $result);
    }

    #[Test]
    public function it_handles_configuration_with_special_characters(): void
    {
        // Arrange
        $key = 'special_test';
        $specialValue = 'Value with special chars: !@#$%^&*()[]{}|;:,.<>?';

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($specialValue);

        // Act
        $result = $this->configManager->get($key);

        // Assert
        $this->assertEquals($specialValue, $result);
    }

    #[Test]
    public function it_handles_configuration_with_unicode_characters(): void
    {
        // Arrange
        $key = 'unicode_test';
        $unicodeValue = 'Unicode test: 🚀 中文 العربية русский';

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($unicodeValue);

        // Act
        $result = $this->configManager->get($key);

        // Assert
        $this->assertEquals($unicodeValue, $result);
    }

    #[Test]
    public function it_handles_configuration_timeout_scenarios(): void
    {
        // Arrange
        $key = 'timeout_test';

        // Simulate timeout by having cache/config return null
        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn(null);

        // Act
        $result = $this->configManager->get($key, 'timeout_default');

        // Assert
        $this->assertEquals('timeout_default', $result);
    }

    #[Test]
    public function it_handles_configuration_with_large_values(): void
    {
        // Arrange
        $key = 'large_value_test';
        $largeValue = str_repeat('Large configuration value ', 1000); // ~25KB string

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($largeValue);

        // Act
        $result = $this->configManager->get($key);

        // Assert
        $this->assertEquals($largeValue, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
