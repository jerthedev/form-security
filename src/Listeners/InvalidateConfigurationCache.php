<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Listeners;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use JTD\FormSecurity\Events\ConfigurationCacheInvalidated;
use JTD\FormSecurity\Events\ConfigurationChanged;

/**
 * Configuration cache invalidation listener.
 *
 * This listener handles cache invalidation when configuration changes,
 * ensuring cache consistency and performance optimization.
 */
class InvalidateConfigurationCache
{
    /**
     * Create a new cache invalidation listener instance.
     *
     * @param  CacheRepository  $cache  Cache repository
     */
    public function __construct(
        protected CacheRepository $cache
    ) {}

    /**
     * Handle the configuration changed event.
     *
     * @param  ConfigurationChanged  $event  Configuration changed event
     */
    public function handle(ConfigurationChanged $event): void
    {
        $keysToInvalidate = $event->getAffectedCacheKeys();

        $invalidatedKeys = [];

        foreach ($keysToInvalidate as $cacheKey) {
            if ($this->invalidateCacheKey($cacheKey)) {
                $invalidatedKeys[] = $cacheKey;
            }
        }

        if (! empty($invalidatedKeys)) {
            // Fire cache invalidated event
            ConfigurationCacheInvalidated::dispatch(
                $invalidatedKeys,
                'configuration_changed',
                [
                    'configuration_key' => $event->key,
                    'change_type' => $event->changeType,
                    'user_id' => $event->userId,
                ]
            );

            // Log cache invalidation
            Log::info('Configuration cache invalidated', [
                'configuration_key' => $event->key,
                'invalidated_keys' => $invalidatedKeys,
                'change_type' => $event->changeType,
                'user_id' => $event->userId,
            ]);
        }
    }

    /**
     * Invalidate a specific cache key.
     *
     * @param  string  $cacheKey  Cache key to invalidate
     * @return bool True if cache was invalidated successfully
     */
    protected function invalidateCacheKey(string $cacheKey): bool
    {
        try {
            // Handle wildcard patterns
            if (str_contains($cacheKey, '*')) {
                return $this->invalidateWildcardPattern($cacheKey);
            }

            // Handle specific cache key
            return $this->cache->forget($cacheKey);
        } catch (\Exception $e) {
            Log::warning('Failed to invalidate cache key', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Invalidate cache keys matching a wildcard pattern.
     *
     * @param  string  $pattern  Wildcard pattern
     * @return bool True if invalidation was attempted
     */
    protected function invalidateWildcardPattern(string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = '/^'.str_replace(['*', '.'], ['.*', '\.'], preg_quote($pattern, '/')).'$/';

        // Get all cache keys (this is cache-driver dependent)
        // For Redis, we can use KEYS command
        // For other drivers, we might need to maintain a key registry

        try {
            if (method_exists($this->cache->getStore(), 'connection')) {
                $connection = $this->cache->getStore()->connection();

                if (method_exists($connection, 'keys')) {
                    $keys = $connection->keys($pattern);

                    foreach ($keys as $key) {
                        $this->cache->forget($key);
                    }

                    return true;
                }
            }

            // Fallback: just log the pattern for manual cleanup
            Log::info('Wildcard cache invalidation requested', [
                'pattern' => $pattern,
                'note' => 'Manual cleanup may be required for non-Redis cache drivers',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::warning('Failed to invalidate wildcard cache pattern', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the cache tags for configuration.
     *
     * @param  string  $configurationKey  Configuration key
     * @return array<string> Cache tags
     */
    protected function getCacheTags(string $configurationKey): array
    {
        $tags = ['form_security_config'];

        // Add hierarchical tags
        $keyParts = explode('.', $configurationKey);
        $currentKey = '';

        foreach ($keyParts as $part) {
            $currentKey = $currentKey ? "{$currentKey}.{$part}" : $part;
            $tags[] = "config_{$currentKey}";
        }

        return $tags;
    }

    /**
     * Invalidate cache by tags if supported.
     *
     * @param  array<string>  $tags  Cache tags to invalidate
     * @return bool True if invalidation was successful
     */
    protected function invalidateByTags(array $tags): bool
    {
        try {
            if (method_exists($this->cache, 'tags')) {
                $this->cache->tags($tags)->flush();

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('Failed to invalidate cache by tags', [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
