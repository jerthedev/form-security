<?php

declare(strict_types=1);

/**
 * Contract File: CacheableModelInterface.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Contract interface for models that support caching operations
 * including cache key generation, expiration management, and cache invalidation.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Contracts;

use Carbon\Carbon;

/**
 * CacheableModelInterface Contract
 *
 * Defines the interface for models that support caching operations.
 * This includes cache key generation, expiration management, and cache invalidation.
 * Implemented by models like IpReputation and SpamPattern.
 */
interface CacheableModelInterface extends ModelInterface
{
    /**
     * Generate a unique cache key for this model instance
     */
    public function getCacheKey(): string;

    /**
     * Generate a cache key for a specific lookup value
     */
    public static function getCacheKeyFor(string $identifier): string;

    /**
     * Get the cache expiration time for this model
     */
    public function getCacheExpiration(): ?Carbon;

    /**
     * Check if the cached data has expired
     */
    public function isCacheExpired(): bool;

    /**
     * Refresh the cache expiration time
     */
    public function refreshCacheExpiration(): bool;

    /**
     * Invalidate the cache for this model instance
     */
    public function invalidateCache(): bool;

    /**
     * Get cached data or retrieve from database
     */
    public static function getCached(string $identifier): ?static;

    /**
     * Store model data in cache
     */
    public function storeInCache(): bool;

    /**
     * Remove model data from cache
     */
    public function removeFromCache(): bool;

    /**
     * Get the default cache TTL in seconds
     */
    public static function getDefaultCacheTtl(): int;
}
