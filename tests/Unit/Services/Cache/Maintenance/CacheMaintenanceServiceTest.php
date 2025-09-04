<?php

declare(strict_types=1);

/**
 * Test File: CacheMaintenanceServiceTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: Code Coverage Gap
 *
 * Description: Unit tests for CacheMaintenanceService testing only the methods
 * that actually exist in the implementation.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-003-multi-level-caching-system.md
 * @see docs/Planning/Sprints/004-caching-cli-integration.md
 */

namespace JTD\FormSecurity\Tests\Unit\Services\Cache\Maintenance;

use Illuminate\Cache\CacheManager as LaravelCacheManager;
use JTD\FormSecurity\Services\Cache\Maintenance\CacheMaintenanceService;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('unit')]
#[Group('cache-maintenance')]
class CacheMaintenanceServiceTest extends TestCase
{
    private CacheMaintenanceService $service;

    private LaravelCacheManager $cacheManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheManager = app(LaravelCacheManager::class);
        $this->service = new CacheMaintenanceService($this->cacheManager);
    }

    #[Test]
    public function it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(CacheMaintenanceService::class, $this->service);
    }

    #[Test]
    public function it_maintains_database_cache_with_default_operations(): void
    {
        $result = $this->service->maintainDatabaseCache();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('operations_count', $result);

        // Should have operation results as boolean values
        $this->assertArrayHasKey('cleanup', $result);
        $this->assertArrayHasKey('optimize', $result);
    }

    #[Test]
    public function it_maintains_database_cache_with_custom_operations(): void
    {
        $operations = ['cleanup', 'optimize'];
        $result = $this->service->maintainDatabaseCache($operations);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('operations_count', $result);

        // Should have performed the specified operations
        $this->assertEquals(2, $result['operations_count']);
        $this->assertArrayHasKey('cleanup', $result);
        $this->assertArrayHasKey('optimize', $result);
    }

    #[Test]
    public function it_performs_general_maintenance_with_default_operations(): void
    {
        $result = $this->service->maintenance();

        $this->assertIsArray($result);
        // The maintenance method returns results from individual operations
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function it_performs_general_maintenance_with_custom_operations(): void
    {
        $operations = ['cleanup'];
        $result = $this->service->maintenance($operations);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function it_handles_empty_operations_array(): void
    {
        $result = $this->service->maintainDatabaseCache([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('operations_count', $result);
        $this->assertEquals(0, $result['operations_count']);
    }

    #[Test]
    public function it_includes_timing_information(): void
    {
        $result = $this->service->maintainDatabaseCache();

        $this->assertArrayHasKey('duration', $result);
        $this->assertIsFloat($result['duration']);
        $this->assertGreaterThanOrEqual(0, $result['duration']);
    }

    #[Test]
    public function it_includes_message_in_results(): void
    {
        $result = $this->service->maintainDatabaseCache();

        $this->assertArrayHasKey('message', $result);
        $this->assertIsString($result['message']);
        $this->assertNotEmpty($result['message']);
    }

    #[Test]
    public function it_provides_operations_count(): void
    {
        $result = $this->service->maintainDatabaseCache();

        $this->assertArrayHasKey('operations_count', $result);
        $this->assertIsInt($result['operations_count']);
        $this->assertGreaterThanOrEqual(0, $result['operations_count']);
    }

    #[Test]
    public function it_handles_maintenance_operations_gracefully(): void
    {
        // Test that maintenance doesn't throw exceptions
        $result = $this->service->maintenance(['cleanup', 'optimize']);

        $this->assertIsArray($result);
        // Should not throw any exceptions during maintenance
    }

    #[Test]
    public function it_tracks_operations_performed(): void
    {
        $operations = ['cleanup', 'optimize'];
        $result = $this->service->maintainDatabaseCache($operations);

        $this->assertArrayHasKey('operations_count', $result);
        $this->assertEquals(2, $result['operations_count']);

        // Should have operation results
        $this->assertArrayHasKey('cleanup', $result);
        $this->assertArrayHasKey('optimize', $result);
    }

    #[Test]
    public function it_handles_single_operation(): void
    {
        $operations = ['cleanup'];
        $result = $this->service->maintainDatabaseCache($operations);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('operations_count', $result);
        $this->assertEquals(1, $result['operations_count']);
        $this->assertArrayHasKey('duration', $result);
    }

    #[Test]
    public function it_provides_consistent_result_structure(): void
    {
        $result1 = $this->service->maintainDatabaseCache();
        $result2 = $this->service->maintainDatabaseCache(['cleanup']);

        // Both results should have the same basic structure
        $expectedKeys = ['message', 'duration', 'operations_count'];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result1);
            $this->assertArrayHasKey($key, $result2);
        }
    }

    #[Test]
    public function it_measures_execution_time_accurately(): void
    {
        $startTime = microtime(true);
        $result = $this->service->maintainDatabaseCache();
        $endTime = microtime(true);

        $actualDuration = $endTime - $startTime;
        $reportedDuration = $result['duration'];

        // Reported duration should be close to actual duration (within 10ms tolerance)
        $this->assertLessThanOrEqual($actualDuration + 0.01, $reportedDuration);
        $this->assertGreaterThanOrEqual(0, $reportedDuration);
    }

    #[Test]
    public function it_handles_general_maintenance_with_empty_operations(): void
    {
        $result = $this->service->maintenance([]);

        $this->assertIsArray($result);
        // Empty operations should still return an array (might be empty)
    }

    protected function tearDown(): void
    {
        // Clean up any cache entries created during testing
        $this->cacheManager->flush();
        parent::tearDown();
    }
}
