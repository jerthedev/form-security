<?php

declare(strict_types=1);

/**
 * Observer File: SpamPatternObserver.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Observer for SpamPattern model handling cache invalidation,
 * pattern optimization, and performance monitoring.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Observers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\FormSecurity\Models\SpamPattern;

/**
 * SpamPatternObserver Class
 *
 * Handles model events for SpamPattern including cache management,
 * pattern optimization, and performance monitoring.
 */
class SpamPatternObserver
{
    /**
     * Handle the SpamPattern "creating" event
     */
    public function creating(SpamPattern $spamPattern): void
    {
        // Set default values if not provided
        if (is_null($spamPattern->is_active)) {
            $spamPattern->is_active = true;
        }

        if (is_null($spamPattern->is_learning)) {
            $spamPattern->is_learning = true; // New patterns start in learning mode
        }

        if (is_null($spamPattern->priority)) {
            $spamPattern->priority = 5; // Default medium priority
        }

        if (is_null($spamPattern->match_count)) {
            $spamPattern->match_count = 0;
        }

        if (is_null($spamPattern->false_positive_count)) {
            $spamPattern->false_positive_count = 0;
        }

        if (is_null($spamPattern->accuracy_rate)) {
            $spamPattern->accuracy_rate = 1.0; // Start with perfect accuracy
        }

        // Set default processing time
        if (is_null($spamPattern->processing_time_ms)) {
            $spamPattern->processing_time_ms = 0;
        }

        Log::info('Creating spam pattern', [
            'pattern_type' => $spamPattern->pattern_type?->value,
            'action' => $spamPattern->action?->value,
            'risk_score' => $spamPattern->risk_score,
            'priority' => $spamPattern->priority,
        ]);
    }

    /**
     * Handle the SpamPattern "created" event
     */
    public function created(SpamPattern $spamPattern): void
    {
        // Invalidate pattern caches
        $this->invalidatePatternCaches();

        Log::info('Spam pattern created', [
            'id' => $spamPattern->id,
            'pattern_type' => $spamPattern->pattern_type?->value,
            'action' => $spamPattern->action?->value,
        ]);
    }

    /**
     * Handle the SpamPattern "updating" event
     */
    public function updating(SpamPattern $spamPattern): void
    {
        // Recalculate accuracy rate if match counts changed
        if ($spamPattern->isDirty(['match_count', 'false_positive_count'])) {
            $spamPattern->accuracy_rate = $spamPattern->match_count > 0
                ? ($spamPattern->match_count - $spamPattern->false_positive_count) / $spamPattern->match_count
                : 1.0;
        }

        // Auto-optimize pattern based on performance
        if ($spamPattern->isDirty(['accuracy_rate', 'processing_time_ms', 'match_count'])) {
            $this->autoOptimizePattern($spamPattern);
        }

        // Log significant changes
        if ($spamPattern->isDirty(['is_active', 'priority', 'accuracy_rate'])) {
            Log::info('Spam pattern being updated', [
                'id' => $spamPattern->id,
                'changes' => array_intersect_key(
                    $spamPattern->getDirty(),
                    array_flip(['is_active', 'priority', 'accuracy_rate'])
                ),
            ]);
        }
    }

    /**
     * Handle the SpamPattern "updated" event
     */
    public function updated(SpamPattern $spamPattern): void
    {
        // Invalidate caches if important fields changed
        $importantFields = ['is_active', 'pattern_type', 'action', 'priority', 'pattern'];

        if ($spamPattern->wasChanged($importantFields)) {
            $this->invalidatePatternCaches();
        }

        // Log performance issues
        if ($spamPattern->wasChanged('accuracy_rate') && $spamPattern->accuracy_rate < 0.5) {
            Log::warning('Spam pattern showing poor accuracy', [
                'id' => $spamPattern->id,
                'accuracy_rate' => $spamPattern->accuracy_rate,
                'match_count' => $spamPattern->match_count,
                'false_positive_count' => $spamPattern->false_positive_count,
                'performance_category' => $spamPattern->getPerformanceCategory(),
            ]);
        }
    }

    /**
     * Handle the SpamPattern "deleted" event
     */
    public function deleted(SpamPattern $spamPattern): void
    {
        // Remove from cache
        $spamPattern->removeFromCache();

        // Invalidate pattern caches
        $this->invalidatePatternCaches();

        Log::info('Spam pattern deleted', [
            'id' => $spamPattern->id,
            'pattern_type' => $spamPattern->pattern_type?->value,
        ]);
    }

    /**
     * Auto-optimize pattern based on performance metrics
     */
    private function autoOptimizePattern(SpamPattern $spamPattern): void
    {
        $effectiveness = $spamPattern->getEffectivenessScore();
        $processingTime = $spamPattern->processing_time_ms;

        // Adjust priority based on performance
        if ($effectiveness > 0.9 && $processingTime < 5) {
            // Excellent performance - increase priority
            $spamPattern->priority = min(10, $spamPattern->priority + 1);
        } elseif ($effectiveness < 0.5 || $processingTime > 50) {
            // Poor performance - decrease priority
            $spamPattern->priority = max(1, $spamPattern->priority - 1);
        }

        // Disable patterns with consistently poor performance
        if ($effectiveness < 0.3 && $spamPattern->match_count > 100) {
            $spamPattern->is_active = false;

            Log::warning('Auto-disabling poorly performing spam pattern', [
                'id' => $spamPattern->id,
                'effectiveness_score' => $effectiveness,
                'accuracy_rate' => $spamPattern->accuracy_rate,
                'match_count' => $spamPattern->match_count,
            ]);
        }

        // Graduate from learning mode if pattern is stable
        if ($spamPattern->is_learning &&
            $spamPattern->match_count > 50 &&
            $effectiveness > 0.8) {
            $spamPattern->is_learning = false;

            Log::info('Spam pattern graduated from learning mode', [
                'id' => $spamPattern->id,
                'effectiveness_score' => $effectiveness,
                'match_count' => $spamPattern->match_count,
            ]);
        }
    }

    /**
     * Invalidate pattern-related caches
     */
    private function invalidatePatternCaches(): void
    {
        $cacheKeys = [
            'active_patterns',
            'patterns_by_type',
            'high_priority_patterns',
            'learning_patterns',
            'pattern_performance_stats',
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Invalidate all pattern type caches
        foreach (['regex', 'keyword', 'phrase', 'email_pattern', 'url_pattern', 'behavioral'] as $type) {
            Cache::forget("patterns_by_type:{$type}");
        }

        // Invalidate tagged caches
        Cache::tags(['spam_patterns', 'pattern_cache'])->flush();
    }
}
