<?php

declare(strict_types=1);

/**
 * Contract File: PerformanceProfilerInterface.php
 *
 * EPIC: EPIC-006-analytics-monitoring
 * SPEC: SPEC-013-performance-monitoring-profiling
 * SPRINT: Sprint-005-performance-monitoring
 * TICKET: 2051-performance-profiling-system
 *
 * Description: Contract for performance profiling services providing
 * detailed code execution analysis and bottleneck identification.
 *
 * @see docs/Planning/Epics/EPIC-006-analytics-monitoring.md
 * @see docs/Planning/Specs/Analytics-Monitoring/SPEC-013-performance-monitoring-profiling.md
 */

namespace JTD\FormSecurity\Contracts;

/**
 * Performance Profiler Interface
 *
 * Defines the contract for performance profiling services to analyze
 * code execution patterns and identify performance bottlenecks.
 */
interface PerformanceProfilerInterface
{
    /**
     * Start profiling session
     */
    public function startProfiling(?string $sessionId = null): string;

    /**
     * Stop profiling session
     */
    public function stopProfiling(string $sessionId): array;

    /**
     * Profile a callable function or method
     */
    public function profile(callable $callback, ?string $name = null, array $context = []): mixed;

    /**
     * Start profiling a specific operation
     */
    public function startOperation(string $operation, array $context = []): string;

    /**
     * Stop profiling a specific operation
     */
    public function stopOperation(string $operationId): array;

    /**
     * Add a profiling checkpoint
     */
    public function checkpoint(string $name, array $data = []): void;

    /**
     * Get profiling results for a session
     */
    public function getProfilingResults(string $sessionId): array;

    /**
     * Get top slowest operations
     */
    public function getSlowestOperations(int $limit = 10): array;

    /**
     * Get memory usage analysis
     */
    public function getMemoryAnalysis(): array;

    /**
     * Export profiling data
     */
    public function exportProfilingData(string $format = 'json'): string;

    /**
     * Configure profiling options
     */
    public function configure(array $options): void;
}
