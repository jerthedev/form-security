<?php

declare(strict_types=1);

/**
 * Test File: PerformanceRegressionTest.php
 *
 * EPIC: EPIC-006-analytics-monitoring
 * SPEC: SPEC-013-performance-monitoring-profiling
 * SPRINT: Sprint-005-performance-monitoring
 * TICKET: 2054-automated-regression-testing
 *
 * Description: Automated performance regression testing to detect
 * performance degradations and ensure performance targets are maintained.
 *
 * @see docs/Planning/Epics/EPIC-006-analytics-monitoring.md
 * @see docs/Planning/Specs/Analytics-Monitoring/SPEC-013-performance-monitoring-profiling.md
 */

namespace JTD\FormSecurity\Tests\Performance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\FormSecurity\Contracts\PerformanceMonitorInterface;
use JTD\FormSecurity\Contracts\PerformanceProfilerInterface;
use JTD\FormSecurity\Services\PerformanceMonitoringService;
use JTD\FormSecurity\Services\PerformanceProfiler;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Performance Regression Test Suite
 *
 * Automated testing to detect performance regressions and ensure
 * the system maintains acceptable performance characteristics.
 */
#[Group('performance')]
#[Group('regression')]
#[Group('epic-006')]
#[Group('monitoring')]
class PerformanceRegressionTest extends TestCase
{
    /**
     * Performance monitor instance
     */
    private PerformanceMonitorInterface $monitor;

    /**
     * Performance profiler instance
     */
    private PerformanceProfilerInterface $profiler;

    /**
     * Performance baselines for regression testing
     */
    private array $baselines = [
        'service_provider_boot_time' => 50, // milliseconds
        'cache_operation_time' => 10, // milliseconds
        'database_query_time' => 100, // milliseconds
        'memory_usage_limit' => 10 * 1024 * 1024, // 10MB
        'profiling_overhead' => 5, // 5% overhead
    ];

    /**
     * Setup test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->monitor = new PerformanceMonitoringService;
        $this->profiler = new PerformanceProfiler;

        // Clear any existing performance data
        $this->monitor->clearData();
        Cache::flush();
    }

    /**
     * Test service provider bootstrap performance
     */
    #[Test]
    public function service_provider_bootstrap_performance_meets_baseline(): void
    {
        $iterations = 10;
        $totalTime = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            // Simulate service provider bootstrap
            $this->app->register(\JTD\FormSecurity\FormSecurityServiceProvider::class);

            $endTime = microtime(true);
            $totalTime += ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        $averageTime = $totalTime / $iterations;

        $this->assertLessThan(
            $this->baselines['service_provider_boot_time'],
            $averageTime,
            "Service provider bootstrap time ({$averageTime}ms) exceeds baseline ({$this->baselines['service_provider_boot_time']}ms)"
        );
    }

    /**
     * Test performance monitoring overhead
     */
    #[Test]
    public function performance_monitoring_overhead_is_acceptable(): void
    {
        $iterations = 100;

        // Measure baseline execution time without monitoring
        $this->monitor->stopMonitoring();
        $baselineStart = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $this->performSampleOperations();
        }

        $baselineTime = microtime(true) - $baselineStart;

        // Measure execution time with monitoring enabled
        $this->monitor->startMonitoring();
        $monitoredStart = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $this->performSampleOperations();
        }

        $monitoredTime = microtime(true) - $monitoredStart;
        $this->monitor->stopMonitoring();

        // Calculate overhead percentage
        $overhead = (($monitoredTime - $baselineTime) / $baselineTime) * 100;

        $this->assertLessThan(
            $this->baselines['profiling_overhead'],
            $overhead,
            "Performance monitoring overhead ({$overhead}%) exceeds acceptable limit ({$this->baselines['profiling_overhead']}%)"
        );
    }

    /**
     * Test profiling system performance
     */
    #[Test]
    public function profiling_system_performance_meets_requirements(): void
    {
        $sessionId = $this->profiler->startProfiling();
        $operationsCount = 50;

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        for ($i = 0; $i < $operationsCount; $i++) {
            $operationId = $this->profiler->startOperation("test_operation_{$i}");

            // Simulate some work
            usleep(1000); // 1ms

            $this->profiler->stopOperation($operationId);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $results = $this->profiler->stopProfiling($sessionId);

        // Validate profiling performance
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = $endMemory - $startMemory;

        $this->assertArrayHasKey('duration', $results);
        $this->assertArrayHasKey('operations', $results);
        $this->assertCount($operationsCount, $results['operations']);

        // Check that profiling overhead is minimal
        $this->assertLessThan(
            $operationsCount * 2, // 2ms per operation maximum overhead
            $totalTime - $operationsCount, // Subtract expected operation time
            'Profiling overhead is too high'
        );

        // Check memory usage
        $this->assertLessThan(
            $this->baselines['memory_usage_limit'],
            $memoryUsed,
            "Profiling memory usage ({$memoryUsed} bytes) exceeds limit"
        );
    }

    /**
     * Test database query monitoring performance
     */
    #[Test]
    public function database_query_monitoring_performance_regression(): void
    {
        $this->monitor->startMonitoring();

        $queryCount = 20;
        $startTime = microtime(true);

        for ($i = 0; $i < $queryCount; $i++) {
            DB::select('SELECT 1 as test_value');
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $this->monitor->stopMonitoring();

        // Get monitoring statistics
        $stats = $this->monitor->getStatistics('1m');

        // Validate query monitoring performance
        $averageQueryTime = $totalTime / $queryCount;

        $this->assertLessThan(
            $this->baselines['database_query_time'],
            $averageQueryTime,
            "Average query time with monitoring ({$averageQueryTime}ms) exceeds baseline"
        );

        // Verify monitoring data was collected
        $this->assertArrayHasKey('operation_duration', $stats);
        $this->assertGreaterThan(0, $stats['operation_duration']['count'] ?? 0);
    }

    /**
     * Test cache operations performance
     */
    #[Test]
    public function cache_operations_performance_regression(): void
    {
        $this->monitor->startMonitoring();

        $operations = 100;
        $startTime = microtime(true);

        for ($i = 0; $i < $operations; $i++) {
            Cache::put("test_key_{$i}", "test_value_{$i}", 60);
            Cache::get("test_key_{$i}");
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $this->monitor->stopMonitoring();

        $averageOperationTime = $totalTime / ($operations * 2); // 2 operations per iteration

        $this->assertLessThan(
            $this->baselines['cache_operation_time'],
            $averageOperationTime,
            "Average cache operation time ({$averageOperationTime}ms) exceeds baseline"
        );
    }

    /**
     * Test memory usage regression
     */
    #[Test]
    public function memory_usage_regression_test(): void
    {
        $startMemory = memory_get_usage(true);

        $this->monitor->startMonitoring();
        $sessionId = $this->profiler->startProfiling();

        // Perform operations that should not cause memory leaks
        for ($i = 0; $i < 100; $i++) {
            $this->monitor->recordMetric('test_metric', $i);

            $operationId = $this->profiler->startOperation("memory_test_{$i}");

            // Simulate some memory usage
            $data = array_fill(0, 100, "test_data_{$i}");
            unset($data); // Clean up

            $this->profiler->stopOperation($operationId);
        }

        $this->profiler->stopProfiling($sessionId);
        $this->monitor->stopMonitoring();

        $endMemory = memory_get_usage(true);
        $memoryIncrease = $endMemory - $startMemory;

        $this->assertLessThan(
            $this->baselines['memory_usage_limit'],
            $memoryIncrease,
            "Memory usage increase ({$memoryIncrease} bytes) indicates potential memory leak"
        );
    }

    /**
     * Test performance threshold validation
     */
    #[Test]
    public function performance_thresholds_are_properly_validated(): void
    {
        $this->monitor->startMonitoring();

        // Set test thresholds
        $this->monitor->setThreshold('test_metric', 100.0, 'gt');

        // Record metrics that should trigger alerts
        $this->monitor->recordMetric('test_metric', 150.0);
        $this->monitor->recordMetric('test_metric', 50.0);

        $violations = $this->monitor->checkThresholds();

        $this->assertNotEmpty($violations, 'Expected threshold violations were not detected');
        $this->assertEquals('test_metric', $violations[0]['metric']);
        $this->assertEquals(150.0, $violations[0]['value']);
        $this->assertEquals(100.0, $violations[0]['threshold']);

        $this->monitor->stopMonitoring();
    }

    /**
     * Test concurrent performance monitoring
     */
    #[Test]
    public function concurrent_monitoring_performance_test(): void
    {
        $this->monitor->startMonitoring();

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Simulate concurrent operations
        $timers = [];
        for ($i = 0; $i < 20; $i++) {
            $timers[] = $this->monitor->startTimer("concurrent_operation_{$i}");
        }

        // Simulate some work
        usleep(10000); // 10ms

        // Stop all timers
        foreach ($timers as $timerId) {
            $this->monitor->stopTimer($timerId);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $this->monitor->stopMonitoring();

        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = $endMemory - $startMemory;

        // Verify concurrent operations don't cause excessive overhead
        $this->assertLessThan(50, $totalTime, 'Concurrent monitoring operations took too long');
        $this->assertLessThan(1024 * 1024, $memoryUsed, 'Concurrent monitoring used too much memory'); // 1MB limit
    }

    /**
     * Test performance report generation speed
     */
    #[Test]
    public function performance_report_generation_speed_test(): void
    {
        // Generate sample data
        $this->monitor->startMonitoring();

        for ($i = 0; $i < 50; $i++) {
            $this->monitor->recordMetric('sample_metric', $i * 10);
            $this->monitor->recordMemoryUsage("checkpoint_{$i}");
        }

        $startTime = microtime(true);

        // Generate report
        $report = $this->monitor->generateReport([
            'period' => '1h',
            'include_details' => true,
        ]);

        $endTime = microtime(true);
        $generationTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $this->monitor->stopMonitoring();

        // Verify report generation speed
        $this->assertLessThan(100, $generationTime, 'Performance report generation took too long');

        // Verify report structure
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('statistics', $report);
        $this->assertArrayHasKey('recommendations', $report);
    }

    /**
     * Test performance monitoring data cleanup
     */
    #[Test]
    public function performance_data_cleanup_efficiency_test(): void
    {
        $this->monitor->startMonitoring();

        // Generate a large amount of test data
        for ($i = 0; $i < 500; $i++) {
            $this->monitor->recordMetric('cleanup_test_metric', $i);
        }

        $startTime = microtime(true);

        // Test cleanup operation
        $this->monitor->clearData();

        $endTime = microtime(true);
        $cleanupTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Verify cleanup efficiency
        $this->assertLessThan(50, $cleanupTime, 'Performance data cleanup took too long');

        // Verify data was actually cleared
        $metrics = $this->monitor->getMetrics();
        $this->assertEmpty($metrics, 'Performance data was not properly cleared');
    }

    /**
     * Perform sample operations for testing
     */
    private function performSampleOperations(): void
    {
        // Simulate typical application operations
        Cache::put('sample_key', 'sample_value', 60);
        Cache::get('sample_key');

        // Simulate database operation
        DB::select('SELECT 1 as sample_value');

        // Simulate memory allocation
        $data = array_fill(0, 100, 'sample_data');
        unset($data);
    }

    /**
     * Cleanup test environment
     */
    protected function tearDown(): void
    {
        // Ensure monitoring is stopped
        if (method_exists($this->monitor, 'stopMonitoring')) {
            $this->monitor->stopMonitoring();
        }

        // Clear performance data
        $this->monitor->clearData();
        Cache::flush();

        parent::tearDown();
    }
}
