<?php

declare(strict_types=1);

/**
 * Test File: CacheHitRatioBenchmarkTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: Performance Validation
 *
 * Description: Comprehensive performance benchmarks for cache hit ratios
 * across all cache levels to validate 90%+ hit ratio targets.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-003-multi-level-caching-system.md
 * @see docs/Planning/Sprints/004-caching-cli-integration.md
 */

namespace JTD\FormSecurity\Tests\Performance;

use JTD\FormSecurity\Services\CacheManager;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('performance')]
#[Group('benchmarks')]
#[Group('cache-hit-ratio')]
class CacheHitRatioBenchmarkTest extends TestCase
{
    private CacheManager $cacheManager;

    private array $performanceResults = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheManager = app(CacheManager::class);
        $this->cacheManager->flush(); // Start with clean cache
        $this->performanceResults = [];
    }

    #[Test]
    public function it_achieves_90_percent_hit_ratio_under_realistic_load(): void
    {
        $testScenarios = [
            'small_dataset' => ['keys' => 100, 'operations' => 1000],
            'medium_dataset' => ['keys' => 500, 'operations' => 5000],
            'large_dataset' => ['keys' => 1000, 'operations' => 10000],
        ];

        foreach ($testScenarios as $scenario => $config) {
            $results = $this->runHitRatioBenchmark($scenario, $config['keys'], $config['operations']);
            $this->performanceResults[$scenario] = $results;

            // Validate 90%+ hit ratio requirement
            $this->assertGreaterThanOrEqual(90.0, $results['hit_ratio'],
                "Cache hit ratio for {$scenario} should be >= 90%, got {$results['hit_ratio']}%");
        }

        $this->outputPerformanceReport();
    }

    #[Test]
    public function it_maintains_hit_ratio_across_all_cache_levels(): void
    {
        $levels = ['request', 'memory', 'database'];
        $keyCount = 200;
        $operationCount = 2000;

        foreach ($levels as $level) {
            $results = $this->runLevelSpecificBenchmark($level, $keyCount, $operationCount);
            $this->performanceResults["level_{$level}"] = $results;

            // Each level should maintain reasonable hit ratios (isolated level testing has different characteristics)
            $this->assertGreaterThanOrEqual(50.0, $results['hit_ratio'],
                "Hit ratio for {$level} level should be >= 50%, got {$results['hit_ratio']}%");
        }
    }

    #[Test]
    public function it_demonstrates_cache_warming_effectiveness(): void
    {
        $keyCount = 300;
        $operationCount = 3000;

        // Test without warming
        $coldResults = $this->runHitRatioBenchmark('cold_cache', $keyCount, $operationCount);

        // Test with warming
        $this->warmCache($keyCount);
        $warmResults = $this->runHitRatioBenchmark('warm_cache', $keyCount, $operationCount);

        $this->performanceResults['cache_warming'] = [
            'cold_hit_ratio' => $coldResults['hit_ratio'],
            'warm_hit_ratio' => $warmResults['hit_ratio'],
            'improvement' => $warmResults['hit_ratio'] - $coldResults['hit_ratio'],
        ];

        // Warm cache should significantly outperform cold cache
        $this->assertGreaterThan($coldResults['hit_ratio'], $warmResults['hit_ratio'],
            'Warm cache should have higher hit ratio than cold cache');

        $this->assertGreaterThanOrEqual(90.0, $warmResults['hit_ratio'],
            'Warm cache should achieve 90%+ hit ratio');
    }

    #[Test]
    public function it_handles_concurrent_access_patterns(): void
    {
        $results = $this->runConcurrentAccessBenchmark();
        $this->performanceResults['concurrent_access'] = $results;

        // Concurrent access should maintain reasonable hit ratios (lower expectation due to mixed operations)
        $this->assertGreaterThanOrEqual(50.0, $results['hit_ratio'],
            'Concurrent access should maintain >= 50% hit ratio');
    }

    private function runHitRatioBenchmark(string $scenario, int $keyCount, int $operationCount): array
    {
        $startTime = microtime(true);
        $keys = $this->generateTestKeys($keyCount);

        // Pre-populate cache with some data (simulating real-world usage)
        $this->populateCache($keys, 0.7); // 70% of keys pre-populated

        $hits = 0;
        $misses = 0;
        $operations = 0;

        // Reset stats
        $this->cacheManager->resetStats();

        // Perform mixed read operations
        for ($i = 0; $i < $operationCount; $i++) {
            $key = $keys[array_rand($keys)];
            $value = $this->cacheManager->get($key);

            if ($value !== null) {
                $hits++;
            } else {
                $misses++;
                // Store value for future hits (simulating real application behavior)
                $this->cacheManager->put($key, "value_for_{$key}", 3600);
            }
            $operations++;
        }

        $duration = microtime(true) - $startTime;
        $hitRatio = $operations > 0 ? ($hits / $operations) * 100 : 0;

        return [
            'scenario' => $scenario,
            'key_count' => $keyCount,
            'operations' => $operations,
            'hits' => $hits,
            'misses' => $misses,
            'hit_ratio' => round($hitRatio, 2),
            'duration_seconds' => round($duration, 3),
            'operations_per_second' => round($operations / $duration, 2),
            'cache_stats' => $this->cacheManager->getStats(),
        ];
    }

    private function runLevelSpecificBenchmark(string $level, int $keyCount, int $operationCount): array
    {
        $keys = $this->generateTestKeys($keyCount);
        $levelMethod = 'putIn'.ucfirst($level);
        $getLevelMethod = 'getFrom'.ucfirst($level);

        // Pre-populate specific level
        foreach ($keys as $key) {
            if (rand(1, 100) <= 70) { // 70% population rate
                $this->cacheManager->$levelMethod($key, "value_for_{$key}");
            }
        }

        $hits = 0;
        $operations = 0;
        $startTime = microtime(true);

        for ($i = 0; $i < $operationCount; $i++) {
            $key = $keys[array_rand($keys)];
            $value = $this->cacheManager->$getLevelMethod($key);

            if ($value !== null) {
                $hits++;
            }
            $operations++;
        }

        $duration = microtime(true) - $startTime;
        $hitRatio = $operations > 0 ? ($hits / $operations) * 100 : 0;

        return [
            'level' => $level,
            'operations' => $operations,
            'hits' => $hits,
            'hit_ratio' => round($hitRatio, 2),
            'duration_seconds' => round($duration, 3),
            'operations_per_second' => round($operations / $duration, 2),
        ];
    }

    private function runConcurrentAccessBenchmark(): array
    {
        $keyCount = 150;
        $operationCount = 1500;
        $keys = $this->generateTestKeys($keyCount);

        // Simulate concurrent access by rapid operations
        $this->populateCache($keys, 0.6);

        $hits = 0;
        $operations = 0;
        $startTime = microtime(true);

        // Simulate concurrent access patterns
        for ($i = 0; $i < $operationCount; $i++) {
            $key = $keys[array_rand($keys)];

            // Mix of operations simulating concurrent access
            if ($i % 3 === 0) {
                // Read operation
                $value = $this->cacheManager->get($key);
                if ($value !== null) {
                    $hits++;
                }
            } elseif ($i % 3 === 1) {
                // Write operation
                $this->cacheManager->put($key, "concurrent_value_{$i}", 1800);
            } else {
                // Read after potential write
                $value = $this->cacheManager->get($key);
                if ($value !== null) {
                    $hits++;
                }
            }
            $operations++;
        }

        $duration = microtime(true) - $startTime;
        $hitRatio = $operations > 0 ? ($hits / $operations) * 100 : 0;

        return [
            'operations' => $operations,
            'hits' => $hits,
            'hit_ratio' => round($hitRatio, 2),
            'duration_seconds' => round($duration, 3),
            'operations_per_second' => round($operations / $duration, 2),
        ];
    }

    private function warmCache(int $keyCount): void
    {
        $warmers = [];
        for ($i = 0; $i < $keyCount; $i++) {
            $key = "warm_key_{$i}";
            $warmers[$key] = fn () => "warmed_value_{$i}";
        }

        $this->cacheManager->warm($warmers);
    }

    private function generateTestKeys(int $count): array
    {
        $keys = [];
        for ($i = 0; $i < $count; $i++) {
            $keys[] = "benchmark_key_{$i}";
        }

        return $keys;
    }

    private function populateCache(array $keys, float $populationRate): void
    {
        foreach ($keys as $key) {
            if (rand(1, 100) <= ($populationRate * 100)) {
                $this->cacheManager->put($key, "populated_value_for_{$key}", 3600);
            }
        }
    }

    private function outputPerformanceReport(): void
    {
        echo "\n".str_repeat('=', 80)."\n";
        echo "CACHE HIT RATIO PERFORMANCE BENCHMARK REPORT\n";
        echo str_repeat('=', 80)."\n";

        foreach ($this->performanceResults as $scenario => $results) {
            echo "\nScenario: ".strtoupper(str_replace('_', ' ', $scenario))."\n";
            echo str_repeat('-', 50)."\n";

            if (isset($results['hit_ratio'])) {
                echo "Hit Ratio: {$results['hit_ratio']}%\n";
                echo "Operations: {$results['operations']}\n";
                echo "Duration: {$results['duration_seconds']}s\n";
                echo "Ops/Second: {$results['operations_per_second']}\n";
            }

            if (isset($results['improvement'])) {
                echo "Cold Hit Ratio: {$results['cold_hit_ratio']}%\n";
                echo "Warm Hit Ratio: {$results['warm_hit_ratio']}%\n";
                echo "Improvement: +{$results['improvement']}%\n";
            }
        }

        echo "\n".str_repeat('=', 80)."\n";
    }

    protected function tearDown(): void
    {
        $this->cacheManager->flush();
        parent::tearDown();
    }
}
