<?php

/**
 * Test File: PatternEffectivenessTest.php
 *
 * EPIC: EPIC-002-core-spam-detection-engine
 * SPEC: SPEC-004-pattern-based-spam-detection
 * SPRINT: Sprint-007-Epic-002-Foundation-Setup
 * TICKET: 2011-spam-pattern-model-repository
 *
 * Description: Comprehensive unit tests for the PatternEffectiveness value object
 * including metrics calculation, validation, and comparison operations.
 *
 * @see docs/Planning/Epics/EPIC-002-core-spam-detection-engine.md
 * @see docs/Planning/Specs/Core-Detection-Engine/SPEC-004-pattern-based-spam-detection.md
 * @see docs/Planning/Tickets/Core-Spam-Detection-Engine/Implementation/2011-spam-pattern-model-repository.md
 */

namespace JTD\FormSecurity\Tests\Unit\ValueObjects;

use InvalidArgumentException;
use JTD\FormSecurity\Enums\PatternAction;
use JTD\FormSecurity\Enums\PatternType;
use JTD\FormSecurity\Models\SpamPattern;
use JTD\FormSecurity\Tests\TestCase;
use JTD\FormSecurity\ValueObjects\PatternEffectiveness;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('sprint-007')]
#[Group('epic-002')]
#[Group('value-objects')]
#[Group('spam-detection')]
#[Group('ticket-2011')]
class PatternEffectivenessTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_valid_data(): void
    {
        $effectiveness = new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 5,
            accuracyRate: 0.95,
            averageProcessingTime: 10.5,
            priority: 8,
            effectivenessScore: 0.9,
            performanceCategory: 'excellent',
            recommendations: ['Consider increasing priority']
        );

        $this->assertEquals(100, $effectiveness->totalMatches);
        $this->assertEquals(5, $effectiveness->falsePositives);
        $this->assertEquals(0.95, $effectiveness->accuracyRate);
        $this->assertEquals(10.5, $effectiveness->averageProcessingTime);
        $this->assertEquals(8, $effectiveness->priority);
        $this->assertEquals(0.9, $effectiveness->effectivenessScore);
        $this->assertEquals('excellent', $effectiveness->performanceCategory);
        $this->assertEquals(['Consider increasing priority'], $effectiveness->recommendations);
    }

    #[Test]
    public function it_validates_total_matches_cannot_be_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Total matches cannot be negative');

        new PatternEffectiveness(
            totalMatches: -1,
            falsePositives: 0,
            accuracyRate: 1.0,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 0.8,
            performanceCategory: 'good'
        );
    }

    #[Test]
    public function it_validates_false_positives_cannot_be_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('False positives cannot be negative');

        new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: -1,
            accuracyRate: 1.0,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 0.8,
            performanceCategory: 'good'
        );
    }

    #[Test]
    public function it_validates_false_positives_cannot_exceed_total_matches(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('False positives cannot exceed total matches');

        new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 101,
            accuracyRate: 1.0,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 0.8,
            performanceCategory: 'good'
        );
    }

    #[Test]
    public function it_validates_accuracy_rate_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Accuracy rate must be between 0.0 and 1.0');

        new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 5,
            accuracyRate: 1.5,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 0.8,
            performanceCategory: 'good'
        );
    }

    #[Test]
    public function it_validates_processing_time_cannot_be_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Processing time cannot be negative');

        new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 5,
            accuracyRate: 0.95,
            averageProcessingTime: -1.0,
            priority: 5,
            effectivenessScore: 0.8,
            performanceCategory: 'good'
        );
    }

    #[Test]
    public function it_validates_priority_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be between 1 and 10');

        new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 5,
            accuracyRate: 0.95,
            averageProcessingTime: 10.0,
            priority: 11,
            effectivenessScore: 0.8,
            performanceCategory: 'good'
        );
    }

    #[Test]
    public function it_validates_effectiveness_score_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Effectiveness score must be between 0.0 and 1.0');

        new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 5,
            accuracyRate: 0.95,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 1.5,
            performanceCategory: 'good'
        );
    }

    #[Test]
    public function it_validates_performance_category(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid performance category');

        new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 5,
            accuracyRate: 0.95,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 0.8,
            performanceCategory: 'invalid_category'
        );
    }

    #[Test]
    public function it_creates_from_spam_pattern(): void
    {
        $pattern = new SpamPattern([
            'name' => 'Test Pattern',
            'pattern_type' => PatternType::KEYWORD,
            'pattern' => 'test',
            'action' => PatternAction::BLOCK,
            'match_count' => 100,
            'false_positive_count' => 5,
            'accuracy_rate' => 0.95,
            'processing_time_ms' => 10,
            'priority' => 8,
        ]);

        $effectiveness = PatternEffectiveness::fromSpamPattern($pattern);

        $this->assertEquals(100, $effectiveness->totalMatches);
        $this->assertEquals(5, $effectiveness->falsePositives);
        $this->assertEquals(0.95, $effectiveness->accuracyRate);
        $this->assertEquals(10.0, $effectiveness->averageProcessingTime);
        $this->assertEquals(8, $effectiveness->priority);
    }

    #[Test]
    public function it_creates_from_metrics(): void
    {
        $effectiveness = PatternEffectiveness::fromMetrics(
            totalMatches: 50,
            falsePositives: 5,
            averageProcessingTime: 15.0,
            priority: 6
        );

        $this->assertEquals(50, $effectiveness->totalMatches);
        $this->assertEquals(5, $effectiveness->falsePositives);
        $this->assertEquals(0.9, $effectiveness->accuracyRate); // (50-5)/50
        $this->assertEquals(15.0, $effectiveness->averageProcessingTime);
        $this->assertEquals(6, $effectiveness->priority);
        $this->assertEquals(0.45, $effectiveness->effectivenessScore); // 0.9 * 0.5 (usage weight)
    }

    #[Test]
    public function it_calculates_true_positives(): void
    {
        $effectiveness = new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 15,
            accuracyRate: 0.85,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 0.8,
            performanceCategory: 'good'
        );

        $this->assertEquals(85, $effectiveness->getTruePositives());
    }

    #[Test]
    public function it_calculates_false_positive_rate(): void
    {
        $effectiveness = new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 20,
            accuracyRate: 0.8,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 0.75,
            performanceCategory: 'acceptable'
        );

        $this->assertEquals(0.2, $effectiveness->getFalsePositiveRate());
    }

    #[Test]
    public function it_calculates_false_positive_rate_for_zero_matches(): void
    {
        $effectiveness = new PatternEffectiveness(
            totalMatches: 0,
            falsePositives: 0,
            accuracyRate: 1.0,
            averageProcessingTime: 0.0,
            priority: 5,
            effectivenessScore: 0.0,
            performanceCategory: 'critical'
        );

        $this->assertEquals(0.0, $effectiveness->getFalsePositiveRate());
    }

    #[Test]
    public function it_checks_high_accuracy(): void
    {
        $highAccuracy = new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 5,
            accuracyRate: 0.95,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 0.9,
            performanceCategory: 'excellent'
        );

        $lowAccuracy = new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 20,
            accuracyRate: 0.8,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 0.75,
            performanceCategory: 'acceptable'
        );

        $this->assertTrue($highAccuracy->isHighAccuracy());
        $this->assertFalse($lowAccuracy->isHighAccuracy());
        $this->assertTrue($lowAccuracy->isHighAccuracy(0.75)); // Custom threshold
    }

    #[Test]
    public function it_checks_fast_processing(): void
    {
        $fastPattern = new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 5,
            accuracyRate: 0.95,
            averageProcessingTime: 5.0,
            priority: 5,
            effectivenessScore: 0.9,
            performanceCategory: 'excellent'
        );

        $slowPattern = new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 5,
            accuracyRate: 0.95,
            averageProcessingTime: 25.0,
            priority: 5,
            effectivenessScore: 0.9,
            performanceCategory: 'acceptable'
        );

        $this->assertTrue($fastPattern->isFastProcessing());
        $this->assertFalse($slowPattern->isFastProcessing());
        $this->assertTrue($slowPattern->isFastProcessing(30.0)); // Custom threshold
    }

    #[Test]
    public function it_checks_effectiveness(): void
    {
        $effective = new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 5,
            accuracyRate: 0.95,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 0.9,
            performanceCategory: 'excellent'
        );

        $ineffective = new PatternEffectiveness(
            totalMatches: 10,
            falsePositives: 3,
            accuracyRate: 0.7,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 0.6,
            performanceCategory: 'acceptable'
        );

        $this->assertTrue($effective->isEffective());
        $this->assertFalse($ineffective->isEffective());
    }

    #[Test]
    public function it_determines_optimization_needs(): void
    {
        $needsOptimization = new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 30,
            accuracyRate: 0.7,
            averageProcessingTime: 50.0,
            priority: 5,
            effectivenessScore: 0.6,
            performanceCategory: 'poor'
        );

        $optimized = new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 2,
            accuracyRate: 0.98,
            averageProcessingTime: 5.0,
            priority: 5,
            effectivenessScore: 0.95,
            performanceCategory: 'excellent'
        );

        $this->assertTrue($needsOptimization->needsOptimization());
        $this->assertFalse($optimized->needsOptimization());
    }

    #[Test]
    public function it_determines_if_should_be_disabled(): void
    {
        $shouldDisable = new PatternEffectiveness(
            totalMatches: 150,
            falsePositives: 120,
            accuracyRate: 0.2,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 0.1,
            performanceCategory: 'critical'
        );

        $shouldKeep = new PatternEffectiveness(
            totalMatches: 50,
            falsePositives: 5,
            accuracyRate: 0.9,
            averageProcessingTime: 10.0,
            priority: 5,
            effectivenessScore: 0.85,
            performanceCategory: 'good'
        );

        $this->assertTrue($shouldDisable->shouldBeDisabled());
        $this->assertFalse($shouldKeep->shouldBeDisabled());
    }

    #[Test]
    public function it_calculates_effectiveness_grade(): void
    {
        $gradeA = new PatternEffectiveness(
            totalMatches: 100, falsePositives: 2, accuracyRate: 0.98, averageProcessingTime: 5.0,
            priority: 5, effectivenessScore: 0.95, performanceCategory: 'excellent'
        );

        $gradeB = new PatternEffectiveness(
            totalMatches: 100, falsePositives: 10, accuracyRate: 0.9, averageProcessingTime: 10.0,
            priority: 5, effectivenessScore: 0.85, performanceCategory: 'good'
        );

        $gradeF = new PatternEffectiveness(
            totalMatches: 100, falsePositives: 80, accuracyRate: 0.2, averageProcessingTime: 100.0,
            priority: 5, effectivenessScore: 0.1, performanceCategory: 'critical'
        );

        $this->assertEquals('A', $gradeA->getEffectivenessGrade());
        $this->assertEquals('B', $gradeB->getEffectivenessGrade());
        $this->assertEquals('F', $gradeF->getEffectivenessGrade());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $effectiveness = new PatternEffectiveness(
            totalMatches: 100,
            falsePositives: 5,
            accuracyRate: 0.95,
            averageProcessingTime: 10.5,
            priority: 8,
            effectivenessScore: 0.9,
            performanceCategory: 'excellent',
            recommendations: ['Test recommendation']
        );

        $array = $effectiveness->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('total_matches', $array);
        $this->assertArrayHasKey('true_positives', $array);
        $this->assertArrayHasKey('false_positives', $array);
        $this->assertArrayHasKey('false_positive_rate', $array);
        $this->assertArrayHasKey('accuracy_rate', $array);
        $this->assertArrayHasKey('average_processing_time', $array);
        $this->assertArrayHasKey('effectiveness_score', $array);
        $this->assertArrayHasKey('effectiveness_grade', $array);
        $this->assertArrayHasKey('performance_category', $array);
        $this->assertArrayHasKey('recommendations', $array);

        $this->assertEquals(100, $array['total_matches']);
        $this->assertEquals(95, $array['true_positives']);
        $this->assertEquals(5, $array['false_positives']);
        $this->assertEquals('A', $array['effectiveness_grade']);
        $this->assertEquals('excellent', $array['performance_category']);
    }

    #[Test]
    public function it_converts_to_json(): void
    {
        $effectiveness = new PatternEffectiveness(
            totalMatches: 50,
            falsePositives: 2,
            accuracyRate: 0.96,
            averageProcessingTime: 8.0,
            priority: 7,
            effectivenessScore: 0.88,
            performanceCategory: 'good'
        );

        $json = $effectiveness->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertEquals(50, $decoded['total_matches']);
        $this->assertEquals('good', $decoded['performance_category']);
    }

    #[Test]
    public function it_compares_effectiveness_instances(): void
    {
        $better = new PatternEffectiveness(
            totalMatches: 100, falsePositives: 2, accuracyRate: 0.98, averageProcessingTime: 5.0,
            priority: 8, effectivenessScore: 0.95, performanceCategory: 'excellent'
        );

        $worse = new PatternEffectiveness(
            totalMatches: 50, falsePositives: 10, accuracyRate: 0.8, averageProcessingTime: 20.0,
            priority: 5, effectivenessScore: 0.6, performanceCategory: 'acceptable'
        );

        $this->assertGreaterThan(0, $better->compareTo($worse));
        $this->assertLessThan(0, $worse->compareTo($better));
        $this->assertEquals(0, $better->compareTo($better));

        $this->assertTrue($better->isBetterThan($worse));
        $this->assertFalse($worse->isBetterThan($better));
    }

    #[Test]
    public function it_merges_with_another_effectiveness(): void
    {
        $first = new PatternEffectiveness(
            totalMatches: 50, falsePositives: 5, accuracyRate: 0.9, averageProcessingTime: 10.0,
            priority: 8, effectivenessScore: 0.8, performanceCategory: 'good'
        );

        $second = new PatternEffectiveness(
            totalMatches: 30, falsePositives: 3, accuracyRate: 0.9, averageProcessingTime: 15.0,
            priority: 6, effectivenessScore: 0.7, performanceCategory: 'acceptable'
        );

        $merged = $first->mergeWith($second);

        $this->assertEquals(80, $merged->totalMatches); // 50 + 30
        $this->assertEquals(8, $merged->falsePositives); // 5 + 3
        $this->assertEquals(12.5, $merged->averageProcessingTime); // (10 + 15) / 2
        $this->assertEquals(7, $merged->priority); // (8 + 6) / 2 rounded
    }
}
