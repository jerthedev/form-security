<?php

declare(strict_types=1);

/**
 * Enum File: CacheLevel.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Enumeration for the three-tier caching system levels
 * including request-level, memory-level, and database-level caching.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-003-multi-level-caching-system.md
 * @see docs/Planning/Sprints/004-caching-cli-integration.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1014-multi-level-caching-system.md
 */

namespace JTD\FormSecurity\Enums;

/**
 * CacheLevel Enumeration
 *
 * Defines the three-tier caching system levels used by the CacheManager.
 * Each level has different performance characteristics and use cases:
 * - REQUEST: Ultra-fast in-memory caching for single request lifecycle
 * - MEMORY: High-speed distributed caching using Redis/Memcached
 * - DATABASE: Persistent caching with database storage for durability
 */
enum CacheLevel: string
{
    /**
     * Request-level caching using Laravel 12's memo driver
     * - Fastest access (sub-1ms response times)
     * - Single request lifecycle only
     * - No persistence between requests
     * - Ideal for repeated calculations within same request
     */
    case REQUEST = 'request';

    /**
     * Memory-level caching using Redis/Memcached
     * - Fast access (1-5ms response times)
     * - Shared across requests and processes
     * - Configurable TTL and eviction policies
     * - Ideal for frequently accessed data
     */
    case MEMORY = 'memory';

    /**
     * Database-level caching with persistent storage
     * - Slower access (5-50ms response times)
     * - Persistent across server restarts
     * - Longer TTL for stable data
     * - Ideal for expensive computations and external API results
     */
    case DATABASE = 'database';

    /**
     * Get the default TTL (Time To Live) for this cache level in seconds
     */
    public function getDefaultTtl(): int
    {
        return match ($this) {
            self::REQUEST => 0, // No TTL - cleared at end of request
            self::MEMORY => 3600, // 1 hour
            self::DATABASE => 86400, // 24 hours
        };
    }

    /**
     * Get the maximum TTL allowed for this cache level in seconds
     */
    public function getMaxTtl(): int
    {
        return match ($this) {
            self::REQUEST => 0, // No TTL - request lifecycle only
            self::MEMORY => 43200, // 12 hours
            self::DATABASE => 604800, // 7 days
        };
    }

    /**
     * Get the expected response time range for this cache level
     *
     * @return array{min: float, max: float} Response time in milliseconds
     */
    public function getResponseTimeRange(): array
    {
        return match ($this) {
            self::REQUEST => ['min' => 0.1, 'max' => 0.9],
            self::MEMORY => ['min' => 1.0, 'max' => 4.9],
            self::DATABASE => ['min' => 5.0, 'max' => 50.0],
        };
    }

    /**
     * Check if this cache level supports tagging
     */
    public function supportsTagging(): bool
    {
        return match ($this) {
            self::REQUEST => false, // Laravel memo driver doesn't support tags
            self::MEMORY => true, // Redis/Memcached support tags
            self::DATABASE => true, // Database cache supports tags
        };
    }

    /**
     * Check if this cache level supports distributed caching
     */
    public function supportsDistribution(): bool
    {
        return match ($this) {
            self::REQUEST => false, // Request-level is per-process only
            self::MEMORY => true, // Redis/Memcached are distributed
            self::DATABASE => true, // Database is shared across instances
        };
    }

    /**
     * Check if this cache level supports pattern matching for key operations
     */
    public function supportsPatternMatching(): bool
    {
        return match ($this) {
            self::REQUEST => false, // Array driver doesn't support pattern matching
            self::MEMORY => true, // Redis supports pattern matching with KEYS command
            self::DATABASE => false, // Database cache doesn't support pattern matching efficiently
        };
    }

    /**
     * Get the priority order for cache level fallback
     * Lower numbers indicate higher priority
     */
    public function getPriority(): int
    {
        return match ($this) {
            self::REQUEST => 1, // Highest priority - check first
            self::MEMORY => 2, // Medium priority - check second
            self::DATABASE => 3, // Lowest priority - check last
        };
    }

    /**
     * Get all cache levels ordered by priority (fastest to slowest)
     *
     * @return array<self>
     */
    public static function getByPriority(): array
    {
        $levels = self::cases();
        usort($levels, fn (self $a, self $b) => $a->getPriority() <=> $b->getPriority());

        return $levels;
    }

    /**
     * Get cache levels that support the given feature
     *
     * @param  string  $feature  Feature name ('tagging', 'distribution', 'pattern_matching', etc.)
     * @return array<self>
     */
    public static function getSupportingLevels(string $feature): array
    {
        return array_filter(self::cases(), function (self $level) use ($feature) {
            return match ($feature) {
                'tagging' => $level->supportsTagging(),
                'distribution' => $level->supportsDistribution(),
                'pattern_matching' => $level->supportsPatternMatching(),
                default => false,
            };
        });
    }

    /**
     * Get the Laravel cache driver name for this level
     */
    public function getDriverName(): string
    {
        // In test environment, use array driver for all levels except database
        if (app()->environment('testing')) {
            return match ($this) {
                self::REQUEST => 'array',
                self::MEMORY => 'array',
                self::DATABASE => 'array', // Use array for database cache in tests too
            };
        }

        return match ($this) {
            self::REQUEST => 'array', // Use array driver for request-level
            self::MEMORY => config('form-security-cache.driver', 'redis'),
            self::DATABASE => 'database',
        };
    }

    /**
     * Check if this cache level is suitable for the given data size
     *
     * @param  int  $sizeInBytes  Data size in bytes
     */
    public function isSuitableForSize(int $sizeInBytes): bool
    {
        return match ($this) {
            self::REQUEST => $sizeInBytes <= 1024 * 1024, // 1MB limit for request cache
            self::MEMORY => $sizeInBytes <= 10 * 1024 * 1024, // 10MB limit for memory cache
            self::DATABASE => true, // No size limit for database cache
        };
    }

    /**
     * Get human-readable description of this cache level
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::REQUEST => 'Ultra-fast request-level caching for single request lifecycle',
            self::MEMORY => 'High-speed memory caching using Redis/Memcached for shared access',
            self::DATABASE => 'Persistent database caching for long-term storage and durability',
        };
    }
}
