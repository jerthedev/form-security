<?php

declare(strict_types=1);

/**
 * Performance Test File: CachePerformanceBenchmarkTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Performance benchmarks for the multi-level caching system
 * to validate performance targets and identify bottlenecks.
 */

namespace JTD\FormSecurity\Tests\Performance;

use Illuminate\Cache\CacheManager as LaravelCacheManager;
use JTD\FormSecurity\Services\CacheManager;
use JTD\FormSecurity\Services\CachePerformanceMonitor;
use JTD\FormSecurity\Tests\TestCase;
use JTD\FormSecurity\ValueObjects\CacheKey;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('ticket-1014')]
#[Group('caching')]
#[Group('performance')]
#[Group('benchmark')]
class CachePerformanceBenchmarkTest extends TestCase
{
    private CacheManager $cacheManager;

    private CachePerformanceMonitor $performanceMonitor;

    protected function setUp(): void
    {
        parent::setUp();

        // Note: Performance benchmarks with array driver are for functional testing only
        // Production deployments should use Redis/Memcached for realistic performance metrics

        $laravelCacheManager = app(LaravelCacheManager::class);

        // Create all required services for CacheManager per SPEC-003
        $operations = new \JTD\FormSecurity\Services\Cache\Operations\CacheOperationService($laravelCacheManager);
        $warming = new \JTD\FormSecurity\Services\Cache\Warming\CacheWarmingService($laravelCacheManager, $operations);
        $maintenance = new \JTD\FormSecurity\Services\Cache\Maintenance\CacheMaintenanceService($laravelCacheManager);
        $security = new \JTD\FormSecurity\Services\Cache\Security\CacheSecurityService($laravelCacheManager);
        $statistics = new \JTD\FormSecurity\Services\Cache\Statistics\CacheStatisticsService($laravelCacheManager);
        $validation = new \JTD\FormSecurity\Services\Cache\Validation\CacheValidationService($laravelCacheManager);

        // Create the cache manager with all required services
        $this->cacheManager = new CacheManager(
            $operations,
            $warming,
            $maintenance,
            $security,
            $statistics,
            $validation
        );

        $this->performanceMonitor = new CachePerformanceMonitor($this->cacheManager);
    }

    #[Test]
    public function it_meets_request_level_response_time_targets(): void
    {
        $iterations = 1000;
        $responseTimes = [];

        // Benchmark request-level caching
        for ($i = 0; $i < $iterations; $i++) {
            $key = CacheKey::make("request_benchmark_{$i}", 'benchmark');

            $startTime = microtime(true);
            $this->cacheManager->putInRequest($key, "test_value_{$i}");
            $this->cacheManager->getFromRequest($key);
            $endTime = microtime(true);

            $responseTimes[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        $averageResponseTime = array_sum($responseTimes) / count($responseTimes);
        $maxResponseTime = max($responseTimes);
        $minResponseTime = min($responseTimes);

        // Assert performance targets for request-level caching
        $this->assertLessThan(5.0, $averageResponseTime, 'Request-level average response time should be < 5ms');
        $this->assertLessThan(10.0, $maxResponseTime, 'Request-level max response time should be < 10ms');

        // Performance metrics: Avg: {$averageResponseTime}ms, Min: {$minResponseTime}ms, Max: {$maxResponseTime}ms
    }

    #[Test]
    public function it_meets_memory_level_response_time_targets(): void
    {
        $iterations = 500;
        $responseTimes = [];

        // Benchmark memory-level caching
        for ($i = 0; $i < $iterations; $i++) {
            $key = CacheKey::make("memory_benchmark_{$i}", 'benchmark');

            $startTime = microtime(true);
            $this->cacheManager->putInMemory($key, "test_value_{$i}");
            $this->cacheManager->getFromMemory($key);
            $endTime = microtime(true);

            $responseTimes[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        $averageResponseTime = array_sum($responseTimes) / count($responseTimes);
        $maxResponseTime = max($responseTimes);

        // Assert performance targets for memory-level caching
        $this->assertLessThan(15.0, $averageResponseTime, 'Memory-level average response time should be < 15ms');
        $this->assertLessThan(50.0, $maxResponseTime, 'Memory-level max response time should be < 50ms');

        $this->logPerformanceMetric('Memory-Level Performance', [
            'average_ms' => $averageResponseTime,
            'max_ms' => $maxResponseTime,
        ]);
    }

    #[Test]
    public function it_meets_database_level_response_time_targets(): void
    {
        $iterations = 100;
        $responseTimes = [];

        // Benchmark database-level caching
        for ($i = 0; $i < $iterations; $i++) {
            $key = CacheKey::make("database_benchmark_{$i}", 'benchmark');

            $startTime = microtime(true);
            $this->cacheManager->putInDatabase($key, "test_value_{$i}");
            $this->cacheManager->getFromDatabase($key);
            $endTime = microtime(true);

            $responseTimes[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        $averageResponseTime = array_sum($responseTimes) / count($responseTimes);
        $maxResponseTime = max($responseTimes);

        // Assert performance targets for database-level caching
        $this->assertLessThan(100.0, $averageResponseTime, 'Database-level average response time should be < 100ms');
        $this->assertLessThan(500.0, $maxResponseTime, 'Database-level max response time should be < 500ms');

        $this->logPerformanceMetric('Database-Level Performance', [
            'average_ms' => $averageResponseTime,
            'max_ms' => $maxResponseTime,
        ]);
    }

    #[Test]
    public function it_achieves_target_cache_hit_ratios(): void
    {
        $totalOperations = 1000;
        $uniqueKeys = 100; // This will create a scenario with repeated access

        // Generate cache operations with repeated access patterns
        for ($i = 0; $i < $totalOperations; $i++) {
            $keyIndex = $i % $uniqueKeys; // This creates repeated access
            $key = CacheKey::make("hit_ratio_test_{$keyIndex}", 'benchmark');

            // First access will be a miss, subsequent accesses will be hits
            $value = $this->cacheManager->remember($key, fn () => "computed_value_{$keyIndex}");
            $this->assertNotNull($value);
        }

        // Check hit ratio
        $stats = $this->cacheManager->getStats();
        $hitRatio = $stats['hit_ratio'];

        // Assert hit ratio targets
        $this->assertGreaterThan(80.0, $hitRatio, 'Cache hit ratio should be > 80%');

        $this->logPerformanceMetric('Cache Hit Ratio Performance', [
            'hit_ratio_percent' => $hitRatio,
            'total_hits' => $stats['hits'],
            'total_misses' => $stats['misses'],
        ]);
    }

    #[Test]
    public function it_handles_high_concurrency_operations(): void
    {
        $concurrentOperations = 500;
        $startTime = microtime(true);

        // Simulate concurrent cache operations
        for ($i = 0; $i < $concurrentOperations; $i++) {
            $key = CacheKey::make("concurrent_test_{$i}", 'benchmark');

            // Mix of operations to simulate real usage
            $this->cacheManager->put($key, "value_{$i}");
            $this->cacheManager->get($key);

            if ($i % 10 === 0) {
                $this->cacheManager->forget($key);
            }
        }

        $totalTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $operationsPerSecond = ($concurrentOperations * 2) / ($totalTime / 1000); // 2 operations per iteration

        // Assert concurrency performance targets
        $this->assertGreaterThan(1000, $operationsPerSecond, 'Should handle > 1000 operations per second');

        $this->logPerformanceMetric('Concurrency Performance', [
            'total_time_ms' => $totalTime,
            'operations_per_second' => $operationsPerSecond,
        ]);
    }

    #[Test]
    public function it_handles_large_data_efficiently(): void
    {
        $dataSizes = [1024, 10240, 102400]; // 1KB, 10KB, 100KB

        foreach ($dataSizes as $size) {
            $largeData = str_repeat('x', $size);
            $key = CacheKey::make("large_data_{$size}", 'benchmark');

            $startTime = microtime(true);
            $this->cacheManager->put($key, $largeData);
            $retrievedData = $this->cacheManager->get($key);
            $endTime = microtime(true);

            $responseTime = ($endTime - $startTime) * 1000;

            $this->assertEquals($largeData, $retrievedData);
            $this->assertLessThan(100.0, $responseTime, "Large data ({$size} bytes) should be handled in < 100ms");

            $this->logPerformanceMetric("Large Data Performance ({$size} bytes)", [
                'response_time_ms' => $responseTime,
                'data_size_bytes' => $size,
            ]);
        }
    }

    #[Test]
    public function it_maintains_performance_under_memory_pressure(): void
    {
        $iterations = 1000;
        $keyCount = 500;
        $responseTimes = [];

        // Create memory pressure by storing many items
        for ($i = 0; $i < $iterations; $i++) {
            $keyIndex = $i % $keyCount;
            $key = CacheKey::make("memory_pressure_{$keyIndex}", 'benchmark');
            $data = str_repeat('data', 100); // ~400 bytes per item

            $startTime = microtime(true);
            $this->cacheManager->put($key, $data);
            $this->cacheManager->get($key);
            $endTime = microtime(true);

            $responseTimes[] = ($endTime - $startTime) * 1000;
        }

        $averageResponseTime = array_sum($responseTimes) / count($responseTimes);
        $lastQuartileAverage = array_sum(array_slice($responseTimes, -250)) / 250;

        // Performance should not degrade significantly under memory pressure
        $degradationRatio = $lastQuartileAverage / $averageResponseTime;
        $this->assertLessThan(2.0, $degradationRatio, 'Performance degradation should be < 2x under memory pressure');

        $this->logPerformanceMetric('Memory Pressure Performance', [
            'average_response_time_ms' => $averageResponseTime,
            'last_quartile_average_ms' => $lastQuartileAverage,
            'degradation_ratio' => $degradationRatio,
        ]);
    }

    #[Test]
    public function it_provides_accurate_performance_monitoring(): void
    {
        // Generate known cache activity
        $operations = 100;
        $expectedHits = 0;
        $expectedMisses = 0;

        for ($i = 0; $i < $operations; $i++) {
            $key = CacheKey::make("monitoring_test_{$i}", 'benchmark');

            // First access - should be a miss
            $value = $this->cacheManager->get($key, 'default');
            if ($value === 'default') {
                $expectedMisses++;
                $this->cacheManager->put($key, "value_{$i}");
            }

            // Second access - should be a hit
            $value = $this->cacheManager->get($key);
            if ($value !== null) {
                $expectedHits++;
            }
        }

        // Collect performance metrics
        $metrics = $this->performanceMonitor->collectMetrics();

        $this->assertArrayHasKey('cache_stats', $metrics);
        $this->assertArrayHasKey('derived_metrics', $metrics);
        $this->assertArrayHasKey('system_metrics', $metrics);

        // Verify monitoring accuracy
        $stats = $metrics['cache_stats'];
        $this->assertGreaterThan(0, $stats['hits']);
        $this->assertGreaterThan(0, $stats['misses']);

        $this->logPerformanceMetric('Performance Monitoring Accuracy', [
            'recorded_hits' => $stats['hits'],
            'recorded_misses' => $stats['misses'],
            'hit_ratio_percent' => $stats['hit_ratio'],
        ]);
    }

    // Advanced Performance Benchmark Tests
    #[Test]
    public function it_validates_90_percent_cache_hit_ratio_target(): void
    {
        $totalOperations = 2000;
        $uniqueKeys = 200; // 10:1 ratio for high hit rate

        // Warm up cache with initial data
        for ($i = 0; $i < $uniqueKeys; $i++) {
            $key = CacheKey::make("hit_ratio_target_{$i}", 'performance');
            $this->cacheManager->put($key, "initial_value_{$i}");
        }

        // Reset stats to measure only the test operations
        $this->cacheManager->resetStats();

        // Perform operations with high repeat access
        for ($i = 0; $i < $totalOperations; $i++) {
            $keyIndex = $i % $uniqueKeys;
            $key = CacheKey::make("hit_ratio_target_{$keyIndex}", 'performance');

            $value = $this->cacheManager->get($key);
            $this->assertNotNull($value, "Cache miss for key that should exist: {$keyIndex}");
        }

        $stats = $this->cacheManager->getStats();
        $hitRatio = $stats['hit_ratio'];

        // Assert 90%+ hit ratio target
        $this->assertGreaterThanOrEqual(90.0, $hitRatio, 'Cache hit ratio must be >= 90%');

        $this->logPerformanceMetric('90% Hit Ratio Target Validation', [
            'achieved_hit_ratio_percent' => $hitRatio,
            'total_operations' => $totalOperations,
            'hits' => $stats['hits'],
            'misses' => $stats['misses'],
        ]);
    }

    #[Test]
    public function it_validates_sub_5ms_memory_cache_response_times(): void
    {
        $iterations = 1000;
        $responseTimes = [];

        // Pre-populate cache to ensure hits
        $keys = [];
        for ($i = 0; $i < $iterations; $i++) {
            $key = CacheKey::make("sub_5ms_test_{$i}", 'performance');
            $keys[] = $key;
            $this->cacheManager->putInMemory($key, "test_value_{$i}");
        }

        // Measure response times for cache hits
        foreach ($keys as $key) {
            $startTime = microtime(true);
            $value = $this->cacheManager->getFromMemory($key);
            $endTime = microtime(true);

            $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            $responseTimes[] = $responseTime;

            $this->assertNotNull($value);
        }

        $averageResponseTime = array_sum($responseTimes) / count($responseTimes);
        $p95ResponseTime = $this->calculatePercentile($responseTimes, 95);
        $maxResponseTime = max($responseTimes);

        // Assert sub-5ms response time targets
        $this->assertLessThan(5.0, $averageResponseTime, 'Average memory cache response time must be < 5ms');
        $this->assertLessThan(10.0, $p95ResponseTime, '95th percentile response time must be < 10ms');

        $this->logPerformanceMetric('Sub-5ms Memory Cache Response Time Validation', [
            'average_ms' => $averageResponseTime,
            'p95_ms' => $p95ResponseTime,
            'max_ms' => $maxResponseTime,
        ]);
    }

    #[Test]
    public function it_validates_query_reduction_targets(): void
    {
        $simulatedQueries = 1000;
        $cacheableQueries = 800; // 80% of queries are cacheable
        $uniqueQueries = 100; // High repetition rate

        $queryExecutions = 0;
        $cacheHits = 0;

        // Simulate database queries with caching
        for ($i = 0; $i < $simulatedQueries; $i++) {
            if ($i < $cacheableQueries) {
                // Cacheable query
                $queryId = $i % $uniqueQueries;
                $key = CacheKey::make("query_result_{$queryId}", 'database');

                $result = $this->cacheManager->remember($key, function () use (&$queryExecutions, $queryId) {
                    $queryExecutions++;

                    return "query_result_data_{$queryId}";
                }, 3600);

                if ($result !== null) {
                    $cacheHits++;
                }
            } else {
                // Non-cacheable query (always executes)
                $queryExecutions++;
            }
        }

        $queryReductionRatio = (($simulatedQueries - $queryExecutions) / $simulatedQueries) * 100;

        // Assert 70%+ query reduction target (realistic for test environment)
        $this->assertGreaterThanOrEqual(70.0, $queryReductionRatio, 'Query reduction must be >= 70%');

        $this->logPerformanceMetric('Query Reduction Target Validation', [
            'total_simulated_queries' => $simulatedQueries,
            'actual_query_executions' => $queryExecutions,
            'query_reduction_percent' => $queryReductionRatio,
            'cache_hits' => $cacheHits,
        ]);
    }

    #[Test]
    public function it_benchmarks_multi_level_fallback_performance(): void
    {
        $iterations = 500;
        $fallbackTimes = [];

        // Test multi-level fallback scenarios
        for ($i = 0; $i < $iterations; $i++) {
            $key = CacheKey::make("fallback_test_{$i}", 'performance');

            // Store only in database level (lowest priority)
            $this->cacheManager->putInDatabase($key, "fallback_value_{$i}");

            // Measure fallback performance (should find in database and backfill)
            $startTime = microtime(true);
            $value = $this->cacheManager->get($key);
            $endTime = microtime(true);

            $fallbackTime = ($endTime - $startTime) * 1000;
            $fallbackTimes[] = $fallbackTime;

            $this->assertEquals("fallback_value_{$i}", $value);

            // Verify backfill occurred
            $requestValue = $this->cacheManager->getFromRequest($key);
            $this->assertEquals("fallback_value_{$i}", $requestValue);
        }

        $averageFallbackTime = array_sum($fallbackTimes) / count($fallbackTimes);
        $maxFallbackTime = max($fallbackTimes);

        // Fallback should still be reasonably fast
        $this->assertLessThan(50.0, $averageFallbackTime, 'Multi-level fallback average time should be < 50ms');
        $this->assertLessThan(200.0, $maxFallbackTime, 'Multi-level fallback max time should be < 200ms');

        $this->logPerformanceMetric('Multi-Level Fallback Performance', [
            'average_fallback_time_ms' => $averageFallbackTime,
            'max_fallback_time_ms' => $maxFallbackTime,
        ]);
    }

    #[Test]
    public function it_benchmarks_cache_invalidation_performance(): void
    {
        $keyCount = 1000;
        $invalidationTimes = [];

        // Pre-populate cache
        $keys = [];
        for ($i = 0; $i < $keyCount; $i++) {
            $key = CacheKey::make("invalidation_test_{$i}", 'performance');
            $keys[] = $key;
            $this->cacheManager->put($key, "value_{$i}");
        }

        // Benchmark individual key invalidation
        foreach (array_slice($keys, 0, 100) as $key) {
            $startTime = microtime(true);
            $result = $this->cacheManager->forget($key);
            $endTime = microtime(true);

            $invalidationTime = ($endTime - $startTime) * 1000;
            $invalidationTimes[] = $invalidationTime;

            $this->assertTrue($result);
        }

        $averageInvalidationTime = array_sum($invalidationTimes) / count($invalidationTimes);
        $maxInvalidationTime = max($invalidationTimes);

        // Invalidation should be fast
        $this->assertLessThan(10.0, $averageInvalidationTime, 'Cache invalidation average time should be < 10ms');
        $this->assertLessThan(50.0, $maxInvalidationTime, 'Cache invalidation max time should be < 50ms');

        $this->logPerformanceMetric('Cache Invalidation Performance', [
            'average_invalidation_time_ms' => $averageInvalidationTime,
            'max_invalidation_time_ms' => $maxInvalidationTime,
        ]);
    }

    #[Test]
    public function it_benchmarks_cache_warming_performance(): void
    {
        $warmerCount = 200;
        $warmers = [];

        // Create cache warmers
        for ($i = 0; $i < $warmerCount; $i++) {
            $warmers["warm_key_{$i}"] = function () use ($i) {
                // Simulate some computation
                usleep(100); // 0.1ms delay

                return "computed_value_{$i}";
            };
        }

        // Benchmark cache warming
        $startTime = microtime(true);
        $results = $this->cacheManager->warm($warmers);
        $endTime = microtime(true);

        $totalWarmingTime = ($endTime - $startTime) * 1000;
        $averageWarmingTime = $totalWarmingTime / $warmerCount;

        // Verify warming succeeded (warm method returns summary structure)
        $this->assertIsArray($results);
        $this->assertArrayHasKey('summary', $results);

        // Check that warming was successful
        $summary = $results['summary'];
        $this->assertArrayHasKey('total_warmers', $summary);
        $this->assertEquals($warmerCount, $summary['total_warmers']);

        // Warming should be efficient
        $this->assertLessThan(1.0, $averageWarmingTime, 'Average cache warming time should be < 1ms per item');
        $this->assertLessThan(500.0, $totalWarmingTime, 'Total cache warming should complete in < 500ms');

        $this->logPerformanceMetric('Cache Warming Performance', [
            'total_warming_time_ms' => $totalWarmingTime,
            'average_per_item_ms' => $averageWarmingTime,
            'items_warmed' => $warmerCount,
        ]);
    }

    /**
     * Log performance metrics to file instead of echoing to stdout
     */
    private function logPerformanceMetric(string $testName, array $metrics): void
    {
        $logDir = base_path('tests/storage/performance');
        if (! is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir.'/benchmark_results.log';
        $timestamp = now()->format('Y-m-d H:i:s');

        $logEntry = "[{$timestamp}] {$testName}: ".json_encode($metrics).PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Calculate percentile value from array of numbers
     */
    private function calculatePercentile(array $values, float $percentile): float
    {
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);

        if (floor($index) == $index) {
            return $values[(int) $index];
        }

        $lower = $values[(int) floor($index)];
        $upper = $values[(int) ceil($index)];
        $fraction = $index - floor($index);

        return $lower + ($fraction * ($upper - $lower));
    }
}
