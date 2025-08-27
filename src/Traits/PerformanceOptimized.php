<?php

declare(strict_types=1);

/**
 * Trait File: PerformanceOptimized.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Performance optimization trait providing eager loading strategies,
 * query optimization, and caching for <100ms query targets.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * PerformanceOptimized Trait
 *
 * Provides performance optimization methods including eager loading strategies,
 * query optimization, and intelligent caching for sub-100ms query targets.
 */
trait PerformanceOptimized
{
    /**
     * Cache TTL for performance-optimized queries (in seconds)
     */
    protected int $performanceCacheTtl = 300; // 5 minutes

    /**
     * Get optimized query with eager loading
     */
    public function scopeOptimized(Builder $query): Builder
    {
        return $query->select($this->getOptimizedSelectColumns())
            ->with($this->getOptimizedEagerLoads());
    }

    /**
     * Get optimized select columns (override in models)
     */
    protected function getOptimizedSelectColumns(): array
    {
        // Default: select all columns, but models can override for specific use cases
        return ['*'];
    }

    /**
     * Get optimized eager loading relationships (override in models)
     */
    protected function getOptimizedEagerLoads(): array
    {
        // Default: no eager loading, but models can override
        return [];
    }

    /**
     * Execute query with performance monitoring
     */
    public function scopeWithPerformanceMonitoring(Builder $query): Builder
    {
        return $query->tap(function ($query) {
            $startTime = microtime(true);

            // Add query executed listener
            DB::listen(function ($queryExecuted) use ($startTime) {
                $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

                if ($executionTime > 100) { // Log slow queries
                    \Log::warning('Slow query detected', [
                        'sql' => $queryExecuted->sql,
                        'bindings' => $queryExecuted->bindings,
                        'time_ms' => $executionTime,
                        'model' => static::class,
                    ]);
                }
            });
        });
    }

    /**
     * Get cached results with automatic cache key generation
     */
    public static function getCachedResults(string $method, array $parameters = [], ?int $ttl = null): mixed
    {
        $cacheKey = static::generateCacheKey($method, $parameters);
        $ttl = $ttl ?? (new static)->performanceCacheTtl;

        return Cache::remember($cacheKey, $ttl, function () use ($method, $parameters) {
            return static::$method(...$parameters);
        });
    }

    /**
     * Generate cache key for method and parameters
     */
    protected static function generateCacheKey(string $method, array $parameters): string
    {
        $modelClass = str_replace('\\', '_', static::class);
        $parameterHash = md5(serialize($parameters));

        return "model_cache:{$modelClass}:{$method}:{$parameterHash}";
    }

    /**
     * Chunk large datasets for memory efficiency
     */
    public function scopeChunked(Builder $query, int $chunkSize = 1000): Builder
    {
        return $query->orderBy($this->getKeyName()); // Ensure consistent ordering for chunking
    }

    /**
     * Get paginated results with optimized counting
     */
    public function scopeOptimizedPaginate(Builder $query, int $perPage = 15): Builder
    {
        // Use approximate counting for large datasets to improve performance
        return $query->tap(function ($query) {
            // For very large tables, consider using estimated counts
            $estimatedCount = $this->getEstimatedRowCount();

            if ($estimatedCount > 100000) {
                // Use fast approximate counting for large datasets
                $query->selectRaw('SQL_CALC_FOUND_ROWS *');
            }
        });
    }

    /**
     * Get estimated row count for performance optimization
     */
    protected function getEstimatedRowCount(): int
    {
        $tableName = $this->getTable();

        try {
            $result = DB::selectOne('
                SELECT table_rows as estimated_count 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = ?
            ', [$tableName]);

            return (int) ($result->estimated_count ?? 0);
        } catch (\Exception $e) {
            // Fallback to actual count if information_schema is not available
            return static::count();
        }
    }

    /**
     * Optimize query with indexes hint
     */
    public function scopeWithIndexHint(Builder $query, string $indexName): Builder
    {
        $tableName = $this->getTable();

        return $query->from(DB::raw("{$tableName} USE INDEX ({$indexName})"));
    }

    /**
     * Batch load related models to avoid N+1 queries
     */
    public static function batchLoadRelations(Collection $models, array $relations): void
    {
        foreach ($relations as $relation) {
            if (method_exists(static::class, $relation)) {
                $models->load($relation);
            }
        }
    }

    /**
     * Get query execution plan for optimization analysis
     */
    public function scopeExplain(Builder $query): array
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        // Replace placeholders with actual values for EXPLAIN
        foreach ($bindings as $binding) {
            $sql = preg_replace('/\?/', "'".addslashes($binding)."'", $sql, 1);
        }

        try {
            $explanation = DB::select("EXPLAIN {$sql}");

            return array_map(function ($row) {
                return (array) $row;
            }, $explanation);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Optimize bulk operations
     */
    public static function bulkInsert(array $data, int $chunkSize = 1000): bool
    {
        if (empty($data)) {
            return true;
        }

        $chunks = array_chunk($data, $chunkSize);

        try {
            DB::transaction(function () use ($chunks) {
                foreach ($chunks as $chunk) {
                    static::insert($chunk);
                }
            });

            return true;
        } catch (\Exception $e) {
            \Log::error('Bulk insert failed', [
                'model' => static::class,
                'error' => $e->getMessage(),
                'data_count' => count($data),
            ]);

            return false;
        }
    }

    /**
     * Optimize bulk updates
     */
    public static function bulkUpdate(array $updates, string $keyColumn = 'id'): int
    {
        if (empty($updates)) {
            return 0;
        }

        $table = (new static)->getTable();
        $cases = [];
        $ids = [];
        $updateColumns = [];

        foreach ($updates as $update) {
            if (! isset($update[$keyColumn])) {
                continue;
            }

            $id = $update[$keyColumn];
            $ids[] = $id;

            foreach ($update as $column => $value) {
                if ($column === $keyColumn) {
                    continue;
                }

                if (! in_array($column, $updateColumns)) {
                    $updateColumns[] = $column;
                    $cases[$column] = [];
                }

                $cases[$column][] = "WHEN {$keyColumn} = ".DB::getPdo()->quote((string) $id).' THEN '.DB::getPdo()->quote((string) $value);
            }
        }

        if (empty($cases) || empty($ids)) {
            return 0;
        }

        $setClauses = [];
        foreach ($updateColumns as $column) {
            $whenClauses = implode(' ', $cases[$column]);
            $setClauses[] = "{$column} = CASE {$whenClauses} ELSE {$column} END";
        }

        $setClause = implode(', ', $setClauses);
        $whereClause = $keyColumn.' IN ('.implode(',', $ids).')';

        try {
            return DB::update("UPDATE {$table} SET {$setClause} WHERE {$whereClause}");
        } catch (\Exception $e) {
            \Log::error('Bulk update failed', [
                'model' => static::class,
                'error' => $e->getMessage(),
                'updates_count' => count($updates),
            ]);

            return 0;
        }
    }

    /**
     * Clear performance-related caches
     */
    public static function clearPerformanceCache(): void
    {
        $modelClass = str_replace('\\', '_', static::class);
        $pattern = "model_cache:{$modelClass}:*";

        // Clear all cached results for this model
        Cache::flush(); // In production, you'd want more targeted cache clearing
    }
}
