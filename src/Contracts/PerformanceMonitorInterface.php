<?php

declare(strict_types=1);

/**
 * Contract File: PerformanceMonitorInterface.php
 *
 * EPIC: EPIC-006-analytics-monitoring
 * SPEC: SPEC-013-performance-monitoring-profiling
 * SPRINT: Sprint-005-performance-monitoring
 * TICKET: 2050-comprehensive-performance-monitoring
 *
 * Description: Contract for performance monitoring services providing
 * comprehensive metrics collection, profiling, and alerting capabilities.
 *
 * @see docs/Planning/Epics/EPIC-006-analytics-monitoring.md
 * @see docs/Planning/Specs/Analytics-Monitoring/SPEC-013-performance-monitoring-profiling.md
 */

namespace JTD\FormSecurity\Contracts;

/**
 * Performance Monitor Interface
 *
 * Defines the contract for performance monitoring services to track
 * and analyze application performance metrics in real-time.
 */
interface PerformanceMonitorInterface
{
    /**
     * Start monitoring system performance
     */
    public function startMonitoring(): void;

    /**
     * Stop monitoring system performance
     */
    public function stopMonitoring(): void;

    /**
     * Record a performance metric
     */
    public function recordMetric(string $name, float $value, array $tags = []): void;

    /**
     * Start timing an operation
     */
    public function startTimer(string $operation): string;

    /**
     * Stop timing an operation and record the duration
     */
    public function stopTimer(string $timerId): float;

    /**
     * Record memory usage at a specific point
     */
    public function recordMemoryUsage(string $checkpoint): void;

    /**
     * Set performance threshold for alerting
     */
    public function setThreshold(string $metric, float $threshold, string $comparison = 'gt'): void;

    /**
     * Get current performance metrics
     */
    public function getMetrics(array $filters = []): array;

    /**
     * Get performance statistics for a time period
     */
    public function getStatistics(string $period = '1h'): array;

    /**
     * Generate performance report
     */
    public function generateReport(array $options = []): array;

    /**
     * Check if performance thresholds are exceeded
     */
    public function checkThresholds(): array;

    /**
     * Clear performance monitoring data
     */
    public function clearData(?string $before = null): void;
}
