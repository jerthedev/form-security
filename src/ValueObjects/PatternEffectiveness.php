<?php

declare(strict_types=1);

/**
 * Value Object File: PatternEffectiveness.php
 *
 * EPIC: EPIC-002-core-spam-detection-engine
 * SPEC: SPEC-004-pattern-based-spam-detection
 * SPRINT: Sprint-007-Epic-002-Foundation-Setup
 * TICKET: 2011-spam-pattern-model-repository
 *
 * Description: Immutable value object for pattern effectiveness metrics and analytics
 * providing comprehensive performance evaluation for spam detection patterns.
 *
 * @see docs/Planning/Epics/EPIC-002-core-spam-detection-engine.md
 * @see docs/Planning/Specs/Core-Detection-Engine/SPEC-004-pattern-based-spam-detection.md
 * @see docs/Planning/Tickets/Core-Spam-Detection-Engine/Implementation/2011-spam-pattern-model-repository.md
 */

namespace JTD\FormSecurity\ValueObjects;

use InvalidArgumentException;

/**
 * PatternEffectiveness Value Object
 *
 * Immutable value object representing pattern effectiveness metrics and analytics.
 * Provides comprehensive performance evaluation for spam detection patterns.
 */
final readonly class PatternEffectiveness
{
    /**
     * Create a new pattern effectiveness instance
     *
     * @param  int  $totalMatches  Total number of pattern matches
     * @param  int  $falsePositives  Number of false positive matches
     * @param  float  $accuracyRate  Pattern accuracy rate (0.0-1.0)
     * @param  float  $averageProcessingTime  Average processing time in milliseconds
     * @param  int  $priority  Pattern priority (1-10)
     * @param  float  $effectivenessScore  Overall effectiveness score (0.0-1.0)
     * @param  string  $performanceCategory  Performance category (excellent, good, acceptable, poor, critical)
     * @param  array<string>  $recommendations  Array of optimization recommendations
     */
    public function __construct(
        public int $totalMatches,
        public int $falsePositives,
        public float $accuracyRate,
        public float $averageProcessingTime,
        public int $priority,
        public float $effectivenessScore,
        public string $performanceCategory,
        public array $recommendations = []
    ) {
        $this->validateMetrics();
    }

    /**
     * Create from SpamPattern model
     */
    public static function fromSpamPattern(\JTD\FormSecurity\Models\SpamPattern $pattern): self
    {
        return new self(
            totalMatches: $pattern->match_count,
            falsePositives: $pattern->false_positive_count,
            accuracyRate: (float) $pattern->accuracy_rate,
            averageProcessingTime: (float) $pattern->processing_time_ms,
            priority: $pattern->priority,
            effectivenessScore: $pattern->getEffectivenessScore(),
            performanceCategory: $pattern->getPerformanceCategory(),
            recommendations: $pattern->analyzeMatchingTrends()['recommendations'] ?? []
        );
    }

    /**
     * Create from raw metrics
     */
    public static function fromMetrics(
        int $totalMatches,
        int $falsePositives,
        float $averageProcessingTime,
        int $priority = 5
    ): self {
        $accuracyRate = $totalMatches > 0 ? ($totalMatches - $falsePositives) / $totalMatches : 1.0;
        $usageWeight = min($totalMatches / 100, 1.0);
        $effectivenessScore = $accuracyRate * $usageWeight;

        $performanceCategory = match (true) {
            $effectivenessScore > 0.9 && $averageProcessingTime < 5 => 'excellent',
            $effectivenessScore > 0.8 && $averageProcessingTime < 10 => 'good',
            $effectivenessScore > 0.6 && $averageProcessingTime < 25 => 'acceptable',
            $effectivenessScore > 0.4 => 'poor',
            default => 'critical',
        };

        $recommendations = self::generateRecommendations(
            $effectivenessScore,
            $averageProcessingTime,
            $falsePositives,
            $totalMatches
        );

        return new self(
            totalMatches: $totalMatches,
            falsePositives: $falsePositives,
            accuracyRate: $accuracyRate,
            averageProcessingTime: $averageProcessingTime,
            priority: $priority,
            effectivenessScore: $effectivenessScore,
            performanceCategory: $performanceCategory,
            recommendations: $recommendations
        );
    }

    /**
     * Get true positives count
     */
    public function getTruePositives(): int
    {
        return max(0, $this->totalMatches - $this->falsePositives);
    }

    /**
     * Get false positive rate
     */
    public function getFalsePositiveRate(): float
    {
        return $this->totalMatches > 0 ? $this->falsePositives / $this->totalMatches : 0.0;
    }

    /**
     * Check if pattern has high accuracy
     */
    public function isHighAccuracy(float $threshold = 0.9): bool
    {
        return $this->accuracyRate >= $threshold;
    }

    /**
     * Check if pattern is fast processing
     */
    public function isFastProcessing(float $threshold = 10.0): bool
    {
        return $this->averageProcessingTime <= $threshold;
    }

    /**
     * Check if pattern is effective
     */
    public function isEffective(float $threshold = 0.8): bool
    {
        return $this->effectivenessScore >= $threshold;
    }

    /**
     * Check if pattern needs optimization
     */
    public function needsOptimization(): bool
    {
        return $this->performanceCategory === 'poor' ||
               $this->performanceCategory === 'critical' ||
               $this->averageProcessingTime > 25.0 ||
               $this->getFalsePositiveRate() > 0.2;
    }

    /**
     * Check if pattern should be disabled
     */
    public function shouldBeDisabled(): bool
    {
        return $this->performanceCategory === 'critical' ||
               ($this->accuracyRate < 0.3 && $this->totalMatches > 100);
    }

    /**
     * Get effectiveness grade (A-F)
     */
    public function getEffectivenessGrade(): string
    {
        return match (true) {
            $this->effectivenessScore >= 0.9 => 'A',
            $this->effectivenessScore >= 0.8 => 'B',
            $this->effectivenessScore >= 0.7 => 'C',
            $this->effectivenessScore >= 0.6 => 'D',
            default => 'F',
        };
    }

    /**
     * Get performance summary as array
     */
    public function toArray(): array
    {
        return [
            'total_matches' => $this->totalMatches,
            'true_positives' => $this->getTruePositives(),
            'false_positives' => $this->falsePositives,
            'false_positive_rate' => round($this->getFalsePositiveRate(), 4),
            'accuracy_rate' => round($this->accuracyRate, 4),
            'average_processing_time' => round($this->averageProcessingTime, 2),
            'priority' => $this->priority,
            'effectiveness_score' => round($this->effectivenessScore, 4),
            'effectiveness_grade' => $this->getEffectivenessGrade(),
            'performance_category' => $this->performanceCategory,
            'is_high_accuracy' => $this->isHighAccuracy(),
            'is_fast_processing' => $this->isFastProcessing(),
            'is_effective' => $this->isEffective(),
            'needs_optimization' => $this->needsOptimization(),
            'should_be_disabled' => $this->shouldBeDisabled(),
            'recommendations' => $this->recommendations,
        ];
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Compare with another effectiveness instance
     */
    public function compareTo(self $other): int
    {
        // Compare by effectiveness score first
        $scoreComparison = $this->effectivenessScore <=> $other->effectivenessScore;
        if ($scoreComparison !== 0) {
            return $scoreComparison;
        }

        // If same effectiveness, compare by processing time (lower is better)
        return $other->averageProcessingTime <=> $this->averageProcessingTime;
    }

    /**
     * Check if this effectiveness is better than another
     */
    public function isBetterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /**
     * Merge with another effectiveness instance (for aggregation)
     */
    public function mergeWith(self $other): self
    {
        $combinedMatches = $this->totalMatches + $other->totalMatches;
        $combinedFalsePositives = $this->falsePositives + $other->falsePositives;
        $averageProcessingTime = ($this->averageProcessingTime + $other->averageProcessingTime) / 2;
        $averagePriority = (int) round(($this->priority + $other->priority) / 2);

        return self::fromMetrics(
            $combinedMatches,
            $combinedFalsePositives,
            $averageProcessingTime,
            $averagePriority
        );
    }

    /**
     * Validate metrics
     */
    private function validateMetrics(): void
    {
        if ($this->totalMatches < 0) {
            throw new InvalidArgumentException('Total matches cannot be negative');
        }

        if ($this->falsePositives < 0) {
            throw new InvalidArgumentException('False positives cannot be negative');
        }

        if ($this->falsePositives > $this->totalMatches) {
            throw new InvalidArgumentException('False positives cannot exceed total matches');
        }

        if ($this->accuracyRate < 0.0 || $this->accuracyRate > 1.0) {
            throw new InvalidArgumentException('Accuracy rate must be between 0.0 and 1.0');
        }

        if ($this->averageProcessingTime < 0.0) {
            throw new InvalidArgumentException('Processing time cannot be negative');
        }

        if ($this->priority < 1 || $this->priority > 10) {
            throw new InvalidArgumentException('Priority must be between 1 and 10');
        }

        if ($this->effectivenessScore < 0.0 || $this->effectivenessScore > 1.0) {
            throw new InvalidArgumentException('Effectiveness score must be between 0.0 and 1.0');
        }

        $validCategories = ['excellent', 'good', 'acceptable', 'poor', 'critical'];
        if (! in_array($this->performanceCategory, $validCategories, true)) {
            throw new InvalidArgumentException('Invalid performance category');
        }
    }

    /**
     * Generate optimization recommendations
     */
    private static function generateRecommendations(
        float $effectivenessScore,
        float $averageProcessingTime,
        int $falsePositives,
        int $totalMatches
    ): array {
        $recommendations = [];

        if ($effectivenessScore < 0.5) {
            $recommendations[] = 'Consider disabling or rewriting this pattern';
            $recommendations[] = 'Review pattern logic and test cases';
        }

        if ($averageProcessingTime > 25) {
            $recommendations[] = 'Optimize pattern for better performance';
            $recommendations[] = 'Consider simplifying regex or logic';
        }

        if ($totalMatches > 0 && ($falsePositives / $totalMatches) > 0.2) {
            $recommendations[] = 'Reduce false positive rate';
            $recommendations[] = 'Add more specific matching criteria';
        }

        if ($totalMatches < 10) {
            $recommendations[] = 'Pattern may be too specific';
            $recommendations[] = 'Consider broadening matching criteria';
        }

        if ($effectivenessScore >= 0.9 && $averageProcessingTime < 5) {
            $recommendations[] = 'Consider increasing priority';
            $recommendations[] = 'Use as template for similar patterns';
        }

        return $recommendations;
    }
}
