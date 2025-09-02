<?php

declare(strict_types=1);

/**
 * Test File: CachePerformanceMonitorTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Tests for the CachePerformanceMonitor service
 * including metrics collection and performance analysis.
 */

namespace JTD\FormSecurity\Tests\Unit\Services;

use JTD\FormSecurity\Contracts\CacheManagerInterface;
use JTD\FormSecurity\Services\CacheManager;
use JTD\FormSecurity\Services\CachePerformanceMonitor;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('ticket-1014')]
#[Group('caching')]
#[Group('performance')]
#[Group('unit')]
class CachePerformanceMonitorTest extends TestCase
{
    private CachePerformanceMonitor $performanceMonitor;

    private CacheManagerInterface $cacheManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a real CacheManager with Laravel's cache system
        $laravelCacheManager = app(\Illuminate\Cache\CacheManager::class);

        // Create all required services
        $operations = new \JTD\FormSecurity\Services\Cache\Operations\CacheOperationService($laravelCacheManager);
        $warming = new \JTD\FormSecurity\Services\Cache\Warming\CacheWarmingService($laravelCacheManager, $operations);
        $maintenance = new \JTD\FormSecurity\Services\Cache\Maintenance\CacheMaintenanceService($laravelCacheManager);
        $security = new \JTD\FormSecurity\Services\Cache\Security\CacheSecurityService($laravelCacheManager);
        $statistics = new \JTD\FormSecurity\Services\Cache\Statistics\CacheStatisticsService($laravelCacheManager);
        $validation = new \JTD\FormSecurity\Services\Cache\Validation\CacheValidationService($laravelCacheManager);

        // Create the cache manager with all services
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
    public function it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(CachePerformanceMonitor::class, $this->performanceMonitor);
    }

    #[Test]
    public function it_can_collect_performance_metrics(): void
    {
        // Generate some cache activity to have stats to collect
        $this->cacheManager->put('test_key', 'test_value');
        $this->cacheManager->get('test_key');
        $this->cacheManager->get('missing_key'); // This will be a miss

        $metrics = $this->performanceMonitor->collectMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('timestamp', $metrics);
        $this->assertArrayHasKey('cache_stats', $metrics);
        $this->assertArrayHasKey('level_stats', $metrics);
        $this->assertArrayHasKey('derived_metrics', $metrics);
        $this->assertArrayHasKey('system_metrics', $metrics);
    }

    #[Test]
    public function it_can_get_dashboard_data(): void
    {
        // Generate some cache activity
        $this->cacheManager->put('dashboard_test', 'value');
        $this->cacheManager->get('dashboard_test');

        $dashboardData = $this->performanceMonitor->getDashboardData();

        $this->assertIsArray($dashboardData);
        $this->assertArrayHasKey('current_metrics', $dashboardData);
        $this->assertArrayHasKey('health_status', $dashboardData);
        $this->assertArrayHasKey('recent_alerts', $dashboardData);
        $this->assertArrayHasKey('quick_stats', $dashboardData);
        $this->assertArrayHasKey('level_comparison', $dashboardData);
    }

    #[Test]
    public function it_can_set_and_get_performance_thresholds(): void
    {
        $customThresholds = [
            'hit_ratio_warning' => 75.0,
            'response_time_critical' => 100.0,
        ];

        $this->performanceMonitor->setThresholds($customThresholds);
        $thresholds = $this->performanceMonitor->getThresholds();

        $this->assertEquals(75.0, $thresholds['hit_ratio_warning']);
        $this->assertEquals(100.0, $thresholds['response_time_critical']);

        // Should still have default values for other thresholds
        $this->assertArrayHasKey('hit_ratio_critical', $thresholds);
    }

    #[Test]
    public function it_calculates_excellent_health_status(): void
    {
        // Generate excellent cache activity
        for ($i = 0; $i < 20; $i++) {
            $this->cacheManager->put("excellent_test_{$i}", "value_{$i}");
            $this->cacheManager->get("excellent_test_{$i}"); // This will be a hit
        }

        $dashboardData = $this->performanceMonitor->getDashboardData();

        // With high hit ratio, health should be good or excellent
        $this->assertContains($dashboardData['health_status'], ['excellent', 'good']);
    }

    #[Test]
    public function it_calculates_poor_health_status(): void
    {
        // Generate poor cache activity (lots of misses)
        for ($i = 0; $i < 20; $i++) {
            $this->cacheManager->get("missing_key_{$i}"); // These will be misses
        }

        $dashboardData = $this->performanceMonitor->getDashboardData();

        // With low hit ratio, health should be fair or poor
        $this->assertContains($dashboardData['health_status'], ['poor', 'fair']);
    }

    #[Test]
    public function it_can_get_recent_alerts(): void
    {
        // Generate poor performance to trigger alerts
        for ($i = 0; $i < 50; $i++) {
            $this->cacheManager->get("missing_key_{$i}"); // Generate misses
        }

        $this->performanceMonitor->collectMetrics();

        $alerts = $this->performanceMonitor->getRecentAlerts(1);

        $this->assertIsArray($alerts);
        // Alerts may or may not be generated depending on thresholds
    }

    #[Test]
    public function it_can_generate_performance_report(): void
    {
        // Generate some cache activity
        $this->cacheManager->put('report_test', 'value');
        $this->cacheManager->get('report_test');

        // Collect some metrics first
        $this->performanceMonitor->collectMetrics();

        $report = $this->performanceMonitor->getPerformanceReport(1);

        $this->assertIsArray($report);
        if (! isset($report['error'])) {
            $this->assertArrayHasKey('period', $report);
            $this->assertArrayHasKey('metrics_count', $report);
            $this->assertArrayHasKey('summary', $report);
            $this->assertArrayHasKey('trends', $report);
            $this->assertArrayHasKey('alerts', $report);
            $this->assertArrayHasKey('recommendations', $report);
        }
    }

    #[Test]
    public function it_can_cleanup_old_data(): void
    {
        // Generate some cache activity
        $this->cacheManager->put('cleanup_test', 'value');
        $this->cacheManager->get('cleanup_test');

        // Collect some metrics
        $this->performanceMonitor->collectMetrics();

        // Cleanup should not throw any errors
        $this->performanceMonitor->cleanup(1); // 1 hour retention

        $this->assertTrue(true); // If we get here, cleanup worked
    }

    #[Test]
    public function it_provides_quick_stats_in_dashboard(): void
    {
        // Generate some cache activity
        for ($i = 0; $i < 10; $i++) {
            $this->cacheManager->put("quick_stats_test_{$i}", "value_{$i}");
            $this->cacheManager->get("quick_stats_test_{$i}");
        }

        $dashboardData = $this->performanceMonitor->getDashboardData();
        $quickStats = $dashboardData['quick_stats'];

        $this->assertIsFloat($quickStats['hit_ratio']);
        $this->assertIsInt($quickStats['total_hits']);
        $this->assertIsInt($quickStats['total_misses']);
        $this->assertIsFloat($quickStats['avg_response_time']);
        $this->assertContains($quickStats['health_status'], ['excellent', 'good', 'fair', 'poor']);
    }

    #[Test]
    public function it_compares_cache_level_performance(): void
    {
        // Generate some cache activity
        $this->cacheManager->put('level_comparison_test', 'value');
        $this->cacheManager->get('level_comparison_test');

        $dashboardData = $this->performanceMonitor->getDashboardData();
        $levelComparison = $dashboardData['level_comparison'];

        $this->assertIsArray($levelComparison);
        $this->assertArrayHasKey('request', $levelComparison);
        $this->assertArrayHasKey('memory', $levelComparison);
        $this->assertArrayHasKey('database', $levelComparison);

        // Each level should have priority and efficiency rating
        foreach ($levelComparison as $level => $data) {
            $this->assertArrayHasKey('priority', $data);
            $this->assertArrayHasKey('efficiency_rating', $data);
        }
    }

    #[Test]
    public function it_handles_empty_metrics_gracefully(): void
    {
        // Test with no metrics collected
        $report = $this->performanceMonitor->getPerformanceReport(1);

        $this->assertArrayHasKey('error', $report);
        $this->assertStringContainsString('No metrics available', $report['error']);
    }
}
