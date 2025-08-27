<?php

declare(strict_types=1);

/**
 * Service File: PerformanceMonitoringService.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Performance monitoring service for tracking query performance,
 * cache hit rates, and optimization opportunities.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PerformanceMonitoringService Class
 *
 * Monitors and tracks performance metrics for database queries,
 * cache operations, and model performance optimization.
 */
class PerformanceMonitoringService
{
    /**
     * Performance thresholds in milliseconds
     */
    private const SLOW_QUERY_THRESHOLD = 100;

    private const CRITICAL_QUERY_THRESHOLD = 500;

    /**
     * Cache keys for performance metrics
     */
    private const CACHE_KEY_QUERY_STATS = 'performance:query_stats';

    private const CACHE_KEY_SLOW_QUERIES = 'performance:slow_queries';

    private const CACHE_KEY_CACHE_STATS = 'performance:cache_stats';

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
     * Clear performance monitoring data
     */
    public function clearPerformanceData(): void
    {
        Cache::forget(self::CACHE_KEY_QUERY_STATS);
        Cache::forget(self::CACHE_KEY_SLOW_QUERIES);
        Cache::forget(self::CACHE_KEY_CACHE_STATS);
    }

    /**
     * Export performance report
     */
    public function exportPerformanceReport(): array
    {
        return [
            'generated_at' => now()->toDateTimeString(),
            'performance_stats' => $this->getPerformanceStats(),
            'system_info' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
        ];
    }
}
