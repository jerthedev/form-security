<?php

declare(strict_types=1);

/**
 * Test File: BootstrapPerformanceTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: N/A - Performance testing
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1010-service-provider-package-registration
 *
 * Description: Performance tests for service provider bootstrap time
 * validation and optimization. Tests must validate <50ms bootstrap target.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1010-service-provider-package-registration.md
 */

namespace JTD\FormSecurity\Tests\Performance;

use JTD\FormSecurity\FormSecurityServiceProvider;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-002')]
#[Group('ticket-1010')]
#[Group('performance')]
#[Group('service-provider')]
class BootstrapPerformanceTest extends TestCase
{
    /**
     * Performance threshold in milliseconds (50ms as per requirements).
     */
    private const PERFORMANCE_THRESHOLD_MS = 50.0;

    #[Test]
    public function service_provider_registration_meets_performance_target(): void
    {
        // Test service provider registration performance
        $times = [];
        $iterations = 10;

        for ($i = 0; $i < $iterations; $i++) {
            $app = $this->createApplication();

            $startTime = microtime(true);
            $serviceProvider = new FormSecurityServiceProvider($app);
            $serviceProvider->register();
            $endTime = microtime(true);

            $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        $averageTime = array_sum($times) / count($times);
        $maxTime = max($times);

        $this->assertLessThanOrEqual(
            self::PERFORMANCE_THRESHOLD_MS,
            $averageTime,
            "Service provider registration average time ({$averageTime}ms) exceeds threshold (".self::PERFORMANCE_THRESHOLD_MS.'ms)'
        );

        $this->assertLessThanOrEqual(
            self::PERFORMANCE_THRESHOLD_MS * 1.5, // Allow 50% variance for max time
            $maxTime,
            "Service provider registration max time ({$maxTime}ms) significantly exceeds threshold"
        );
    }

    #[Test]
    public function service_provider_boot_meets_performance_target(): void
    {
        // Test service provider boot performance
        $times = [];
        $iterations = 10;

        for ($i = 0; $i < $iterations; $i++) {
            $app = $this->createApplication();
            $serviceProvider = new FormSecurityServiceProvider($app);
            $serviceProvider->register();

            $startTime = microtime(true);
            $serviceProvider->boot();
            $endTime = microtime(true);

            $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        $averageTime = array_sum($times) / count($times);
        $maxTime = max($times);

        $this->assertLessThanOrEqual(
            self::PERFORMANCE_THRESHOLD_MS,
            $averageTime,
            "Service provider boot average time ({$averageTime}ms) exceeds threshold (".self::PERFORMANCE_THRESHOLD_MS.'ms)'
        );

        $this->assertLessThanOrEqual(
            self::PERFORMANCE_THRESHOLD_MS * 1.5,
            $maxTime,
            "Service provider boot max time ({$maxTime}ms) significantly exceeds threshold"
        );
    }

    #[Test]
    public function complete_service_provider_lifecycle_meets_performance_target(): void
    {
        // Test complete service provider lifecycle (register + boot)
        $times = [];
        $iterations = 10;

        for ($i = 0; $i < $iterations; $i++) {
            $app = $this->createApplication();

            $startTime = microtime(true);
            $serviceProvider = new FormSecurityServiceProvider($app);
            $serviceProvider->register();
            $serviceProvider->boot();
            $endTime = microtime(true);

            $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        $averageTime = array_sum($times) / count($times);
        $maxTime = max($times);

        $this->assertLessThanOrEqual(
            self::PERFORMANCE_THRESHOLD_MS,
            $averageTime,
            "Complete service provider lifecycle average time ({$averageTime}ms) exceeds threshold (".self::PERFORMANCE_THRESHOLD_MS.'ms)'
        );

        $this->assertLessThanOrEqual(
            self::PERFORMANCE_THRESHOLD_MS * 1.5,
            $maxTime,
            "Complete service provider lifecycle max time ({$maxTime}ms) significantly exceeds threshold"
        );
    }

    #[Test]
    public function service_resolution_meets_performance_target(): void
    {
        // Test service resolution performance
        $times = [];
        $iterations = 100; // More iterations for service resolution

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            // Resolve all core services
            $this->app->make(\JTD\FormSecurity\Contracts\ConfigurationContract::class);
            $this->app->make(\JTD\FormSecurity\Contracts\FormSecurityContract::class);
            $this->app->make(\JTD\FormSecurity\Contracts\SpamDetectionContract::class);

            $endTime = microtime(true);

            $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        $averageTime = array_sum($times) / count($times);
        $maxTime = max($times);

        // Service resolution should be much faster than bootstrap
        $resolutionThreshold = self::PERFORMANCE_THRESHOLD_MS * 0.1; // 5ms

        $this->assertLessThanOrEqual(
            $resolutionThreshold,
            $averageTime,
            "Service resolution average time ({$averageTime}ms) exceeds threshold ({$resolutionThreshold}ms)"
        );

        $this->assertLessThanOrEqual(
            $resolutionThreshold * 2,
            $maxTime,
            "Service resolution max time ({$maxTime}ms) significantly exceeds threshold"
        );
    }

    #[Test]
    public function facade_access_meets_performance_target(): void
    {
        // Test facade access performance
        $times = [];
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            // Access facade methods
            \JTD\FormSecurity\Facades\FormSecurity::isEnabled();
            \JTD\FormSecurity\Facades\FormSecurity::getConfig('enabled');
            \JTD\FormSecurity\Facades\FormSecurity::version();

            $endTime = microtime(true);

            $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        $averageTime = array_sum($times) / count($times);
        $maxTime = max($times);

        // Facade access should be very fast
        $facadeThreshold = self::PERFORMANCE_THRESHOLD_MS * 0.05; // 2.5ms

        $this->assertLessThanOrEqual(
            $facadeThreshold,
            $averageTime,
            "Facade access average time ({$averageTime}ms) exceeds threshold ({$facadeThreshold}ms)"
        );

        $this->assertLessThanOrEqual(
            $facadeThreshold * 2,
            $maxTime,
            "Facade access max time ({$maxTime}ms) significantly exceeds threshold"
        );
    }

    #[Test]
    public function memory_usage_stays_within_limits(): void
    {
        // Test memory usage during service provider lifecycle
        $memoryBefore = memory_get_usage(true);

        // Create multiple service provider instances to test memory usage
        for ($i = 0; $i < 10; $i++) {
            $app = $this->createApplication();
            $serviceProvider = new FormSecurityServiceProvider($app);
            $serviceProvider->register();
            $serviceProvider->boot();

            // Resolve services
            $app->make(\JTD\FormSecurity\Contracts\ConfigurationContract::class);
            $app->make(\JTD\FormSecurity\Contracts\FormSecurityContract::class);
            $app->make(\JTD\FormSecurity\Contracts\SpamDetectionContract::class);
        }

        $memoryAfter = memory_get_usage(true);
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB

        // Memory usage should stay under 10MB for 10 instances
        $memoryLimit = 10.0; // MB

        $this->assertLessThanOrEqual(
            $memoryLimit,
            $memoryUsed,
            "Memory usage ({$memoryUsed}MB) exceeds limit ({$memoryLimit}MB)"
        );
    }

    #[Test]
    public function concurrent_service_resolution_performance(): void
    {
        // Test performance when resolving services concurrently
        $startTime = microtime(true);

        // Simulate concurrent access by resolving services multiple times
        for ($i = 0; $i < 50; $i++) {
            $config = $this->app->make(\JTD\FormSecurity\Contracts\ConfigurationContract::class);
            $formSecurity = $this->app->make(\JTD\FormSecurity\Contracts\FormSecurityContract::class);
            $spamDetector = $this->app->make(\JTD\FormSecurity\Contracts\SpamDetectionContract::class);

            // Verify services are properly resolved
            $this->assertNotNull($config);
            $this->assertNotNull($formSecurity);
            $this->assertNotNull($spamDetector);
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Total time for 50 concurrent resolutions should be reasonable
        $concurrentThreshold = self::PERFORMANCE_THRESHOLD_MS * 2; // 100ms

        $this->assertLessThanOrEqual(
            $concurrentThreshold,
            $totalTime,
            "Concurrent service resolution time ({$totalTime}ms) exceeds threshold ({$concurrentThreshold}ms)"
        );
    }

    #[Test]
    public function performance_regression_detection(): void
    {
        // Baseline performance test to detect regressions
        $baselineTime = 25.0; // Expected baseline in milliseconds

        $startTime = microtime(true);

        $app = $this->createApplication();
        $serviceProvider = new FormSecurityServiceProvider($app);
        $serviceProvider->register();
        $serviceProvider->boot();

        // Resolve all services
        $app->make(\JTD\FormSecurity\Contracts\ConfigurationContract::class);
        $app->make(\JTD\FormSecurity\Contracts\FormSecurityContract::class);
        $app->make(\JTD\FormSecurity\Contracts\SpamDetectionContract::class);

        $endTime = microtime(true);
        $actualTime = ($endTime - $startTime) * 1000;

        // Allow 100% variance from baseline (performance can vary by environment)
        $regressionThreshold = $baselineTime * 2;

        $this->assertLessThanOrEqual(
            $regressionThreshold,
            $actualTime,
            "Performance regression detected: actual time ({$actualTime}ms) significantly exceeds baseline ({$baselineTime}ms)"
        );

        // Performance metrics are validated through assertions above
        // Logging removed to avoid risky test warnings in PHPUnit 12
    }
}
