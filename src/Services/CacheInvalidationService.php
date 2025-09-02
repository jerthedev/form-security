<?php

declare(strict_types=1);

/**
 * Service File: CacheInvalidationService.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Intelligent cache invalidation service with dependency tracking
 * and event-driven updates for the multi-level caching system.
 */

namespace JTD\FormSecurity\Services;

use Illuminate\Events\Dispatcher;
use JTD\FormSecurity\Contracts\CacheManagerInterface;
use JTD\FormSecurity\Events\CacheInvalidated;
use JTD\FormSecurity\ValueObjects\CacheKey;

/**
 * CacheInvalidationService
 *
 * Handles intelligent cache invalidation with dependency tracking,
 * event-driven updates, and cascade invalidation for related cache entries.
 */
class CacheInvalidationService
{
    /**
     * Cache dependency mappings
     *
     * @var array<string, array<string>>
     */
    private array $dependencies = [];

    /**
     * Invalidation statistics
     *
     * @var array<string, int>
     */
    private array $stats = [
        'invalidations' => 0,
        'cascade_invalidations' => 0,
        'dependency_invalidations' => 0,
    ];

    public function __construct(
        private CacheManagerInterface $cacheManager,
        private Dispatcher $eventDispatcher
    ) {
        $this->initializeDependencies();
    }

    /**
     * Initialize cache dependency mappings
     */
    private function initializeDependencies(): void
    {
        $this->dependencies = [
            // Configuration changes affect many other caches
            'configuration' => [
                'spam_patterns',
                'ip_reputation',
                'analytics',
            ],

            // Spam pattern changes affect analysis results
            'spam_patterns' => [
                'analytics',
                'statistics',
            ],

            // IP reputation changes affect geolocation and analytics
            'ip_reputation' => [
                'geolocation',
                'analytics',
                'statistics',
            ],

            // Model changes affect their related caches
            'models.spam_pattern' => [
                'spam_patterns',
                'analytics',
            ],

            'models.ip_reputation' => [
                'ip_reputation',
                'geolocation',
                'analytics',
            ],
        ];
    }

    /**
     * Invalidate cache by key with dependency tracking
     */
    public function invalidate(string|CacheKey $key, ?array $levels = null): bool
    {
        $cacheKey = $this->normalizeCacheKey($key);
        $success = $this->cacheManager->forget($cacheKey, $levels);

        if ($success) {
            $this->stats['invalidations']++;

            // Fire invalidation event
            $this->eventDispatcher->dispatch(new CacheInvalidated($cacheKey, $levels));

            // Handle cascade invalidation
            $this->handleCascadeInvalidation($cacheKey, $levels);
        }

        return $success;
    }

    /**
     * Invalidate cache by tags with intelligent dependency handling
     */
    public function invalidateByTags(array $tags, ?array $levels = null): bool
    {
        $success = $this->cacheManager->invalidateByTags($tags, $levels);

        if ($success) {
            $this->stats['invalidations']++;

            // Handle dependent tag invalidation
            $this->handleDependentTagInvalidation($tags, $levels);
        }

        return $success;
    }

    /**
     * Invalidate cache by namespace with dependency tracking
     */
    public function invalidateByNamespace(string $namespace, ?array $levels = null): bool
    {
        // Create a pattern-based invalidation for the namespace
        $pattern = "form_security:{$namespace}:*";
        $success = $this->cacheManager->invalidateByPattern($pattern, $levels);

        if ($success) {
            $this->stats['invalidations']++;

            // Handle namespace dependency invalidation
            $this->handleNamespaceDependencyInvalidation($namespace, $levels);
        }

        return $success;
    }

    /**
     * Invalidate cache when model is updated
     */
    public function invalidateForModel(string $modelClass, mixed $modelId, ?array $levels = null): bool
    {
        $modelKey = 'models.'.strtolower(class_basename($modelClass));
        $success = true;

        // Invalidate specific model cache
        $specificKey = CacheKey::make("{$modelKey}:{$modelId}");
        if (! $this->invalidate($specificKey, $levels)) {
            $success = false;
        }

        // Invalidate model collection caches
        $collectionKey = CacheKey::make($modelKey);
        if (! $this->invalidate($collectionKey, $levels)) {
            $success = false;
        }

        // Handle model-specific dependencies
        $this->handleModelDependencyInvalidation($modelKey, $levels);

        return $success;
    }

    /**
     * Handle cascade invalidation based on dependencies
     */
    private function handleCascadeInvalidation(CacheKey $key, ?array $levels): void
    {
        $dependentNamespaces = $this->dependencies[$key->namespace] ?? [];

        foreach ($dependentNamespaces as $dependentNamespace) {
            $this->invalidateByNamespace($dependentNamespace, $levels);
            $this->stats['cascade_invalidations']++;
        }
    }

    /**
     * Handle dependent tag invalidation
     */
    private function handleDependentTagInvalidation(array $tags, ?array $levels): void
    {
        foreach ($tags as $tag) {
            $dependentNamespaces = $this->dependencies[$tag] ?? [];

            foreach ($dependentNamespaces as $dependentNamespace) {
                $this->invalidateByNamespace($dependentNamespace, $levels);
                $this->stats['dependency_invalidations']++;
            }
        }
    }

    /**
     * Handle namespace dependency invalidation
     */
    private function handleNamespaceDependencyInvalidation(string $namespace, ?array $levels): void
    {
        $dependentNamespaces = $this->dependencies[$namespace] ?? [];

        foreach ($dependentNamespaces as $dependentNamespace) {
            $this->invalidateByNamespace($dependentNamespace, $levels);
            $this->stats['dependency_invalidations']++;
        }
    }

    /**
     * Handle model-specific dependency invalidation
     */
    private function handleModelDependencyInvalidation(string $modelKey, ?array $levels): void
    {
        $dependentNamespaces = $this->dependencies[$modelKey] ?? [];

        foreach ($dependentNamespaces as $dependentNamespace) {
            $this->invalidateByNamespace($dependentNamespace, $levels);
            $this->stats['dependency_invalidations']++;
        }
    }

    /**
     * Add a cache dependency relationship
     */
    public function addDependency(string $source, string $dependent): void
    {
        if (! isset($this->dependencies[$source])) {
            $this->dependencies[$source] = [];
        }

        if (! in_array($dependent, $this->dependencies[$source], true)) {
            $this->dependencies[$source][] = $dependent;
        }
    }

    /**
     * Remove a cache dependency relationship
     */
    public function removeDependency(string $source, string $dependent): void
    {
        if (isset($this->dependencies[$source])) {
            $this->dependencies[$source] = array_filter(
                $this->dependencies[$source],
                fn ($dep) => $dep !== $dependent
            );
        }
    }

    /**
     * Get invalidation statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get dependency mappings
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Reset invalidation statistics
     */
    public function resetStats(): void
    {
        $this->stats = [
            'invalidations' => 0,
            'cascade_invalidations' => 0,
            'dependency_invalidations' => 0,
        ];
    }

    /**
     * Normalize cache key to CacheKey object
     */
    private function normalizeCacheKey(string|CacheKey $key): CacheKey
    {
        if (is_string($key)) {
            return CacheKey::make($key);
        }

        return $key;
    }
}
