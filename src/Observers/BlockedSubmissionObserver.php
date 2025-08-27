<?php

declare(strict_types=1);

/**
 * Observer File: BlockedSubmissionObserver.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-008-blocked-submissions-tracking
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Observer for BlockedSubmission model handling audit trails,
 * cache invalidation, and event-driven functionality.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Data-Management-Analytics/SPEC-008-blocked-submissions-tracking.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Observers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\FormSecurity\Models\BlockedSubmission;
use JTD\FormSecurity\Models\IpReputation;

/**
 * BlockedSubmissionObserver Class
 *
 * Handles model events for BlockedSubmission including audit trails,
 * cache management, and automatic IP reputation updates.
 */
class BlockedSubmissionObserver
{
    /**
     * Handle the BlockedSubmission "creating" event
     */
    public function creating(BlockedSubmission $blockedSubmission): void
    {
        // Set default values if not provided
        if (is_null($blockedSubmission->blocked_at)) {
            $blockedSubmission->blocked_at = now();
        }

        // Generate risk score if not set
        if (is_null($blockedSubmission->risk_score)) {
            $blockedSubmission->risk_score = $blockedSubmission->calculateComprehensiveRiskScore();
        }

        // Log the creation attempt
        Log::info('Creating blocked submission', [
            'ip_address' => $blockedSubmission->ip_address,
            'form_identifier' => $blockedSubmission->form_identifier,
            'block_reason' => $blockedSubmission->block_reason?->value,
            'risk_score' => $blockedSubmission->risk_score,
        ]);
    }

    /**
     * Handle the BlockedSubmission "created" event
     */
    public function created(BlockedSubmission $blockedSubmission): void
    {
        // Update IP reputation
        $this->updateIpReputation($blockedSubmission);

        // Invalidate relevant caches
        $this->invalidateCaches($blockedSubmission);

        // Log the successful creation
        Log::info('Blocked submission created', [
            'id' => $blockedSubmission->id,
            'ip_address' => $blockedSubmission->ip_address,
            'form_identifier' => $blockedSubmission->form_identifier,
            'block_reason' => $blockedSubmission->block_reason?->value,
        ]);

        // Trigger security alerts for high-risk submissions
        if ($blockedSubmission->risk_score >= 90) {
            $this->triggerHighRiskAlert($blockedSubmission);
        }
    }

    /**
     * Handle the BlockedSubmission "updated" event
     */
    public function updated(BlockedSubmission $blockedSubmission): void
    {
        // Invalidate caches if important fields changed
        $importantFields = ['ip_address', 'risk_score', 'block_reason', 'country_code'];

        if ($blockedSubmission->wasChanged($importantFields)) {
            $this->invalidateCaches($blockedSubmission);
        }

        // Log significant updates
        if ($blockedSubmission->wasChanged(['risk_score', 'block_reason'])) {
            Log::info('Blocked submission updated', [
                'id' => $blockedSubmission->id,
                'changes' => $blockedSubmission->getChanges(),
            ]);
        }
    }

    /**
     * Handle the BlockedSubmission "deleted" event
     */
    public function deleted(BlockedSubmission $blockedSubmission): void
    {
        // Invalidate caches
        $this->invalidateCaches($blockedSubmission);

        // Log the deletion
        Log::info('Blocked submission deleted', [
            'id' => $blockedSubmission->id,
            'ip_address' => $blockedSubmission->ip_address,
        ]);
    }

    /**
     * Update IP reputation based on blocked submission
     */
    private function updateIpReputation(BlockedSubmission $blockedSubmission): void
    {
        try {
            // Check if IP reputation already exists
            $existingReputation = IpReputation::where('ip_address', $blockedSubmission->ip_address)->first();
            $ipReputation = $blockedSubmission->getOrCreateIpReputation();

            // Only increment if the reputation already existed
            if ($existingReputation) {
                $ipReputation->increment('submission_count');
                $ipReputation->increment('blocked_count');
            }

            $ipReputation->last_seen = $blockedSubmission->blocked_at;
            $ipReputation->last_blocked = $blockedSubmission->blocked_at;

            // Recalculate block rate
            $ipReputation->block_rate = $ipReputation->submission_count > 0
                ? $ipReputation->blocked_count / $ipReputation->submission_count
                : 0;

            // Update reputation score
            $ipReputation->updateReputationScore();

            $ipReputation->save();
        } catch (\Exception $e) {
            Log::error('Failed to update IP reputation', [
                'ip_address' => $blockedSubmission->ip_address,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Invalidate relevant caches
     */
    private function invalidateCaches(BlockedSubmission $blockedSubmission): void
    {
        $cacheKeys = [
            'blocked_submissions_stats',
            "blocked_submissions_by_country_{$blockedSubmission->country_code}",
            "blocked_submissions_by_form_{$blockedSubmission->form_identifier}",
            "blocked_submissions_by_ip_{$blockedSubmission->ip_address}",
            'high_risk_ips',
            'recent_blocks_24h',
            'analytics_summary_'.now()->format('Y-m-d'),
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Invalidate pattern-based caches
        Cache::tags(['blocked_submissions', 'analytics'])->flush();
    }

    /**
     * Trigger high-risk security alert
     */
    private function triggerHighRiskAlert(BlockedSubmission $blockedSubmission): void
    {
        Log::warning('High-risk submission detected', [
            'id' => $blockedSubmission->id,
            'ip_address' => $blockedSubmission->ip_address,
            'risk_score' => $blockedSubmission->risk_score,
            'block_reason' => $blockedSubmission->block_reason?->value,
            'country_code' => $blockedSubmission->country_code,
            'form_identifier' => $blockedSubmission->form_identifier,
            'threat_assessment' => $blockedSubmission->generateThreatAssessment(),
        ]);

        // Here you could trigger additional alerts:
        // - Send notification to security team
        // - Update threat intelligence feeds
        // - Trigger automated blocking rules
        // - Queue for manual review
    }
}
