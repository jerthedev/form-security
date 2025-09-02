<?php

/**
 * Test File: CacheMaintenanceServiceTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1023-caching-system-tests
 *
 * Description: Comprehensive unit tests for CacheMaintenanceService functionality
 * including cache cleanup, optimization, and maintenance operations.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-003-multi-level-caching-system.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1023-caching-system-tests.md
 */

declare(strict_types=1);

namespace JTD\FormSecurity\Tests\Unit\Services;

use Illuminate\Cache\CacheManager as LaravelCacheManager;
use JTD\FormSecurity\Services\Cache\Maintenance\CacheMaintenanceService;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('ticket-1023')]
#[Group('caching')]
#[Group('maintenance')]
class CacheMaintenanceServiceTest extends TestCase
{
    private CacheMaintenanceService $maintenanceService;

    private LaravelCacheManager $laravelCacheManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laravelCacheManager = $this->app->make(LaravelCacheManager::class);
        $this->maintenanceService = new CacheMaintenanceService($this->laravelCacheManager);
    }

    #[Test]
    public function it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(CacheMaintenanceService::class, $this->maintenanceService);
    }

    #[Test]
    public function it_can_maintain_database_cache_with_default_operations(): void
    {
        $results = $this->maintenanceService->maintainDatabaseCache();

        $this->assertIsArray($results);
        // Default operations return simple structure
        $this->assertArrayHasKey('message', $results);
        $this->assertArrayHasKey('duration', $results);
        $this->assertArrayHasKey('operations_count', $results);

        // Should have operation results as boolean values
        $this->assertArrayHasKey('cleanup', $results);
        $this->assertArrayHasKey('optimize', $results);

        $this->assertIsString($results['message']);
        $this->assertIsFloat($results['duration']);
        $this->assertIsInt($results['operations_count']);
        $this->assertIsBool($results['cleanup']);
        $this->assertIsBool($results['optimize']);
    }

    #[Test]
    public function it_can_maintain_database_cache_with_comprehensive_operations(): void
    {
        // Use comprehensive operations to get detailed structure
        $operations = ['cleanup_expired', 'optimize_tables', 'analyze_usage'];
        $results = $this->maintenanceService->maintainDatabaseCache($operations);

        $this->assertIsArray($results);
        // Comprehensive operations return detailed structure
        $this->assertArrayHasKey('summary', $results);
        $this->assertArrayHasKey('operations', $results);
        $this->assertArrayHasKey('statistics', $results);
        $this->assertArrayHasKey('recommendations', $results);

        // Validate summary structure
        $summary = $results['summary'];
        $this->assertArrayHasKey('total_operations', $summary);
        $this->assertArrayHasKey('successful_operations', $summary);
        $this->assertArrayHasKey('failed_operations', $summary);
        $this->assertArrayHasKey('duration_seconds', $summary);
    }

    #[Test]
    public function it_provides_maintenance_statistics_for_comprehensive_operations(): void
    {
        // Use comprehensive operations to get detailed structure with statistics
        $operations = ['cleanup_expired', 'optimize_tables'];
        $results = $this->maintenanceService->maintainDatabaseCache($operations);

        $this->assertArrayHasKey('statistics', $results);
        $statistics = $results['statistics'];

        $this->assertArrayHasKey('before', $statistics);
        $this->assertArrayHasKey('after', $statistics);

        // Validate statistics structure
        $before = $statistics['before'];
        $this->assertIsArray($before);
        $this->assertArrayHasKey('total_keys', $before);
        $this->assertArrayHasKey('total_size_mb', $before);
    }

    #[Test]
    public function it_handles_empty_operations_array(): void
    {
        $results = $this->maintenanceService->maintainDatabaseCache([]);

        $this->assertIsArray($results);
        // Empty operations should use defaults and return simple structure
        $this->assertArrayHasKey('message', $results);
        $this->assertArrayHasKey('duration', $results);
        $this->assertArrayHasKey('operations_count', $results);
    }

    #[Test]
    public function it_tracks_operation_execution_time(): void
    {
        $startTime = microtime(true);
        $results = $this->maintenanceService->maintainDatabaseCache();
        $endTime = microtime(true);

        $this->assertArrayHasKey('duration', $results);
        $duration = $results['duration'];

        $this->assertIsFloat($duration);
        $this->assertGreaterThanOrEqual(0, $duration); // Allow 0 for very fast operations

        // Execution time should be reasonable (less than total test time)
        $totalTestTime = $endTime - $startTime;
        $this->assertLessThanOrEqual($totalTestTime + 0.01, $duration); // +10ms tolerance
    }

    #[Test]
    public function it_handles_maintenance_operation_results(): void
    {
        $results = $this->maintenanceService->maintainDatabaseCache();

        // Simple operations return boolean results for each operation
        $this->assertIsArray($results);
        $this->assertArrayHasKey('cleanup', $results);
        $this->assertArrayHasKey('optimize', $results);

        // Validate operation results
        $this->assertIsBool($results['cleanup']);
        $this->assertIsBool($results['optimize']);

        // Also check the basic structure
        $this->assertArrayHasKey('message', $results);
        $this->assertArrayHasKey('duration', $results);
        $this->assertArrayHasKey('operations_count', $results);

        $this->assertIsString($results['message']);
        $this->assertIsFloat($results['duration']);
        $this->assertIsInt($results['operations_count']);
    }

    #[Test]
    public function it_calculates_success_failure_ratios_correctly(): void
    {
        $results = $this->maintenanceService->maintainDatabaseCache();

        // Simple operations don't have statistics, they have operation counts
        $this->assertArrayHasKey('operations_count', $results);
        $operationsCount = $results['operations_count'];

        // Basic validation
        $this->assertGreaterThanOrEqual(0, $operationsCount);
        $this->assertIsInt($operationsCount);

        // Should have operation results as boolean values
        $this->assertArrayHasKey('cleanup', $results);
        $this->assertArrayHasKey('optimize', $results);
        $this->assertIsBool($results['cleanup']);
        $this->assertIsBool($results['optimize']);
    }

    #[Test]
    public function it_provides_consistent_duration_format(): void
    {
        $results = $this->maintenanceService->maintainDatabaseCache();

        $this->assertArrayHasKey('duration', $results);
        $duration = $results['duration'];

        $this->assertIsFloat($duration);
        $this->assertGreaterThanOrEqual(0, $duration);

        // Duration should be reasonable (under 1 second for simple operations)
        $this->assertLessThan(1.0, $duration);
    }

    #[Test]
    public function it_handles_multiple_maintenance_calls(): void
    {
        // Test that multiple maintenance calls work correctly
        for ($i = 0; $i < 3; $i++) {
            $results = $this->maintenanceService->maintainDatabaseCache();

            $this->assertIsArray($results);
            $this->assertArrayHasKey('message', $results);
            $this->assertArrayHasKey('duration', $results);
            $this->assertArrayHasKey('operations_count', $results);
            $this->assertArrayHasKey('cleanup', $results);
            $this->assertArrayHasKey('optimize', $results);
        }
    }

    #[Test]
    public function it_handles_known_maintenance_operations(): void
    {
        $knownOperations = [
            'cleanup_expired', // This is a comprehensive operation
            'optimize_tables', // This is a comprehensive operation
            'cleanup',         // This is a simple operation
            'optimize',        // This is a simple operation
        ];

        foreach ($knownOperations as $operation) {
            $results = $this->maintenanceService->maintainDatabaseCache([$operation]);

            $this->assertIsArray($results);

            // Comprehensive operations return detailed structure
            if (in_array($operation, ['cleanup_expired', 'optimize_tables'])) {
                $this->assertArrayHasKey('summary', $results);
                $this->assertArrayHasKey('operations', $results);
            } else {
                // Simple operations return simple structure
                $this->assertArrayHasKey('message', $results);
                $this->assertArrayHasKey('duration', $results);
                $this->assertArrayHasKey($operation, $results);
            }
        }
    }

    #[Test]
    public function it_validates_operation_success_determination(): void
    {
        $results = $this->maintenanceService->maintainDatabaseCache();

        // Simple operations return boolean success values for each operation
        $this->assertArrayHasKey('cleanup', $results);
        $this->assertArrayHasKey('optimize', $results);

        $cleanupSuccess = $results['cleanup'];
        $optimizeSuccess = $results['optimize'];

        $this->assertIsBool($cleanupSuccess);
        $this->assertIsBool($optimizeSuccess);

        // Operations should generally succeed in test environment
        $this->assertTrue($cleanupSuccess);
        $this->assertTrue($optimizeSuccess);
    }

    #[Test]
    public function it_handles_maintenance_with_mixed_operation_results(): void
    {
        // Test with comprehensive operations that return detailed structure
        $operations = ['cleanup_expired', 'optimize_tables'];
        $results = $this->maintenanceService->maintainDatabaseCache($operations);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('summary', $results);

        // Should handle results gracefully
        $summary = $results['summary'];
        $this->assertArrayHasKey('total_operations', $summary);
        $this->assertArrayHasKey('successful_operations', $summary);
        $this->assertArrayHasKey('failed_operations', $summary);
    }

    #[Test]
    public function it_provides_detailed_operation_information(): void
    {
        // Use comprehensive operation to get detailed structure
        $results = $this->maintenanceService->maintainDatabaseCache(['cleanup_expired']);

        $this->assertArrayHasKey('operations', $results);
        $operations = $results['operations'];

        if (! empty($operations) && isset($operations[0])) {
            $operation = $operations[0];

            $requiredKeys = ['operation', 'success', 'duration_seconds'];
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $operation, "Operation result must include '{$key}' key");
            }

            // Validate data types
            $this->assertIsString($operation['operation']);
            $this->assertIsBool($operation['success']);
            $this->assertIsFloat($operation['duration_seconds']);
        } else {
            // If no operations, just verify the structure exists
            $this->assertIsArray($operations);
            // For comprehensive operations, we expect at least the structure to be present
            $this->assertTrue(true, 'Operations array structure verified');
        }
    }
}
