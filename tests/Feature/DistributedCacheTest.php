<?php

declare(strict_types=1);

/**
 * Test File: DistributedCacheTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1023-caching-system-tests
 *
 * Description: Tests for distributed caching functionality including
 * multi-server cache synchronization, consistency, and failover scenarios.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-003-multi-level-caching-system.md
 * @see docs/Planning/Sprints/004-caching-cli-integration.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1023-caching-system-tests.md
 */

namespace JTD\FormSecurity\Tests\Feature;

use Illuminate\Cache\CacheManager as LaravelCacheManager;
use JTD\FormSecurity\Enums\CacheLevel;
use JTD\FormSecurity\Services\CacheManager;
use JTD\FormSecurity\Tests\TestCase;
use JTD\FormSecurity\ValueObjects\CacheKey;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('ticket-1023')]
#[Group('caching')]
#[Group('distributed')]
#[Group('feature')]
class DistributedCacheTest extends TestCase
{
    private CacheManager $cacheManager;

    private LaravelCacheManager $laravelCacheManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laravelCacheManager = app(LaravelCacheManager::class);

        // Create all required services for CacheManager per SPEC-003
        $operations = new \JTD\FormSecurity\Services\Cache\Operations\CacheOperationService($this->laravelCacheManager);
        $warming = new \JTD\FormSecurity\Services\Cache\Warming\CacheWarmingService($this->laravelCacheManager, $operations);
        $maintenance = new \JTD\FormSecurity\Services\Cache\Maintenance\CacheMaintenanceService($this->laravelCacheManager);
        $security = new \JTD\FormSecurity\Services\Cache\Security\CacheSecurityService($this->laravelCacheManager);
        $statistics = new \JTD\FormSecurity\Services\Cache\Statistics\CacheStatisticsService($this->laravelCacheManager);
        $validation = new \JTD\FormSecurity\Services\Cache\Validation\CacheValidationService($this->laravelCacheManager);

        // Create the cache manager with all required services
        $this->cacheManager = new CacheManager(
            $operations,
            $warming,
            $maintenance,
            $security,
            $statistics,
            $validation
        );
    }

    #[Test]
    public function it_supports_distributed_cache_configuration(): void
    {
        // Verify that memory and database levels support distribution
        $this->assertTrue(CacheLevel::MEMORY->supportsDistribution());
        $this->assertTrue(CacheLevel::DATABASE->supportsDistribution());

        // Request level doesn't support distribution (single request scope)
        $this->assertFalse(CacheLevel::REQUEST->supportsDistribution());
    }

    #[Test]
    public function it_can_store_and_retrieve_across_distributed_levels(): void
    {
        $key = CacheKey::make('distributed_test_key', 'distributed');
        $value = 'distributed_test_value';

        // Store in distributed levels (memory and database)
        $distributedLevels = [CacheLevel::MEMORY, CacheLevel::DATABASE];
        $result = $this->cacheManager->put($key, $value, 3600, $distributedLevels);

        $this->assertTrue($result);

        // Verify it's stored in both distributed levels
        $this->assertEquals($value, $this->cacheManager->getFromMemory($key));
        $this->assertEquals($value, $this->cacheManager->getFromDatabase($key));

        // Should not be in request level (not included in distributed levels)
        $this->assertNull($this->cacheManager->getFromRequest($key));
    }

    #[Test]
    public function it_handles_distributed_cache_consistency(): void
    {
        $key = CacheKey::make('consistency_test', 'distributed');
        $initialValue = 'initial_value';
        $updatedValue = 'updated_value';

        // Store initial value in distributed levels
        $distributedLevels = [CacheLevel::MEMORY, CacheLevel::DATABASE];
        $this->cacheManager->put($key, $initialValue, 3600, $distributedLevels);

        // Verify consistency across levels
        $this->assertEquals($initialValue, $this->cacheManager->getFromMemory($key));
        $this->assertEquals($initialValue, $this->cacheManager->getFromDatabase($key));

        // Update value in distributed levels
        $this->cacheManager->put($key, $updatedValue, 3600, $distributedLevels);

        // Verify consistency is maintained
        $this->assertEquals($updatedValue, $this->cacheManager->getFromMemory($key));
        $this->assertEquals($updatedValue, $this->cacheManager->getFromDatabase($key));
    }

    #[Test]
    public function it_handles_distributed_cache_invalidation(): void
    {
        $keys = ['dist_inv_1', 'dist_inv_2', 'dist_inv_3'];
        $distributedLevels = [CacheLevel::MEMORY, CacheLevel::DATABASE];

        // Store values in distributed levels
        foreach ($keys as $key) {
            $this->cacheManager->put($key, "value_{$key}", 3600, $distributedLevels);
        }

        // Verify all are stored
        foreach ($keys as $key) {
            $this->assertEquals("value_{$key}", $this->cacheManager->getFromMemory($key));
            $this->assertEquals("value_{$key}", $this->cacheManager->getFromDatabase($key));
        }

        // Invalidate from distributed levels
        foreach ($keys as $key) {
            $result = $this->cacheManager->forget($key, $distributedLevels);
            $this->assertTrue($result);
        }

        // Verify all are gone from distributed levels
        foreach ($keys as $key) {
            $this->assertNull($this->cacheManager->getFromMemory($key));
            $this->assertNull($this->cacheManager->getFromDatabase($key));
        }
    }

    #[Test]
    public function it_handles_distributed_cache_tagging(): void
    {
        $key1 = CacheKey::make('tagged_dist_1', 'distributed', ['user', 'profile']);
        $key2 = CacheKey::make('tagged_dist_2', 'distributed', ['user', 'settings']);
        $key3 = CacheKey::make('tagged_dist_3', 'distributed', ['admin']);

        $distributedLevels = [CacheLevel::MEMORY, CacheLevel::DATABASE];

        // Store tagged values in distributed levels
        $this->cacheManager->put($key1, 'value1', 3600, $distributedLevels);
        $this->cacheManager->put($key2, 'value2', 3600, $distributedLevels);
        $this->cacheManager->put($key3, 'value3', 3600, $distributedLevels);

        // Verify tagging support in distributed levels
        $this->assertTrue(CacheLevel::MEMORY->supportsTagging());
        $this->assertTrue(CacheLevel::DATABASE->supportsTagging());

        // Test tag-based operations (if supported by driver)
        $result = $this->cacheManager->invalidateByTags(['user'], $distributedLevels);
        $this->assertTrue($result);
    }

    #[Test]
    public function it_simulates_multi_server_cache_scenarios(): void
    {
        // Simulate multiple servers by using different key prefixes
        $servers = ['server1', 'server2', 'server3'];
        $sharedData = [];

        foreach ($servers as $server) {
            $key = CacheKey::make("shared_data_{$server}", 'distributed');
            $value = "data_from_{$server}";
            $sharedData[$server] = ['key' => $key, 'value' => $value];

            // Each "server" stores its data in distributed cache
            $distributedLevels = [CacheLevel::MEMORY, CacheLevel::DATABASE];
            $result = $this->cacheManager->put($key, $value, 3600, $distributedLevels);
            $this->assertTrue($result);
        }

        // Simulate cross-server data access
        foreach ($servers as $accessingServer) {
            foreach ($servers as $dataServer) {
                $key = $sharedData[$dataServer]['key'];
                $expectedValue = $sharedData[$dataServer]['value'];

                // Any server should be able to access any other server's cached data
                $retrievedValue = $this->cacheManager->get($key);
                $this->assertEquals($expectedValue, $retrievedValue);
            }
        }
    }

    #[Test]
    public function it_handles_distributed_cache_failover_scenarios(): void
    {
        $key = CacheKey::make('failover_test', 'distributed');
        $value = 'failover_value';

        // Store in all levels initially
        $this->cacheManager->put($key, $value, 3600);

        // Simulate memory cache failure by removing from memory and request levels
        // This forces retrieval from database level to test backfill
        $this->cacheManager->forgetFromMemory($key);
        $this->cacheManager->forgetFromRequest($key);

        // Should still be retrievable from database level (failover)
        $retrievedValue = $this->cacheManager->get($key);
        $this->assertEquals($value, $retrievedValue);

        // Verify it was backfilled to memory level
        $memoryValue = $this->cacheManager->getFromMemory($key);
        $this->assertEquals($value, $memoryValue, 'Value should be backfilled to memory level after database retrieval');
    }

    #[Test]
    public function it_handles_distributed_cache_synchronization(): void
    {
        $key = CacheKey::make('sync_test', 'distributed');
        $distributedLevels = [CacheLevel::MEMORY, CacheLevel::DATABASE];

        // Store initial value
        $initialValue = 'initial_sync_value';
        $this->cacheManager->put($key, $initialValue, 3600, $distributedLevels);

        // Simulate out-of-sync scenario by updating only one level
        $this->cacheManager->putInMemory($key, 'memory_only_value');

        // Verify levels are out of sync
        $this->assertEquals('memory_only_value', $this->cacheManager->getFromMemory($key));
        $this->assertEquals($initialValue, $this->cacheManager->getFromDatabase($key));

        // Re-synchronize by updating all distributed levels
        $syncValue = 'synchronized_value';
        $this->cacheManager->put($key, $syncValue, 3600, $distributedLevels);

        // Verify synchronization
        $this->assertEquals($syncValue, $this->cacheManager->getFromMemory($key));
        $this->assertEquals($syncValue, $this->cacheManager->getFromDatabase($key));
    }

    #[Test]
    public function it_handles_distributed_cache_load_balancing(): void
    {
        $keyCount = 100;
        $distributedLevels = [CacheLevel::MEMORY, CacheLevel::DATABASE];

        // Simulate load balancing by distributing keys across levels
        for ($i = 0; $i < $keyCount; $i++) {
            $key = CacheKey::make("load_balance_test_{$i}", 'distributed');
            $value = "load_balance_value_{$i}";

            // Store in distributed levels
            $result = $this->cacheManager->put($key, $value, 3600, $distributedLevels);
            $this->assertTrue($result);
        }

        // Verify all keys are accessible
        for ($i = 0; $i < $keyCount; $i++) {
            $key = CacheKey::make("load_balance_test_{$i}", 'distributed');
            $expectedValue = "load_balance_value_{$i}";

            $retrievedValue = $this->cacheManager->get($key);
            $this->assertEquals($expectedValue, $retrievedValue);
        }

        // Verify distribution across levels
        $memoryHits = 0;
        $databaseHits = 0;

        for ($i = 0; $i < $keyCount; $i++) {
            $key = CacheKey::make("load_balance_test_{$i}", 'distributed');

            if ($this->cacheManager->getFromMemory($key) !== null) {
                $memoryHits++;
            }
            if ($this->cacheManager->getFromDatabase($key) !== null) {
                $databaseHits++;
            }
        }

        // Both levels should have all keys (full replication in this test)
        $this->assertEquals($keyCount, $memoryHits);
        $this->assertEquals($keyCount, $databaseHits);
    }

    #[Test]
    public function it_measures_distributed_cache_performance(): void
    {
        $iterations = 100;
        $distributedLevels = [CacheLevel::MEMORY, CacheLevel::DATABASE];
        $responseTimes = [];

        // Benchmark distributed cache operations
        for ($i = 0; $i < $iterations; $i++) {
            $key = CacheKey::make("perf_dist_test_{$i}", 'distributed');
            $value = "perf_dist_value_{$i}";

            $startTime = microtime(true);

            // Store in distributed levels
            $this->cacheManager->put($key, $value, 3600, $distributedLevels);

            // Retrieve from distributed cache
            $retrievedValue = $this->cacheManager->get($key);

            $endTime = microtime(true);

            $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            $responseTimes[] = $responseTime;

            $this->assertEquals($value, $retrievedValue);
        }

        $averageResponseTime = array_sum($responseTimes) / count($responseTimes);
        $maxResponseTime = max($responseTimes);

        // Distributed cache should still be reasonably fast
        $this->assertLessThan(100.0, $averageResponseTime, 'Distributed cache average response time should be < 100ms');
        $this->assertLessThan(500.0, $maxResponseTime, 'Distributed cache max response time should be < 500ms');

        // Performance metrics are captured in assertions above
        // Average: {$averageResponseTime}ms, Max: {$maxResponseTime}ms, Operations: {$iterations}
    }
}
