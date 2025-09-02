<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services\Cache\Validation;

use Illuminate\Cache\CacheManager as LaravelCacheManager;
use JTD\FormSecurity\Contracts\Cache\CacheValidationServiceInterface;
use JTD\FormSecurity\Enums\CacheLevel;
use JTD\FormSecurity\Services\Cache\Traits\CacheErrorHandlingTrait;
use JTD\FormSecurity\Services\Cache\Traits\CacheUtilitiesTrait;

/**
 * CacheValidationService
 */
class CacheValidationService implements CacheValidationServiceInterface
{
    use CacheErrorHandlingTrait;
    use CacheUtilitiesTrait;

    private array $stats = [];

    public function __construct(
        private LaravelCacheManager $cacheManager
    ) {
        $this->initializeRepositories();
    }

    /**
     * Validate cache performance against SPEC-003 requirements
     */
    public function validatePerformance(): array
    {
        $results = [
            'overall_status' => 'unknown',
            'timestamp' => time(),
            'requirements' => [
                'memory_cache_response_time' => ['target' => 5, 'unit' => 'ms', 'status' => 'unknown', 'actual' => null],
                'database_cache_response_time' => ['target' => 20, 'unit' => 'ms', 'status' => 'unknown', 'actual' => null],
                'throughput' => ['target' => 10000, 'unit' => 'ops/minute', 'status' => 'unknown', 'actual' => null],
                'hit_ratio' => ['target' => 85, 'unit' => '%', 'status' => 'unknown', 'actual' => null],
            ],
            'details' => [],
            'recommendations' => [],
        ];

        try {
            // Test memory cache response time (SPEC-003 NFR-001: 5ms)
            $memoryResponseTime = $this->measureCacheResponseTime(CacheLevel::MEMORY);
            $results['requirements']['memory_cache_response_time']['actual'] = $memoryResponseTime;
            $results['requirements']['memory_cache_response_time']['status'] =
                $memoryResponseTime <= 5 ? 'pass' : 'fail';

            // Test database cache response time (SPEC-003 NFR-001: 20ms)
            $databaseResponseTime = $this->measureCacheResponseTime(CacheLevel::DATABASE);
            $results['requirements']['database_cache_response_time']['actual'] = $databaseResponseTime;
            $results['requirements']['database_cache_response_time']['status'] =
                $databaseResponseTime <= 20 ? 'pass' : 'fail';

            // Test throughput (SPEC-003 NFR-002: 10,000 ops/minute)
            $throughput = $this->measureCacheThroughput();
            $results['requirements']['throughput']['actual'] = $throughput;
            $results['requirements']['throughput']['status'] =
                $throughput >= 10000 ? 'pass' : 'fail';

            // Test hit ratio (SPEC-003 NFR-004: 85%+)
            $hitRatio = $this->getHitRatio();
            $results['requirements']['hit_ratio']['actual'] = $hitRatio;
            $results['requirements']['hit_ratio']['status'] =
                $hitRatio >= 85 ? 'pass' : 'fail';

            // Determine overall status
            $failedTests = array_filter($results['requirements'], fn ($req) => $req['status'] === 'fail');
            $results['overall_status'] = empty($failedTests) ? 'pass' : 'fail';

            // Generate recommendations for failed tests
            $results['recommendations'] = $this->generatePerformanceRecommendations($results['requirements']);

        } catch (\Exception $e) {
            $results['overall_status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Validate cache size and capacity against SPEC-003 requirements (up to 10GB)
     */
    public function validateCacheCapacity(): array
    {
        $results = [
            'overall_status' => 'unknown',
            'timestamp' => time(),
            'capacity_limits' => [
                'total_limit_bytes' => 10 * 1024 * 1024 * 1024, // 10GB
                'memory_limit_bytes' => 8 * 1024 * 1024 * 1024,  // 8GB for memory cache
                'database_limit_bytes' => 2 * 1024 * 1024 * 1024, // 2GB for database cache
            ],
            'current_usage' => [],
            'level_details' => [],
            'warnings' => [],
            'recommendations' => [],
        ];

        try {
            $totalUsage = 0;

            // Check each cache level
            foreach (CacheLevel::cases() as $level) {
                $levelUsage = $this->measureCacheLevelSize($level);
                $results['current_usage'][$level->value] = $levelUsage;
                $totalUsage += $levelUsage['size_bytes'];

                // Check level-specific limits
                $levelLimit = $this->getCacheLevelLimit($level);
                $usagePercent = $levelLimit > 0 ? ($levelUsage['size_bytes'] / $levelLimit) * 100 : 0;

                $results['level_details'][$level->value] = [
                    'limit_bytes' => $levelLimit,
                    'usage_bytes' => $levelUsage['size_bytes'],
                    'usage_percent' => round($usagePercent, 2),
                    'entry_count' => $levelUsage['entry_count'],
                    'average_entry_size' => $levelUsage['average_entry_size'],
                    'status' => $usagePercent > 90 ? 'critical' : ($usagePercent > 75 ? 'warning' : 'ok'),
                ];

                // Generate warnings for high usage
                if ($usagePercent > 90) {
                    $results['warnings'][] = [
                        'level' => $level->value,
                        'type' => 'critical_usage',
                        'message' => "Cache level {$level->value} is at {$usagePercent}% capacity",
                        'action_required' => true,
                    ];
                } elseif ($usagePercent > 75) {
                    $results['warnings'][] = [
                        'level' => $level->value,
                        'type' => 'high_usage',
                        'message' => "Cache level {$level->value} is at {$usagePercent}% capacity",
                        'action_required' => false,
                    ];
                }
            }

            // Check total usage against 10GB limit
            $totalUsagePercent = ($totalUsage / $results['capacity_limits']['total_limit_bytes']) * 100;
            $results['total_usage'] = [
                'size_bytes' => $totalUsage,
                'size_human' => $this->formatBytes($totalUsage),
                'usage_percent' => round($totalUsagePercent, 2),
                'status' => $totalUsagePercent > 90 ? 'critical' : ($totalUsagePercent > 75 ? 'warning' : 'ok'),
            ];

            // Determine overall status
            $results['overall_status'] = $this->determineCacheCapacityStatus($results);

            // Generate capacity management recommendations
            $results['recommendations'] = $this->generateCapacityRecommendations($results);

        } catch (\Exception $e) {
            $results['overall_status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Validate concurrent operation support up to 10,000 requests per minute
     */
    public function validateConcurrentOperations(int $targetRpm = 10000, int $testDurationSeconds = 60): array
    {

        $startTime = microtime(true);
        $targetRps = $targetRpm / 60; // Requests per second

        $results = [
            'summary' => [
                'target_rpm' => $targetRpm,
                'target_rps' => round($targetRps, 2),
                'test_duration_seconds' => $testDurationSeconds,
                'start_time' => $startTime,
                'end_time' => null,
                'total_operations' => 0,
                'successful_operations' => 0,
                'failed_operations' => 0,
                'actual_rps' => 0.0,
                'actual_rpm' => 0.0,
                'success_rate' => 0.0,
                'meets_requirements' => false,
            ],
            'performance_metrics' => [
                'avg_response_time_ms' => 0,
                'min_response_time_ms' => PHP_FLOAT_MAX,
                'max_response_time_ms' => 0,
                'p95_response_time_ms' => 0,
                'p99_response_time_ms' => 0,
                'memory_usage_peak_mb' => 0,
                'memory_usage_avg_mb' => 0,
            ],
            'level_performance' => [],
            'errors' => [],
            'recommendations' => [],
        ];

        try {
            // Test each cache level individually
            foreach (CacheLevel::cases() as $level) {
                try {
                    $levelResults = $this->testConcurrentOperationsForLevel($level, $targetRps, $testDurationSeconds);
                    $results['level_performance'][$level->value] = $levelResults;

                    $results['summary']['total_operations'] += $levelResults['operations_completed'] ?? 0;
                    $results['summary']['successful_operations'] += $levelResults['operations_completed'] ?? 0;
                    $results['summary']['failed_operations'] += $levelResults['errors'] ?? 0;
                } catch (\Exception $e) {
                    // If a level fails, add a default result
                    $results['level_performance'][$level->value] = [
                        'level' => $level->value,
                        'target_rpm' => $targetRpm,
                        'actual_rpm' => 0.0,
                        'operations_completed' => 0,
                        'errors' => 1,
                        'success_rate' => 0.0,
                        'avg_response_time_ms' => 0.0,
                        'max_response_time_ms' => 0.0,
                        'min_response_time_ms' => 0.0,
                        'duration_seconds' => 0.0,
                        'error' => $e->getMessage(),
                    ];
                    $results['summary']['failed_operations'] += 1;
                }
            }

            // Test combined operations across all levels
            $combinedResults = $this->testCombinedConcurrentOperations($targetRps, $testDurationSeconds);
            $results['combined_performance'] = $combinedResults;

            // Calculate overall metrics
            $totalDuration = microtime(true) - $startTime;
            $results['summary']['end_time'] = microtime(true);
            $results['summary']['actual_rps'] = $totalDuration > 0 ?
                round($results['summary']['total_operations'] / $totalDuration, 2) : 0.0;
            $results['summary']['actual_rpm'] = (float) ($results['summary']['actual_rps'] * 60);
            $results['summary']['success_rate'] = $results['summary']['total_operations'] > 0 ?
                round(($results['summary']['successful_operations'] / $results['summary']['total_operations']) * 100, 2) : 0.0;
            $results['summary']['meets_requirements'] =
                $results['summary']['actual_rpm'] >= $targetRpm && $results['summary']['success_rate'] >= 95;

            // Generate performance recommendations
            $results['recommendations'] = $this->generateConcurrencyRecommendations($results);

        } catch (\Exception $e) {
            $results['errors'][] = [
                'type' => 'validation_error',
                'message' => $e->getMessage(),
                'timestamp' => microtime(true),
            ];
        }

        return $results;
    }

    /**
     * Implement automatic capacity management
     */
    public function manageCapacity(array $options = []): array
    {
        $results = [
            'actions_taken' => [],
            'capacity_before' => [],
            'capacity_after' => [],
            'success' => false,
        ];

        try {
            // Get current capacity status
            $capacityStatus = $this->validateCacheCapacity();
            $results['capacity_before'] = $capacityStatus;

            // Take actions based on capacity status
            if ($capacityStatus['overall_status'] === 'critical') {
                $results['actions_taken'] = $this->performEmergencyCapacityManagement($options);
            } elseif ($capacityStatus['overall_status'] === 'warning') {
                $results['actions_taken'] = $this->performPreventiveCapacityManagement($options);
            }

            // Get updated capacity status
            $results['capacity_after'] = $this->validateCacheCapacity();
            $results['success'] = $results['capacity_after']['overall_status'] !== 'critical';

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Test concurrent operations for a specific cache level
     */
    private function testConcurrentOperationsForLevel(CacheLevel $level, float $targetRpm, int $testDurationSeconds): array
    {
        $startTime = microtime(true);
        $operations = 0;
        $errors = 0;
        $responseTimes = [];

        $endTime = $startTime + $testDurationSeconds;
        $targetOpsPerSecond = $targetRpm / 60;

        while (microtime(true) < $endTime) {
            $opStart = microtime(true);

            try {
                // Perform a test operation (put/get cycle)
                $testKey = "concurrent_test_{$level->value}_" . $operations;
                $testValue = "test_value_" . time();

                // Test put operation
                $repository = $this->repositories[$level->value] ?? null;
                if ($repository) {
                    $repository->put($testKey, $testValue, 60);
                    $retrieved = $repository->get($testKey);
                    $repository->forget($testKey);

                    if ($retrieved === $testValue) {
                        $operations++;
                    } else {
                        $errors++;
                    }
                } else {
                    $errors++;
                }
            } catch (\Exception $e) {
                $errors++;
            }

            $opEnd = microtime(true);
            $responseTimes[] = ($opEnd - $opStart) * 1000; // Convert to milliseconds

            // Throttle to target rate
            $expectedDuration = 1.0 / $targetOpsPerSecond;
            $actualDuration = $opEnd - $opStart;
            if ($actualDuration < $expectedDuration) {
                usleep((int)(($expectedDuration - $actualDuration) * 1000000));
            }
        }

        $totalDuration = microtime(true) - $startTime;
        $actualRpm = ($operations / $totalDuration) * 60;

        return [
            'level' => $level->value,
            'target_rpm' => $targetRpm,
            'actual_rpm' => round($actualRpm, 2),
            'operations_completed' => $operations,
            'errors' => $errors,
            'success_rate' => $operations > 0 ? round(($operations / ($operations + $errors)) * 100, 2) : 0,
            'avg_response_time_ms' => !empty($responseTimes) ? round(array_sum($responseTimes) / count($responseTimes), 2) : 0,
            'max_response_time_ms' => !empty($responseTimes) ? round(max($responseTimes), 2) : 0,
            'min_response_time_ms' => !empty($responseTimes) ? round(min($responseTimes), 2) : 0,
            'duration_seconds' => round($totalDuration, 2),
        ];
    }

    /**
     * Test combined concurrent operations across all levels
     */
    private function testCombinedConcurrentOperations(float $targetRps, int $testDurationSeconds): array
    {
        $startTime = microtime(true);
        $operations = 0;
        $errors = 0;
        $responseTimes = [];

        $endTime = $startTime + $testDurationSeconds;

        while (microtime(true) < $endTime) {
            $opStart = microtime(true);

            try {
                // Test operations across all levels
                $testKey = "combined_test_" . $operations;
                $testValue = "test_value_" . time();

                // Test all levels in sequence
                foreach (CacheLevel::cases() as $level) {
                    $repository = $this->repositories[$level->value] ?? null;
                    if ($repository) {
                        $repository->put($testKey, $testValue, 60);
                        $retrieved = $repository->get($testKey);
                        $repository->forget($testKey);

                        if ($retrieved !== $testValue) {
                            $errors++;
                        }
                    } else {
                        $errors++;
                    }
                }

                $operations++;
            } catch (\Exception $e) {
                $errors++;
            }

            $opEnd = microtime(true);
            $responseTimes[] = ($opEnd - $opStart) * 1000; // Convert to milliseconds

            // Throttle to target rate
            $expectedDuration = 1.0 / $targetRps;
            $actualDuration = $opEnd - $opStart;
            if ($actualDuration < $expectedDuration) {
                usleep((int)(($expectedDuration - $actualDuration) * 1000000));
            }
        }

        $totalDuration = microtime(true) - $startTime;
        $actualRpm = ($operations / $totalDuration) * 60;

        return [
            'target_rpm' => $targetRps * 60,
            'actual_rpm' => round($actualRpm, 2),
            'operations_completed' => $operations,
            'errors' => $errors,
            'success_rate' => $operations > 0 ? round(($operations / ($operations + $errors)) * 100, 2) : 0,
            'avg_response_time_ms' => !empty($responseTimes) ? round(array_sum($responseTimes) / count($responseTimes), 2) : 0,
            'max_response_time_ms' => !empty($responseTimes) ? round(max($responseTimes), 2) : 0,
            'min_response_time_ms' => !empty($responseTimes) ? round(min($responseTimes), 2) : 0,
            'duration_seconds' => round($totalDuration, 2),
        ];
    }

    /**
     * Generate concurrency recommendations based on test results
     */
    private function generateConcurrencyRecommendations(array $results): array
    {
        $recommendations = [];

        $actualRpm = $results['summary']['actual_rpm'] ?? 0;
        $targetRpm = $results['summary']['target_rpm'] ?? 0;
        $successRate = $results['summary']['success_rate'] ?? 0;

        if ($actualRpm < $targetRpm * 0.8) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'message' => 'Actual RPM is significantly below target. Consider optimizing cache operations.',
                'suggestion' => 'Review cache configuration and consider increasing cache sizes or optimizing queries.',
            ];
        }

        if ($successRate < 95) {
            $recommendations[] = [
                'type' => 'reliability',
                'priority' => 'high',
                'message' => 'Success rate is below 95%. Investigate error causes.',
                'suggestion' => 'Check error logs and consider implementing retry mechanisms.',
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'status',
                'priority' => 'info',
                'message' => 'Concurrent operations are performing within acceptable parameters.',
                'suggestion' => 'Continue monitoring performance metrics.',
            ];
        }

        return $recommendations;
    }

    /**
     * Measure cache response time for a specific cache level.
     *
     * @param CacheLevel $level The cache level to measure
     * @return float Response time in milliseconds
     */
    private function measureCacheResponseTime(CacheLevel $level): float
    {
        $testKey = 'response_time_test_' . uniqid();
        $testValue = 'test_value_' . time();

        $startTime = microtime(true);

        // Perform cache operation based on level
        switch ($level) {
            case CacheLevel::MEMORY:
                $this->cacheManager->store('array')->put($testKey, $testValue, 60);
                $result = $this->cacheManager->store('array')->get($testKey);
                break;
            case CacheLevel::DATABASE:
                $this->cacheManager->store('database')->put($testKey, $testValue, 60);
                $result = $this->cacheManager->store('database')->get($testKey);
                break;
            default:
                $this->cacheManager->put($testKey, $testValue, 60);
                $result = $this->cacheManager->get($testKey);
                break;
        }

        $endTime = microtime(true);

        // Clean up test data
        try {
            $this->cacheManager->forget($testKey);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }

        return ($endTime - $startTime) * 1000; // Convert to milliseconds
    }

    /**
     * Measure cache level size and usage.
     *
     * @param CacheLevel $level The cache level to measure
     * @return array Size information including keys count and memory usage
     */
    private function measureCacheLevelSize(CacheLevel $level): array
    {
        try {
            switch ($level) {
                case CacheLevel::MEMORY:
                    // For array cache, we can estimate size
                    return [
                        'keys' => 0, // Array cache doesn't provide key count easily
                        'memory_usage' => memory_get_usage(),
                        'estimated_size' => 0
                    ];
                case CacheLevel::DATABASE:
                    // For database cache, we could query the cache table
                    return [
                        'keys' => 0, // Would need to query cache table
                        'memory_usage' => 0,
                        'estimated_size' => 0
                    ];
                default:
                    return [
                        'keys' => 0,
                        'memory_usage' => memory_get_usage(),
                        'estimated_size' => 0
                    ];
            }
        } catch (\Exception $e) {
            return [
                'keys' => 0,
                'memory_usage' => 0,
                'estimated_size' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
}
