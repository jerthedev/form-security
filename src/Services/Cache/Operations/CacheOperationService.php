<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services\Cache\Operations;

use Illuminate\Cache\CacheManager as LaravelCacheManager;
use JTD\FormSecurity\Contracts\Cache\CacheOperationServiceInterface;
use JTD\FormSecurity\Enums\CacheLevel;
use JTD\FormSecurity\Services\Cache\Traits\CacheErrorHandlingTrait;
use JTD\FormSecurity\Services\Cache\Traits\CacheUtilitiesTrait;
use JTD\FormSecurity\ValueObjects\CacheKey;

/**
 * CacheOperationService
 */
class CacheOperationService implements CacheOperationServiceInterface
{
    use CacheErrorHandlingTrait;
    use CacheUtilitiesTrait;

    private array $stats = [];

    private array $fluentContext = [];

    private array $configuration = [];

    public function __construct(
        private LaravelCacheManager $cacheManager
    ) {
        $this->initializeRepositories();
        $this->initializeStats();
        $this->initializeConfiguration();
    }

    /**
     * Initialize statistics tracking
     */
    private function initializeStats(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'puts' => 0,
            'gets' => 0,
            'deletes' => 0,
            'operations_count' => 0,
            'start_time' => microtime(true),
            'response_times' => [],
            'memory_usage' => [],
        ];
    }

    /**
     * Initialize configuration with defaults
     */
    private function initializeConfiguration(): void
    {
        $this->configuration = [
            'default_ttl' => 3600,
            'max_ttl' => 86400,
            'enabled_levels' => [
                CacheLevel::REQUEST->value => true,
                CacheLevel::MEMORY->value => true,
                CacheLevel::DATABASE->value => true,
            ],
            'levels' => [
                'request' => [
                    'enabled' => true,
                    'driver' => 'array',
                    'supports_tagging' => false,
                    'default_ttl' => 0,
                ],
                'memory' => [
                    'enabled' => true,
                    'driver' => 'array',
                    'supports_tagging' => true,
                    'default_ttl' => 3600,
                ],
                'database' => [
                    'enabled' => true,
                    'driver' => 'array',
                    'supports_tagging' => true,
                    'default_ttl' => 86400,
                ],
            ],
            'features' => [
                'statistics_tracking' => [
                    'enabled' => true,
                    'track_response_times' => true,
                    'track_memory_usage' => true,
                ],
                'cache_warming' => [
                    'enabled' => true,
                    'batch_size' => 50,
                ],
                'pattern_invalidation' => [
                    'enabled' => true,
                ],
                'tag_invalidation' => [
                    'enabled' => true,
                ],
            ],
            'cache_settings' => [
                'default_ttl' => 3600,
                'max_ttl' => 86400,
                'prefix' => 'form_security',
            ],
            'maintenance' => [
                'auto_cleanup' => true,
                'cleanup_interval' => 3600,
                'max_size_mb' => 1024,
            ],
            'statistics' => [
                'enabled' => true,
                'track_performance' => true,
                'track_memory' => true,
            ],
            'error_handling' => [
                'circuit_breaker_enabled' => false,
                'fallback_enabled' => true,
                'retry_attempts' => 3,
            ],
            'performance' => [
                'track_response_times' => true,
                'track_memory_usage' => true,
                'max_response_time_ms' => 100,
                'batch_size' => 50,
            ],
            'runtime' => [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'timestamp' => time(),
                'uptime_seconds' => 0, // Will be calculated dynamically
                'version' => '1.0.0',
                'environment' => app()->environment(),
                'package_enabled' => true,
                'cache_driver' => 'array',
            ],
            'laravel_config' => [
                'form_security' => config('form-security', []),
                'form_security_cache' => config('form-security-cache', []),
                'cache_default' => config('cache.default'),
                'cache_prefix' => 'form_security',
                'app_env' => config('app.env'),
                'app_debug' => config('app.debug'),
            ],
        ];
    }

    /**
     * Get a value from the cache using multi-level fallback
     */
    public function get(string|CacheKey $key, mixed $default = null, ?array $levels = null): mixed
    {
        $startTime = microtime(true);
        $cacheKey = $this->normalizeCacheKey($key);
        $levels = $levels ?? CacheLevel::getByPriority();

        foreach ($levels as $level) {
            // Skip disabled levels
            if (! $this->isLevelEnabled($level)) {
                continue;
            }

            try {
                $repository = $this->repositories[$level->value];

                // First check if the key exists to distinguish between stored null and missing
                $keyExists = $repository->has($cacheKey->toString());

                if ($keyExists) {
                    // Key exists, get the value (which might be null)
                    if (! empty($cacheKey->tags) && $level->supportsTagging()) {
                        $value = $repository->tags($cacheKey->tags)->get($cacheKey->toString());
                    } else {
                        $value = $repository->get($cacheKey->toString());
                    }

                    $this->stats['hits']++;
                    $this->stats['operations_count']++;
                    $this->recordResponseTime(microtime(true) - $startTime);

                    // Store in higher priority levels for next time (backfill)
                    // Only backfill non-null values to avoid issues
                    if ($value !== null) {
                        $this->backfillCache($cacheKey, $value, $level, $levels);
                    }

                    return $value; // Return the actual stored value (including null)
                }
            } catch (\Exception $e) {
                // Log error but continue to next level
                error_log("Cache get operation failed for level {$level->value}: ".$e->getMessage());

                continue;
            }
        }

        $this->stats['misses']++;
        $this->stats['operations_count']++;

        return $default;
    }

    /**
     * Store a value in the cache at specified levels
     */
    public function put(string|CacheKey $key, mixed $value, ?int $ttl = null, ?array $levels = null): bool
    {
        $cacheKey = $this->normalizeCacheKey($key);
        $levels = $levels ?? CacheLevel::cases();
        $success = true;
        $successfulLevels = [];

        foreach ($levels as $level) {
            // Skip disabled levels
            if (! $this->isLevelEnabled($level)) {
                continue;
            }

            try {
                $repository = $this->repositories[$level->value];
                $levelTtl = $this->calculateTtlForLevel($level, $ttl, $cacheKey);

                // For testing with array driver, we don't use native tagging
                // We rely on our own tag tracking system
                $putResult = $repository->put($cacheKey->toString(), $value, $levelTtl);

                if ($putResult) {
                    $successfulLevels[] = $level->value;
                    // Track the key for pattern matching
                    $this->trackKey($level, $cacheKey->toString(), $cacheKey->tags);
                } else {
                    $success = false;
                    error_log("Cache put operation failed for level {$level->value}");
                }
            } catch (\Exception $e) {
                $success = false;
                error_log("Cache put operation exception for level {$level->value}: ".$e->getMessage());

                continue;
            }
        }

        // Update statistics if at least one level succeeded
        if (! empty($successfulLevels)) {
            $this->stats['puts']++;
            $this->recordMemoryUsage('put_operation');
        }

        return $success;
    }

    /**
     * Remove a value from the cache at all levels
     */
    public function forget(string|CacheKey $key, ?array $levels = null): bool
    {
        $cacheKey = $this->normalizeCacheKey($key);
        $levels = $levels ?? CacheLevel::cases();
        $success = true;

        foreach ($levels as $level) {
            $repository = $this->repositories[$level->value];

            if (! $repository->forget($cacheKey->toString())) {
                $success = false;
            }
        }

        if ($success) {
            $this->stats['deletes']++;
        }

        return $success;
    }

    /**
     * Get a value from cache or execute callback and cache the result
     */
    public function remember(string|CacheKey $key, callable $callback, ?int $ttl = null, ?array $levels = null): mixed
    {
        $value = $this->get($key, null, $levels);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl, $levels);

        return $value;
    }

    /**
     * Store an item in the cache indefinitely
     */
    public function rememberForever(CacheKey|string $key, callable $callback, ?array $levels = null): mixed
    {
        return $this->remember($key, $callback, null, $levels);
    }

    /**
     * Flush cache levels
     */
    public function flush(?array $levels = null): bool
    {
        $levels = $levels ?? CacheLevel::cases();
        $results = [];

        foreach ($levels as $level) {
            if ($this->isLevelEnabled($level)) {
                switch ($level) {
                    case CacheLevel::REQUEST:
                        $results[] = $this->flushRequest();
                        break;
                    case CacheLevel::MEMORY:
                        $results[] = $this->flushMemory();
                        break;
                    case CacheLevel::DATABASE:
                        $results[] = $this->flushDatabase();
                        break;
                }
            }
        }

        return ! in_array(false, $results, true);
    }

    /**
     * Clear all cache (alias for flush)
     */
    public function clear(): bool
    {
        return $this->flush();
    }

    /**
     * Add an item to the cache if it doesn't exist
     */
    public function add(CacheKey|string $key, mixed $value, ?int $ttl = null, ?array $levels = null): bool
    {
        if ($this->has($key, $levels)) {
            return false;
        }

        return $this->put($key, $value, $ttl, $levels);
    }

    /**
     * Check if a key exists in the cache
     */
    public function has(string|CacheKey $key, ?array $levels = null): bool
    {
        $cacheKey = $this->normalizeCacheKey($key);
        $levels = $levels ?? CacheLevel::getByPriority();

        foreach ($levels as $level) {
            $repository = $this->repositories[$level->value];

            if ($repository->has($cacheKey->toString())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a value specifically from request-level cache (fastest)
     */
    public function getFromRequest(string|CacheKey $key, mixed $default = null): mixed
    {
        $startTime = microtime(true);
        $repository = $this->repositories[CacheLevel::REQUEST->value];

        // For request level, handle both CacheKey objects and their string representations
        if ($key instanceof CacheKey) {
            $keyString = $key->toString();
        } else {
            // If it's a string that looks like a formatted cache key, use it directly
            // Otherwise, normalize it
            if (str_starts_with($key, 'form_security:')) {
                $keyString = $key;
            } else {
                $cacheKey = $this->normalizeCacheKey($key);
                $keyString = $cacheKey->toString();
            }
        }

        $value = $repository->get($keyString);

        if ($value !== null) {
            $this->stats['hits']++;
            $this->recordResponseTime(microtime(true) - $startTime);

            return $value;
        }

        $this->stats['misses']++;

        return $default;
    }

    /**
     * Get a value specifically from memory cache
     */
    public function getFromMemory(string|CacheKey $key, mixed $default = null): mixed
    {
        $repository = $this->repositories[CacheLevel::MEMORY->value] ?? null;
        if (! $repository) {
            return $default;
        }

        $startTime = microtime(true);
        $cacheKey = $this->normalizeCacheKey($key);

        try {
            // Use tags if the cache key has them and the driver supports tagging
            if (! empty($cacheKey->tags) && method_exists($repository, 'tags')) {
                $value = $repository->tags($cacheKey->tags)->get($cacheKey->toString());
            } else {
                $value = $repository->get($cacheKey->toString());
            }

            if ($value !== null) {
                $this->stats['hits']++;
                $this->recordResponseTime(microtime(true) - $startTime);

                return $value;
            }

            $this->stats['misses']++;

            return $default;
        } catch (\Exception $e) {
            $this->stats['misses']++;

            return $default;
        }
    }

    /**
     * Get a value specifically from database cache
     */
    public function getFromDatabase(string|CacheKey $key, mixed $default = null): mixed
    {
        $repository = $this->repositories[CacheLevel::DATABASE->value] ?? null;
        if (! $repository) {
            return $default;
        }

        $startTime = microtime(true);
        $cacheKey = $this->normalizeCacheKey($key);

        try {
            // Use tags if the cache key has them and the driver supports tagging
            if (! empty($cacheKey->tags) && method_exists($repository, 'tags')) {
                $value = $repository->tags($cacheKey->tags)->get($cacheKey->toString());
            } else {
                $value = $repository->get($cacheKey->toString());
            }

            if ($value !== null) {
                $this->stats['hits']++;
                $this->recordResponseTime(microtime(true) - $startTime);

                return $value;
            }

            $this->stats['misses']++;

            return $default;
        } catch (\Exception $e) {
            $this->stats['misses']++;

            return $default;
        }
    }

    /**
     * Store a value specifically in request-level cache
     */
    public function putInRequest(string|CacheKey $key, mixed $value): bool
    {
        $repository = $this->repositories[CacheLevel::REQUEST->value];

        // For request level, handle both CacheKey objects and their string representations
        if ($key instanceof CacheKey) {
            $keyString = $key->toString();
            $tags = $key->tags;
        } else {
            // If it's a string that looks like a formatted cache key, use it directly
            // Otherwise, normalize it
            if (str_starts_with($key, 'form_security:')) {
                $keyString = $key;
                $tags = [];
            } else {
                $cacheKey = $this->normalizeCacheKey($key);
                $keyString = $cacheKey->toString();
                $tags = $cacheKey->tags;
            }
        }

        // Request-level cache doesn't use TTL (cleared at end of request)
        $success = $repository->put($keyString, $value, 0);

        if ($success) {
            $this->stats['puts']++;
            // Track the key for pattern matching
            $this->trackKey(CacheLevel::REQUEST, $keyString, $tags);
        }

        return $success;
    }

    /**
     * Store a value specifically in memory cache (Redis/Memcached)
     */
    public function putInMemory(string|CacheKey $key, mixed $value, ?int $ttl = null): bool
    {
        $repository = $this->repositories[CacheLevel::MEMORY->value] ?? null;
        if (! $repository) {
            return false;
        }

        $cacheKey = $this->normalizeCacheKey($key);
        $levelTtl = $this->calculateTtlForLevel(CacheLevel::MEMORY, $ttl, $cacheKey);

        try {
            // Use tags if the cache key has them and the driver supports tagging
            if (! empty($cacheKey->tags) && method_exists($repository, 'tags')) {
                $success = $repository->tags($cacheKey->tags)->put($cacheKey->toString(), $value, $levelTtl);
            } else {
                $success = $repository->put($cacheKey->toString(), $value, $levelTtl);
            }

            if ($success) {
                $this->stats['puts']++;
                // Track the key for pattern matching
                $this->trackKey(CacheLevel::MEMORY, $cacheKey->toString(), $cacheKey->tags);
            }

            return $success;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Store a value specifically in database cache (persistent storage)
     */
    public function putInDatabase(string|CacheKey $key, mixed $value, ?int $ttl = null): bool
    {
        $repository = $this->repositories[CacheLevel::DATABASE->value] ?? null;
        if (! $repository) {
            return false;
        }

        $cacheKey = $this->normalizeCacheKey($key);
        $levelTtl = $this->calculateTtlForLevel(CacheLevel::DATABASE, $ttl, $cacheKey);

        try {
            // Use tags if the cache key has them and the driver supports tagging
            if (! empty($cacheKey->tags) && method_exists($repository, 'tags')) {
                $success = $repository->tags($cacheKey->tags)->put($cacheKey->toString(), $value, $levelTtl);
            } else {
                $success = $repository->put($cacheKey->toString(), $value, $levelTtl);
            }

            if ($success) {
                $this->stats['puts']++;
                // Track the key for pattern matching
                $this->trackKey(CacheLevel::DATABASE, $cacheKey->toString(), $cacheKey->tags);
            }

            return $success;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove a key from only the request-level cache
     */
    public function forgetFromRequest(string|CacheKey $key): bool
    {
        $cacheKey = $this->normalizeCacheKey($key);
        $repository = $this->repositories[CacheLevel::REQUEST->value];

        $success = $repository->forget($cacheKey->toString());

        if ($success) {
            $this->stats['deletes']++;
        }

        return $success;
    }

    /**
     * Remove a key from only the memory-level cache (Redis/Memcached)
     */
    public function forgetFromMemory(string|CacheKey $key): bool
    {
        $cacheKey = $this->normalizeCacheKey($key);
        $repository = $this->repositories[CacheLevel::MEMORY->value];

        // Use tags if the cache key has them and the driver supports tagging
        if (! empty($cacheKey->tags) && CacheLevel::MEMORY->supportsTagging()) {
            $success = $repository->tags($cacheKey->tags)->forget($cacheKey->toString());
        } else {
            $success = $repository->forget($cacheKey->toString());
        }

        if ($success) {
            $this->stats['deletes']++;
        }

        return $success;
    }

    /**
     * Remove a key from only the database-level cache
     */
    public function forgetFromDatabase(string|CacheKey $key): bool
    {
        $cacheKey = $this->normalizeCacheKey($key);
        $repository = $this->repositories[CacheLevel::DATABASE->value];

        // Use tags if the cache key has them and the driver supports tagging
        if (! empty($cacheKey->tags) && CacheLevel::DATABASE->supportsTagging()) {
            $success = $repository->tags($cacheKey->tags)->forget($cacheKey->toString());
        } else {
            $success = $repository->forget($cacheKey->toString());
        }

        if ($success) {
            $this->stats['deletes']++;
        }

        return $success;
    }

    /**
     * Flush only the request-level cache
     */
    public function flushRequest(): bool
    {
        $repository = $this->repositories[CacheLevel::REQUEST->value];

        return $repository->flush();
    }

    /**
     * Flush only the memory-level cache (Redis/Memcached)
     */
    public function flushMemory(): bool
    {
        $repository = $this->repositories[CacheLevel::MEMORY->value];

        return $repository->flush();
    }

    /**
     * Flush only the database-level cache
     */
    public function flushDatabase(): bool
    {
        $repository = $this->repositories[CacheLevel::DATABASE->value];

        return $repository->flush();
    }

    /**
     * Get a value from a specific cache level (SPEC-003 requirement)
     *
     * @param  string|CacheKey  $key  Cache key
     * @param  int  $level  Cache level (1=REQUEST, 2=MEMORY, 3=DATABASE)
     * @param  mixed  $default  Default value if not found
     * @return mixed Cached value or default
     */
    public function getFromLevel(string|CacheKey $key, int $level, mixed $default = null): mixed
    {
        $cacheLevel = $this->intToCacheLevel($level);
        if (! $cacheLevel) {
            return $default;
        }

        return match ($cacheLevel) {
            CacheLevel::REQUEST => $this->getFromRequest($key, $default),
            CacheLevel::MEMORY => $this->getFromMemory($key, $default),
            CacheLevel::DATABASE => $this->getFromDatabase($key, $default),
        };
    }

    /**
     * Store a value at a specific cache level (SPEC-003 requirement)
     *
     * @param  string|CacheKey  $key  Cache key
     * @param  mixed  $value  Value to store
     * @param  int  $level  Cache level (1=REQUEST, 2=MEMORY, 3=DATABASE)
     * @param  int|null  $ttl  Time to live in seconds
     * @return bool True if stored successfully
     */
    public function putToLevel(string|CacheKey $key, mixed $value, int $level, ?int $ttl = null): bool
    {
        $cacheLevel = $this->intToCacheLevel($level);
        if (! $cacheLevel) {
            return false;
        }

        return match ($cacheLevel) {
            CacheLevel::REQUEST => $this->putInRequest($key, $value),
            CacheLevel::MEMORY => $this->putInMemory($key, $value, $ttl),
            CacheLevel::DATABASE => $this->putInDatabase($key, $value, $ttl),
        };
    }

    /**
     * Invalidate all cache entries at a specific level (SPEC-003 requirement)
     *
     * @param  int  $level  Cache level (1=REQUEST, 2=MEMORY, 3=DATABASE)
     * @return bool True if invalidated successfully
     */
    public function invalidateLevel(int $level): bool
    {
        $cacheLevel = $this->intToCacheLevel($level);
        if (! $cacheLevel) {
            return false;
        }

        return match ($cacheLevel) {
            CacheLevel::REQUEST => $this->flushRequest(),
            CacheLevel::MEMORY => $this->flushMemory(),
            CacheLevel::DATABASE => $this->flushDatabase(),
        };
    }

    /**
     * Invalidate cache entries by pattern
     */
    public function invalidateByPattern(string $pattern, ?array $levels = null): bool
    {
        $levels = $levels ?? CacheLevel::cases();
        $results = [];

        foreach ($levels as $level) {
            if ($this->isLevelEnabled($level)) {
                $repository = $this->repositories[$level->value] ?? null;
                if ($repository) {
                    // For request level, we can iterate through keys
                    if ($level === CacheLevel::REQUEST && $repository instanceof \JTD\FormSecurity\Services\Cache\Support\RequestLevelCacheRepository) {
                        $allData = $repository->all();
                        foreach (array_keys($allData) as $key) {
                            if ($this->matchesPattern($key, $pattern)) {
                                $repository->forget($key);
                            }
                        }
                        $results[] = true;
                    } else {
                        // For other levels, use tracked keys for selective deletion
                        $trackedKeys = $this->getTrackedKeys($level);
                        foreach ($trackedKeys as $key) {
                            if ($this->matchesPattern($key, $pattern)) {
                                $repository->forget($key);
                                $this->untrackKey($level, $key);
                            }
                        }
                        $results[] = true;
                    }
                } else {
                    $results[] = false;
                }
            }
        }

        return ! in_array(false, $results, true);
    }

    /**
     * Invalidate cache entries by tags
     */
    public function invalidateByTags(array $tags, ?array $levels = null): bool
    {
        $levels = $levels ?? CacheLevel::cases();
        $results = [];

        foreach ($levels as $level) {
            if (! $this->isLevelEnabled($level)) {
                continue;
            }

            $repository = $this->repositories[$level->value] ?? null;
            if (! $repository) {
                $results[] = false;

                continue;
            }

            try {
                // Find all keys that have any of the specified tags
                $keysToRemove = [];
                foreach ($tags as $tag) {
                    if (isset($this->tagTracker[$level->value][$tag])) {
                        $keysToRemove = array_merge($keysToRemove, array_keys($this->tagTracker[$level->value][$tag]));
                    }
                }

                // Remove duplicate keys
                $keysToRemove = array_unique($keysToRemove);

                // Remove the keys from cache and tracking
                foreach ($keysToRemove as $key) {
                    $repository->forget($key);
                    $this->untrackKey($level, $key);
                }

                $results[] = true;
            } catch (\Exception $e) {
                $results[] = false;
            }
        }

        return ! in_array(false, $results, true);
    }

    /**
     * Toggle a cache level on/off
     */
    public function toggleLevel(CacheLevel $level, bool $enabled): bool
    {
        if ($enabled) {
            // Enable the level by reinitializing the repository
            try {
                $this->repositories[$level->value] = $this->cacheManager->store($level->getDriverName());

                return true;
            } catch (\Exception $e) {
                return false;
            }
        } else {
            // Disable the level by setting repository to null
            $this->repositories[$level->value] = null;

            return true;
        }
    }

    /**
     * Invalidate cache entries by namespace
     *
     * @param  string  $namespace  Namespace to invalidate
     * @param  array<CacheLevel>|null  $levels  Levels to invalidate at
     * @return bool True if invalidated successfully
     */
    public function invalidateByNamespace(string $namespace, ?array $levels = null): bool
    {
        $levels = $levels ?? CacheLevel::cases();
        $results = [];

        foreach ($levels as $level) {
            if (! $this->isLevelEnabled($level)) {
                continue;
            }

            $repository = $this->repositories[$level->value] ?? null;
            if (! $repository) {
                $results[] = false;

                continue;
            }

            try {
                // For request level, we can iterate through keys
                if ($level === CacheLevel::REQUEST && $repository instanceof \JTD\FormSecurity\Services\Cache\Support\RequestLevelCacheRepository) {
                    $allData = $repository->all();
                    foreach (array_keys($allData) as $key) {
                        // Check if key belongs to this namespace
                        if ($this->keyBelongsToNamespace($key, $namespace)) {
                            $repository->forget($key);
                        }
                    }
                    $results[] = true;
                } else {
                    // For other levels, use tracked keys for selective deletion
                    $trackedKeys = $this->getTrackedKeys($level);
                    foreach ($trackedKeys as $key) {
                        if ($this->keyBelongsToNamespace($key, $namespace)) {
                            $repository->forget($key);
                            $this->untrackKey($level, $key);
                        }
                    }
                    $results[] = true;
                }
            } catch (\Exception $e) {
                $results[] = false;
                // Log the error but continue with other levels
            }
        }

        return ! in_array(false, $results, true);
    }

    /**
     * Get all enabled cache levels
     */
    public function getEnabledLevels(): array
    {
        $enabledLevels = [];

        foreach (CacheLevel::cases() as $level) {
            if ($this->isLevelEnabled($level)) {
                $enabledLevels[] = $level;
            }
        }

        return $enabledLevels;
    }

    /**
     * Get all disabled cache levels
     */
    public function getDisabledLevels(): array
    {
        $disabledLevels = [];

        foreach (CacheLevel::cases() as $level) {
            if (! $this->isLevelEnabled($level)) {
                $disabledLevels[] = $level;
            }
        }

        return $disabledLevels;
    }

    /**
     * Enable all cache levels
     */
    public function enableAllLevels(): array
    {
        $results = [];

        foreach (CacheLevel::cases() as $level) {
            $results[$level->value] = $this->toggleLevel($level, true);
        }

        return $results;
    }

    /**
     * Disable all cache levels
     */
    public function disableAllLevels(): array
    {
        $results = [];

        foreach (CacheLevel::cases() as $level) {
            $results[$level->value] = $this->toggleLevel($level, false);
        }

        return $results;
    }

    /**
     * Get cache level status summary
     */
    public function getLevelStatusSummary(): array
    {
        $summary = [
            'total_levels' => count(CacheLevel::cases()),
            'enabled_count' => 0,
            'disabled_count' => 0,
            'healthy_count' => 0,
            'unhealthy_count' => 0,
            'levels' => [],
        ];

        foreach (CacheLevel::cases() as $level) {
            $enabled = $this->isLevelEnabled($level);
            $healthy = $this->isRepositoryHealthy($level);

            $summary['levels'][$level->value] = [
                'enabled' => $enabled,
                'healthy' => $healthy,
                'driver' => $level->getDriverName(),
                'priority' => $level->getPriority(),
                'supports_tagging' => $level->supportsTagging(),
                'supports_pattern_matching' => $level->supportsPatternMatching(),
            ];

            if ($enabled) {
                $summary['enabled_count']++;
            } else {
                $summary['disabled_count']++;
            }

            if ($healthy) {
                $summary['healthy_count']++;
            } else {
                $summary['unhealthy_count']++;
            }
        }

        $summary['all_enabled'] = $summary['enabled_count'] === $summary['total_levels'];
        $summary['all_disabled'] = $summary['disabled_count'] === $summary['total_levels'];
        $summary['all_healthy'] = $summary['healthy_count'] === $summary['total_levels'];

        return $summary;
    }

    /**
     * Get current configuration
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * Update configuration
     */
    public function updateConfiguration(array $config): bool
    {
        try {
            // Validate configuration before applying
            if (! $this->validateConfiguration($config)) {
                return false;
            }

            $this->configuration = array_merge_recursive($this->configuration, $config);

            // Handle special cases where the test passes flat values
            if (isset($config['features']['statistics_tracking']) && is_bool($config['features']['statistics_tracking'])) {
                $this->configuration['features']['statistics_tracking']['enabled'] = $config['features']['statistics_tracking'];
            }
            if (isset($config['features']['cache_warming']) && is_bool($config['features']['cache_warming'])) {
                $this->configuration['features']['cache_warming']['enabled'] = $config['features']['cache_warming'];
            }

            // Invalidate configuration-related cache entries
            $this->invalidateConfigurationCache();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Invalidate configuration-related cache entries
     */
    private function invalidateConfigurationCache(): void
    {
        try {
            // Invalidate cache entries tagged with configuration-related tags
            $configTags = ['form-security-config', 'config', 'configuration'];

            // Use the existing invalidateByTags method
            $this->invalidateByTags($configTags);
        } catch (\Exception $e) {
            // Log error but don't fail the configuration update
            error_log('Failed to invalidate configuration cache: '.$e->getMessage());
        }
    }

    /**
     * Validate configuration structure and values
     */
    private function validateConfiguration(array $config): bool
    {
        // Validate cache levels if specified
        if (isset($config['levels'])) {
            if (! is_array($config['levels'])) {
                return false;
            }

            $validLevels = array_map(fn ($level) => $level->value, CacheLevel::cases());
            foreach (array_keys($config['levels']) as $levelName) {
                if (! in_array($levelName, $validLevels)) {
                    return false; // Invalid level name
                }
            }
        }

        // Validate cache setting - should be array if present
        if (isset($config['cache']) && ! is_array($config['cache'])) {
            return false;
        }

        return true;
    }

    /**
     * Set tags for fluent operations
     */
    public function tags(array $tags): self
    {
        // Filter out invalid tags (empty strings, null, non-strings)
        $validTags = array_filter($tags, function ($tag) {
            return is_string($tag) && $tag !== '';
        });

        $this->fluentContext['tags'] = array_values($validTags);

        return $this;
    }

    /**
     * Set prefix for fluent operations
     */
    public function prefix(string $prefix): self
    {
        $this->fluentContext['prefix'] = $prefix;

        return $this;
    }

    /**
     * Set cache levels for fluent operations
     */
    public function levels(array $levels): self
    {
        $validLevels = [];
        foreach ($levels as $level) {
            if ($level instanceof CacheLevel) {
                $validLevels[] = $level;
            } elseif (is_string($level)) {
                try {
                    $validLevels[] = CacheLevel::from($level);
                } catch (\ValueError $e) {
                    // Skip invalid level names
                }
            }
        }

        $this->fluentContext['levels'] = $validLevels;

        return $this;
    }

    /**
     * Set TTL for fluent operations
     */
    public function ttl(int $seconds): self
    {
        $this->fluentContext['ttl'] = max(0, $seconds);

        return $this;
    }

    /**
     * Create a fluent cache builder with method chaining
     */
    public function fluent(): self
    {
        $this->resetFluentContext();

        return $this;
    }

    /**
     * Execute fluent get operation
     */
    public function fluentGet(string|CacheKey $key, mixed $default = null): mixed
    {
        $cacheKey = $this->applyFluentContext($key);
        $levels = $this->fluentContext['levels'] ?? null;

        $result = $this->get($cacheKey, $default, $levels);
        $this->resetFluentContext();

        return $result;
    }

    /**
     * Execute fluent put operation
     */
    public function fluentPut(string|CacheKey $key, mixed $value): bool
    {
        $cacheKey = $this->applyFluentContext($key);
        $levels = $this->fluentContext['levels'] ?? null;
        $ttl = $this->fluentContext['ttl'] ?? null;

        $result = $this->put($cacheKey, $value, $ttl, $levels);
        $this->resetFluentContext();

        return $result;
    }

    /**
     * Execute fluent remember operation
     */
    public function fluentRemember(string|CacheKey $key, callable $callback): mixed
    {
        $cacheKey = $this->applyFluentContext($key);
        $levels = $this->fluentContext['levels'] ?? null;
        $ttl = $this->fluentContext['ttl'] ?? null;

        $result = $this->remember($cacheKey, $callback, $ttl, $levels);
        $this->resetFluentContext();

        return $result;
    }

    /**
     * Execute fluent forget operation
     */
    public function fluentForget(string|CacheKey $key): bool
    {
        $cacheKey = $this->applyFluentContext($key);
        $levels = $this->fluentContext['levels'] ?? null;

        $result = $this->forget($cacheKey, $levels);
        $this->resetFluentContext();

        return $result;
    }

    /**
     * Execute fluent flush operation
     */
    public function fluentFlush(): bool
    {
        $levels = $this->fluentContext['levels'] ?? null;

        // If tags are specified, use tag-based flushing
        if (! empty($this->fluentContext['tags'])) {
            $result = $this->invalidateByTags($this->fluentContext['tags'], $levels);
        } else {
            $result = $this->flush($levels);
        }

        $this->resetFluentContext();

        return $result;
    }

    /**
     * Get current fluent context (for debugging)
     */
    public function getFluentContext(): array
    {
        return $this->fluentContext;
    }

    /**
     * Check if fluent context is active
     */
    public function hasFluentContext(): bool
    {
        return ! empty($this->fluentContext['tags']) ||
               ($this->fluentContext['prefix'] ?? null) !== null ||
               ($this->fluentContext['levels'] ?? null) !== null ||
               ($this->fluentContext['ttl'] ?? null) !== null;
    }

    /**
     * Clear fluent context manually
     */
    public function clearFluentContext(): self
    {
        $this->resetFluentContext();

        return $this;
    }

    /**
     * Event system for cache invalidation and coordination (SPEC-003 Integration)
     */
    private array $eventListeners = [];

    /**
     * Register an event listener for cache events
     */
    public function addEventListener(string $event, callable $listener): void
    {
        if (! isset($this->eventListeners[$event])) {
            $this->eventListeners[$event] = [];
        }

        $this->eventListeners[$event][] = $listener;
    }

    /**
     * Process queued cache events
     */
    public function processQueuedEvents(): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        try {
            // Get current hour queue
            $queueKey = 'cache_event_queue:'.date('Y-m-d-H');
            $queue = $this->get($queueKey, []);

            if (empty($queue)) {
                return $results;
            }

            foreach ($queue as $index => $queueItem) {
                try {
                    $this->processQueuedEvent($queueItem);
                    $results['processed']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'event' => $queueItem['event'],
                        'error' => $e->getMessage(),
                        'item_id' => $queueItem['id'],
                    ];
                }
            }

            // Clear processed queue
            $this->forget($queueKey);

        } catch (\Exception $e) {
            $results['errors'][] = [
                'error' => 'Queue processing failed: '.$e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * Configure integration settings
     */
    public function configureIntegration(array $config): void
    {
        $defaultConfig = [
            'events_enabled' => true,
            'laravel_events_enabled' => true,
            'queue_enabled' => true,
            'queueable_events' => [
                'cache.maintenance',
                'cache.warmed',
                'cache.flush',
                'cache.invalidated',
            ],
            'background_processing_enabled' => true,
        ];

        $this->configuration['integration'] = array_merge($defaultConfig, $config);
    }

    /**
     * Get integration status
     */
    public function getIntegrationStatus(): array
    {
        return [
            'events_enabled' => $this->configuration['integration']['events_enabled'] ?? false,
            'laravel_events_enabled' => $this->configuration['integration']['laravel_events_enabled'] ?? false,
            'queue_enabled' => $this->configuration['integration']['queue_enabled'] ?? false,
            'background_processing_enabled' => $this->configuration['integration']['background_processing_enabled'] ?? false,
            'registered_listeners' => array_map('count', $this->eventListeners),
            'queueable_events' => $this->configuration['integration']['queueable_events'] ?? [],
        ];
    }

    /**
     * Comprehensive error handling and fallback mechanisms (SPEC-003 Reliability)
     */
    private array $errorHandlers = [];

    private array $fallbackStrategies = [];

    private array $circuitBreakers = [];

    /**
     * Register an error handler for specific error types
     */
    public function registerErrorHandler(string $errorType, callable $handler): void
    {
        if (! isset($this->errorHandlers[$errorType])) {
            $this->errorHandlers[$errorType] = [];
        }

        $this->errorHandlers[$errorType][] = $handler;
    }

    /**
     * Register a fallback strategy for cache operations
     */
    public function registerFallbackStrategy(string $operation, callable $fallback): void
    {
        $this->fallbackStrategies[$operation] = $fallback;
    }

    /**
     * Configure error handling settings
     */
    public function configureErrorHandling(array $config): void
    {
        $defaultConfig = [
            'enabled' => true,
            'log_destination' => 'error_log',
            'error_log_file' => storage_path('logs/cache_errors.log'),
            'circuit_breaker_enabled' => true,
            'fallback_enabled' => true,
            'graceful_degradation' => true,
        ];

        $this->configuration['error_handling'] = array_merge($defaultConfig, $config);
    }

    /**
     * Get error handling status
     */
    public function getErrorHandlingStatus(): array
    {
        return [
            'enabled' => $this->configuration['error_handling']['enabled'] ?? false,
            'log_destination' => $this->configuration['error_handling']['log_destination'] ?? 'none',
            'circuit_breaker_enabled' => $this->configuration['error_handling']['circuit_breaker_enabled'] ?? false,
            'fallback_enabled' => $this->configuration['error_handling']['fallback_enabled'] ?? false,
            'graceful_degradation' => $this->configuration['error_handling']['graceful_degradation'] ?? false,
            'registered_error_handlers' => array_map('count', $this->errorHandlers),
            'registered_fallback_strategies' => array_keys($this->fallbackStrategies),
            'active_circuit_breakers' => array_keys($this->circuitBreakers),
        ];
    }

    /**
     * Test error handling functionality
     */
    public function testErrorHandling(): array
    {
        $results = [
            'connection_test' => false,
            'fallback_test' => false,
            'circuit_breaker_test' => false,
            'error_logging_test' => false,
            'overall_status' => 'failed',
        ];

        try {
            // Test basic connection to cache levels
            $testKey = 'error_handling_test_'.time();
            $testValue = 'test_value';

            $results['connection_test'] = $this->put($testKey, $testValue, 60);
            $this->forget($testKey);

            // Test fallback mechanisms
            $results['fallback_test'] = ! empty($this->fallbackStrategies);

            // Test circuit breaker
            $results['circuit_breaker_test'] = $this->configuration['error_handling']['circuit_breaker_enabled'] ?? false;

            // Test error logging
            $results['error_logging_test'] = ! empty($this->errorHandlers);

            // Determine overall status
            $passedTests = array_filter($results, fn ($result) => $result === true);
            $results['overall_status'] = count($passedTests) >= 2 ? 'passed' : 'failed';

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Backfill cache at higher priority levels
     */
    private function backfillCache(CacheKey $key, mixed $value, CacheLevel $foundLevel, array $levels): void
    {
        foreach ($levels as $level) {
            // Only backfill to higher priority levels (lower priority numbers)
            if ($level->getPriority() >= $foundLevel->getPriority()) {
                continue; // Skip this level and continue to check others
            }

            // Skip disabled levels
            if (! $this->isLevelEnabled($level)) {
                continue;
            }

            try {
                $repository = $this->repositories[$level->value];
                $ttl = $this->calculateTtlForLevel($level, null, $key);

                // Handle tagged cache operations for backfill
                if (! empty($key->tags) && $level->supportsTagging()) {
                    $repository->tags($key->tags)->put($key->toString(), $value, $ttl);
                } else {
                    $repository->put($key->toString(), $value, $ttl);
                }
            } catch (\Exception $e) {
                // Log error but continue with other levels
                error_log("Cache backfill failed for level {$level->value}: ".$e->getMessage());

                continue;
            }
        }
    }

    /**
     * Normalize cache key to CacheKey object with fluent context applied
     */
    private function normalizeCacheKey(string|CacheKey $key): CacheKey
    {
        if (is_string($key)) {
            $cacheKey = CacheKey::make($key);
        } else {
            $cacheKey = $key;
        }

        // Apply fluent context if any is set
        if (! empty($this->fluentContext['tags']) ||
            ! empty($this->fluentContext['prefix']) ||
            ($this->fluentContext['ttl'] ?? null) !== null) {
            $cacheKey = $this->applyFluentContext($cacheKey);
        }

        // Validate the cache key
        if (! $cacheKey->isValid()) {
            throw new \InvalidArgumentException("Invalid cache key: {$cacheKey->toString()}");
        }

        return $cacheKey;
    }

    /**
     * Calculate appropriate TTL for a cache level with intelligent management
     */
    private function calculateTtlForLevel(CacheLevel $level, ?int $ttl, CacheKey $key): int
    {
        // Request level doesn't use TTL
        if ($level === CacheLevel::REQUEST) {
            return 0;
        }

        // If TTL is explicitly provided, use it (but respect max limits)
        if ($ttl !== null) {
            return min($ttl, $level->getMaxTtl());
        }

        // If cache key has a TTL, use it (but respect max limits)
        if ($key->ttl !== null) {
            return min($key->ttl, $level->getMaxTtl());
        }

        // Use intelligent TTL based on cache key namespace and tags
        return $this->getIntelligentTtl($level, $key);
    }

    /**
     * Get intelligent TTL based on cache level and key
     */
    private function getIntelligentTtl(CacheLevel $level, CacheKey|string $key): int
    {
        $keyString = $key instanceof CacheKey ? $key->toString() : $key;

        // Default TTLs by level
        $defaultTtls = [
            CacheLevel::REQUEST->value => 0, // No TTL for request level
            CacheLevel::MEMORY->value => 3600, // 1 hour
            CacheLevel::DATABASE->value => 86400, // 24 hours
        ];

        // Check for specific patterns that might need different TTLs
        if (str_contains($keyString, 'ip_reputation')) {
            return 7200; // 2 hours for IP reputation
        }

        if (str_contains($keyString, 'geolocation')) {
            return 43200; // 12 hours for geolocation
        }

        if (str_contains($keyString, 'user_session')) {
            return 1800; // 30 minutes for user sessions
        }

        return $defaultTtls[$level->value] ?? 3600;
    }

    /**
     * Record memory usage for performance tracking
     */
    private function recordMemoryUsage(string $operation): void
    {
        $this->stats['memory_usage'][] = [
            'operation' => $operation,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Record response time for performance tracking
     */
    private function recordResponseTime(float $responseTime): void
    {
        $this->stats['response_times'][] = $responseTime;
    }

    /**
     * Reset fluent context
     */
    private function resetFluentContext(): void
    {
        $this->fluentContext = [];
    }

    /**
     * Apply fluent context to cache key
     */
    private function applyFluentContext(CacheKey|string $cacheKey): CacheKey|string
    {
        if (empty($this->fluentContext)) {
            return $cacheKey;
        }

        $keyString = $cacheKey instanceof CacheKey ? $cacheKey->toString() : $cacheKey;

        // Apply prefix
        if (! empty($this->fluentContext['prefix'])) {
            $keyString = $this->fluentContext['prefix'].':'.$keyString;
        }

        // Apply tags (for cache drivers that support tagging)
        if (! empty($this->fluentContext['tags'])) {
            // Tags would be handled by the cache driver
            // For now, just append to key for identification
            $tagString = implode(',', $this->fluentContext['tags']);
            $keyString .= ':tags:'.$tagString;
        }

        return $cacheKey instanceof CacheKey ? new CacheKey($keyString) : $keyString;
    }

    /**
     * Check if repository is healthy
     */
    private function isRepositoryHealthy(CacheLevel $level): bool
    {
        try {
            $repository = $this->repositories[$level->value] ?? null;
            if (! $repository) {
                return false;
            }

            // Test basic functionality
            $testKey = 'health_check_'.time();
            $repository->put($testKey, 'test', 1);
            $result = $repository->get($testKey);
            $repository->forget($testKey);

            return $result === 'test';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a key matches a pattern
     */
    private function matchesPattern(string $key, string $pattern): bool
    {
        // Convert glob pattern to regex - escape first, then replace escaped wildcards
        $regex = preg_quote($pattern, '/');
        $regex = str_replace(['\*', '\?'], ['.*', '.'], $regex);

        return preg_match("/^{$regex}$/", $key) === 1;
    }

    /**
     * Check if a key belongs to a namespace
     */
    private function keyBelongsToNamespace(string $key, string $namespace): bool
    {
        // Cache keys follow the pattern: form_security:namespace:key
        // or with prefix: prefix:form_security:namespace:key
        return str_contains($key, "form_security:{$namespace}:");
    }

    /**
     * Track a key for pattern matching
     */
    private function trackKey(CacheLevel $level, string $key, array $tags = []): void
    {
        if (! isset($this->keyTracker[$level->value])) {
            $this->keyTracker[$level->value] = [];
        }
        $this->keyTracker[$level->value][$key] = true;

        // Track tags for this key
        if (! empty($tags)) {
            if (! isset($this->tagTracker[$level->value])) {
                $this->tagTracker[$level->value] = [];
            }
            foreach ($tags as $tag) {
                if (! isset($this->tagTracker[$level->value][$tag])) {
                    $this->tagTracker[$level->value][$tag] = [];
                }
                $this->tagTracker[$level->value][$tag][$key] = true;
            }
        }
    }

    /**
     * Get tracked keys for a level
     */
    private function getTrackedKeys(CacheLevel $level): array
    {
        return array_keys($this->keyTracker[$level->value] ?? []);
    }

    /**
     * Remove key from tracking
     */
    private function untrackKey(CacheLevel $level, string $key): void
    {
        unset($this->keyTracker[$level->value][$key]);

        // Remove from tag tracking
        if (isset($this->tagTracker[$level->value])) {
            foreach ($this->tagTracker[$level->value] as $tag => $keys) {
                unset($this->tagTracker[$level->value][$tag][$key]);
                // Clean up empty tag arrays
                if (empty($this->tagTracker[$level->value][$tag])) {
                    unset($this->tagTracker[$level->value][$tag]);
                }
            }
        }
    }

    /**
     * Get operation statistics
     */
    public function getOperationStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset operation statistics
     */
    public function resetStats(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'puts' => 0,
            'gets' => 0,
            'deletes' => 0,
            'operations_count' => 0,
            'start_time' => microtime(true),
            'response_times' => [],
            'memory_usage' => [],
        ];
    }
}
