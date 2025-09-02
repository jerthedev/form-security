<?php

declare(strict_types=1);

/**
 * Observer File: CacheObserver.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Model observer for automatic cache invalidation
 * when models are created, updated, or deleted.
 */

namespace JTD\FormSecurity\Observers;

use Illuminate\Database\Eloquent\Model;
use JTD\FormSecurity\Services\CacheInvalidationService;

/**
 * CacheObserver
 *
 * Automatically invalidates related cache entries when models
 * are created, updated, or deleted to maintain cache consistency.
 */
class CacheObserver
{
    public function __construct(
        private CacheInvalidationService $cacheInvalidationService
    ) {}

    /**
     * Handle the model "created" event.
     */
    public function created(Model $model): void
    {
        $this->invalidateModelCache($model, 'created');
    }

    /**
     * Handle the model "updated" event.
     */
    public function updated(Model $model): void
    {
        $this->invalidateModelCache($model, 'updated');
    }

    /**
     * Handle the model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->invalidateModelCache($model, 'deleted');
    }

    /**
     * Handle the model "restored" event.
     */
    public function restored(Model $model): void
    {
        $this->invalidateModelCache($model, 'restored');
    }

    /**
     * Handle the model "force deleted" event.
     */
    public function forceDeleted(Model $model): void
    {
        $this->invalidateModelCache($model, 'force_deleted');
    }

    /**
     * Invalidate cache for the given model
     */
    private function invalidateModelCache(Model $model, string $action): void
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        // Invalidate model-specific cache
        $this->cacheInvalidationService->invalidateForModel(
            $modelClass,
            $modelId,
            null // All cache levels
        );

        // Handle specific model types with custom invalidation logic
        $this->handleSpecificModelInvalidation($model, $action);
    }

    /**
     * Handle specific model invalidation logic
     */
    private function handleSpecificModelInvalidation(Model $model, string $action): void
    {
        $modelClass = get_class($model);

        match (class_basename($modelClass)) {
            'SpamPattern' => $this->handleSpamPatternInvalidation($model, $action),
            'IpReputation' => $this->handleIpReputationInvalidation($model, $action),
            'BlockedSubmission' => $this->handleBlockedSubmissionInvalidation($model, $action),
            default => null,
        };
    }

    /**
     * Handle spam pattern model invalidation
     */
    private function handleSpamPatternInvalidation(Model $model, string $action): void
    {
        // Invalidate pattern-related caches
        $this->cacheInvalidationService->invalidateByTags([
            'spam_patterns',
            'detection',
            'patterns',
        ]);

        // Invalidate analytics that depend on patterns
        $this->cacheInvalidationService->invalidateByNamespace('analytics');
    }

    /**
     * Handle IP reputation model invalidation
     */
    private function handleIpReputationInvalidation(Model $model, string $action): void
    {
        // Invalidate IP-related caches
        $this->cacheInvalidationService->invalidateByTags([
            'ip_reputation',
            'security',
            'ip_data',
        ]);

        // If the model has an IP address, invalidate specific IP cache
        if (isset($model->ip_address)) {
            $this->cacheInvalidationService->invalidateByNamespace('geolocation');
        }
    }

    /**
     * Handle blocked submission model invalidation
     */
    private function handleBlockedSubmissionInvalidation(Model $model, string $action): void
    {
        // Invalidate analytics and statistics
        $this->cacheInvalidationService->invalidateByTags([
            'analytics',
            'statistics',
            'blocked_submissions',
        ]);

        // Invalidate country-specific caches if available
        if (isset($model->country_code)) {
            $this->cacheInvalidationService->invalidateByNamespace('geolocation');
        }
    }
}
