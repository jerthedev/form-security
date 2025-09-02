<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services\Cache\Support;

/**
 * RequestLevelCacheRepository
 * 
 * In-memory cache repository for request-level caching
 * This provides the same interface as Laravel's cache repositories
 * but stores data in a simple array that persists only for the current request
 */
class RequestLevelCacheRepository
{
    private array $data = [];
    private array $expiration = [];

    public function __construct(private array &$requestCache)
    {
        // Reference the shared request cache array
        $this->data = &$requestCache;
    }

    /**
     * Retrieve an item from the cache by key
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Check if key exists and hasn't expired
        if (!$this->has($key)) {
            return $default;
        }

        return $this->data[$key] ?? $default;
    }

    /**
     * Store an item in the cache for a given number of seconds
     */
    public function put(string $key, mixed $value, int $seconds = null): bool
    {
        $this->data[$key] = $value;
        
        // Set expiration if TTL is provided
        if ($seconds !== null && $seconds > 0) {
            $this->expiration[$key] = time() + $seconds;
        }

        return true;
    }

    /**
     * Store an item in the cache indefinitely
     */
    public function forever(string $key, mixed $value): bool
    {
        $this->data[$key] = $value;
        // Remove any expiration
        unset($this->expiration[$key]);
        return true;
    }

    /**
     * Remove an item from the cache
     */
    public function forget(string $key): bool
    {
        unset($this->data[$key]);
        unset($this->expiration[$key]);
        return true;
    }

    /**
     * Remove all items from the cache
     */
    public function flush(): bool
    {
        $this->data = [];
        $this->expiration = [];
        return true;
    }

    /**
     * Determine if an item exists in the cache
     */
    public function has(string $key): bool
    {
        // Check if key exists
        if (!array_key_exists($key, $this->data)) {
            return false;
        }

        // Check if key has expired
        if (isset($this->expiration[$key]) && $this->expiration[$key] <= time()) {
            // Key has expired, remove it
            $this->forget($key);
            return false;
        }

        return true;
    }

    /**
     * Store an item in the cache if the key doesn't exist
     */
    public function add(string $key, mixed $value, int $seconds = null): bool
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->put($key, $value, $seconds);
    }

    /**
     * Increment the value of an item in the cache
     */
    public function increment(string $key, int $value = 1): int|false
    {
        if (!$this->has($key)) {
            $this->put($key, $value);
            return $value;
        }

        $current = $this->get($key, 0);
        if (!is_numeric($current)) {
            return false;
        }

        $new = (int)$current + $value;
        $this->put($key, $new);
        return $new;
    }

    /**
     * Decrement the value of an item in the cache
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

    /**
     * Get multiple items from the cache by key
     */
    public function many(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    /**
     * Store multiple items in the cache for a given number of seconds
     */
    public function putMany(array $values, int $seconds = null): bool
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }
        return true;
    }

    /**
     * Get the cache key prefix
     */
    public function getPrefix(): string
    {
        return 'request_cache:';
    }

    /**
     * Get all cached data (for debugging)
     */
    public function all(): array
    {
        // Clean expired items first
        $this->cleanExpired();
        return $this->data;
    }

    /**
     * Clean expired items
     */
    private function cleanExpired(): void
    {
        $now = time();
        foreach ($this->expiration as $key => $expireTime) {
            if ($expireTime <= $now) {
                $this->forget($key);
            }
        }
    }

    /**
     * Get cache size information
     */
    public function size(): array
    {
        $this->cleanExpired();
        return [
            'keys' => count($this->data),
            'memory_usage' => strlen(serialize($this->data)),
        ];
    }
}
