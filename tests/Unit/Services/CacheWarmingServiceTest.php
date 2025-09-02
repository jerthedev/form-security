<?php

declare(strict_types=1);

/**
 * Test File: CacheWarmingServiceTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Tests for the CacheWarmingService
 * including warming strategies and maintenance procedures.
 */

namespace JTD\FormSecurity\Tests\Unit\Services;

use JTD\FormSecurity\Contracts\CacheManagerInterface;
use JTD\FormSecurity\Services\CacheManager;
use JTD\FormSecurity\Services\CacheWarmingService;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('ticket-1014')]
#[Group('caching')]
#[Group('warming')]
#[Group('unit')]
class CacheWarmingServiceTest extends TestCase
{
    private CacheWarmingService $warmingService;

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

        // Create the main CacheWarmingService that has all the expected methods
        $this->warmingService = new CacheWarmingService($this->cacheManager);
    }

    #[Test]
    public function it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(CacheWarmingService::class, $this->warmingService);
    }

    #[Test]
    public function it_has_default_warming_strategies(): void
    {
        $strategies = $this->warmingService->getStrategies();

        $this->assertIsArray($strategies);
        $this->assertContains('frequent_data', $strategies);
        $this->assertContains('critical_data', $strategies);
        $this->assertContains('analytics_data', $strategies);
        $this->assertContains('configuration_data', $strategies);
        $this->assertContains('security_data', $strategies);
    }

    #[Test]
    public function it_can_warm_frequent_data(): void
    {
        $results = $this->warmingService->warmFrequentData();

        $this->assertIsArray($results);
        // Verify that some keys were warmed (the exact keys depend on the implementation)
        $this->assertNotEmpty($results);
    }

    #[Test]
    public function it_can_warm_critical_data(): void
    {
        $results = $this->warmingService->warmCriticalData();

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    #[Test]
    public function it_can_warm_analytics_data(): void
    {
        $results = $this->warmingService->warmAnalyticsData();

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    #[Test]
    public function it_can_register_custom_strategies(): void
    {
        $customStrategy = function (?array $levels) {
            return ['custom_key' => true];
        };

        $this->warmingService->registerStrategy('custom', $customStrategy);
        $strategies = $this->warmingService->getStrategies();

        $this->assertContains('custom', $strategies);
    }

    #[Test]
    public function it_can_warm_cache_with_strategies(): void
    {
        $results = $this->warmingService->warmCache(['frequent_data']);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('frequent_data', $results);
        $this->assertTrue($results['frequent_data']['success']);
    }

    #[Test]
    public function it_tracks_warming_statistics(): void
    {
        // Initial stats should be zero
        $initialStats = $this->warmingService->getStats();
        $this->assertEquals(0, $initialStats['total_warmed']);

        // Warm cache
        $this->warmingService->warmCache(['frequent_data']);

        // Stats should be updated
        $updatedStats = $this->warmingService->getStats();
        $this->assertGreaterThan(0, $updatedStats['total_warmed']);
        $this->assertNotNull($updatedStats['last_warming_time']);
    }

    #[Test]
    public function it_can_reset_statistics(): void
    {
        // Warm cache to generate stats
        $this->warmingService->warmCache(['frequent_data']);

        $stats = $this->warmingService->getStats();
        $this->assertGreaterThan(0, $stats['total_warmed']);

        // Reset stats
        $this->warmingService->resetStats();

        $resetStats = $this->warmingService->getStats();
        $this->assertEquals(0, $resetStats['total_warmed']);
        $this->assertEquals(0, $resetStats['successful_warmed']);
        $this->assertEquals(0, $resetStats['failed_warmed']);
        $this->assertNull($resetStats['last_warming_time']);
    }

    #[Test]
    public function it_handles_warming_failures_gracefully(): void
    {
        // Test with non-existent strategy
        $results = $this->warmingService->warmCache(['non_existent_strategy']);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('non_existent_strategy', $results);
        $this->assertFalse($results['non_existent_strategy']['success']);
        $this->assertArrayHasKey('error', $results['non_existent_strategy']);
    }

    #[Test]
    public function it_can_schedule_warming(): void
    {
        $result = $this->warmingService->scheduleWarming('hourly');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_provides_warming_duration_in_stats(): void
    {
        $this->warmingService->warmCache(['frequent_data']);

        $stats = $this->warmingService->getStats();
        $this->assertArrayHasKey('warming_duration', $stats);
        $this->assertIsFloat($stats['warming_duration']);
        $this->assertGreaterThanOrEqual(0, $stats['warming_duration']);
    }

    #[Test]
    public function it_counts_successful_and_failed_warming_operations(): void
    {
        $this->warmingService->warmCache(['frequent_data']);

        $stats = $this->warmingService->getStats();
        $this->assertGreaterThan(0, $stats['total_warmed']);
        $this->assertGreaterThanOrEqual(0, $stats['successful_warmed']);
        $this->assertGreaterThanOrEqual(0, $stats['failed_warmed']);
    }

    #[Test]
    public function it_warms_all_default_strategies_when_none_specified(): void
    {
        $results = $this->warmingService->warmCache(); // No strategies specified

        $this->assertIsArray($results);

        // Should have results for all default strategies
        $defaultStrategies = ['frequent_data', 'critical_data', 'analytics_data', 'configuration_data', 'security_data'];
        foreach ($defaultStrategies as $strategy) {
            $this->assertArrayHasKey($strategy, $results);
        }
    }
}
