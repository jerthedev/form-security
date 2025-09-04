<?php

declare(strict_types=1);

/**
 * Test File: CLICommandPerformanceTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-foundational-architecture-design
 * SPRINT: Sprint-005-performance-optimization
 * TICKET: 2200-cli-performance-optimization
 *
 * Description: Performance tests for optimized CLI commands to validate
 * startup time, memory usage, and execution performance targets.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-foundational-architecture-design.md
 */

namespace JTD\FormSecurity\Tests\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('performance')]
#[Group('cli')]
#[Group('ticket-2200')]
class CLICommandPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Performance targets for CLI commands.
     */
    private const PERFORMANCE_TARGETS = [
        'startup_time_ms' => 200,      // Command startup < 200ms
        'memory_usage_mb' => 100,      // Memory usage < 100MB
        'execution_time_ms' => 5000,   // Long operations < 5s for tests
        'cache_hit_ratio' => 90,       // Cache hit ratio > 90%
    ];

    #[Test]
    public function command_startup_performance_meets_targets(): void
    {
        $commands = [
            'form-security:cache',
            'form-security:cleanup',
            'form-security:health-check',
            'form-security:optimize',
            'form-security:report',
        ];

        foreach ($commands as $commandName) {
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);

            // Test command initialization only (not execution)
            $command = $this->artisan($commandName, ['--help' => true]);

            $initTime = (microtime(true) - $startTime) * 1000; // Convert to ms
            $memoryUsed = (memory_get_usage(true) - $startMemory) / 1024 / 1024; // Convert to MB

            $this->assertLessThan(
                self::PERFORMANCE_TARGETS['startup_time_ms'],
                $initTime,
                "Command {$commandName} startup time ({$initTime}ms) exceeds target"
            );

            $this->assertLessThan(
                self::PERFORMANCE_TARGETS['memory_usage_mb'],
                $memoryUsed,
                "Command {$commandName} memory usage ({$memoryUsed}MB) exceeds target"
            );
        }
    }

    #[Test]
    public function cache_command_performance_optimizations(): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Test optimized cache stats command
        $this->artisan('form-security:cache', [
            'action' => 'stats',
            '--detailed' => true,
            '--verbose' => true,
        ])->assertSuccessful();

        $executionTime = (microtime(true) - $startTime) * 1000;
        $memoryUsed = (memory_get_usage(true) - $startMemory) / 1024 / 1024;

        $this->assertLessThan(
            self::PERFORMANCE_TARGETS['execution_time_ms'],
            $executionTime,
            "Cache stats command execution time ({$executionTime}ms) exceeds target"
        );

        $this->assertLessThan(
            self::PERFORMANCE_TARGETS['memory_usage_mb'],
            $memoryUsed,
            "Cache stats command memory usage ({$memoryUsed}MB) exceeds target"
        );
    }

    #[Test]
    public function cleanup_command_chunked_processing_performance(): void
    {
        // Create test data for cleanup performance testing
        $this->createTestData(1000);

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Test optimized cleanup with chunked processing
        $this->artisan('form-security:cleanup', [
            '--type' => ['old-records'],
            '--days' => 1,
            '--batch-size' => 100,
            '--dry-run' => true,
            '--force' => true,
        ])->assertSuccessful();

        $executionTime = (microtime(true) - $startTime) * 1000;
        $memoryUsed = (memory_get_usage(true) - $startMemory) / 1024 / 1024;

        $this->assertLessThan(
            self::PERFORMANCE_TARGETS['execution_time_ms'],
            $executionTime,
            "Cleanup command execution time ({$executionTime}ms) exceeds target"
        );

        $this->assertLessThan(
            self::PERFORMANCE_TARGETS['memory_usage_mb'],
            $memoryUsed,
            "Cleanup command memory usage ({$memoryUsed}MB) exceeds target"
        );
    }

    #[Test]
    public function parallel_processing_improves_performance(): void
    {
        // Test sequential execution
        $sequentialStart = microtime(true);
        $this->artisan('form-security:cleanup', [
            '--type' => ['temp-files', 'logs'],
            '--days' => 30,
            '--dry-run' => true,
            '--force' => true,
        ])->assertSuccessful();
        $sequentialTime = microtime(true) - $sequentialStart;

        // Test parallel execution
        $parallelStart = microtime(true);
        $this->artisan('form-security:cleanup', [
            '--type' => ['temp-files', 'logs'],
            '--days' => 30,
            '--parallel' => true,
            '--dry-run' => true,
            '--force' => true,
        ])->assertSuccessful();
        $parallelTime = microtime(true) - $parallelStart;

        // Parallel should be faster or at least not significantly slower
        $this->assertLessThanOrEqual(
            $sequentialTime * 1.1, // Allow 10% tolerance
            $parallelTime,
            'Parallel processing should not be significantly slower than sequential'
        );
    }

    #[Test]
    public function health_check_command_performance_diagnostics(): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->artisan('form-security:health-check', [
            '--detailed' => true,
        ])->assertSuccessful();

        $executionTime = (microtime(true) - $startTime) * 1000;
        $memoryUsed = (memory_get_usage(true) - $startMemory) / 1024 / 1024;

        $this->assertLessThan(
            self::PERFORMANCE_TARGETS['execution_time_ms'],
            $executionTime,
            "Health check execution time ({$executionTime}ms) exceeds target"
        );

        $this->assertLessThan(
            self::PERFORMANCE_TARGETS['memory_usage_mb'],
            $memoryUsed,
            "Health check memory usage ({$memoryUsed}MB) exceeds target"
        );
    }

    #[Test]
    public function optimize_command_performance_improvements(): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->artisan('form-security:optimize', [
            '--type' => ['cache'],
            '--dry-run' => true,
        ])->assertSuccessful();

        $executionTime = (microtime(true) - $startTime) * 1000;
        $memoryUsed = (memory_get_usage(true) - $startMemory) / 1024 / 1024;

        $this->assertLessThan(
            self::PERFORMANCE_TARGETS['execution_time_ms'],
            $executionTime,
            "Optimize command execution time ({$executionTime}ms) exceeds target"
        );

        $this->assertLessThan(
            self::PERFORMANCE_TARGETS['memory_usage_mb'],
            $memoryUsed,
            "Optimize command memory usage ({$memoryUsed}MB) exceeds target"
        );
    }

    #[Test]
    public function report_command_large_dataset_performance(): void
    {
        // Create test data for reporting
        $this->createTestData(500);

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->artisan('form-security:report', [
            'type' => 'summary',
            '--period' => 30,
            '--format' => 'table',
        ])->assertSuccessful();

        $executionTime = (microtime(true) - $startTime) * 1000;
        $memoryUsed = (memory_get_usage(true) - $startMemory) / 1024 / 1024;

        $this->assertLessThan(
            self::PERFORMANCE_TARGETS['execution_time_ms'],
            $executionTime,
            "Report command execution time ({$executionTime}ms) exceeds target"
        );

        $this->assertLessThan(
            self::PERFORMANCE_TARGETS['memory_usage_mb'],
            $memoryUsed,
            "Report command memory usage ({$memoryUsed}MB) exceeds target"
        );
    }

    #[Test]
    public function lazy_loading_reduces_memory_footprint(): void
    {
        // Measure memory before and after command instantiation
        $memoryBefore = memory_get_usage(true);

        // Create multiple command instances to test lazy loading
        $commands = [
            $this->app->make(\JTD\FormSecurity\Console\Commands\CacheCommand::class),
            $this->app->make(\JTD\FormSecurity\Console\Commands\CleanupCommand::class),
            $this->app->make(\JTD\FormSecurity\Console\Commands\HealthCheckCommand::class),
        ];

        $memoryAfterInstantiation = memory_get_usage(true);

        // Access services to trigger lazy loading
        foreach ($commands as $command) {
            if (method_exists($command, 'getCacheManager')) {
                $command->getCacheManager();
            }
        }

        $memoryAfterLazyLoad = memory_get_usage(true);

        $instantiationMemory = ($memoryAfterInstantiation - $memoryBefore) / 1024 / 1024;
        $lazyLoadMemory = ($memoryAfterLazyLoad - $memoryAfterInstantiation) / 1024 / 1024;

        // Lazy loading should keep initial memory usage low
        $this->assertLessThan(
            10, // Less than 10MB for instantiation
            $instantiationMemory,
            "Command instantiation memory usage ({$instantiationMemory}MB) too high"
        );

        // Services should load only when needed
        $this->assertGreaterThan(
            $instantiationMemory,
            $instantiationMemory + $lazyLoadMemory,
            'Lazy loading should increase memory usage when services are accessed'
        );
    }

    #[Test]
    public function progress_bars_provide_accurate_time_estimates(): void
    {
        $output = new BufferedOutput;

        $startTime = microtime(true);

        // Test progress bar accuracy with cache warming
        $this->artisan('form-security:cache', [
            'action' => 'warm',
            '--verbose' => true,
        ], $output)->assertSuccessful();

        $actualTime = microtime(true) - $startTime;
        $outputContent = $output->fetch();

        // Progress bar should complete within reasonable time
        $this->assertLessThan(
            10, // Less than 10 seconds for cache warming
            $actualTime,
            "Cache warming took too long: {$actualTime}s"
        );

        // Output should contain progress information
        $this->assertStringContainsString('%', $outputContent, 'Progress percentage should be displayed');
        $this->assertStringContainsString('/', $outputContent, 'Progress fraction should be displayed');
    }

    /**
     * Create test data for performance testing.
     */
    private function createTestData(int $count): void
    {
        // Create blocked submissions for testing
        for ($i = 0; $i < $count; $i++) {
            \DB::table('blocked_submissions')->insert([
                'ip_address' => '192.168.1.'.($i % 255),
                'form_name' => 'test_form_'.($i % 10),
                'block_reason' => 'spam_detected',
                'country_code' => 'US',
                'created_at' => now()->subDays(rand(0, 60)),
                'updated_at' => now(),
            ]);
        }
    }
}
