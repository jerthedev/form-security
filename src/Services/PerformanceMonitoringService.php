<?php

declare(strict_types=1);

/**
 * Service File: PerformanceMonitoringService.php
 *
 * EPIC: EPIC-006-analytics-monitoring
 * SPEC: SPEC-013-performance-monitoring-profiling
 * SPRINT: Sprint-005-performance-monitoring
 * TICKET: 2050-comprehensive-performance-monitoring
 *
 * Description: Comprehensive performance monitoring service for tracking
 * system performance, query execution, cache operations, and providing
 * real-time metrics with alerting capabilities.
 *
 * @see docs/Planning/Epics/EPIC-006-analytics-monitoring.md
 * @see docs/Planning/Specs/Analytics-Monitoring/SPEC-013-performance-monitoring-profiling.md
 */

namespace JTD\FormSecurity\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JTD\FormSecurity\Contracts\PerformanceMonitorInterface;

/**
 * PerformanceMonitoringService Class
 *
 * Comprehensive performance monitoring service that tracks system performance,
 * database queries, cache operations, memory usage, and provides real-time
 * metrics collection with intelligent alerting capabilities.
 */
class PerformanceMonitoringService implements PerformanceMonitorInterface
{
    /**
     * Performance thresholds in milliseconds
     */
    private const SLOW_QUERY_THRESHOLD = 100;

    private const CRITICAL_QUERY_THRESHOLD = 500;

    private const MEMORY_WARNING_THRESHOLD = 50 * 1024 * 1024; // 50MB

    private const MEMORY_CRITICAL_THRESHOLD = 100 * 1024 * 1024; // 100MB

    /**
     * Cache keys for performance metrics
     */
    private const CACHE_KEY_QUERY_STATS = 'performance:query_stats';

    private const CACHE_KEY_SLOW_QUERIES = 'performance:slow_queries';

    private const CACHE_KEY_CACHE_STATS = 'performance:cache_stats';

    private const CACHE_KEY_METRICS = 'performance:metrics';

    private const CACHE_KEY_TIMERS = 'performance:timers';

    private const CACHE_KEY_MEMORY = 'performance:memory';

    private const CACHE_KEY_THRESHOLDS = 'performance:thresholds';

    private const CACHE_KEY_ALERTS = 'performance:alerts';

    /**
     * Monitoring state
     */
    private bool $monitoringActive = false;

    private array $activeTimers = [];

    private array $thresholds = [];

    private int $startTime;

    private int $startMemory;

    /**
     * Start monitoring system performance
     */
    public function startMonitoring(): void
    {
        $this->monitoringActive = true;
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);

        // Start database query monitoring
        $this->startQueryMonitoring();

        // Initialize monitoring data structures
        $this->initializeMonitoringData();

        Log::info('Performance monitoring started', [
            'timestamp' => now()->toDateTimeString(),
            'memory_usage' => $this->formatBytes($this->startMemory),
        ]);
    }

    /**
     * Stop monitoring system performance
     */
    public function stopMonitoring(): void
    {
        if (! $this->monitoringActive) {
            return;
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $duration = ($endTime - $this->startTime) * 1000; // Convert to milliseconds

        $this->recordMetric('monitoring_session_duration', $duration);
        $this->recordMetric('memory_usage_change', $endMemory - $this->startMemory);

        $this->monitoringActive = false;

        Log::info('Performance monitoring stopped', [
            'duration_ms' => $duration,
            'memory_change' => $this->formatBytes($endMemory - $this->startMemory),
        ]);
    }

    /**
     * Start monitoring database queries
     */
    public function startQueryMonitoring(): void
    {
        DB::listen(function ($query) {
            $this->recordQueryPerformance($query);
        });
    }

    /**
     * Record query performance metrics
     */
    private function recordQueryPerformance($query): void
    {
        $executionTime = $query->time;
        $sql = $query->sql;
        $bindings = $query->bindings;

        // Update query statistics
        $this->updateQueryStatistics($executionTime);

        // Log slow queries
        if ($executionTime > self::SLOW_QUERY_THRESHOLD) {
            $this->recordSlowQuery($sql, $bindings, $executionTime);
        }

        // Alert on critical queries
        if ($executionTime > self::CRITICAL_QUERY_THRESHOLD) {
            $this->alertCriticalQuery($sql, $bindings, $executionTime);
        }
    }

    /**
     * Update query statistics
     */
    private function updateQueryStatistics(float $executionTime): void
    {
        $stats = Cache::get(self::CACHE_KEY_QUERY_STATS, [
            'total_queries' => 0,
            'total_time' => 0,
            'slow_queries' => 0,
            'critical_queries' => 0,
            'avg_time' => 0,
        ]);

        $stats['total_queries']++;
        $stats['total_time'] += $executionTime;
        $stats['avg_time'] = $stats['total_time'] / $stats['total_queries'];

        if ($executionTime > self::SLOW_QUERY_THRESHOLD) {
            $stats['slow_queries']++;
        }

        if ($executionTime > self::CRITICAL_QUERY_THRESHOLD) {
            $stats['critical_queries']++;
        }

        Cache::put(self::CACHE_KEY_QUERY_STATS, $stats, 3600); // 1 hour
    }

    /**
     * Record slow query for analysis
     */
    private function recordSlowQuery(string $sql, array $bindings, float $executionTime): void
    {
        $slowQueries = Cache::get(self::CACHE_KEY_SLOW_QUERIES, []);

        $queryHash = md5($sql);

        if (! isset($slowQueries[$queryHash])) {
            $slowQueries[$queryHash] = [
                'sql' => $sql,
                'count' => 0,
                'total_time' => 0,
                'max_time' => 0,
                'avg_time' => 0,
                'first_seen' => now()->toDateTimeString(),
                'last_seen' => now()->toDateTimeString(),
            ];
        }

        $slowQueries[$queryHash]['count']++;
        $slowQueries[$queryHash]['total_time'] += $executionTime;
        $slowQueries[$queryHash]['max_time'] = max($slowQueries[$queryHash]['max_time'], $executionTime);
        $slowQueries[$queryHash]['avg_time'] = $slowQueries[$queryHash]['total_time'] / $slowQueries[$queryHash]['count'];
        $slowQueries[$queryHash]['last_seen'] = now()->toDateTimeString();

        // Keep only the top 100 slow queries
        if (count($slowQueries) > 100) {
            uasort($slowQueries, function ($a, $b) {
                return $b['avg_time'] <=> $a['avg_time'];
            });
            $slowQueries = array_slice($slowQueries, 0, 100, true);
        }

        Cache::put(self::CACHE_KEY_SLOW_QUERIES, $slowQueries, 7200); // 2 hours
    }

    /**
     * Alert on critical query performance
     */
    private function alertCriticalQuery(string $sql, array $bindings, float $executionTime): void
    {
        Log::critical('Critical query performance detected', [
            'sql' => $sql,
            'bindings' => $bindings,
            'execution_time_ms' => $executionTime,
            'threshold_ms' => self::CRITICAL_QUERY_THRESHOLD,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get performance statistics
     */
    public function getPerformanceStats(): array
    {
        return [
            'query_stats' => Cache::get(self::CACHE_KEY_QUERY_STATS, []),
            'cache_stats' => $this->getCacheStatistics(),
            'slow_queries' => $this->getTopSlowQueries(10),
            'recommendations' => $this->generateOptimizationRecommendations(),
        ];
    }

    /**
     * Get cache statistics
     */
    private function getCacheStatistics(): array
    {
        // This would typically integrate with your cache driver's statistics
        // For now, we'll return basic metrics
        return [
            'hit_rate' => 0.85, // Example: 85% cache hit rate
            'miss_rate' => 0.15,
            'total_operations' => 10000,
            'memory_usage' => '50MB',
        ];
    }

    /**
     * Get top slow queries
     */
    public function getTopSlowQueries(int $limit = 10): array
    {
        $slowQueries = Cache::get(self::CACHE_KEY_SLOW_QUERIES, []);

        uasort($slowQueries, function ($a, $b) {
            return $b['avg_time'] <=> $a['avg_time'];
        });

        return array_slice($slowQueries, 0, $limit, true);
    }

    /**
     * Generate optimization recommendations
     */
    private function generateOptimizationRecommendations(): array
    {
        $recommendations = [];
        $queryStats = Cache::get(self::CACHE_KEY_QUERY_STATS, []);
        $slowQueries = Cache::get(self::CACHE_KEY_SLOW_QUERIES, []);

        // Check overall performance
        if (isset($queryStats['avg_time']) && $queryStats['avg_time'] > 50) {
            $recommendations[] = 'Overall query performance is below target. Consider query optimization.';
        }

        // Check slow query ratio
        if (isset($queryStats['slow_queries'], $queryStats['total_queries'])) {
            $slowRatio = $queryStats['slow_queries'] / $queryStats['total_queries'];
            if ($slowRatio > 0.1) { // More than 10% slow queries
                $recommendations[] = 'High percentage of slow queries detected. Review indexing strategy.';
            }
        }

        // Check for repeated slow queries
        foreach ($slowQueries as $query) {
            if ($query['count'] > 10 && $query['avg_time'] > 100) {
                $recommendations[] = 'Frequently executed slow query detected. Consider optimization or caching.';
                break;
            }
        }

        // Generic recommendations
        if (empty($recommendations)) {
            $recommendations[] = 'Performance is within acceptable limits. Continue monitoring.';
        }

        return $recommendations;
    }

    /**
     * Clear legacy performance monitoring data (backwards compatibility)
     */
    public function clearPerformanceData(): void
    {
        $this->clearData();
    }

    /**
     * Record a performance metric
     */
    public function recordMetric(string $name, float $value, array $tags = []): void
    {
        if (! $this->monitoringActive) {
            return;
        }

        $metrics = Cache::get(self::CACHE_KEY_METRICS, []);
        $timestamp = microtime(true);

        $metric = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => $timestamp,
            'datetime' => now()->toDateTimeString(),
        ];

        $metrics[] = $metric;

        // Keep only the last 1000 metrics per key to prevent memory issues
        if (count($metrics) > 1000) {
            $metrics = array_slice($metrics, -1000);
        }

        Cache::put(self::CACHE_KEY_METRICS, $metrics, 3600);

        // Check thresholds for this metric
        $this->checkMetricThreshold($name, $value, $tags);
    }

    /**
     * Start timing an operation
     */
    public function startTimer(string $operation): string
    {
        $timerId = Str::uuid()->toString();
        $this->activeTimers[$timerId] = [
            'operation' => $operation,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
        ];

        return $timerId;
    }

    /**
     * Stop timing an operation and record the duration
     */
    public function stopTimer(string $timerId): float
    {
        if (! isset($this->activeTimers[$timerId])) {
            throw new \InvalidArgumentException("Timer ID {$timerId} not found");
        }

        $timer = $this->activeTimers[$timerId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $duration = ($endTime - $timer['start_time']) * 1000; // Convert to milliseconds

        // Record metrics
        $this->recordMetric('operation_duration', $duration, [
            'operation' => $timer['operation'],
        ]);

        $this->recordMetric('operation_memory_usage', $endMemory - $timer['start_memory'], [
            'operation' => $timer['operation'],
        ]);

        unset($this->activeTimers[$timerId]);

        return $duration;
    }

    /**
     * Record memory usage at a specific checkpoint
     */
    public function recordMemoryUsage(string $checkpoint): void
    {
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $this->recordMetric('memory_usage', $memoryUsage, ['checkpoint' => $checkpoint]);
        $this->recordMetric('peak_memory_usage', $peakMemory, ['checkpoint' => $checkpoint]);

        // Check memory thresholds
        if ($memoryUsage > self::MEMORY_CRITICAL_THRESHOLD) {
            Log::critical('Critical memory usage detected', [
                'checkpoint' => $checkpoint,
                'memory_usage' => $this->formatBytes($memoryUsage),
                'peak_memory' => $this->formatBytes($peakMemory),
            ]);
        } elseif ($memoryUsage > self::MEMORY_WARNING_THRESHOLD) {
            Log::warning('High memory usage detected', [
                'checkpoint' => $checkpoint,
                'memory_usage' => $this->formatBytes($memoryUsage),
            ]);
        }
    }

    /**
     * Set performance threshold for alerting
     */
    public function setThreshold(string $metric, float $threshold, string $comparison = 'gt'): void
    {
        $this->thresholds[$metric] = [
            'threshold' => $threshold,
            'comparison' => $comparison,
            'created_at' => now()->toDateTimeString(),
        ];

        Cache::put(self::CACHE_KEY_THRESHOLDS, $this->thresholds, 7200);
    }

    /**
     * Get current performance metrics
     */
    public function getMetrics(array $filters = []): array
    {
        $metrics = Cache::get(self::CACHE_KEY_METRICS, []);

        if (empty($filters)) {
            return $metrics;
        }

        return array_filter($metrics, function ($metric) use ($filters) {
            foreach ($filters as $key => $value) {
                if ($key === 'name' && $metric['name'] !== $value) {
                    return false;
                }
                if ($key === 'tags' && ! empty(array_diff($value, $metric['tags']))) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Get performance statistics for a time period
     */
    public function getStatistics(string $period = '1h'): array
    {
        $metrics = $this->getMetrics();
        $cutoffTime = $this->getPeriodCutoffTime($period);

        $recentMetrics = array_filter($metrics, function ($metric) use ($cutoffTime) {
            return $metric['timestamp'] >= $cutoffTime;
        });

        return $this->calculateStatistics($recentMetrics);
    }

    /**
     * Generate performance report
     */
    public function generateReport(array $options = []): array
    {
        $period = $options['period'] ?? '1h';
        $includeDetails = $options['include_details'] ?? false;

        return [
            'generated_at' => now()->toDateTimeString(),
            'period' => $period,
            'system_info' => $this->getSystemInfo(),
            'performance_stats' => $this->getPerformanceStats(),
            'statistics' => $this->getStatistics($period),
            'slow_queries' => $this->getTopSlowQueries(10),
            'memory_analysis' => $this->getMemoryAnalysis(),
            'alerts' => $this->getRecentAlerts(),
            'recommendations' => $this->generateOptimizationRecommendations(),
            'details' => $includeDetails ? $this->getMetrics() : null,
        ];
    }

    /**
     * Check if performance thresholds are exceeded
     */
    public function checkThresholds(): array
    {
        $violations = [];
        $thresholds = Cache::get(self::CACHE_KEY_THRESHOLDS, []);
        $recentMetrics = $this->getMetrics();

        foreach ($thresholds as $metricName => $threshold) {
            $metricValues = array_filter($recentMetrics, function ($metric) use ($metricName) {
                return $metric['name'] === $metricName;
            });

            if (empty($metricValues)) {
                continue;
            }

            $latestMetric = end($metricValues);

            if ($this->compareValue($latestMetric['value'], $threshold['threshold'], $threshold['comparison'])) {
                $violations[] = [
                    'metric' => $metricName,
                    'value' => $latestMetric['value'],
                    'threshold' => $threshold['threshold'],
                    'comparison' => $threshold['comparison'],
                    'timestamp' => $latestMetric['datetime'],
                ];
            }
        }

        return $violations;
    }

    /**
     * Clear performance monitoring data
     */
    public function clearData(?string $before = null): void
    {
        if ($before === null) {
            Cache::forget(self::CACHE_KEY_QUERY_STATS);
            Cache::forget(self::CACHE_KEY_SLOW_QUERIES);
            Cache::forget(self::CACHE_KEY_CACHE_STATS);
            Cache::forget(self::CACHE_KEY_METRICS);
            Cache::forget(self::CACHE_KEY_TIMERS);
            Cache::forget(self::CACHE_KEY_MEMORY);
            Cache::forget(self::CACHE_KEY_ALERTS);
        } else {
            $cutoffTime = Carbon::parse($before)->timestamp;
            $this->clearDataBefore($cutoffTime);
        }
    }

    /**
     * Export performance report
     */
    public function exportPerformanceReport(): array
    {
        return $this->generateReport(['include_details' => true]);
    }

    /**
     * Initialize monitoring data structures
     */
    private function initializeMonitoringData(): void
    {
        // Load existing thresholds
        $this->thresholds = Cache::get(self::CACHE_KEY_THRESHOLDS, []);

        // Set default thresholds if none exist
        if (empty($this->thresholds)) {
            $this->setThreshold('operation_duration', self::SLOW_QUERY_THRESHOLD, 'gt');
            $this->setThreshold('memory_usage', self::MEMORY_WARNING_THRESHOLD, 'gt');
        }
    }

    /**
     * Check metric against thresholds
     */
    private function checkMetricThreshold(string $name, float $value, array $tags): void
    {
        if (! isset($this->thresholds[$name])) {
            return;
        }

        $threshold = $this->thresholds[$name];

        if ($this->compareValue($value, $threshold['threshold'], $threshold['comparison'])) {
            $this->recordAlert($name, $value, $threshold, $tags);
        }
    }

    /**
     * Record a performance alert
     */
    private function recordAlert(string $metric, float $value, array $threshold, array $tags): void
    {
        $alert = [
            'metric' => $metric,
            'value' => $value,
            'threshold' => $threshold['threshold'],
            'comparison' => $threshold['comparison'],
            'tags' => $tags,
            'timestamp' => microtime(true),
            'datetime' => now()->toDateTimeString(),
        ];

        $alerts = Cache::get(self::CACHE_KEY_ALERTS, []);
        $alerts[] = $alert;

        // Keep only the last 100 alerts
        if (count($alerts) > 100) {
            $alerts = array_slice($alerts, -100);
        }

        Cache::put(self::CACHE_KEY_ALERTS, $alerts, 7200);

        Log::warning('Performance threshold exceeded', $alert);
    }

    /**
     * Compare a value against a threshold
     */
    private function compareValue(float $value, float $threshold, string $comparison): bool
    {
        return match ($comparison) {
            'gt' => $value > $threshold,
            'gte' => $value >= $threshold,
            'lt' => $value < $threshold,
            'lte' => $value <= $threshold,
            'eq' => abs($value - $threshold) < 0.001, // Float comparison with epsilon
            default => false,
        };
    }

    /**
     * Get period cutoff time
     */
    private function getPeriodCutoffTime(string $period): float
    {
        $now = microtime(true);

        return match ($period) {
            '1m' => $now - 60,
            '5m' => $now - 300,
            '15m' => $now - 900,
            '30m' => $now - 1800,
            '1h' => $now - 3600,
            '6h' => $now - 21600,
            '12h' => $now - 43200,
            '24h' => $now - 86400,
            default => $now - 3600, // Default to 1 hour
        };
    }

    /**
     * Calculate statistics from metrics
     */
    private function calculateStatistics(array $metrics): array
    {
        if (empty($metrics)) {
            return [];
        }

        $groupedMetrics = [];
        foreach ($metrics as $metric) {
            $groupedMetrics[$metric['name']][] = $metric['value'];
        }

        $statistics = [];
        foreach ($groupedMetrics as $name => $values) {
            $statistics[$name] = [
                'count' => count($values),
                'min' => min($values),
                'max' => max($values),
                'avg' => array_sum($values) / count($values),
                'median' => $this->calculateMedian($values),
                'p95' => $this->calculatePercentile($values, 95),
                'p99' => $this->calculatePercentile($values, 99),
            ];
        }

        return $statistics;
    }

    /**
     * Calculate median value
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intval($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Calculate percentile value
     */
    private function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $count = count($values);
        $index = ($percentile / 100) * ($count - 1);

        if (floor($index) === $index) {
            return $values[intval($index)];
        }

        $lower = $values[intval(floor($index))];
        $upper = $values[intval(ceil($index))];
        $fraction = $index - floor($index);

        return $lower + ($fraction * ($upper - $lower));
    }

    /**
     * Get memory analysis
     */
    private function getMemoryAnalysis(): array
    {
        return [
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'current_formatted' => $this->formatBytes(memory_get_usage(true)),
            'peak_formatted' => $this->formatBytes(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * Get recent alerts
     */
    private function getRecentAlerts(int $limit = 20): array
    {
        $alerts = Cache::get(self::CACHE_KEY_ALERTS, []);

        return array_slice($alerts, -$limit);
    }

    /**
     * Get system information
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'server_time' => now()->toDateTimeString(),
            'timezone' => config('app.timezone'),
        ];
    }

    /**
     * Clear data before a specific timestamp
     */
    private function clearDataBefore(float $cutoffTime): void
    {
        $keys = [
            self::CACHE_KEY_METRICS,
            self::CACHE_KEY_ALERTS,
        ];

        foreach ($keys as $key) {
            $data = Cache::get($key, []);
            $filtered = array_filter($data, function ($item) use ($cutoffTime) {
                return $item['timestamp'] >= $cutoffTime;
            });
            Cache::put($key, array_values($filtered), 3600);
        }
    }

    /**
     * Format bytes into human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
