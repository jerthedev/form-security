<?php

declare(strict_types=1);

/**
 * Observer File: IpReputationObserver.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Observer for IpReputation model handling cache management,
 * threat intelligence updates, and reputation change notifications.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Observers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\FormSecurity\Enums\ReputationStatus;
use JTD\FormSecurity\Models\IpReputation;

/**
 * IpReputationObserver Class
 *
 * Handles model events for IpReputation including cache management,
 * threat intelligence updates, and reputation change notifications.
 */
class IpReputationObserver
{
    /**
     * Handle the IpReputation "creating" event
     */
    public function creating(IpReputation $ipReputation): void
    {
        // Set default cache expiration if not provided
        if (is_null($ipReputation->cache_expires_at)) {
            $ipReputation->cache_expires_at = now()->addHours(24);
        }

        // Set first_seen if not provided
        if (is_null($ipReputation->first_seen)) {
            $ipReputation->first_seen = now();
        }

        // Set last_seen if not provided
        if (is_null($ipReputation->last_seen)) {
            $ipReputation->last_seen = now();
        }

        // Initialize counters if not set
        if (is_null($ipReputation->submission_count)) {
            $ipReputation->submission_count = 0;
        }

        if (is_null($ipReputation->blocked_count)) {
            $ipReputation->blocked_count = 0;
        }

        // Calculate initial block rate
        $ipReputation->block_rate = $ipReputation->submission_count > 0
            ? $ipReputation->blocked_count / $ipReputation->submission_count
            : 0;

        Log::info('Creating IP reputation record', [
            'ip_address' => $ipReputation->ip_address,
            'reputation_score' => $ipReputation->reputation_score,
            'reputation_status' => $ipReputation->reputation_status?->value,
        ]);
    }

    /**
     * Handle the IpReputation "created" event
     */
    public function created(IpReputation $ipReputation): void
    {
        // Store in cache
        $ipReputation->storeInCache();

        // Invalidate related caches
        $this->invalidateCaches($ipReputation);

        Log::info('IP reputation record created', [
            'id' => $ipReputation->id,
            'ip_address' => $ipReputation->ip_address,
            'reputation_status' => $ipReputation->reputation_status?->value,
        ]);
    }

    /**
     * Handle the IpReputation "updating" event
     */
    public function updating(IpReputation $ipReputation): void
    {
        // Track reputation status changes
        if ($ipReputation->isDirty('reputation_status')) {
            $oldStatus = $ipReputation->getOriginal('reputation_status');
            $newStatus = $ipReputation->reputation_status;

            Log::info('IP reputation status changing', [
                'ip_address' => $ipReputation->ip_address,
                'old_status' => $oldStatus,
                'new_status' => $newStatus?->value,
                'reputation_score' => $ipReputation->reputation_score,
            ]);

            // Trigger alerts for significant status changes
            $this->handleReputationStatusChange($ipReputation, $oldStatus, $newStatus);
        }

        // Recalculate block rate if counts changed
        if ($ipReputation->isDirty(['submission_count', 'blocked_count'])) {
            $ipReputation->block_rate = $ipReputation->submission_count > 0
                ? $ipReputation->blocked_count / $ipReputation->submission_count
                : 0;
        }
    }

    /**
     * Handle the IpReputation "updated" event
     */
    public function updated(IpReputation $ipReputation): void
    {
        // Update cache with new data
        $ipReputation->storeInCache();

        // Invalidate related caches if important fields changed
        $importantFields = ['reputation_status', 'reputation_score', 'is_blacklisted', 'is_whitelisted'];

        if ($ipReputation->wasChanged($importantFields)) {
            $this->invalidateCaches($ipReputation);
        }

        // Log significant updates
        if ($ipReputation->wasChanged(['reputation_status', 'reputation_score'])) {
            Log::info('IP reputation updated', [
                'id' => $ipReputation->id,
                'ip_address' => $ipReputation->ip_address,
                'changes' => $ipReputation->getChanges(),
            ]);
        }
    }

    /**
     * Handle the IpReputation "deleted" event
     */
    public function deleted(IpReputation $ipReputation): void
    {
        // Remove from cache
        $ipReputation->removeFromCache();

        // Invalidate related caches
        $this->invalidateCaches($ipReputation);

        Log::info('IP reputation record deleted', [
            'id' => $ipReputation->id,
            'ip_address' => $ipReputation->ip_address,
        ]);
    }

    /**
     * Handle reputation status changes
     */
    private function handleReputationStatusChange(
        IpReputation $ipReputation,
        ?ReputationStatus $oldStatus,
        ?ReputationStatus $newStatus
    ): void {
        // Alert on transitions to malicious or blocked status
        if ($newStatus === ReputationStatus::MALICIOUS || $newStatus === ReputationStatus::BLOCKED) {
            Log::warning('IP reputation degraded to high-risk status', [
                'ip_address' => $ipReputation->ip_address,
                'old_status' => $oldStatus?->value,
                'new_status' => $newStatus->value,
                'reputation_score' => $ipReputation->reputation_score,
                'block_rate' => $ipReputation->block_rate,
                'threat_intelligence' => $ipReputation->calculateThreatIntelligenceScore(),
            ]);
        }

        // Alert on transitions from trusted to suspicious
        if ($oldStatus === 'trusted' &&
            ($newStatus === ReputationStatus::SUSPICIOUS || $newStatus === ReputationStatus::MALICIOUS)) {
            Log::warning('Previously trusted IP showing suspicious activity', [
                'ip_address' => $ipReputation->ip_address,
                'old_status' => $oldStatus,
                'new_status' => $newStatus->value,
                'recent_activity' => $ipReputation->recentBlockedSubmissions(24)->count(),
            ]);
        }
    }

    /**
     * Invalidate relevant caches
     */
    private function invalidateCaches(IpReputation $ipReputation): void
    {
        $cacheKeys = [
            'ip_reputation_stats',
            'high_risk_ips',
            'trusted_ips',
            'blacklisted_ips',
            "reputation_by_country_{$ipReputation->country_code}",
            'threat_intelligence_summary',
            'analytics_summary_'.now()->format('Y-m-d'),
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Invalidate tagged caches
        Cache::tags(['ip_reputation', 'threat_intelligence'])->flush();
    }
}
