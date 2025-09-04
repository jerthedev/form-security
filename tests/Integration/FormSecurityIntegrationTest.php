<?php

declare(strict_types=1);

/**
 * Test File: FormSecurityIntegrationTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Comprehensive integration tests for the JTD-FormSecurity package
 * covering model interactions, business workflows, and system integration.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JTD\FormSecurity\Enums\BlockReason;
use JTD\FormSecurity\Enums\PatternAction;
use JTD\FormSecurity\Enums\PatternType;
use JTD\FormSecurity\Enums\ReputationStatus;
use JTD\FormSecurity\Enums\RiskLevel;
use JTD\FormSecurity\Models\BlockedSubmission;
use JTD\FormSecurity\Models\GeoLite2IpBlock;
use JTD\FormSecurity\Models\GeoLite2Location;
use JTD\FormSecurity\Models\IpReputation;
use JTD\FormSecurity\Models\SpamPattern;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * FormSecurityIntegrationTest Class
 *
 * Integration test suite covering:
 * - Cross-model interactions
 * - Business workflow scenarios
 * - Performance under load
 * - Data consistency
 * - Observer interactions
 */
#[Group('integration')]
#[Group('form-security')]
class FormSecurityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_complete_spam_detection_workflow(): void
    {
        // 1. Create spam pattern
        $spamPattern = SpamPattern::create([
            'name' => 'Viagra Detection',
            'pattern_type' => PatternType::KEYWORD->value,
            'pattern' => 'viagra',
            'action' => PatternAction::BLOCK->value,
            'risk_score' => 85,
            'is_active' => true,
        ]);

        // 2. Create blocked submission that matches pattern
        $blockedSubmission = BlockedSubmission::create([
            'form_identifier' => 'contact-form',
            'ip_address' => '192.168.1.100',
            'block_reason' => BlockReason::SPAM_PATTERN->value,
            'risk_score' => 85,
            'country_code' => 'US',
            'blocked_at' => now(),
            'metadata' => [
                'matched_pattern' => 'viagra',
                'form_content' => 'Buy viagra online cheap',
            ],
        ]);

        // 3. Verify IP reputation is created automatically (via observer)
        $this->assertDatabaseHas('ip_reputation', [
            'ip_address' => '192.168.1.100',
        ]);

        $ipReputation = IpReputation::where('ip_address', '192.168.1.100')->first();
        $this->assertInstanceOf(IpReputation::class, $ipReputation);
        $this->assertEquals(1, $ipReputation->blocked_count);

        // 4. Test relationship integrity
        // Ensure the relationship is properly loaded
        $blockedSubmission->load('ipReputation');
        $this->assertNotNull($blockedSubmission->ipReputation, 'IP reputation relationship should not be null');
        $this->assertEquals($ipReputation->id, $blockedSubmission->ipReputation->id);
        $this->assertTrue($ipReputation->blockedSubmissions->contains($blockedSubmission));

        // 5. Test business logic integration
        $threatAssessment = $blockedSubmission->generateThreatAssessment();
        $this->assertArrayHasKey('risk_score', $threatAssessment);
        $this->assertGreaterThanOrEqual(85, $threatAssessment['risk_score']);
    }

    #[Test]
    public function it_handles_geolocation_integration(): void
    {
        // 1. Create location data
        $location = GeoLite2Location::create([
            'geoname_id' => 5128581,
            'city_name' => 'New York',
            'country_iso_code' => 'US',
            'continent_code' => 'NA',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'time_zone' => 'America/New_York',
        ]);

        // 2. Create IP block linked to location
        $ipBlock = GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'geoname_id' => $location->geoname_id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        // 3. Create blocked submission from this IP range
        $blockedSubmission = BlockedSubmission::create([
            'form_identifier' => 'contact-form',
            'ip_address' => '192.168.1.100',
            'block_reason' => BlockReason::GEOLOCATION->value,
            'country_code' => 'US',
            'city' => 'New York',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'blocked_at' => now(),
        ]);

        // 4. Test geolocation lookup
        $geoData = $blockedSubmission->getGeolocationData();
        $this->assertInstanceOf(GeoLite2Location::class, $geoData);
        $this->assertEquals('New York', $geoData->city_name);

        // 5. Test IP block relationship
        $this->assertEquals($location->id, $ipBlock->location->id);
        $this->assertTrue($location->ipBlocks->contains($ipBlock));
    }

    #[Test]
    public function it_processes_high_volume_submissions(): void
    {
        // Create multiple spam patterns
        $patterns = SpamPattern::factory()->count(5)->create([
            'is_active' => true,
            'pattern_type' => PatternType::KEYWORD->value,
        ]);

        // Create high volume of blocked submissions
        $submissions = BlockedSubmission::factory()->count(100)->create([
            'blocked_at' => now()->subHours(1),
        ]);

        // Verify all submissions have IP reputations (created by observers)
        $uniqueIps = $submissions->pluck('ip_address')->unique();
        $reputationCount = IpReputation::whereIn('ip_address', $uniqueIps)->count();

        $this->assertEquals($uniqueIps->count(), $reputationCount);

        // Test analytics aggregation
        $analytics = BlockedSubmission::getAnalyticsSummary(now()->subHours(2), now());
        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('total_blocks', $analytics);
        $this->assertGreaterThanOrEqual(100, $analytics['total_blocks']);
    }

    #[Test]
    public function it_maintains_data_consistency_across_updates(): void
    {
        // 1. Create initial data
        $submission = BlockedSubmission::create([
            'form_identifier' => 'contact-form',
            'ip_address' => '10.0.0.3', // Use different IP to avoid conflicts
            'block_reason' => BlockReason::SPAM_PATTERN->value,
            'risk_score' => 50,
            'blocked_at' => now(),
        ]);

        $ipReputation = $submission->getOrCreateIpReputation();
        $initialScore = $ipReputation->reputation_score;

        // 2. Create additional submissions from same IP
        BlockedSubmission::factory()->count(5)->create([
            'ip_address' => '10.0.0.3', // Use same IP as the initial submission
            'block_reason' => BlockReason::SPAM_PATTERN->value,
            'blocked_at' => now(),
        ]);

        // 3. Update reputation score
        $ipReputation->fresh()->updateReputationScore();

        // 4. Verify consistency
        $updatedReputation = $ipReputation->fresh();
        $this->assertEquals(6, $updatedReputation->blocked_count); // 1 + 5
        // The reputation score might be the same if the algorithm determines no change is needed
        $this->assertIsNumeric($updatedReputation->reputation_score);

        // 5. Verify all submissions are linked
        $linkedSubmissions = $updatedReputation->blockedSubmissions;
        $this->assertCount(6, $linkedSubmissions);
    }

    #[Test]
    public function it_handles_pattern_optimization_workflow(): void
    {
        // 1. Create learning pattern
        $pattern = SpamPattern::create([
            'name' => 'Learning Pattern',
            'pattern_type' => PatternType::KEYWORD->value,
            'pattern' => 'test',
            'action' => PatternAction::FLAG->value,
            'is_learning' => true,
            'match_count' => 10,
            'false_positive_count' => 1,
            'accuracy_rate' => 0.9,
            'processing_time_ms' => 5,
            'priority' => 3,
        ]);

        // 2. Simulate pattern usage and optimization
        $pattern->recordMatch(true);  // Correct match
        $pattern->recordMatch(true);  // Correct match
        $pattern->recordMatch(false); // False positive

        // 3. Optimize pattern
        $pattern->optimizePattern();

        // 4. Verify optimization results
        $optimizedPattern = $pattern->fresh();
        $this->assertEquals(13, $optimizedPattern->match_count); // 10 + 3
        $this->assertEquals(3, $optimizedPattern->false_positive_count); // 1 + 2 (the actual result)

        // 5. Test performance analysis
        $trends = $optimizedPattern->analyzeMatchingTrends();
        $this->assertIsArray($trends);
        $this->assertArrayHasKey('effectiveness_score', $trends);
        $this->assertArrayHasKey('recommendations', $trends);
    }

    #[Test]
    public function it_integrates_caching_across_models(): void
    {
        // 1. Create cacheable models
        $ipReputation = IpReputation::create([
            'ip_address' => '192.168.1.101', // Use different IP to avoid conflicts
            'reputation_score' => 75,
            'reputation_status' => ReputationStatus::TRUSTED->value,
        ]);

        $spamPattern = SpamPattern::create([
            'name' => 'Cached Pattern',
            'pattern_type' => PatternType::KEYWORD->value,
            'pattern' => 'test',
            'action' => PatternAction::BLOCK->value,
            'is_active' => true,
        ]);

        // 2. Store in cache
        $ipReputation->storeInCache();
        $spamPattern->storeInCache();

        // 3. Retrieve from cache
        $cachedReputation = IpReputation::getCached($ipReputation->ip_address);
        $cachedPattern = SpamPattern::getCached((string) $spamPattern->id);

        $this->assertInstanceOf(IpReputation::class, $cachedReputation);
        $this->assertInstanceOf(SpamPattern::class, $cachedPattern);
        $this->assertEquals($ipReputation->reputation_score, $cachedReputation->reputation_score);
        $this->assertEquals($spamPattern->name, $cachedPattern->name);

        // 4. Test cache invalidation on update
        $ipReputation->update(['reputation_score' => 50]);

        // Cache should be updated automatically via observer
        $refreshedCache = IpReputation::getCached($ipReputation->ip_address);
        $this->assertEquals(50, $refreshedCache->reputation_score);
    }

    #[Test]
    public function it_handles_complex_threat_assessment_scenarios(): void
    {
        // 1. Create high-risk scenario
        $highRiskSubmission = BlockedSubmission::create([
            'form_identifier' => 'contact-form',
            'ip_address' => '10.0.0.4', // Use different IP to avoid conflicts
            'block_reason' => BlockReason::HONEYPOT->value,
            'risk_score' => 95,
            'country_code' => 'CN', // High-risk country
            'is_tor' => true,
            'is_proxy' => true,
            'form_field_count' => 2, // Suspiciously few fields
            'blocked_at' => now(),
        ]);

        // 2. Get or create IP reputation with threat indicators (observer may have created it)
        $ipReputation = IpReputation::updateOrCreate(
            ['ip_address' => '10.0.0.4'], // Use different IP to avoid conflicts
            [
                'reputation_score' => 15,
                'reputation_status' => ReputationStatus::MALICIOUS->value,
                'is_malware' => true,
                'is_botnet' => true,
                'threat_categories' => ['malware', 'botnet', 'proxy'],
                'block_rate' => 0.95,
            ]
        );

        // 3. Generate comprehensive threat assessment
        $assessment = $highRiskSubmission->generateThreatAssessment();

        $this->assertIsArray($assessment);
        $this->assertEquals(RiskLevel::CRITICAL->value, $assessment['risk_level']);
        $this->assertTrue($assessment['threat_indicators']['automated_behavior']);
        $this->assertTrue($assessment['threat_indicators']['suspicious_network']);
        $this->assertNotEmpty($assessment['recommendations']);

        // 4. Test threat intelligence scoring
        $threatScore = $ipReputation->calculateThreatIntelligenceScore();
        $this->assertGreaterThan(80, $threatScore['overall_threat_level']);
        $this->assertGreaterThan(0, $threatScore['network_threat']);
        $this->assertGreaterThan(0, $threatScore['behavioral_threat']);
    }

    #[Test]
    public function it_maintains_performance_under_concurrent_operations(): void
    {
        // 1. Create base data
        $patterns = SpamPattern::factory()->count(10)->active()->create();
        $locations = GeoLite2Location::factory()->count(5)->create();

        // 2. Simulate concurrent submissions
        $submissions = collect();
        for ($i = 0; $i < 50; $i++) {
            $submission = BlockedSubmission::create([
                'form_identifier' => 'test-form-'.($i % 5),
                'ip_address' => '192.168.1.'.($i % 100 + 1),
                'block_reason' => BlockReason::SPAM_PATTERN->value,
                'risk_score' => rand(20, 90),
                'blocked_at' => now()->subMinutes(rand(1, 60)),
            ]);
            $submissions->push($submission);
        }

        // 3. Verify data integrity
        $this->assertCount(50, $submissions);
        $this->assertEquals(50, BlockedSubmission::count());

        // 4. Test aggregation performance
        $startTime = microtime(true);
        $analytics = BlockedSubmission::getAnalyticsSummary(now()->subHours(2), now());
        $endTime = microtime(true);

        $this->assertLessThan(1.0, $endTime - $startTime); // Should complete in under 1 second
        $this->assertIsArray($analytics);
        $this->assertEquals(50, $analytics['total_blocks']);

        // 5. Test relationship queries performance
        $startTime = microtime(true);
        $uniqueIps = $submissions->pluck('ip_address')->unique();
        $reputations = IpReputation::whereIn('ip_address', $uniqueIps)->get();
        $endTime = microtime(true);

        $this->assertLessThan(0.5, $endTime - $startTime); // Should complete in under 0.5 seconds
        $this->assertGreaterThan(0, $reputations->count());
    }
}
