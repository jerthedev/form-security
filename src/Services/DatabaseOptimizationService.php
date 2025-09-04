<?php

declare(strict_types=1);

/**
 * Service File: DatabaseOptimizationService.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-012-database-performance-optimization
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1089-database-query-optimization
 *
 * Description: Comprehensive database query optimization service providing
 * intelligent query routing, batch operations, and performance monitoring
 * for high-volume form security operations.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-012-database-performance-optimization.md
 * @see docs/Planning/Sprints/004-caching-cli-integration.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1089-database-query-optimization.md
 */

namespace JTD\FormSecurity\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\FormSecurity\Models\BlockedSubmission;
use JTD\FormSecurity\Models\IpReputation;
use JTD\FormSecurity\Models\SpamPattern;

/**
 * Database Optimization Service
 *
 * Provides intelligent query optimization, batch processing, and performance
 * monitoring for database operations in the JTD-FormSecurity package.
 */
class DatabaseOptimizationService
{
    /**
     * Default batch size for bulk operations
     */
    protected const DEFAULT_BATCH_SIZE = 500;

    /**
     * Maximum query execution time threshold (in seconds)
     */
    protected const SLOW_QUERY_THRESHOLD = 0.1;

    /**
     * Cache TTL for query results (in seconds)
     */
    protected const QUERY_CACHE_TTL = 600; // 10 minutes

    /**
     * Query performance metrics
     */
    protected array $performanceMetrics = [];

    /**
     * Optimized bulk insert for blocked submissions
     */
    public function bulkInsertBlockedSubmissions(array $submissions, ?int $batchSize = null): int
    {
        $batchSize = $batchSize ?? self::DEFAULT_BATCH_SIZE;
        $insertedCount = 0;
        $now = now();

        $chunks = array_chunk($submissions, $batchSize);

        foreach ($chunks as $chunk) {
            $startTime = microtime(true);

            // Prepare data with timestamps
            $processedChunk = [];
            foreach ($chunk as $submission) {
                $processedChunk[] = array_merge($submission, [
                    'blocked_at' => $submission['blocked_at'] ?? $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Execute bulk insert
            $result = DB::table('blocked_submissions')->insert($processedChunk);

            if ($result) {
                $insertedCount += count($processedChunk);
            }

            $this->recordQueryPerformance('bulk_insert_blocked_submissions', microtime(true) - $startTime);
        }

        return $insertedCount;
    }

    /**
     * Optimized IP reputation lookup with caching
     */
    public function optimizedIpReputationLookup(string $ipAddress): ?IpReputation
    {
        $cacheKey = "ip_reputation_optimized:{$ipAddress}";

        return Cache::remember($cacheKey, self::QUERY_CACHE_TTL, function () use ($ipAddress) {
            $startTime = microtime(true);

            $reputation = IpReputation::optimizedLookup($ipAddress)->first();

            $this->recordQueryPerformance('optimized_ip_lookup', microtime(true) - $startTime);

            return $reputation;
        });
    }

    /**
     * Batch update IP reputation scores
     */
    public function batchUpdateIpReputations(array $updates, ?int $batchSize = null): int
    {
        $batchSize = $batchSize ?? self::DEFAULT_BATCH_SIZE;
        $updatedCount = 0;

        $chunks = array_chunk($updates, $batchSize);

        foreach ($chunks as $chunk) {
            $startTime = microtime(true);

            // Prepare batch update using CASE WHEN for efficiency
            $cases = [];
            $ipAddresses = [];

            foreach ($chunk as $update) {
                $ipAddress = $update['ip_address'];
                $score = $update['reputation_score'];

                $cases[] = "WHEN ip_address = '{$ipAddress}' THEN {$score}";
                $ipAddresses[] = "'{$ipAddress}'";
            }

            $caseSql = implode(' ', $cases);
            $inSql = implode(',', $ipAddresses);

            $sql = "UPDATE ip_reputation SET 
                      reputation_score = CASE {$caseSql} END,
                      updated_at = NOW()
                    WHERE ip_address IN ({$inSql})";

            $affectedRows = DB::update($sql);
            $updatedCount += $affectedRows;

            $this->recordQueryPerformance('batch_update_ip_reputations', microtime(true) - $startTime);
        }

        return $updatedCount;
    }

    /**
     * Optimized analytics query with caching
     */
    public function getCachedAnalytics(Carbon $startDate, Carbon $endDate, string $groupBy = 'day'): array
    {
        $cacheKey = "analytics_optimized:{$startDate->format('Y-m-d')}:{$endDate->format('Y-m-d')}:{$groupBy}";

        return Cache::remember($cacheKey, self::QUERY_CACHE_TTL, function () use ($startDate, $endDate, $groupBy) {
            $startTime = microtime(true);

            $dateFormat = match ($groupBy) {
                'hour' => '%Y-%m-%d %H:00:00',
                'day' => '%Y-%m-%d',
                'week' => '%Y-%u',
                'month' => '%Y-%m',
                default => '%Y-%m-%d',
            };

            // Use optimized analytics scope with covering index
            $results = BlockedSubmission::optimizedAnalytics()
                ->selectRaw("DATE_FORMAT(blocked_at, '{$dateFormat}') as period")
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('AVG(risk_score) as avg_risk_score')
                ->selectRaw('COUNT(DISTINCT ip_address) as unique_ips')
                ->whereBetween('blocked_at', [$startDate, $endDate])
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->toArray();

            $this->recordQueryPerformance('cached_analytics', microtime(true) - $startTime);

            return $results;
        });
    }

    /**
     * Optimized spam pattern selection for evaluation
     */
    public function getOptimizedSpamPatterns(array $patternTypes = []): Collection
    {
        $cacheKey = 'optimized_spam_patterns:'.md5(implode(',', $patternTypes));

        return Cache::remember($cacheKey, self::QUERY_CACHE_TTL, function () use ($patternTypes) {
            $startTime = microtime(true);

            $query = SpamPattern::optimizedExecutionOrder();

            if (! empty($patternTypes)) {
                $query->whereIn('pattern_type', $patternTypes);
            }

            $patterns = $query->get();

            $this->recordQueryPerformance('optimized_pattern_selection', microtime(true) - $startTime);

            return $patterns;
        });
    }

    /**
     * Intelligent query routing based on data size and complexity
     */
    public function routeQuery(string $queryType, array $parameters = []): mixed
    {
        return match ($queryType) {
            'blocked_submissions_by_ip' => $this->optimizedBlockedSubmissionsByIp($parameters),
            'ip_reputation_analytics' => $this->optimizedIpReputationAnalytics($parameters),
            'recent_high_risk_submissions' => $this->optimizedRecentHighRiskSubmissions($parameters),
            'pattern_performance_stats' => $this->optimizedPatternPerformanceStats($parameters),
            default => throw new \InvalidArgumentException("Unknown query type: {$queryType}"),
        };
    }

    /**
     * Optimized blocked submissions by IP query
     */
    protected function optimizedBlockedSubmissionsByIp(array $parameters): Collection
    {
        $ipAddress = $parameters['ip_address'] ?? throw new \InvalidArgumentException('ip_address required');
        $hours = $parameters['hours'] ?? 168; // 7 days default
        $limit = $parameters['limit'] ?? 100;

        $startTime = microtime(true);

        $submissions = BlockedSubmission::optimizedByIp($ipAddress)
            ->where('blocked_at', '>=', now()->subHours($hours))
            ->limit($limit)
            ->get();

        $this->recordQueryPerformance('optimized_submissions_by_ip', microtime(true) - $startTime);

        return $submissions;
    }

    /**
     * Optimized IP reputation analytics query
     */
    protected function optimizedIpReputationAnalytics(array $parameters): array
    {
        $countryCode = $parameters['country_code'] ?? null;
        $minSubmissions = $parameters['min_submissions'] ?? 10;

        $startTime = microtime(true);

        $query = IpReputation::optimizedAnalytics()
            ->where('submission_count', '>=', $minSubmissions);

        if ($countryCode) {
            $query->where('country_code', $countryCode);
        }

        $results = $query->limit(1000)->get()->toArray();

        $this->recordQueryPerformance('optimized_ip_analytics', microtime(true) - $startTime);

        return $results;
    }

    /**
     * Optimized recent high-risk submissions query
     */
    protected function optimizedRecentHighRiskSubmissions(array $parameters): Collection
    {
        $hours = $parameters['hours'] ?? 24;
        $minRiskScore = $parameters['min_risk_score'] ?? 80;
        $limit = $parameters['limit'] ?? 500;

        $startTime = microtime(true);

        $submissions = BlockedSubmission::optimizedHighRisk()
            ->where('blocked_at', '>=', now()->subHours($hours))
            ->where('risk_score', '>=', $minRiskScore)
            ->limit($limit)
            ->get();

        $this->recordQueryPerformance('optimized_high_risk_recent', microtime(true) - $startTime);

        return $submissions;
    }

    /**
     * Optimized pattern performance statistics
     */
    protected function optimizedPatternPerformanceStats(array $parameters): array
    {
        $minAccuracy = $parameters['min_accuracy'] ?? 0.8;
        $limit = $parameters['limit'] ?? 50;

        $startTime = microtime(true);

        $patterns = SpamPattern::optimizedPerformance()
            ->where('accuracy_rate', '>=', $minAccuracy)
            ->limit($limit)
            ->get()
            ->toArray();

        $this->recordQueryPerformance('optimized_pattern_stats', microtime(true) - $startTime);

        return $patterns;
    }

    /**
     * Batch operation for cleaning old records
     */
    public function batchCleanOldRecords(int $retentionDays = 90, ?int $batchSize = null): array
    {
        $batchSize = $batchSize ?? self::DEFAULT_BATCH_SIZE;
        $cutoffDate = now()->subDays($retentionDays);
        $results = [];

        // Clean old blocked submissions
        $blockedSubmissionsDeleted = 0;
        do {
            $startTime = microtime(true);

            $deleted = DB::table('blocked_submissions')
                ->where('blocked_at', '<', $cutoffDate)
                ->limit($batchSize)
                ->delete();

            $blockedSubmissionsDeleted += $deleted;

            $this->recordQueryPerformance('batch_clean_blocked_submissions', microtime(true) - $startTime);

            // Prevent infinite loops and give database a break
            if ($deleted > 0) {
                usleep(10000); // 10ms pause
            }
        } while ($deleted === $batchSize);

        $results['blocked_submissions_deleted'] = $blockedSubmissionsDeleted;

        // Clean expired IP reputation cache
        $startTime = microtime(true);

        $expiredReputationsDeleted = DB::table('ip_reputation')
            ->where('cache_expires_at', '<', now())
            ->where('last_seen', '<', now()->subDays($retentionDays))
            ->delete();

        $results['expired_reputations_deleted'] = $expiredReputationsDeleted;

        $this->recordQueryPerformance('batch_clean_ip_reputation', microtime(true) - $startTime);

        return $results;
    }

    /**
     * Get query performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }

    /**
     * Reset performance metrics
     */
    public function resetPerformanceMetrics(): void
    {
        $this->performanceMetrics = [];
    }

    /**
     * Get database connection pool statistics
     */
    public function getConnectionPoolStats(): array
    {
        // This would integrate with actual connection pool monitoring
        // For now, provide basic Laravel connection info
        return [
            'active_connections' => DB::connection()->getPdo() ? 1 : 0,
            'default_connection' => config('database.default'),
            'connection_config' => config('database.connections.'.config('database.default')),
            'query_log_enabled' => DB::logging(),
        ];
    }

    /**
     * Optimize database maintenance operations
     */
    public function performMaintenanceOptimizations(): array
    {
        $results = [];

        // Analyze table statistics
        $results['table_stats'] = $this->getTableStatistics();

        // Suggest index optimizations
        $results['index_suggestions'] = $this->analyzeIndexUsage();

        // Check query performance
        $results['slow_queries'] = $this->identifySlowQueries();

        return $results;
    }

    /**
     * Get table statistics for optimization analysis
     */
    protected function getTableStatistics(): array
    {
        $tables = ['blocked_submissions', 'ip_reputation', 'spam_patterns', 'geolite2_ipv4_blocks', 'geolite2_locations'];
        $stats = [];

        foreach ($tables as $table) {
            try {
                $result = DB::select("SHOW TABLE STATUS LIKE '{$table}'");
                if (! empty($result)) {
                    $tableStats = (array) $result[0];
                    $stats[$table] = [
                        'rows' => $tableStats['Rows'] ?? 0,
                        'avg_row_length' => $tableStats['Avg_row_length'] ?? 0,
                        'data_length' => $tableStats['Data_length'] ?? 0,
                        'index_length' => $tableStats['Index_length'] ?? 0,
                        'auto_increment' => $tableStats['Auto_increment'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                $stats[$table] = ['error' => $e->getMessage()];
            }
        }

        return $stats;
    }

    /**
     * Analyze index usage patterns
     */
    protected function analyzeIndexUsage(): array
    {
        $suggestions = [];

        // Check for unused indexes (this would require query log analysis in production)
        $suggestions[] = [
            'type' => 'info',
            'message' => 'Monitor index usage with PERFORMANCE_SCHEMA in production',
            'recommendation' => 'Enable performance_schema and query statistics_io_global',
        ];

        // Check for missing indexes based on common query patterns
        $suggestions[] = [
            'type' => 'optimization',
            'message' => 'Consider adding composite indexes for date-range + filter queries',
            'recommendation' => 'Monitor query patterns and add covering indexes as needed',
        ];

        return $suggestions;
    }

    /**
     * Identify slow query patterns
     */
    protected function identifySlowQueries(): array
    {
        $slowQueries = [];

        // Check if slow query log is enabled
        try {
            $result = DB::select("SHOW VARIABLES LIKE 'slow_query_log'");
            $slowQueryLogEnabled = ! empty($result) && $result[0]->Value === 'ON';

            if (! $slowQueryLogEnabled) {
                $slowQueries[] = [
                    'type' => 'warning',
                    'message' => 'Slow query log is disabled',
                    'recommendation' => 'Enable slow_query_log for performance monitoring',
                ];
            }

            // Get long_query_time setting
            $result = DB::select("SHOW VARIABLES LIKE 'long_query_time'");
            $longQueryTime = ! empty($result) ? (float) $result[0]->Value : 10.0;

            $slowQueries[] = [
                'type' => 'info',
                'message' => "Long query time threshold: {$longQueryTime}s",
                'recommendation' => $longQueryTime > self::SLOW_QUERY_THRESHOLD
                    ? 'Consider lowering long_query_time for better monitoring'
                    : 'Long query time is appropriately configured',
            ];

        } catch (\Exception $e) {
            $slowQueries[] = [
                'type' => 'error',
                'message' => 'Unable to check slow query configuration',
                'error' => $e->getMessage(),
            ];
        }

        return $slowQueries;
    }

    /**
     * Record query performance for monitoring
     */
    protected function recordQueryPerformance(string $queryType, float $executionTime): void
    {
        if (! isset($this->performanceMetrics[$queryType])) {
            $this->performanceMetrics[$queryType] = [
                'count' => 0,
                'total_time' => 0.0,
                'avg_time' => 0.0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0.0,
                'slow_queries' => 0,
            ];
        }

        $metrics = &$this->performanceMetrics[$queryType];
        $metrics['count']++;
        $metrics['total_time'] += $executionTime;
        $metrics['avg_time'] = $metrics['total_time'] / $metrics['count'];
        $metrics['min_time'] = min($metrics['min_time'], $executionTime);
        $metrics['max_time'] = max($metrics['max_time'], $executionTime);

        if ($executionTime > self::SLOW_QUERY_THRESHOLD) {
            $metrics['slow_queries']++;
        }

        // Log slow queries for investigation
        if ($executionTime > self::SLOW_QUERY_THRESHOLD) {
            \Log::warning("Slow query detected: {$queryType} took {$executionTime}s");
        }
    }

    /**
     * Generate database optimization recommendations
     */
    public function generateOptimizationRecommendations(): array
    {
        $recommendations = [];

        // Analyze performance metrics
        foreach ($this->performanceMetrics as $queryType => $metrics) {
            if ($metrics['avg_time'] > self::SLOW_QUERY_THRESHOLD) {
                $recommendations[] = [
                    'type' => 'performance',
                    'query_type' => $queryType,
                    'issue' => "Average query time ({$metrics['avg_time']}s) exceeds threshold",
                    'recommendation' => 'Consider optimizing this query or adding appropriate indexes',
                ];
            }

            if ($metrics['slow_queries'] > $metrics['count'] * 0.1) {
                $recommendations[] = [
                    'type' => 'performance',
                    'query_type' => $queryType,
                    'issue' => "High percentage of slow queries ({$metrics['slow_queries']}/{$metrics['count']})",
                    'recommendation' => 'Review query execution plan and optimize accordingly',
                ];
            }
        }

        return $recommendations;
    }
}
