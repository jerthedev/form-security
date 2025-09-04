<?php

declare(strict_types=1);

/**
 * Service File: PerformanceProfiler.php
 *
 * EPIC: EPIC-006-analytics-monitoring
 * SPEC: SPEC-013-performance-monitoring-profiling
 * SPRINT: Sprint-005-performance-monitoring
 * TICKET: 2051-performance-profiling-system
 *
 * Description: Comprehensive performance profiling service providing detailed
 * code execution analysis, bottleneck identification, and performance insights.
 *
 * @see docs/Planning/Epics/EPIC-006-analytics-monitoring.md
 * @see docs/Planning/Specs/Analytics-Monitoring/SPEC-013-performance-monitoring-profiling.md
 */

namespace JTD\FormSecurity\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JTD\FormSecurity\Contracts\PerformanceProfilerInterface;

/**
 * PerformanceProfiler Class
 *
 * Advanced performance profiling service that provides detailed analysis
 * of code execution patterns, memory usage, and performance bottlenecks
 * with comprehensive reporting capabilities.
 */
class PerformanceProfiler implements PerformanceProfilerInterface
{
    /**
     * Cache keys for profiling data
     */
    private const CACHE_KEY_SESSIONS = 'profiler:sessions';

    private const CACHE_KEY_OPERATIONS = 'profiler:operations';

    private const CACHE_KEY_CHECKPOINTS = 'profiler:checkpoints';

    private const CACHE_KEY_RESULTS = 'profiler:results';

    /**
     * Profiling configuration
     */
    private array $config = [
        'enabled' => true,
        'memory_tracking' => true,
        'call_stack_depth' => 10,
        'max_sessions' => 100,
        'max_operations_per_session' => 1000,
    ];

    /**
     * Active profiling sessions
     */
    private array $activeSessions = [];

    private array $activeOperations = [];

    private array $sessionCheckpoints = [];

    /**
     * Start profiling session
     */
    public function startProfiling(?string $sessionId = null): string
    {
        if (! $this->config['enabled']) {
            return '';
        }

        $sessionId = $sessionId ?? Str::uuid()->toString();

        $session = [
            'id' => $sessionId,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_peak_memory' => memory_get_peak_usage(true),
            'operations' => [],
            'checkpoints' => [],
            'created_at' => now()->toDateTimeString(),
        ];

        $this->activeSessions[$sessionId] = $session;
        $this->sessionCheckpoints[$sessionId] = [];

        Log::debug('Profiling session started', ['session_id' => $sessionId]);

        return $sessionId;
    }

    /**
     * Stop profiling session
     */
    public function stopProfiling(string $sessionId): array
    {
        if (! isset($this->activeSessions[$sessionId])) {
            throw new \InvalidArgumentException("Profiling session {$sessionId} not found");
        }

        $session = $this->activeSessions[$sessionId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);

        $results = [
            'session_id' => $sessionId,
            'duration' => ($endTime - $session['start_time']) * 1000, // milliseconds
            'memory_usage' => [
                'start' => $session['start_memory'],
                'end' => $endMemory,
                'peak_start' => $session['start_peak_memory'],
                'peak_end' => $endPeakMemory,
                'allocated' => $endMemory - $session['start_memory'],
                'peak_allocated' => $endPeakMemory - $session['start_peak_memory'],
            ],
            'operations' => $this->getSessionOperations($sessionId),
            'checkpoints' => $this->sessionCheckpoints[$sessionId] ?? [],
            'statistics' => $this->calculateSessionStatistics($sessionId),
            'recommendations' => $this->generateProfilingRecommendations($sessionId),
            'completed_at' => now()->toDateTimeString(),
        ];

        // Store results for later retrieval
        $this->storeProfilingResults($sessionId, $results);

        // Cleanup active session data
        unset($this->activeSessions[$sessionId]);
        unset($this->activeOperations[$sessionId]);
        unset($this->sessionCheckpoints[$sessionId]);

        Log::info('Profiling session completed', [
            'session_id' => $sessionId,
            'duration_ms' => $results['duration'],
            'memory_allocated' => $this->formatBytes($results['memory_usage']['allocated']),
        ]);

        return $results;
    }

    /**
     * Profile a callable function or method
     */
    public function profile(callable $callback, ?string $name = null, array $context = []): mixed
    {
        $sessionId = $this->startProfiling();
        $operationName = $name ?? 'anonymous_function';

        try {
            $operationId = $this->startOperation($operationName, $context);
            $result = $callback();
            $this->stopOperation($operationId);

            return $result;
        } finally {
            $this->stopProfiling($sessionId);
        }
    }

    /**
     * Start profiling a specific operation
     */
    public function startOperation(string $operation, array $context = []): string
    {
        if (! $this->config['enabled']) {
            return '';
        }

        $operationId = Str::uuid()->toString();
        $timestamp = microtime(true);

        $operationData = [
            'id' => $operationId,
            'name' => $operation,
            'context' => $context,
            'start_time' => $timestamp,
            'start_memory' => memory_get_usage(true),
            'start_peak_memory' => memory_get_peak_usage(true),
            'call_stack' => $this->config['memory_tracking'] ? $this->getCaller() : null,
            'session_id' => $this->getCurrentSessionId(),
        ];

        $this->activeOperations[$operationId] = $operationData;

        return $operationId;
    }

    /**
     * Stop profiling a specific operation
     */
    public function stopOperation(string $operationId): array
    {
        if (! isset($this->activeOperations[$operationId])) {
            throw new \InvalidArgumentException("Operation {$operationId} not found");
        }

        $operation = $this->activeOperations[$operationId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);

        $results = [
            'id' => $operationId,
            'name' => $operation['name'],
            'context' => $operation['context'],
            'duration' => ($endTime - $operation['start_time']) * 1000, // milliseconds
            'memory_usage' => [
                'start' => $operation['start_memory'],
                'end' => $endMemory,
                'peak_start' => $operation['start_peak_memory'],
                'peak_end' => $endPeakMemory,
                'allocated' => $endMemory - $operation['start_memory'],
                'peak_allocated' => $endPeakMemory - $operation['start_peak_memory'],
            ],
            'call_stack' => $operation['call_stack'],
            'session_id' => $operation['session_id'],
            'completed_at' => now()->toDateTimeString(),
        ];

        // Store operation results
        $this->storeOperationResults($operationId, $results);

        unset($this->activeOperations[$operationId]);

        return $results;
    }

    /**
     * Add a profiling checkpoint
     */
    public function checkpoint(string $name, array $data = []): void
    {
        if (! $this->config['enabled']) {
            return;
        }

        $sessionId = $this->getCurrentSessionId();
        if (! $sessionId) {
            return;
        }

        $checkpoint = [
            'name' => $name,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'data' => $data,
            'datetime' => now()->toDateTimeString(),
        ];

        $this->sessionCheckpoints[$sessionId][] = $checkpoint;
    }

    /**
     * Get profiling results for a session
     */
    public function getProfilingResults(string $sessionId): array
    {
        $results = Cache::get(self::CACHE_KEY_RESULTS, []);

        if (! isset($results[$sessionId])) {
            throw new \InvalidArgumentException("No profiling results found for session {$sessionId}");
        }

        return $results[$sessionId];
    }

    /**
     * Get top slowest operations
     */
    public function getSlowestOperations(int $limit = 10): array
    {
        $operations = Cache::get(self::CACHE_KEY_OPERATIONS, []);

        // Sort by duration descending
        uasort($operations, function ($a, $b) {
            return $b['duration'] <=> $a['duration'];
        });

        return array_slice($operations, 0, $limit, true);
    }

    /**
     * Get memory usage analysis
     */
    public function getMemoryAnalysis(): array
    {
        $operations = Cache::get(self::CACHE_KEY_OPERATIONS, []);

        $memoryStats = [
            'operations_count' => count($operations),
            'total_allocated' => 0,
            'peak_allocated' => 0,
            'average_allocated' => 0,
            'memory_intensive_operations' => [],
        ];

        foreach ($operations as $operation) {
            $allocated = $operation['memory_usage']['allocated'];
            $peakAllocated = $operation['memory_usage']['peak_allocated'];

            $memoryStats['total_allocated'] += $allocated;
            $memoryStats['peak_allocated'] = max($memoryStats['peak_allocated'], $peakAllocated);

            // Identify memory-intensive operations (>1MB allocation)
            if ($allocated > 1024 * 1024) {
                $memoryStats['memory_intensive_operations'][] = [
                    'name' => $operation['name'],
                    'allocated' => $allocated,
                    'allocated_formatted' => $this->formatBytes($allocated),
                    'duration' => $operation['duration'],
                ];
            }
        }

        if (count($operations) > 0) {
            $memoryStats['average_allocated'] = $memoryStats['total_allocated'] / count($operations);
        }

        return $memoryStats;
    }

    /**
     * Export profiling data
     */
    public function exportProfilingData(string $format = 'json'): string
    {
        $data = [
            'sessions' => Cache::get(self::CACHE_KEY_SESSIONS, []),
            'operations' => Cache::get(self::CACHE_KEY_OPERATIONS, []),
            'results' => Cache::get(self::CACHE_KEY_RESULTS, []),
            'memory_analysis' => $this->getMemoryAnalysis(),
            'slowest_operations' => $this->getSlowestOperations(20),
            'exported_at' => now()->toDateTimeString(),
        ];

        return match ($format) {
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            'array' => serialize($data),
            default => json_encode($data, JSON_PRETTY_PRINT),
        };
    }

    /**
     * Configure profiling options
     */
    public function configure(array $options): void
    {
        $this->config = array_merge($this->config, $options);

        Log::debug('Profiler configuration updated', $this->config);
    }

    /**
     * Get current session ID
     */
    private function getCurrentSessionId(): ?string
    {
        if (empty($this->activeSessions)) {
            return null;
        }

        return array_key_first($this->activeSessions);
    }

    /**
     * Get session operations
     */
    private function getSessionOperations(string $sessionId): array
    {
        $allOperations = Cache::get(self::CACHE_KEY_OPERATIONS, []);

        return array_filter($allOperations, function ($operation) use ($sessionId) {
            return $operation['session_id'] === $sessionId;
        });
    }

    /**
     * Calculate session statistics
     */
    private function calculateSessionStatistics(string $sessionId): array
    {
        $operations = $this->getSessionOperations($sessionId);

        if (empty($operations)) {
            return [];
        }

        $durations = array_column($operations, 'duration');
        $memoryAllocations = array_map(function ($op) {
            return $op['memory_usage']['allocated'];
        }, $operations);

        return [
            'operations_count' => count($operations),
            'total_duration' => array_sum($durations),
            'average_duration' => array_sum($durations) / count($durations),
            'max_duration' => max($durations),
            'min_duration' => min($durations),
            'total_memory_allocated' => array_sum($memoryAllocations),
            'average_memory_allocated' => array_sum($memoryAllocations) / count($memoryAllocations),
            'max_memory_allocated' => max($memoryAllocations),
        ];
    }

    /**
     * Generate profiling recommendations
     */
    private function generateProfilingRecommendations(string $sessionId): array
    {
        $statistics = $this->calculateSessionStatistics($sessionId);
        $recommendations = [];

        if (empty($statistics)) {
            return $recommendations;
        }

        // Check for long-running operations
        if ($statistics['max_duration'] > 1000) { // >1 second
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'message' => 'Long-running operation detected. Consider optimization or caching.',
                'details' => "Maximum operation duration: {$statistics['max_duration']}ms",
            ];
        }

        // Check for high memory usage
        if ($statistics['max_memory_allocated'] > 10 * 1024 * 1024) { // >10MB
            $recommendations[] = [
                'type' => 'memory',
                'priority' => 'high',
                'message' => 'High memory allocation detected. Review memory usage patterns.',
                'details' => 'Maximum memory allocated: '.$this->formatBytes($statistics['max_memory_allocated']),
            ];
        }

        // Check operation count
        if ($statistics['operations_count'] > 100) {
            $recommendations[] = [
                'type' => 'efficiency',
                'priority' => 'medium',
                'message' => 'High number of operations in session. Consider consolidation.',
                'details' => "Operations count: {$statistics['operations_count']}",
            ];
        }

        return $recommendations;
    }

    /**
     * Store profiling results
     */
    private function storeProfilingResults(string $sessionId, array $results): void
    {
        $allResults = Cache::get(self::CACHE_KEY_RESULTS, []);
        $allResults[$sessionId] = $results;

        // Keep only the last 50 sessions
        if (count($allResults) > 50) {
            uasort($allResults, function ($a, $b) {
                return strtotime($b['completed_at']) <=> strtotime($a['completed_at']);
            });
            $allResults = array_slice($allResults, 0, 50, true);
        }

        Cache::put(self::CACHE_KEY_RESULTS, $allResults, 7200);
    }

    /**
     * Store operation results
     */
    private function storeOperationResults(string $operationId, array $results): void
    {
        $allOperations = Cache::get(self::CACHE_KEY_OPERATIONS, []);
        $allOperations[$operationId] = $results;

        // Keep only the last 1000 operations
        if (count($allOperations) > 1000) {
            uasort($allOperations, function ($a, $b) {
                return strtotime($b['completed_at']) <=> strtotime($a['completed_at']);
            });
            $allOperations = array_slice($allOperations, 0, 1000, true);
        }

        Cache::put(self::CACHE_KEY_OPERATIONS, $allOperations, 7200);
    }

    /**
     * Get caller information for call stack tracking
     */
    private function getCaller(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->config['call_stack_depth']);

        return array_map(function ($frame) {
            return [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null,
            ];
        }, array_slice($trace, 2)); // Skip current method and startOperation
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
