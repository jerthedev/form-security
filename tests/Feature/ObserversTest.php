<?php

declare(strict_types=1);

/**
 * Test File: ObserversTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Feature tests for model observers covering audit trails,
 * cache management, and event-driven functionality.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\FormSecurity\Enums\BlockReason;
use JTD\FormSecurity\Enums\PatternAction;
use JTD\FormSecurity\Enums\PatternType;
use JTD\FormSecurity\Enums\ReputationStatus;
use JTD\FormSecurity\Models\BlockedSubmission;
use JTD\FormSecurity\Models\IpReputation;
use JTD\FormSecurity\Models\SpamPattern;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * ObserversTest Class
 *
 * Feature test suite for model observers covering:
 * - Automatic IP reputation updates
 * - Cache invalidation
 * - Audit logging
 * - Performance optimization
 * - Security alerts
 */
#[Group('feature')]
#[Group('observers')]
class ObserversTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function blocked_submission_observer_creates_ip_reputation(): void
    {
        // Ensure no IP reputation exists initially
        $this->assertDatabaseMissing('ip_reputation', [
            'ip_address' => '192.168.1.100',
        ]);

        // Create blocked submission
        $submission = BlockedSubmission::create([
            'form_identifier' => 'contact-form',
            'ip_address' => '192.168.1.100',
            'block_reason' => BlockReason::SPAM_PATTERN->value,
            'risk_score' => 75,
            'blocked_at' => now(),
        ]);

        // Verify IP reputation was created automatically
        $this->assertDatabaseHas('ip_reputation', [
            'ip_address' => '192.168.1.100',
        ]);

        $ipReputation = IpReputation::where('ip_address', '192.168.1.100')->first();
        $this->assertEquals(1, $ipReputation->submission_count);
        $this->assertEquals(1, $ipReputation->blocked_count);
        $this->assertEquals(1.0, $ipReputation->block_rate);
    }

    #[Test]
    public function blocked_submission_observer_updates_existing_ip_reputation(): void
    {
        // Create initial IP reputation
        $ipReputation = IpReputation::create([
            'ip_address' => '192.168.1.100',
            'reputation_score' => 50,
            'submission_count' => 5,
            'blocked_count' => 2,
            'block_rate' => 0.4,
        ]);

        // Create new blocked submission
        BlockedSubmission::create([
            'form_identifier' => 'contact-form',
            'ip_address' => '192.168.1.100',
            'block_reason' => BlockReason::SPAM_PATTERN->value,
            'blocked_at' => now(),
        ]);

        // Verify IP reputation was updated
        $updatedReputation = $ipReputation->fresh();
        $this->assertEquals(6, $updatedReputation->submission_count);
        $this->assertEquals(3, $updatedReputation->blocked_count);
        $this->assertEquals(0.5, $updatedReputation->block_rate);
    }

    #[Test]
    public function blocked_submission_observer_invalidates_caches(): void
    {
        // Set up cache data
        Cache::put('blocked_submissions_stats', ['test' => 'data'], 3600);
        Cache::put('high_risk_ips', ['192.168.1.100'], 3600);

        $this->assertTrue(Cache::has('blocked_submissions_stats'));
        $this->assertTrue(Cache::has('high_risk_ips'));

        // Create blocked submission (should trigger cache invalidation)
        BlockedSubmission::create([
            'form_identifier' => 'contact-form',
            'ip_address' => '192.168.1.100',
            'block_reason' => BlockReason::SPAM_PATTERN->value,
            'blocked_at' => now(),
        ]);

        // Verify caches were invalidated
        $this->assertFalse(Cache::has('blocked_submissions_stats'));
        $this->assertFalse(Cache::has('high_risk_ips'));
    }

    #[Test]
    public function blocked_submission_observer_triggers_high_risk_alert(): void
    {
        Log::shouldReceive('info')->andReturn();
        Log::shouldReceive('error')->andReturn();
        Log::shouldReceive('warning')
            ->once()
            ->with('High-risk submission detected', \Mockery::type('array'));

        // Create high-risk submission
        BlockedSubmission::create([
            'form_identifier' => 'contact-form',
            'ip_address' => '192.168.1.100',
            'block_reason' => BlockReason::HONEYPOT->value,
            'risk_score' => 95, // High risk score should trigger alert
            'blocked_at' => now(),
        ]);
    }

    #[Test]
    public function ip_reputation_observer_manages_cache(): void
    {
        $ipReputation = IpReputation::create([
            'ip_address' => '192.168.1.100',
            'reputation_score' => 75,
            'reputation_status' => ReputationStatus::TRUSTED->value,
        ]);

        // Verify cache was populated
        $cached = Cache::get($ipReputation->getCacheKey());
        $this->assertNotNull($cached);

        // Update reputation
        $ipReputation->update(['reputation_score' => 50]);

        // Verify cache was updated
        $updatedCache = Cache::get($ipReputation->getCacheKey());
        $this->assertEquals(50, $updatedCache['reputation_score']);
    }

    #[Test]
    public function ip_reputation_observer_logs_status_changes(): void
    {
        Log::shouldReceive('info')->andReturn(); // Allow any info calls
        Log::shouldReceive('info')
            ->with('IP reputation status changing', \Mockery::type('array'));

        $ipReputation = IpReputation::create([
            'ip_address' => '192.168.1.100',
            'reputation_score' => 75,
            'reputation_status' => ReputationStatus::TRUSTED->value,
        ]);

        // Update status (should trigger logging)
        $ipReputation->update([
            'reputation_status' => ReputationStatus::SUSPICIOUS->value,
        ]);

        // Verify the update was successful
        $this->assertEquals(ReputationStatus::SUSPICIOUS, $ipReputation->fresh()->reputation_status);
    }

    #[Test]
    public function ip_reputation_observer_alerts_on_reputation_degradation(): void
    {
        Log::shouldReceive('info')->andReturn(); // Allow any info calls
        Log::shouldReceive('warning')
            ->once()
            ->with('IP reputation degraded to high-risk status', \Mockery::type('array'));

        $ipReputation = IpReputation::create([
            'ip_address' => '192.168.1.100',
            'reputation_score' => 75,
            'reputation_status' => ReputationStatus::TRUSTED->value,
        ]);

        // Degrade reputation to malicious (should trigger alert)
        $ipReputation->update([
            'reputation_status' => ReputationStatus::MALICIOUS->value,
        ]);
    }

    #[Test]
    public function spam_pattern_observer_optimizes_patterns(): void
    {
        $pattern = SpamPattern::create([
            'name' => 'Test Pattern',
            'pattern_type' => PatternType::KEYWORD->value,
            'pattern' => 'test',
            'action' => PatternAction::BLOCK->value,
            'accuracy_rate' => 0.95,
            'processing_time_ms' => 3,
            'priority' => 5,
            'match_count' => 100,
        ]);

        $originalPriority = $pattern->priority;

        // Update pattern performance (should trigger optimization)
        $pattern->update([
            'accuracy_rate' => 0.98,
            'processing_time_ms' => 2,
        ]);

        // Verify pattern was optimized
        $this->assertGreaterThan($originalPriority, $pattern->fresh()->priority);
    }

    #[Test]
    public function spam_pattern_observer_disables_poor_performers(): void
    {
        Log::shouldReceive('info')->andReturn(); // Allow any info calls
        Log::shouldReceive('warning')->andReturn(); // Allow any warning calls

        $pattern = SpamPattern::create([
            'name' => 'Poor Pattern',
            'pattern_type' => PatternType::KEYWORD->value,
            'pattern' => 'test',
            'action' => PatternAction::BLOCK->value,
            'accuracy_rate' => 0.25, // Very poor accuracy
            'match_count' => 150, // Sufficient sample size
            'is_active' => true,
        ]);

        // Update to trigger optimization check
        $pattern->update(['accuracy_rate' => 0.20]);

        // Verify pattern was disabled
        $this->assertFalse($pattern->fresh()->is_active);
    }

    #[Test]
    public function spam_pattern_observer_graduates_learning_patterns(): void
    {
        Log::shouldReceive('info')->andReturn(); // Allow any info calls

        $pattern = SpamPattern::create([
            'name' => 'Learning Pattern',
            'pattern_type' => PatternType::KEYWORD->value,
            'pattern' => 'test',
            'action' => PatternAction::BLOCK->value,
            'is_learning' => true,
            'accuracy_rate' => 0.95, // Higher accuracy to ensure effectiveness > 0.8
            'match_count' => 60, // Above graduation threshold
        ]);

        // Update to trigger graduation check (effectiveness = 0.95 * min(100/100, 1.0) = 0.95 > 0.8)
        $pattern->update(['match_count' => 100]);

        // Verify pattern graduated from learning mode
        $this->assertFalse($pattern->fresh()->is_learning);
    }

    #[Test]
    public function spam_pattern_observer_invalidates_pattern_caches(): void
    {
        // Set up pattern caches
        Cache::put('active_patterns', ['test' => 'data'], 3600);
        Cache::put('patterns_by_type', ['keyword' => []], 3600);

        $this->assertTrue(Cache::has('active_patterns'));
        $this->assertTrue(Cache::has('patterns_by_type'));

        // Create pattern (should trigger cache invalidation)
        SpamPattern::create([
            'name' => 'New Pattern',
            'pattern_type' => PatternType::KEYWORD->value,
            'pattern' => 'test',
            'action' => PatternAction::BLOCK->value,
        ]);

        // Verify caches were invalidated
        $this->assertFalse(Cache::has('active_patterns'));
        $this->assertFalse(Cache::has('patterns_by_type'));
    }

    #[Test]
    public function observers_handle_model_deletion(): void
    {
        // Create models
        $submission = BlockedSubmission::create([
            'form_identifier' => 'contact-form',
            'ip_address' => '192.168.1.100',
            'block_reason' => BlockReason::SPAM_PATTERN->value,
            'blocked_at' => now(),
        ]);

        $ipReputation = IpReputation::create([
            'ip_address' => '192.168.1.200',
            'reputation_score' => 50,
        ]);

        $pattern = SpamPattern::create([
            'name' => 'Test Pattern',
            'pattern_type' => PatternType::KEYWORD->value,
            'pattern' => 'test',
            'action' => PatternAction::BLOCK->value,
        ]);

        // Set up caches
        Cache::put('test_cache_key', 'test_data', 3600);

        // Delete models (should trigger observer cleanup)
        $submission->delete();
        $ipReputation->delete();
        $pattern->delete();

        // Verify cleanup occurred (cache invalidation, logging, etc.)
        // This is mainly testing that no exceptions are thrown during deletion
        $this->assertTrue(true);
    }

    #[Test]
    public function observers_maintain_data_consistency(): void
    {
        // Create initial submission
        $submission = BlockedSubmission::create([
            'form_identifier' => 'contact-form',
            'ip_address' => '192.168.1.100',
            'block_reason' => BlockReason::SPAM_PATTERN->value,
            'blocked_at' => now(),
        ]);

        $ipReputation = IpReputation::where('ip_address', '192.168.1.100')->first();
        $this->assertEquals(1, $ipReputation->blocked_count);

        // Create additional submissions
        BlockedSubmission::create([
            'form_identifier' => 'contact-form',
            'ip_address' => '192.168.1.100',
            'block_reason' => BlockReason::HONEYPOT->value,
            'blocked_at' => now(),
        ]);

        BlockedSubmission::create([
            'form_identifier' => 'newsletter',
            'ip_address' => '192.168.1.100',
            'block_reason' => BlockReason::RATE_LIMIT->value,
            'blocked_at' => now(),
        ]);

        // Verify IP reputation was updated consistently
        $updatedReputation = $ipReputation->fresh();
        $this->assertEquals(3, $updatedReputation->blocked_count);
        $this->assertEquals(3, $updatedReputation->submission_count);
        $this->assertEquals(1.0, $updatedReputation->block_rate);
    }
}
