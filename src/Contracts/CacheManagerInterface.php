<?php

declare(strict_types=1);

/**
 * Contract File: CacheManagerInterface.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Contract interface for the multi-level caching system manager
 * defining three-tier caching operations with intelligent invalidation.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-003-multi-level-caching-system.md
 * @see docs/Planning/Sprints/004-caching-cli-integration.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1014-multi-level-caching-system.md
 */

namespace JTD\FormSecurity\Contracts;

use JTD\FormSecurity\Enums\CacheLevel;
use JTD\FormSecurity\ValueObjects\CacheKey;

/**
 * CacheManagerInterface Contract
 *
 * Defines the interface for the multi-level caching system that provides
 * three-tier caching (Request → Memory → Database) with intelligent
 * invalidation and performance monitoring.
 */
interface CacheManagerInterface
{
    /**
     * Get a value from the cache using multi-level fallback
     *
     * @param string|CacheKey $key Cache key
     * @param mixed $default Default value if not found
     * @param array<CacheLevel>|null $levels Specific levels to check (null for all)
     * @return mixed Cached value or default
     */
    public function get(string|CacheKey $key, mixed $default = null, ?array $levels = null): mixed;

    /**
     * Store a value in the cache at specified levels
     *
     * @param string|CacheKey $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (null for default)
     * @param array<CacheLevel>|null $levels Levels to store at (null for all)
     * @return bool True if stored successfully
     */
    public function put(string|CacheKey $key, mixed $value, ?int $ttl = null, ?array $levels = null): bool;

    /**
     * Store a value in the cache only if it doesn't exist
     *
     * @param string|CacheKey $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds
     * @param array<CacheLevel>|null $levels Levels to store at
     * @return bool True if stored successfully
     */
    public function add(string|CacheKey $key, mixed $value, ?int $ttl = null, ?array $levels = null): bool;

    /**
     * Get a value from cache or execute callback and cache the result
     *
     * @param string|CacheKey $key Cache key
     * @param callable $callback Callback to execute if not cached
     * @param int|null $ttl Time to live in seconds
     * @param array<CacheLevel>|null $levels Levels to use
     * @return mixed Cached or computed value
     */
    public function remember(string|CacheKey $key, callable $callback, ?int $ttl = null, ?array $levels = null): mixed;

    /**
     * Get a value from cache or execute callback and cache forever
     *
     * @param string|CacheKey $key Cache key
     * @param callable $callback Callback to execute if not cached
     * @param array<CacheLevel>|null $levels Levels to use
     * @return mixed Cached or computed value
     */
    public function rememberForever(string|CacheKey $key, callable $callback, ?array $levels = null): mixed;

    /**
     * Remove a value from the cache at all levels
     *
     * @param string|CacheKey $key Cache key
     * @param array<CacheLevel>|null $levels Specific levels to remove from
     * @return bool True if removed successfully
     */
    public function forget(string|CacheKey $key, ?array $levels = null): bool;

    /**
     * Check if a key exists in the cache
     *
     * @param string|CacheKey $key Cache key
     * @param array<CacheLevel>|null $levels Levels to check
     * @return bool True if key exists
     */
    public function has(string|CacheKey $key, ?array $levels = null): bool;

    /**
     * Flush all cache data at specified levels
     *
     * @param array<CacheLevel>|null $levels Levels to flush (null for all)
     * @return bool True if flushed successfully
     */
    public function flush(?array $levels = null): bool;

    /**
     * Get cache statistics for performance monitoring
     *
     * @param array<CacheLevel>|null $levels Levels to get stats for
     * @return array<string, mixed> Cache statistics
     */
    public function getStats(?array $levels = null): array;

    /**
     * Warm up the cache with frequently accessed data
     *
     * @param array<string, callable> $warmers Key-callback pairs for warming
     * @param array<CacheLevel>|null $levels Levels to warm
     * @return array<string, bool> Results of warming operations
     */
    public function warm(array $warmers, ?array $levels = null): array;

    /**
     * Invalidate cache entries by tags
     *
     * @param array<string> $tags Tags to invalidate
     * @param array<CacheLevel>|null $levels Levels to invalidate at
     * @return bool True if invalidated successfully
     */
    public function invalidateByTags(array $tags, ?array $levels = null): bool;

    /**
     * Invalidate cache entries by pattern
     *
     * @param string $pattern Key pattern to match
     * @param array<CacheLevel>|null $levels Levels to invalidate at
     * @return bool True if invalidated successfully
     */
    public function invalidateByPattern(string $pattern, ?array $levels = null): bool;

    /**
     * Get the cache hit ratio for performance monitoring
     *
     * @param array<CacheLevel>|null $levels Levels to calculate ratio for
     * @return float Hit ratio as percentage (0.0 to 100.0)
     */
    public function getHitRatio(?array $levels = null): float;

    /**
     * Get the average response time for cache operations
     *
     * @param array<CacheLevel>|null $levels Levels to calculate for
     * @return float Average response time in milliseconds
     */
    public function getAverageResponseTime(?array $levels = null): float;

    /**
     * Enable or disable cache level
     *
     * @param CacheLevel $level Cache level to toggle
     * @param bool $enabled Whether to enable or disable
     * @return bool True if toggled successfully
     */
    public function toggleLevel(CacheLevel $level, bool $enabled): bool;

    /**
     * Check if a cache level is enabled
     *
     * @param CacheLevel $level Cache level to check
     * @return bool True if enabled
     */
    public function isLevelEnabled(CacheLevel $level): bool;

    /**
     * Get the current cache configuration
     *
     * @return array<string, mixed> Cache configuration
     */
    public function getConfiguration(): array;

    /**
     * Update cache configuration at runtime
     *
     * @param array<string, mixed> $config Configuration updates
     * @return bool True if updated successfully
     */
    public function updateConfiguration(array $config): bool;

    /**
     * Perform cache maintenance operations
     *
     * @param array<string> $operations Operations to perform
     * @return array<string, bool> Results of maintenance operations
     */
    public function maintenance(array $operations = ['cleanup', 'optimize']): array;

    /**
     * Get cache size information
     *
     * @param array<CacheLevel>|null $levels Levels to get size for
     * @return array<string, int> Size information in bytes
     */
    public function getSize(?array $levels = null): array;

    /**
     * Set cache tags for subsequent operations
     *
     * @param array<string> $tags Tags to set
     * @return self Fluent interface
     */
    public function tags(array $tags): self;

    /**
     * Set cache prefix for subsequent operations
     *
     * @param string $prefix Prefix to set
     * @return self Fluent interface
     */
    public function prefix(string $prefix): self;
}
