<?php

/**
 * Test File: SpamPatternTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Comprehensive unit tests for the SpamPattern model including
 * pattern matching, performance tracking, accuracy validation, and query scopes.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Models;

use JTD\FormSecurity\Models\SpamPattern;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;

#[Group('sprint-002')]
#[Group('epic-001')]
#[Group('database')]
#[Group('models')]
#[Group('ticket-1021')]
#[Group('spam-pattern')]
class SpamPatternTest extends TestCase
{
    #[Test]
    public function it_can_create_spam_pattern_with_required_fields(): void
    {
        $pattern = SpamPattern::create([
            'name' => 'Test Pattern',
            'pattern_type' => 'regex',
            'pattern' => '/spam|viagra|casino/i',
            'risk_score' => 85,
            'action' => 'block',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(SpamPattern::class, $pattern);
        $this->assertEquals('Test Pattern', $pattern->name);
        $this->assertEquals('regex', $pattern->pattern_type);
        $this->assertEquals('/spam|viagra|casino/i', $pattern->pattern);
        $this->assertEquals(85, $pattern->risk_score);
        $this->assertEquals('block', $pattern->action);
        $this->assertTrue($pattern->is_active);
    }

    #[Test]
    public function it_casts_attributes_correctly(): void
    {
        $pattern = SpamPattern::create([
            'name' => 'Casting Test',
            'pattern_type' => 'keyword',
            'pattern' => 'test pattern',
            'pattern_config' => ['case_sensitive' => true, 'whole_word' => false],
            'case_sensitive' => '1',
            'whole_word_only' => '0',
            'target_fields' => ['subject', 'message'],
            'target_forms' => ['contact', 'comment'],
            'risk_score' => '75',
            'match_count' => '100',
            'false_positive_count' => '5',
            'accuracy_rate' => '0.9500',
            'processing_time_ms' => '15',
            'is_active' => '1',
            'is_learning' => '0',
            'priority' => '8',
            'last_matched' => '2025-01-27 12:00:00',
            'categories' => ['spam', 'promotional'],
            'languages' => ['en', 'es'],
            'regions' => ['US', 'CA'],
            'metadata' => ['source' => 'manual', 'version' => '1.0'],
        ]);

        $this->assertIsArray($pattern->pattern_config);
        $this->assertIsBool($pattern->case_sensitive);
        $this->assertIsBool($pattern->whole_word_only);
        $this->assertIsArray($pattern->target_fields);
        $this->assertIsArray($pattern->target_forms);
        $this->assertIsInt($pattern->risk_score);
        $this->assertIsInt($pattern->match_count);
        $this->assertIsInt($pattern->false_positive_count);
        $this->assertTrue(is_float($pattern->accuracy_rate) || is_string($pattern->accuracy_rate));
        $this->assertIsInt($pattern->processing_time_ms);
        $this->assertIsBool($pattern->is_active);
        $this->assertIsBool($pattern->is_learning);
        $this->assertIsInt($pattern->priority);
        $this->assertInstanceOf(Carbon::class, $pattern->last_matched);
        $this->assertIsArray($pattern->categories);
        $this->assertIsArray($pattern->languages);
        $this->assertIsArray($pattern->regions);
        $this->assertIsArray($pattern->metadata);

        $this->assertEquals(['case_sensitive' => true, 'whole_word' => false], $pattern->pattern_config);
        $this->assertTrue($pattern->case_sensitive);
        $this->assertFalse($pattern->whole_word_only);
        $this->assertEquals(['subject', 'message'], $pattern->target_fields);
        $this->assertEquals(['contact', 'comment'], $pattern->target_forms);
        $this->assertEquals(75, $pattern->risk_score);
        $this->assertEquals(100, $pattern->match_count);
        $this->assertEquals(5, $pattern->false_positive_count);
        $this->assertEquals(15, $pattern->processing_time_ms);
        $this->assertTrue($pattern->is_active);
        $this->assertFalse($pattern->is_learning);
        $this->assertEquals(8, $pattern->priority);
    }

    #[Test]
    public function scope_active_filters_correctly(): void
    {
        SpamPattern::create([
            'name' => 'Active Pattern',
            'pattern_type' => 'regex',
            'pattern' => '/active/',
            'is_active' => true,
        ]);

        SpamPattern::create([
            'name' => 'Inactive Pattern',
            'pattern_type' => 'regex',
            'pattern' => '/inactive/',
            'is_active' => false,
        ]);

        $activePatterns = SpamPattern::active()->get();
        
        $this->assertCount(1, $activePatterns);
        $this->assertEquals('Active Pattern', $activePatterns->first()->name);
        $this->assertTrue($activePatterns->first()->is_active);
    }

    #[Test]
    public function scope_by_type_filters_correctly(): void
    {
        SpamPattern::create([
            'name' => 'Regex Pattern',
            'pattern_type' => 'regex',
            'pattern' => '/test/',
        ]);

        SpamPattern::create([
            'name' => 'Keyword Pattern',
            'pattern_type' => 'keyword',
            'pattern' => 'spam',
        ]);

        $regexPatterns = SpamPattern::byType('regex')->get();
        $keywordPatterns = SpamPattern::byType('keyword')->get();

        $this->assertCount(1, $regexPatterns);
        $this->assertCount(1, $keywordPatterns);
        $this->assertEquals('regex', $regexPatterns->first()->pattern_type);
        $this->assertEquals('keyword', $keywordPatterns->first()->pattern_type);
    }

    #[Test]
    public function scope_high_priority_filters_correctly(): void
    {
        SpamPattern::create([
            'name' => 'High Priority',
            'pattern_type' => 'regex',
            'pattern' => '/urgent/',
            'priority' => 9,
        ]);

        SpamPattern::create([
            'name' => 'Low Priority',
            'pattern_type' => 'regex',
            'pattern' => '/normal/',
            'priority' => 5,
        ]);

        $highPriorityPatterns = SpamPattern::highPriority()->get();
        
        $this->assertCount(1, $highPriorityPatterns);
        $this->assertEquals('High Priority', $highPriorityPatterns->first()->name);
        $this->assertGreaterThanOrEqual(8, $highPriorityPatterns->first()->priority);
    }

    #[Test]
    public function scope_high_accuracy_filters_correctly(): void
    {
        SpamPattern::create([
            'name' => 'High Accuracy',
            'pattern_type' => 'regex',
            'pattern' => '/accurate/',
            'accuracy_rate' => 0.95,
        ]);

        SpamPattern::create([
            'name' => 'Low Accuracy',
            'pattern_type' => 'regex',
            'pattern' => '/inaccurate/',
            'accuracy_rate' => 0.75,
        ]);

        $highAccuracyPatterns = SpamPattern::highAccuracy(0.9)->get();
        
        $this->assertCount(1, $highAccuracyPatterns);
        $this->assertEquals('High Accuracy', $highAccuracyPatterns->first()->name);
        $this->assertGreaterThanOrEqual(0.9, (float)$highAccuracyPatterns->first()->accuracy_rate);
    }

    #[Test]
    public function scope_recently_matched_filters_correctly(): void
    {
        SpamPattern::create([
            'name' => 'Recent Match',
            'pattern_type' => 'regex',
            'pattern' => '/recent/',
            'last_matched' => now()->subHours(2),
        ]);

        SpamPattern::create([
            'name' => 'Old Match',
            'pattern_type' => 'regex',
            'pattern' => '/old/',
            'last_matched' => now()->subHours(25),
        ]);

        $recentPatterns = SpamPattern::recentlyMatched(24)->get();
        
        $this->assertCount(1, $recentPatterns);
        $this->assertEquals('Recent Match', $recentPatterns->first()->name);
        $this->assertTrue($recentPatterns->first()->last_matched->isAfter(now()->subHours(24)));
    }

    #[Test]
    public function scope_learning_filters_correctly(): void
    {
        SpamPattern::create([
            'name' => 'Learning Pattern',
            'pattern_type' => 'regex',
            'pattern' => '/learning/',
            'is_learning' => true,
        ]);

        SpamPattern::create([
            'name' => 'Static Pattern',
            'pattern_type' => 'regex',
            'pattern' => '/static/',
            'is_learning' => false,
        ]);

        $learningPatterns = SpamPattern::learning()->get();
        
        $this->assertCount(1, $learningPatterns);
        $this->assertEquals('Learning Pattern', $learningPatterns->first()->name);
        $this->assertTrue($learningPatterns->first()->is_learning);
    }

    #[Test]
    public function scope_fast_processing_filters_correctly(): void
    {
        SpamPattern::create([
            'name' => 'Fast Pattern',
            'pattern_type' => 'keyword',
            'pattern' => 'fast',
            'processing_time_ms' => 5,
        ]);

        SpamPattern::create([
            'name' => 'Slow Pattern',
            'pattern_type' => 'regex',
            'pattern' => '/complex.*pattern.*with.*many.*groups/',
            'processing_time_ms' => 25,
        ]);

        $fastPatterns = SpamPattern::fastProcessing(10)->get();
        
        $this->assertCount(1, $fastPatterns);
        $this->assertEquals('Fast Pattern', $fastPatterns->first()->name);
        $this->assertLessThanOrEqual(10, $fastPatterns->first()->processing_time_ms);
    }

    #[Test]
    public function is_high_accuracy_method_works_correctly(): void
    {
        $highAccuracyPattern = SpamPattern::create([
            'name' => 'High Accuracy',
            'pattern_type' => 'regex',
            'pattern' => '/test/',
            'accuracy_rate' => 0.95,
        ]);

        $lowAccuracyPattern = SpamPattern::create([
            'name' => 'Low Accuracy',
            'pattern_type' => 'regex',
            'pattern' => '/test2/',
            'accuracy_rate' => 0.75,
        ]);

        $this->assertTrue($highAccuracyPattern->isHighAccuracy());
        $this->assertFalse($lowAccuracyPattern->isHighAccuracy());
    }

    #[Test]
    public function is_high_priority_method_works_correctly(): void
    {
        $highPriorityPattern = SpamPattern::create([
            'name' => 'High Priority',
            'pattern_type' => 'regex',
            'pattern' => '/test/',
            'priority' => 9,
        ]);

        $lowPriorityPattern = SpamPattern::create([
            'name' => 'Low Priority',
            'pattern_type' => 'regex',
            'pattern' => '/test2/',
            'priority' => 5,
        ]);

        $this->assertTrue($highPriorityPattern->isHighPriority());
        $this->assertFalse($lowPriorityPattern->isHighPriority());
    }

    #[Test]
    public function is_fast_processing_method_works_correctly(): void
    {
        $fastPattern = SpamPattern::create([
            'name' => 'Fast Pattern',
            'pattern_type' => 'keyword',
            'pattern' => 'test',
            'processing_time_ms' => 5,
        ]);

        $slowPattern = SpamPattern::create([
            'name' => 'Slow Pattern',
            'pattern_type' => 'regex',
            'pattern' => '/complex/',
            'processing_time_ms' => 25,
        ]);

        $this->assertTrue($fastPattern->isFastProcessing());
        $this->assertFalse($slowPattern->isFastProcessing());
    }

    #[Test]
    public function get_effectiveness_score_calculates_correctly(): void
    {
        $pattern = SpamPattern::create([
            'name' => 'Effectiveness Test',
            'pattern_type' => 'regex',
            'pattern' => '/test/',
            'match_count' => 50,
            'accuracy_rate' => 0.9,
        ]);

        $effectivenessScore = $pattern->getEffectivenessScore();

        // Usage weight = min(50/100, 1.0) = 0.5
        // Effectiveness = 0.9 * 0.5 = 0.45
        $this->assertEquals(0.45, $effectivenessScore);
    }

    #[Test]
    public function record_match_updates_statistics_correctly(): void
    {
        $pattern = SpamPattern::create([
            'name' => 'Statistics Test',
            'pattern_type' => 'regex',
            'pattern' => '/test/',
            'match_count' => 10,
            'false_positive_count' => 1,
            'accuracy_rate' => 0.9,
            'processing_time_ms' => 10,
        ]);

        // Record a true positive match
        $pattern->recordMatch(false, 8);

        $this->assertEquals(11, $pattern->match_count);
        $this->assertEquals(1, $pattern->false_positive_count);
        $this->assertEquals(round(10/11, 4), round((float)$pattern->accuracy_rate, 4)); // 10 true positives out of 11 total
        $this->assertEquals(9, $pattern->processing_time_ms); // Average of 10 and 8
        $this->assertNotNull($pattern->last_matched);

        // Record a false positive match
        $pattern->recordMatch(true, 12);

        $this->assertEquals(12, $pattern->match_count);
        $this->assertEquals(2, $pattern->false_positive_count);
        $this->assertEquals(round(10/12, 4), round((float)$pattern->accuracy_rate, 4)); // 10 true positives out of 12 total
    }

    #[Test]
    public function reset_statistics_clears_data_correctly(): void
    {
        $pattern = SpamPattern::create([
            'name' => 'Reset Test',
            'pattern_type' => 'regex',
            'pattern' => '/test/',
            'match_count' => 100,
            'false_positive_count' => 10,
            'accuracy_rate' => 0.9,
            'processing_time_ms' => 15,
            'last_matched' => now(),
        ]);

        $pattern->resetStatistics();

        $this->assertEquals(0, $pattern->match_count);
        $this->assertEquals(0, $pattern->false_positive_count);
        $this->assertEquals(1.0, (float)$pattern->accuracy_rate);
        $this->assertEquals(0, $pattern->processing_time_ms);
        $this->assertNull($pattern->last_matched);
    }

    #[Test]
    public function get_performance_summary_returns_correct_data(): void
    {
        $pattern = SpamPattern::create([
            'name' => 'Performance Test',
            'pattern_type' => 'regex',
            'pattern' => '/test/',
            'match_count' => 100,
            'false_positive_count' => 5,
            'accuracy_rate' => 0.95,
            'processing_time_ms' => 8,
            'last_matched' => now(),
        ]);

        $summary = $pattern->getPerformanceSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('matches', $summary);
        $this->assertArrayHasKey('false_positives', $summary);
        $this->assertArrayHasKey('accuracy', $summary);
        $this->assertArrayHasKey('effectiveness', $summary);
        $this->assertArrayHasKey('avg_processing_time', $summary);
        $this->assertArrayHasKey('last_matched', $summary);
        $this->assertArrayHasKey('is_high_performance', $summary);

        $this->assertEquals(100, $summary['matches']);
        $this->assertEquals(5, $summary['false_positives']);
        $this->assertEquals(0.95, (float)$summary['accuracy']);
        $this->assertEquals(8, $summary['avg_processing_time']);
        $this->assertTrue($summary['is_high_performance']); // High accuracy (0.95) and fast processing (8ms)
    }

    #[Test]
    public function scope_order_by_priority_works_correctly(): void
    {
        SpamPattern::create([
            'name' => 'Low Priority',
            'pattern_type' => 'regex',
            'pattern' => '/low/',
            'priority' => 3,
        ]);

        SpamPattern::create([
            'name' => 'High Priority',
            'pattern_type' => 'regex',
            'pattern' => '/high/',
            'priority' => 9,
        ]);

        SpamPattern::create([
            'name' => 'Medium Priority',
            'pattern_type' => 'regex',
            'pattern' => '/medium/',
            'priority' => 6,
        ]);

        $orderedPatterns = SpamPattern::orderByPriority()->get();

        $this->assertEquals('High Priority', $orderedPatterns->first()->name);
        $this->assertEquals('Medium Priority', $orderedPatterns->get(1)->name);
        $this->assertEquals('Low Priority', $orderedPatterns->last()->name);
    }

    #[Test]
    public function scope_order_by_accuracy_works_correctly(): void
    {
        SpamPattern::create([
            'name' => 'Low Accuracy',
            'pattern_type' => 'regex',
            'pattern' => '/low/',
            'accuracy_rate' => 0.7,
        ]);

        SpamPattern::create([
            'name' => 'High Accuracy',
            'pattern_type' => 'regex',
            'pattern' => '/high/',
            'accuracy_rate' => 0.95,
        ]);

        SpamPattern::create([
            'name' => 'Medium Accuracy',
            'pattern_type' => 'regex',
            'pattern' => '/medium/',
            'accuracy_rate' => 0.85,
        ]);

        $orderedPatterns = SpamPattern::orderByAccuracy()->get();

        $this->assertEquals('High Accuracy', $orderedPatterns->first()->name);
        $this->assertEquals('Medium Accuracy', $orderedPatterns->get(1)->name);
        $this->assertEquals('Low Accuracy', $orderedPatterns->last()->name);
    }
}
