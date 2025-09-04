# Database Optimization Guide

## JTD-FormSecurity Database Performance Optimization

This guide provides comprehensive recommendations for optimizing database performance in the JTD-FormSecurity package to achieve <100ms response times for 95% of operations.

## Table of Contents

1. [Database Configuration](#database-configuration)
2. [Connection Pooling](#connection-pooling) 
3. [Index Optimization](#index-optimization)
4. [Query Optimization](#query-optimization)
5. [Caching Strategies](#caching-strategies)
6. [Monitoring and Maintenance](#monitoring-and-maintenance)
7. [High-Volume Deployment](#high-volume-deployment)

---

## Database Configuration

### MySQL Optimization Settings

For optimal performance with high-volume form security operations:

```ini
# MySQL Configuration (my.cnf)

[mysqld]
# Connection and Buffer Settings
max_connections = 200
innodb_buffer_pool_size = 2G  # 70-80% of available RAM
innodb_log_file_size = 512M
innodb_log_buffer_size = 64M
innodb_flush_log_at_trx_commit = 2

# Query Cache and Performance
query_cache_type = 1
query_cache_size = 256M
query_cache_limit = 2M

# Index and Join Optimization
join_buffer_size = 8M
sort_buffer_size = 2M
read_buffer_size = 2M
read_rnd_buffer_size = 8M

# InnoDB Optimization
innodb_thread_concurrency = 0
innodb_file_per_table = 1
innodb_flush_method = O_DIRECT
innodb_doublewrite = 1

# Slow Query Logging
slow_query_log = 1
long_query_time = 0.1  # Log queries taking longer than 100ms
log_queries_not_using_indexes = 1
```

### PostgreSQL Optimization Settings

```postgresql
# PostgreSQL Configuration (postgresql.conf)

# Memory Settings
shared_buffers = 2GB
work_mem = 64MB
maintenance_work_mem = 256MB
effective_cache_size = 6GB

# Checkpoint and WAL Settings
checkpoint_completion_target = 0.7
wal_buffers = 16MB
checkpoint_timeout = 10min

# Connection Settings
max_connections = 200
shared_preload_libraries = 'pg_stat_statements'

# Query Planning
random_page_cost = 1.1  # For SSD storage
effective_io_concurrency = 200

# Logging for Performance Analysis
log_min_duration_statement = 100  # Log queries > 100ms
log_checkpoints = on
log_connections = on
log_disconnections = on
```

---

## Connection Pooling

### Laravel Database Configuration

```php
// config/database.php

'connections' => [
    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => 'InnoDB',
        
        // Connection Pool Settings
        'options' => [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_TIMEOUT => 30,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
        
        // Connection Pool Configuration
        'pool' => [
            'min_connections' => 5,
            'max_connections' => 50,
            'connection_timeout' => 30,
            'idle_timeout' => 300,
            'retry_interval' => 5,
        ],
    ],
    
    // Read-Only Replica Configuration
    'mysql_read' => [
        'driver' => 'mysql',
        'read' => [
            'host' => [
                env('DB_READ_HOST_1', '127.0.0.1'),
                env('DB_READ_HOST_2', '127.0.0.1'),
            ],
        ],
        'write' => [
            'host' => [env('DB_WRITE_HOST', '127.0.0.1')],
        ],
        // ... other configuration
    ],
],
```

### Redis Connection Pool for Caching

```php
// config/cache.php

'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'prefix' => env('CACHE_PREFIX', 'form_security'),
        
        // Redis Pool Configuration
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
            'serializer' => Redis::SERIALIZER_IGBINARY,
            'compression' => Redis::COMPRESSION_LZ4,
        ],
        
        // Connection Pool Settings
        'pool' => [
            'min_connections' => 2,
            'max_connections' => 20,
            'connection_timeout' => 5,
            'read_timeout' => 10,
            'retry_interval' => 2,
        ],
    ],
],
```

### High-Performance Connection Management

```php
<?php

namespace JTD\FormSecurity\Services;

use Illuminate\Support\Facades\DB;

class ConnectionPoolManager
{
    protected array $connectionStats = [];
    
    /**
     * Get optimized database connection based on operation type
     */
    public function getOptimizedConnection(string $operationType): string
    {
        return match($operationType) {
            'analytics', 'reporting', 'read_heavy' => 'mysql_read',
            'bulk_insert', 'write_heavy' => 'mysql_write', 
            'real_time', 'cache_lookup' => 'redis',
            default => 'mysql'
        };
    }
    
    /**
     * Monitor connection pool health
     */
    public function getPoolHealth(): array
    {
        $connections = config('database.connections');
        $health = [];
        
        foreach ($connections as $name => $config) {
            try {
                $startTime = microtime(true);
                DB::connection($name)->getPdo();
                $connectionTime = microtime(true) - $startTime;
                
                $health[$name] = [
                    'status' => 'healthy',
                    'connection_time' => $connectionTime,
                    'last_check' => now(),
                ];
            } catch (\Exception $e) {
                $health[$name] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                    'last_check' => now(),
                ];
            }
        }
        
        return $health;
    }
}
```

---

## Index Optimization

### Strategic Index Implementation

The package includes optimized indexes for high-volume operations:

#### Blocked Submissions Table Indexes

```sql
-- Covering indexes for analytics queries (avoid table lookups)
CREATE INDEX idx_blocked_submissions_analytics_covering 
ON blocked_submissions (blocked_at, block_reason, risk_score, country_code);

-- IP-based covering index for reputation correlation  
CREATE INDEX idx_blocked_submissions_ip_analytics_covering
ON blocked_submissions (ip_address, blocked_at, risk_score, block_reason);

-- Risk-based queries with geographic data
CREATE INDEX idx_blocked_submissions_risk_time_country
ON blocked_submissions (risk_score, blocked_at, country_code);

-- Form-specific analysis
CREATE INDEX idx_blocked_submissions_form_risk_time  
ON blocked_submissions (form_identifier, risk_score, blocked_at);
```

#### IP Reputation Table Indexes

```sql
-- Fast reputation lookups with cache management
CREATE INDEX idx_ip_reputation_lookup_covering
ON ip_reputation (ip_address, reputation_status, reputation_score, cache_expires_at);

-- Analytics queries by geography and threat level
CREATE INDEX idx_ip_reputation_analytics_covering
ON ip_reputation (reputation_status, country_code, block_rate, submission_count);

-- Threat intelligence queries
CREATE INDEX idx_ip_reputation_status_score_seen
ON ip_reputation (reputation_status, reputation_score, last_seen);
```

#### Spam Patterns Table Indexes

```sql
-- Pattern selection for evaluation (priority order)
CREATE INDEX idx_spam_patterns_selection_covering
ON spam_patterns (is_active, priority, pattern_type, scope, risk_score);

-- Performance monitoring and optimization
CREATE INDEX idx_spam_patterns_performance_covering  
ON spam_patterns (pattern_type, is_active, accuracy_rate, processing_time_ms);
```

### Index Maintenance

```sql
-- Regular index optimization (run during maintenance windows)
OPTIMIZE TABLE blocked_submissions;
OPTIMIZE TABLE ip_reputation; 
OPTIMIZE TABLE spam_patterns;

-- Analyze table statistics for query optimization
ANALYZE TABLE blocked_submissions;
ANALYZE TABLE ip_reputation;
ANALYZE TABLE spam_patterns;

-- Check index usage statistics
SELECT 
    TABLE_SCHEMA,
    TABLE_NAME,
    INDEX_NAME,
    CARDINALITY,
    SUB_PART,
    PACKED,
    NULLABLE,
    INDEX_TYPE
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'your_database_name'
ORDER BY TABLE_NAME, SEQ_IN_INDEX;
```

---

## Query Optimization

### Optimized Query Patterns

The package includes optimized scopes for common operations:

#### High-Performance Analytics Queries

```php
// Use optimized scopes with covering indexes
$analytics = BlockedSubmission::optimizedAnalytics()
    ->whereBetween('blocked_at', [$startDate, $endDate])
    ->limit(1000)
    ->get();

// IP reputation lookup with caching
$reputation = IpReputation::optimizedLookup($ipAddress)->first();

// Pattern selection for evaluation
$patterns = SpamPattern::optimizedExecutionOrder()->get();
```

#### Batch Operations for High Volume

```php
// Bulk insert with chunking
$optimizationService = new DatabaseOptimizationService();
$insertedCount = $optimizationService->bulkInsertBlockedSubmissions(
    $submissions, 
    500 // Batch size
);

// Batch update IP reputations
$updatedCount = $optimizationService->batchUpdateIpReputations([
    ['ip_address' => '192.168.1.1', 'reputation_score' => 25],
    ['ip_address' => '192.168.1.2', 'reputation_score' => 75],
    // ... more updates
], 500);
```

#### Intelligent Query Routing

```php
// Route queries based on operation type and data characteristics
$results = $optimizationService->routeQuery('blocked_submissions_by_ip', [
    'ip_address' => '192.168.1.1',
    'hours' => 168,
    'limit' => 100
]);

$analytics = $optimizationService->routeQuery('ip_reputation_analytics', [
    'country_code' => 'US',
    'min_submissions' => 50
]);
```

### Query Performance Guidelines

1. **Always use appropriate indexes**: Queries should utilize covering indexes when possible
2. **Limit result sets**: Use LIMIT clauses and pagination for large datasets
3. **Select only needed columns**: Avoid SELECT * queries
4. **Use prepared statements**: Prevent SQL injection and improve parsing performance
5. **Batch operations**: Group multiple operations for better efficiency

---

## Caching Strategies

### Multi-Level Caching Architecture

```php
// Request-level caching (fastest)
$submissions = Cache::remember('recent_high_risk_' . auth()->id(), 60, function() {
    return BlockedSubmission::optimizedHighRisk()
        ->where('blocked_at', '>=', now()->subHours(24))
        ->limit(100)
        ->get();
});

// Application-level caching (Redis)
$ipReputation = Cache::store('redis')->remember(
    "ip_reputation:{$ipAddress}", 
    3600, 
    function() use ($ipAddress) {
        return IpReputation::optimizedLookup($ipAddress)->first();
    }
);

// Database-level caching (query cache)
// Automatically handled by database query cache configuration
```

### Cache Warming Strategies

```php
// Warm critical caches during off-peak hours
$cacheWarming = new CacheWarmingService();

// Pre-load frequently accessed IP reputations
$cacheWarming->warmIpReputationCache([
    'top_ips_last_24h' => 1000,
    'blacklisted_ips' => 'all',
    'high_risk_countries' => ['CN', 'RU', 'KP']
]);

// Pre-load active spam patterns
$cacheWarming->warmSpamPatternsCache([
    'active_patterns' => true,
    'high_priority_patterns' => true,
    'recent_patterns' => 168 // hours
]);
```

---

## Monitoring and Maintenance

### Performance Monitoring Setup

```php
// Monitor query performance
$optimizationService = new DatabaseOptimizationService();

// Get performance metrics
$metrics = $optimizationService->getPerformanceMetrics();

// Example metrics output:
[
    'optimized_ip_lookup' => [
        'count' => 1250,
        'total_time' => 2.5,
        'avg_time' => 0.002,
        'min_time' => 0.001,
        'max_time' => 0.012,
        'slow_queries' => 3
    ],
    // ... more metrics
]

// Generate optimization recommendations
$recommendations = $optimizationService->generateOptimizationRecommendations();
```

### Database Health Checks

```php
// Regular health monitoring
$healthChecks = $optimizationService->performMaintenanceOptimizations();

// Returns analysis of:
// - Table statistics and growth patterns
// - Index usage and efficiency
// - Slow query identification
// - Connection pool health
```

### Automated Maintenance Tasks

```php
// Clean old records in batches (run via scheduled job)
$results = $optimizationService->batchCleanOldRecords(
    $retentionDays = 90,
    $batchSize = 500
);

// Results:
[
    'blocked_submissions_deleted' => 15420,
    'expired_reputations_deleted' => 892
]
```

---

## High-Volume Deployment

### Scaling Recommendations

#### For 10,000+ Daily Submissions

1. **Database Configuration**:
   - Minimum 4GB RAM allocated to database
   - SSD storage for optimal I/O performance
   - Read replicas for analytics queries

2. **Connection Pooling**:
   - Minimum 20 database connections
   - Separate read/write connection pools
   - Redis cluster for caching

3. **Index Strategy**:
   - All covering indexes implemented
   - Regular index maintenance scheduled
   - Query performance monitoring enabled

#### For 100,000+ Daily Submissions

1. **Database Sharding**:
   ```php
   // Shard by IP address hash
   $shard = 'shard_' . (crc32($ipAddress) % 4);
   $connection = config("database.connections.{$shard}");
   ```

2. **Separate Analytics Database**:
   ```php
   // Write to primary, read from analytics replica
   BlockedSubmission::on('primary')->create($data);
   $analytics = BlockedSubmission::on('analytics')->optimizedAnalytics()->get();
   ```

3. **Advanced Caching**:
   ```php
   // Multi-tier cache with automatic invalidation
   Cache::tags(['ip_reputation', 'analytics'])->put($key, $value, $ttl);
   ```

### Performance Targets

| Operation Type | Target Response Time | Optimization Strategy |
|---|---|---|
| IP Reputation Lookup | < 5ms | Covering indexes + Redis cache |
| Form Submission Analysis | < 20ms | Pattern optimization + caching |
| Analytics Queries | < 50ms | Covering indexes + aggregation |
| Bulk Operations | < 2s per 1000 records | Batch processing + chunking |
| Database Writes | < 10ms | Connection pooling + indexes |

### Monitoring and Alerting

```php
// Set up performance alerts
if ($avgQueryTime > 0.1) {
    Log::warning("Slow query performance detected", [
        'avg_time' => $avgQueryTime,
        'query_type' => $queryType,
        'recommendations' => $optimizationService->generateOptimizationRecommendations()
    ]);
}

// Connection pool monitoring
$poolHealth = $connectionManager->getPoolHealth();
foreach ($poolHealth as $connection => $health) {
    if ($health['status'] !== 'healthy') {
        Log::error("Database connection issue", [
            'connection' => $connection,
            'status' => $health
        ]);
    }
}
```

---

## Best Practices Summary

1. **Always measure performance**: Use the built-in performance monitoring tools
2. **Optimize for your workload**: Different patterns for read-heavy vs write-heavy operations  
3. **Use appropriate indexes**: Covering indexes for frequently accessed data combinations
4. **Cache intelligently**: Multi-level caching with appropriate TTLs
5. **Monitor continuously**: Regular health checks and performance analysis
6. **Scale incrementally**: Start with basic optimizations, add complexity as needed

This optimization guide ensures the JTD-FormSecurity package can handle high-volume form security operations while maintaining <100ms response times for 95% of operations.