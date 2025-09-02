<?php

declare(strict_types=1);

/**
 * Service File: CachePerformanceMonitor.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Comprehensive cache performance monitoring service
 * with statistics collection, hit ratio tracking, and performance metrics.
 */

namespace JTD\FormSecurity\Services;

use Illuminate\Support\Facades\Log;
use JTD\FormSecurity\Contracts\CacheManagerInterface;
use JTD\FormSecurity\Enums\CacheLevel;

/**
 * CachePerformanceMonitor Service
 *
 * Monitors cache performance across all levels, tracks metrics,
 * generates reports, and provides alerts for performance issues.
 */
class CachePerformanceMonitor
{
    /**
     * Performance metrics storage
     *
     * @var array<string, mixed>
     */
    private array $metrics = [];

    /**
     * Performance thresholds for alerts
     *
     * @var array<string, mixed>
     */
    private array $thresholds = [
        'hit_ratio_warning' => 70.0, // Below 70% hit ratio
        'hit_ratio_critical' => 50.0, // Below 50% hit ratio
        'response_time_warning' => 10.0, // Above 10ms average
        'response_time_critical' => 50.0, // Above 50ms average
        'memory_usage_warning' => 80.0, // Above 80% memory usage
        'memory_usage_critical' => 95.0, // Above 95% memory usage
    ];

    /**
     * Alert history
     *
     * @var array<array<string, mixed>>
     */
    private array $alerts = [];

    public function __construct(
        private CacheManagerInterface $cacheManager
    ) {
        $this->initializeMetrics();
    }

    /**
     * Collect current performance metrics
     */
    public function collectMetrics(): array
    {
        $timestamp = now()->timestamp;

        // Get basic cache statistics
        $cacheStats = $this->cacheManager->getStats();

        // Get level-specific statistics
        $levelStats = [];
        foreach (CacheLevel::cases() as $level) {
            $levelStats[$level->value] = $this->collectLevelMetrics($level);
        }

        // Calculate derived metrics
        $derivedMetrics = $this->calculateDerivedMetrics($cacheStats, $levelStats);

        $metrics = [
            'timestamp' => $timestamp,
            'cache_stats' => $cacheStats,
            'level_stats' => $levelStats,
            'derived_metrics' => $derivedMetrics,
            'system_metrics' => $this->collectSystemMetrics(),
        ];

        // Store metrics for historical tracking
        $this->storeMetrics($metrics);

        // Check for performance issues
        $this->checkPerformanceThresholds($metrics);

        return $metrics;
    }

    /**
     * Get performance report for a specific time period
     */
    public function getPerformanceReport(int $hours = 24): array
    {
        $cutoffTime = now()->subHours($hours)->timestamp;
        $recentMetrics = array_filter(
            $this->metrics,
            fn ($metric) => $metric['timestamp'] >= $cutoffTime
        );

        if (empty($recentMetrics)) {
            return ['error' => 'No metrics available for the specified period'];
        }

        return [
            'period' => "{$hours} hours",
            'metrics_count' => count($recentMetrics),
            'summary' => $this->generateSummaryReport($recentMetrics),
            'trends' => $this->analyzeTrends($recentMetrics),
            'alerts' => $this->getRecentAlerts($hours),
            'recommendations' => $this->generateRecommendations($recentMetrics),
        ];
    }

    /**
     * Get real-time performance dashboard data
     */
    public function getDashboardData(): array
    {
        $currentMetrics = $this->collectMetrics();
        $recentAlerts = $this->getRecentAlerts(1); // Last hour

        return [
            'current_metrics' => $currentMetrics,
            'health_status' => $this->calculateHealthStatus($currentMetrics),
            'recent_alerts' => $recentAlerts,
            'quick_stats' => $this->getQuickStats($currentMetrics),
            'level_comparison' => $this->compareLevelPerformance($currentMetrics['level_stats']),
        ];
    }

    /**
     * Set custom performance thresholds
     */
    public function setThresholds(array $thresholds): void
    {
        $this->thresholds = array_merge($this->thresholds, $thresholds);
    }

    /**
     * Get current performance thresholds
     */
    public function getThresholds(): array
    {
        return $this->thresholds;
    }

    /**
     * Get recent alerts
     */
    public function getRecentAlerts(int $hours = 24): array
    {
        $cutoffTime = now()->subHours($hours)->timestamp;

        return array_filter(
            $this->alerts,
            fn ($alert) => $alert['timestamp'] >= $cutoffTime
        );
    }

    /**
     * Clear old metrics and alerts
     */
    public function cleanup(int $retentionHours = 168): void // 7 days default
    {
        $cutoffTime = now()->subHours($retentionHours)->timestamp;

        $this->metrics = array_filter(
            $this->metrics,
            fn ($metric) => $metric['timestamp'] >= $cutoffTime
        );

        $this->alerts = array_filter(
            $this->alerts,
            fn ($alert) => $alert['timestamp'] >= $cutoffTime
        );
    }

    /**
     * Initialize metrics storage
     */
    private function initializeMetrics(): void
    {
        $this->metrics = [];
        $this->alerts = [];
    }

    /**
     * Collect metrics for a specific cache level
     */
    private function collectLevelMetrics(CacheLevel $level): array
    {
        return [
            'level' => $level->value,
            'priority' => $level->getPriority(),
            'default_ttl' => $level->getDefaultTtl(),
            'max_ttl' => $level->getMaxTtl(),
            'expected_response_time' => $level->getResponseTimeRange(),
            'supports_tagging' => $level->supportsTagging(),
            'supports_distribution' => $level->supportsDistribution(),
            'driver' => $level->getDriverName(),
        ];
    }

    /**
     * Calculate derived performance metrics
     */
    private function calculateDerivedMetrics(array $cacheStats, array $levelStats): array
    {
        $totalOperations = $cacheStats['hits'] + $cacheStats['misses'];

        return [
            'total_operations' => $totalOperations,
            'operations_per_second' => $totalOperations > 0 ? $totalOperations / 60 : 0, // Rough estimate
            'cache_efficiency' => $this->calculateCacheEfficiency($cacheStats),
            'level_utilization' => $this->calculateLevelUtilization($levelStats),
            'performance_score' => $this->calculatePerformanceScore($cacheStats),
        ];
    }

    /**
     * Collect system-level metrics
     */
    private function collectSystemMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'php_version' => PHP_VERSION,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Store metrics for historical tracking
     */
    private function storeMetrics(array $metrics): void
    {
        $this->metrics[] = $metrics;

        // Keep only last 1000 metric entries to prevent memory bloat
        if (count($this->metrics) > 1000) {
            array_shift($this->metrics);
        }
    }

    /**
     * Check performance against thresholds and generate alerts
     */
    private function checkPerformanceThresholds(array $metrics): void
    {
        $hitRatio = $metrics['cache_stats']['hit_ratio'] ?? 0;
        $avgResponseTime = $metrics['cache_stats']['average_response_time'] ?? 0;

        // Check hit ratio thresholds
        if ($hitRatio < $this->thresholds['hit_ratio_critical']) {
            $this->createAlert('critical', 'hit_ratio', "Cache hit ratio critically low: {$hitRatio}%");
        } elseif ($hitRatio < $this->thresholds['hit_ratio_warning']) {
            $this->createAlert('warning', 'hit_ratio', "Cache hit ratio below optimal: {$hitRatio}%");
        }

        // Check response time thresholds
        if ($avgResponseTime > $this->thresholds['response_time_critical']) {
            $this->createAlert('critical', 'response_time', "Average response time critically high: {$avgResponseTime}ms");
        } elseif ($avgResponseTime > $this->thresholds['response_time_warning']) {
            $this->createAlert('warning', 'response_time', "Average response time above optimal: {$avgResponseTime}ms");
        }
    }

    /**
     * Create a performance alert
     */
    private function createAlert(string $severity, string $type, string $message): void
    {
        $alert = [
            'timestamp' => now()->timestamp,
            'severity' => $severity,
            'type' => $type,
            'message' => $message,
            'id' => uniqid('alert_'),
        ];

        $this->alerts[] = $alert;

        // Log the alert
        Log::channel('cache')->log($severity, $message, $alert);
    }

    /**
     * Calculate cache efficiency score
     */
    private function calculateCacheEfficiency(array $stats): float
    {
        $hitRatio = $stats['hit_ratio'] ?? 0;
        $totalOps = ($stats['hits'] ?? 0) + ($stats['misses'] ?? 0);

        if ($totalOps === 0) {
            return 0.0;
        }

        // Efficiency considers both hit ratio and operation volume
        return ($hitRatio / 100) * min(1.0, $totalOps / 1000);
    }

    /**
     * Calculate performance score (0-100)
     */
    private function calculatePerformanceScore(array $stats): float
    {
        $hitRatio = $stats['hit_ratio'] ?? 0;
        $avgResponseTime = $stats['average_response_time'] ?? 0;

        // Score based on hit ratio (70%) and response time (30%)
        $hitRatioScore = min(100, $hitRatio);
        $responseTimeScore = max(0, 100 - ($avgResponseTime * 2)); // Penalty for slow response

        return ($hitRatioScore * 0.7) + ($responseTimeScore * 0.3);
    }

    /**
     * Calculate level utilization
     */
    private function calculateLevelUtilization(array $levelStats): array
    {
        $utilization = [];

        foreach ($levelStats as $level => $stats) {
            $utilization[$level] = [
                'priority' => $stats['priority'],
                'efficiency_rating' => $this->rateLevelEfficiency($stats),
            ];
        }

        return $utilization;
    }

    /**
     * Rate the efficiency of a cache level
     */
    private function rateLevelEfficiency(array $stats): string
    {
        // This is a simplified rating - in practice, you'd use actual performance data
        return match ($stats['priority']) {
            1 => 'excellent', // Request level
            2 => 'good',      // Memory level
            3 => 'adequate',  // Database level
            default => 'unknown',
        };
    }

    /**
     * Generate summary report from metrics
     */
    private function generateSummaryReport(array $metrics): array
    {
        if (empty($metrics)) {
            return [];
        }

        $hitRatios = array_column(array_column($metrics, 'cache_stats'), 'hit_ratio');
        $responseTimes = array_column(array_column($metrics, 'cache_stats'), 'average_response_time');

        return [
            'avg_hit_ratio' => count($hitRatios) > 0 ? array_sum($hitRatios) / count($hitRatios) : 0,
            'avg_response_time' => count($responseTimes) > 0 ? array_sum($responseTimes) / count($responseTimes) : 0,
            'min_hit_ratio' => count($hitRatios) > 0 ? min($hitRatios) : 0,
            'max_hit_ratio' => count($hitRatios) > 0 ? max($hitRatios) : 0,
            'min_response_time' => count($responseTimes) > 0 ? min($responseTimes) : 0,
            'max_response_time' => count($responseTimes) > 0 ? max($responseTimes) : 0,
        ];
    }

    /**
     * Analyze performance trends
     */
    private function analyzeTrends(array $metrics): array
    {
        // Simplified trend analysis - in practice, you'd use more sophisticated algorithms
        return [
            'trend_direction' => 'stable', // Could be 'improving', 'degrading', 'stable'
            'trend_confidence' => 'medium',
            'notable_changes' => [],
        ];
    }

    /**
     * Generate performance recommendations
     */
    private function generateRecommendations(array $metrics): array
    {
        $recommendations = [];

        if (empty($metrics)) {
            return $recommendations;
        }

        $summary = $this->generateSummaryReport($metrics);

        if ($summary['avg_hit_ratio'] < 70) {
            $recommendations[] = 'Consider increasing cache TTL values or reviewing cache key strategies';
        }

        if ($summary['avg_response_time'] > 10) {
            $recommendations[] = 'Investigate slow cache operations and consider optimizing cache drivers';
        }

        return $recommendations;
    }

    /**
     * Calculate overall health status
     */
    private function calculateHealthStatus(array $metrics): string
    {
        $hitRatio = $metrics['cache_stats']['hit_ratio'] ?? 0;
        $avgResponseTime = $metrics['cache_stats']['average_response_time'] ?? 0;

        if ($hitRatio >= 80 && $avgResponseTime <= 5) {
            return 'excellent';
        } elseif ($hitRatio >= 60 && $avgResponseTime <= 15) {
            return 'good';
        } elseif ($hitRatio >= 40 && $avgResponseTime <= 30) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /**
     * Get quick statistics for dashboard
     */
    private function getQuickStats(array $metrics): array
    {
        return [
            'hit_ratio' => $metrics['cache_stats']['hit_ratio'] ?? 0,
            'total_hits' => $metrics['cache_stats']['hits'] ?? 0,
            'total_misses' => $metrics['cache_stats']['misses'] ?? 0,
            'avg_response_time' => $metrics['cache_stats']['average_response_time'] ?? 0,
            'health_status' => $this->calculateHealthStatus($metrics),
        ];
    }

    /**
     * Compare performance across cache levels
     */
    private function compareLevelPerformance(array $levelStats): array
    {
        $comparison = [];

        foreach ($levelStats as $level => $stats) {
            $comparison[$level] = [
                'priority' => $stats['priority'],
                'expected_range' => $stats['expected_response_time'],
                'efficiency_rating' => $this->rateLevelEfficiency($stats),
            ];
        }

        return $comparison;
    }
}
