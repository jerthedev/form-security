<?php

declare(strict_types=1);

/**
 * Test File: ModelPerformanceTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Performance tests for model operations targeting <100ms
 * query response times and efficient bulk operations.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Tests\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JTD\FormSecurity\Enums\BlockReason;
use JTD\FormSecurity\Enums\PatternType;
use JTD\FormSecurity\Models\BlockedSubmission;
use JTD\FormSecurity\Models\IpReputation;
use JTD\FormSecurity\Models\SpamPattern;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * ModelPerformanceTest Class
 *
 * Performance test suite covering:
 * - Query response times (<100ms target)
 * - Bulk operations efficiency
 * - Memory usage optimization
 * - Caching effectiveness
 * - Relationship loading performance
 */
#[Group('performance')]
#[Group('models')]
class ModelPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const PERFORMANCE_TARGET_MS = 100;

    private const BULK_OPERATION_SIZE = 1000;

    #[Test]
    public function blocked_submission_queries_meet_performance_targets(): void
    {
        // Create test data
        BlockedSubmission::factory()->count(1000)->create();

        // Test basic query performance
        $startTime = microtime(true);
        $submissions = BlockedSubmission::limit(100)->get();
        $queryTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS, $queryTime);
        $this->assertCount(100, $submissions);

        // Test filtered query performance
        $startTime = microtime(true);
        $highRiskSubmissions = BlockedSubmission::highRisk()->limit(50)->get();
        $filterTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS, $filterTime);

        // Test aggregation query performance
        $startTime = microtime(true);
        $analytics = BlockedSubmission::getAnalyticsSummary(now()->subDays(30), now());
        $aggregationTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS * 2, $aggregationTime); // Allow 200ms for complex aggregations
        $this->assertIsArray($analytics);
    }

    #[Test]
    public function ip_reputation_queries_meet_performance_targets(): void
    {
        // Create test data
        IpReputation::factory()->count(1000)->create();

        // Test basic query performance
        $startTime = microtime(true);
        $reputations = IpReputation::limit(100)->get();
        $queryTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS, $queryTime);
        $this->assertCount(100, $reputations);

        // Test high-risk query performance
        $startTime = microtime(true);
        $highRiskIps = IpReputation::malicious()->limit(50)->get();
        $filterTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS, $filterTime);

        // Test threat intelligence query performance
        $startTime = microtime(true);
        $threatIntel = IpReputation::forThreatIntelligence()->limit(100)->get();
        $threatTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS, $threatTime);
    }

    #[Test]
    public function spam_pattern_queries_meet_performance_targets(): void
    {
        // Create test data
        SpamPattern::factory()->count(500)->create();

        // Test active patterns query performance
        $startTime = microtime(true);
        $activePatterns = SpamPattern::active()->get();
        $queryTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS, $queryTime);

        // Test high accuracy patterns query performance
        $startTime = microtime(true);
        $highAccuracyPatterns = SpamPattern::highAccuracy()->limit(50)->get();
        $filterTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS, $filterTime);

        // Test pattern matching performance
        $pattern = SpamPattern::factory()->create([
            'pattern_type' => PatternType::KEYWORD->value,
            'pattern' => 'test',
        ]);

        $startTime = microtime(true);
        $result = $pattern->testPattern('This is a test message with test keyword');
        $matchTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(10, $matchTime); // Pattern matching should be very fast
        $this->assertTrue($result['matches']);
    }

    #[Test]
    public function relationship_loading_is_optimized(): void
    {
        // Create related data
        $submissions = BlockedSubmission::factory()->count(100)->create();

        // IP reputations are automatically created by the observer
        // No need to create them explicitly

        // Test eager loading performance
        $startTime = microtime(true);
        $submissionsWithReputations = BlockedSubmission::with('ipReputation')->limit(100)->get();
        $eagerLoadTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS, $eagerLoadTime);

        // Test optimized query performance
        $startTime = microtime(true);
        $optimizedSubmissions = BlockedSubmission::optimized()->limit(100)->get();
        $optimizedTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS, $optimizedTime);
    }

    #[Test]
    public function bulk_operations_are_efficient(): void
    {
        // Test bulk insert performance
        $bulkData = [];
        for ($i = 0; $i < self::BULK_OPERATION_SIZE; $i++) {
            $bulkData[] = [
                'form_identifier' => 'bulk-test-'.($i % 10),
                'ip_address' => '192.168.'.($i % 255).'.'.($i % 255),
                'block_reason' => BlockReason::SPAM_PATTERN->value,
                'risk_score' => rand(20, 90),
                'blocked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $startTime = microtime(true);
        $result = BlockedSubmission::bulkInsert($bulkData, 100);
        $bulkInsertTime = (microtime(true) - $startTime) * 1000;

        $this->assertTrue($result);
        $this->assertLessThan(5000, $bulkInsertTime); // 5 seconds max for 1000 records
        $this->assertEquals(self::BULK_OPERATION_SIZE, BlockedSubmission::count());

        // Test bulk update performance
        $updates = [];
        $submissions = BlockedSubmission::limit(100)->get();
        foreach ($submissions as $submission) {
            $updates[] = [
                'id' => $submission->id,
                'risk_score' => rand(50, 100),
            ];
        }

        $startTime = microtime(true);
        $updatedCount = BlockedSubmission::bulkUpdate($updates);
        $bulkUpdateTime = (microtime(true) - $startTime) * 1000;

        $this->assertEquals(100, $updatedCount);
        $this->assertLessThan(1000, $bulkUpdateTime); // 1 second max for 100 updates
    }

    #[Test]
    public function caching_improves_performance(): void
    {
        // Create test data
        IpReputation::factory()->count(100)->create();

        // First query (no cache)
        $startTime = microtime(true);
        $firstResult = IpReputation::getCachedThreatIntelligence(24);
        $firstQueryTime = (microtime(true) - $startTime) * 1000;

        // Second query (from cache)
        $startTime = microtime(true);
        $secondResult = IpReputation::getCachedThreatIntelligence(24);
        $secondQueryTime = (microtime(true) - $startTime) * 1000;

        $this->assertEquals($firstResult, $secondResult);
        $this->assertLessThan($firstQueryTime, $secondQueryTime); // Cached query should be faster

        // Test BlockedSubmission caching
        BlockedSubmission::factory()->count(100)->create();

        $startTime = microtime(true);
        $firstStats = BlockedSubmission::getCachedCountryStats(7);
        $firstStatsTime = (microtime(true) - $startTime) * 1000;

        $startTime = microtime(true);
        $secondStats = BlockedSubmission::getCachedCountryStats(7);
        $secondStatsTime = (microtime(true) - $startTime) * 1000;

        $this->assertEquals($firstStats, $secondStats);
        $this->assertLessThan($firstStatsTime, $secondStatsTime);
    }

    #[Test]
    public function memory_usage_is_optimized(): void
    {
        $initialMemory = memory_get_usage(true);

        // Create and process large dataset
        $submissions = BlockedSubmission::factory()->count(1000)->create();

        // Process in chunks to test memory efficiency
        $processedCount = 0;
        BlockedSubmission::chunk(100, function ($chunk) use (&$processedCount) {
            foreach ($chunk as $submission) {
                $submission->calculateComprehensiveRiskScore();
                $processedCount++;
            }
        });

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;

        $this->assertEquals(1000, $processedCount);
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease); // Less than 50MB increase
    }

    #[Test]
    public function analytics_queries_are_optimized(): void
    {
        // Create diverse test data
        BlockedSubmission::factory()->count(500)->create([
            'blocked_at' => now()->subDays(rand(1, 30)),
        ]);

        // Test country statistics performance
        $startTime = microtime(true);
        $countryStats = BlockedSubmission::getCachedCountryStats(30);
        $countryStatsTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS * 2, $countryStatsTime);
        $this->assertIsArray($countryStats);

        // Test high-risk submissions performance
        $startTime = microtime(true);
        $highRiskSubmissions = BlockedSubmission::getCachedHighRiskSubmissions(24);
        $highRiskTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS, $highRiskTime);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $highRiskSubmissions);
    }

    #[Test]
    public function complex_business_logic_meets_performance_targets(): void
    {
        // Create submission with complex scenario
        $testIp = '192.168.'.((int) (microtime(true) * 1000) % 255 + 1).'.'.mt_rand(1, 254);
        $submission = BlockedSubmission::factory()->create([
            'ip_address' => $testIp,
            'block_reason' => BlockReason::HONEYPOT->value,
            'is_tor' => true,
            'country_code' => 'CN',
        ]);

        // Get the IP reputation created by the observer and update it
        $ipReputation = IpReputation::where('ip_address', $testIp)->first();
        $ipReputation->update([
            'is_malware' => true,
            'is_botnet' => true,
            'threat_categories' => ['malware', 'botnet'],
        ]);

        // Test comprehensive risk calculation performance
        $startTime = microtime(true);
        $riskScore = $submission->calculateComprehensiveRiskScore();
        $riskTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(50, $riskTime); // Should be very fast
        $this->assertIsInt($riskScore);

        // Test threat assessment performance
        $startTime = microtime(true);
        $assessment = $submission->generateThreatAssessment();
        $assessmentTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS, $assessmentTime);
        $this->assertIsArray($assessment);

        // Test pattern analysis performance
        $startTime = microtime(true);
        $patterns = $submission->analyzeSubmissionPatterns();
        $patternTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(self::PERFORMANCE_TARGET_MS, $patternTime);
        $this->assertIsArray($patterns);
    }
}
