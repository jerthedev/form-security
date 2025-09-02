<?php

declare(strict_types=1);

/**
 * Event File: CacheInvalidated.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Event fired when cache entries are invalidated
 * for tracking and logging cache invalidation operations.
 */

namespace JTD\FormSecurity\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JTD\FormSecurity\Enums\CacheLevel;
use JTD\FormSecurity\ValueObjects\CacheKey;

/**
 * CacheInvalidated Event
 *
 * Fired when cache entries are invalidated, providing information
 * about what was invalidated and at which cache levels.
 */
class CacheInvalidated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new cache invalidated event instance.
     *
     * @param  CacheKey  $cacheKey  The cache key that was invalidated
     * @param  array<CacheLevel>|null  $levels  Cache levels where invalidation occurred
     * @param  string|null  $reason  Reason for invalidation (optional)
     * @param  array<string, mixed>  $metadata  Additional metadata about the invalidation
     */
    public function __construct(
        public readonly CacheKey $cacheKey,
        public readonly ?array $levels = null,
        public readonly ?string $reason = null,
        public readonly array $metadata = []
    ) {
        // Alias for backward compatibility
        $this->key = $this->cacheKey;
    }

    /**
     * Cache key (alias for cacheKey for backward compatibility)
     */
    public readonly CacheKey $key;

    /**
     * Get the cache key as a string
     */
    public function getCacheKeyString(): string
    {
        return $this->cacheKey->toString();
    }

    /**
     * Get the affected cache levels as strings
     *
     * @return array<string>
     */
    public function getLevelStrings(): array
    {
        if ($this->levels === null) {
            return array_map(fn (CacheLevel $level) => $level->value, CacheLevel::cases());
        }

        return array_map(fn (CacheLevel $level) => $level->value, $this->levels);
    }

    /**
     * Check if a specific cache level was affected
     */
    public function affectedLevel(CacheLevel $level): bool
    {
        if ($this->levels === null) {
            return true; // All levels affected if none specified
        }

        return in_array($level, $this->levels, true);
    }

    /**
     * Get event data for logging
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cache_key' => $this->getCacheKeyString(),
            'namespace' => $this->cacheKey->namespace,
            'tags' => $this->cacheKey->tags,
            'levels' => $this->getLevelStrings(),
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }
}
