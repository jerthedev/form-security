<?php

declare(strict_types=1);

/**
 * Test File: ConfigurationRuntimeTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-002-configuration-management-system
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1022-configuration-system-tests
 *
 * Description: Tests for runtime configuration updates without application restart
 * including event handling, cache invalidation, and persistence.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1022-configuration-system-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Runtime;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use JTD\FormSecurity\Contracts\ConfigurationValidatorInterface;
use JTD\FormSecurity\Events\ConfigurationChanged;
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
#[Group('runtime')]
#[Group('unit')]
class ConfigurationRuntimeTest extends TestCase
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
    public function it_updates_configuration_at_runtime(): void
    {
        // Arrange
        $key = 'spam_threshold';
        $newValue = 0.8;

        $this->validator->shouldReceive('validateValue')
            ->with($key, $newValue)
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->cache->shouldReceive('forget')
            ->with("form_security_config_{$key}")
            ->andReturn(true);

        $this->events->shouldReceive('dispatch')
            ->with(Mockery::type(ConfigurationChanged::class))
            ->once();

        // Act
        $result = $this->configManager->set($key, $newValue);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_fires_configuration_changed_event_on_update(): void
    {
        // Arrange
        $key = 'rate_limit.max_attempts';
        $oldValue = 10;
        $newValue = 20;

        $this->validator->shouldReceive('validateValue')
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->cache->shouldReceive('forget')->andReturn(true);

        // Expect ConfigurationChanged event to be dispatched
        $this->events->shouldReceive('dispatch')
            ->with(Mockery::on(function ($event) use ($key, $newValue) {
                return $event instanceof ConfigurationChanged &&
                       $event->key === $key &&
                       $event->newValue->value === $newValue &&
                       $event->changeType === 'created';
            }))
            ->once();

        // Act
        $this->configManager->set($key, $newValue);

        // Assert - Event expectation is verified by Mockery
        $this->assertTrue(true);
    }

    #[Test]
    public function it_invalidates_cache_on_runtime_update(): void
    {
        // Arrange
        $key = 'features.ai_analysis';
        $newValue = true;

        $this->validator->shouldReceive('validateValue')
            ->andReturn(['valid' => true, 'errors' => []]);

        // Expect cache to be invalidated
        $this->cache->shouldReceive('forget')
            ->with("form_security_config_{$key}")
            ->once()
            ->andReturn(true);

        $this->events->shouldReceive('dispatch');

        // Act
        $this->configManager->set($key, $newValue);

        // Assert - Cache invalidation expectation is verified by Mockery
        $this->assertTrue(true);
    }

    #[Test]
    public function it_maintains_runtime_configuration_across_requests(): void
    {
        // Arrange
        $key = 'debug.enabled';
        $value = true;

        $this->validator->shouldReceive('validateValue')
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->cache->shouldReceive('forget')->andReturn(true);
        $this->events->shouldReceive('dispatch');

        // Act - Set runtime configuration
        $this->configManager->set($key, $value);

        // Simulate subsequent request - should get runtime value without hitting cache/config
        $result = $this->configManager->get($key);

        // Assert
        $this->assertEquals($value, $result);
    }

    #[Test]
    public function it_prioritizes_runtime_configuration_over_file_config(): void
    {
        // Arrange
        $key = 'spam_threshold';
        $fileValue = 0.7;
        $runtimeValue = 0.9;

        // Set up file config
        $this->config->shouldReceive('get')
            ->with("form-security.{$key}")
            ->andReturn($fileValue);

        $this->cache->shouldReceive('get')
            ->with("form_security_config_{$key}")
            ->andReturn(null);

        // First get should return file value
        $initialValue = $this->configManager->get($key);
        $this->assertEquals($fileValue, $initialValue);

        // Set runtime value
        $this->validator->shouldReceive('validateValue')
            ->andReturn(['valid' => true, 'errors' => []]);
        $this->cache->shouldReceive('forget')->andReturn(true);
        $this->events->shouldReceive('dispatch');

        $this->configManager->set($key, $runtimeValue);

        // Act - Get value again
        $updatedValue = $this->configManager->get($key);

        // Assert - Should return runtime value, not file value
        $this->assertEquals($runtimeValue, $updatedValue);
    }

    #[Test]
    public function it_tracks_configuration_change_history(): void
    {
        // Arrange
        $key = 'test_key';
        $value1 = 'value1';
        $value2 = 'value2';

        $this->validator->shouldReceive('validateValue')
            ->andReturn(['valid' => true, 'errors' => []]);
        $this->cache->shouldReceive('forget')->andReturn(true);
        $this->events->shouldReceive('dispatch');

        // Act - Make multiple changes
        $this->configManager->set($key, $value1);
        $this->configManager->set($key, $value2);

        // Get change history
        $history = $this->configManager->getChangeHistory($key);

        // Assert
        $this->assertCount(2, $history);
        $this->assertEquals($value2, $history[0]['new_value']); // Most recent first
        $this->assertEquals($value1, $history[1]['new_value']);
    }

    #[Test]
    public function it_handles_concurrent_runtime_updates(): void
    {
        // Arrange
        $key = 'concurrent_test';
        $values = ['value1', 'value2', 'value3'];

        $this->validator->shouldReceive('validateValue')
            ->andReturn(['valid' => true, 'errors' => []]);
        $this->cache->shouldReceive('forget')->andReturn(true);
        $this->events->shouldReceive('dispatch');

        // Act - Simulate rapid concurrent updates
        foreach ($values as $value) {
            $result = $this->configManager->set($key, $value);
            $this->assertTrue($result);
        }

        // Get final value
        $finalValue = $this->configManager->get($key);

        // Assert - Should have the last value set
        $this->assertEquals('value3', $finalValue);
    }

    #[Test]
    public function it_validates_runtime_updates(): void
    {
        // Arrange
        $key = 'spam_threshold';
        $invalidValue = 1.5; // Invalid: should be between 0 and 1

        $this->validator->shouldReceive('validateValue')
            ->with($key, $invalidValue)
            ->andReturn(['valid' => false, 'errors' => ['Value must be between 0.0 and 1.0']]);

        // Act
        $result = $this->configManager->set($key, $invalidValue);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_refreshes_runtime_configuration(): void
    {
        // Arrange
        $key = 'test_refresh';
        $value = 'runtime_value';

        $this->validator->shouldReceive('validateValue')
            ->andReturn(['valid' => true, 'errors' => []]);
        $this->cache->shouldReceive('forget')->andReturn(true);
        $this->events->shouldReceive('dispatch');

        // Set runtime value
        $this->configManager->set($key, $value);
        $this->assertEquals($value, $this->configManager->get($key));

        // Mock refresh behavior
        $this->config->shouldReceive('set')
            ->with('form-security', Mockery::type('array'))
            ->once();
        $this->config->shouldReceive('get')
            ->with('form-security', [])
            ->andReturn([]);

        // Act - Refresh configuration
        $result = $this->configManager->refresh();

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_persists_runtime_configuration_when_requested(): void
    {
        // Arrange
        $key = 'persistent_setting';
        $value = 'persistent_value';

        $this->validator->shouldReceive('validateValue')
            ->andReturn(['valid' => true, 'errors' => []]);
        $this->cache->shouldReceive('forget')->andReturn(true);
        $this->events->shouldReceive('dispatch');

        // Act - Set value with persistence
        $result = $this->configManager->setValue($key, $value, true, true); // persist=true

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_runtime_update_failures_gracefully(): void
    {
        // Arrange
        $key = 'failing_key';
        $value = 'test_value';

        // Simulate validation failure
        $this->validator->shouldReceive('validateValue')
            ->andThrow(new \Exception('Validation service unavailable'));

        // Act
        $result = $this->configManager->set($key, $value);

        // Assert - Should handle exception gracefully
        $this->assertFalse($result);
    }

    #[Test]
    public function it_provides_performance_metrics_for_runtime_operations(): void
    {
        // Arrange
        $key = 'performance_test';
        $value = 'test_value';

        $this->validator->shouldReceive('validateValue')
            ->andReturn(['valid' => true, 'errors' => []]);
        $this->cache->shouldReceive('forget')->andReturn(true);
        $this->events->shouldReceive('dispatch');

        // Act - Perform runtime operations
        $this->configManager->set($key, $value);
        $this->configManager->get($key);

        $metrics = $this->configManager->getPerformanceMetrics();

        // Assert
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('cache_hits', $metrics);
        $this->assertArrayHasKey('cache_misses', $metrics);
        $this->assertArrayHasKey('validation_calls', $metrics);
        $this->assertGreaterThan(0, $metrics['validation_calls']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
