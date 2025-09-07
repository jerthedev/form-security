<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services;

use JTD\FormSecurity\Contracts\ConfigurationContract;
use JTD\FormSecurity\Enums\DetectionMethod;
use JTD\FormSecurity\ValueObjects\SpamDetectionResult;

/**
 * Score calculator service for weighted spam detection scoring.
 *
 * Implements the hybrid detection algorithm with configurable weights
 * for different detection methods and provides centralized score calculation.
 */
class ScoreCalculatorService
{
    /**
     * Default weights for the hybrid detection algorithm.
     */
    protected array $defaultWeights = [
        DetectionMethod::BAYESIAN->value => 0.40,      // 40%
        DetectionMethod::REGEX->value => 0.30,         // 30%
        DetectionMethod::BEHAVIORAL->value => 0.20,    // 20%
        DetectionMethod::AI->value => 0.10,            // 10%
    ];

    /**
     * Create a new score calculator service instance.
     */
    public function __construct(
        protected ConfigurationContract $config
    ) {}

    /**
     * Calculate weighted spam score from individual method scores.
     *
     * @param  array<string, float>  $methodScores  Scores by detection method
     * @param  array<string, float>  $customWeights  Custom weights override
     * @return float Weighted overall score (0.0 to 1.0)
     */
    public function calculateWeightedScore(array $methodScores, array $customWeights = []): float
    {
        if (empty($methodScores)) {
            return 0.0;
        }

        $weights = $this->getConfiguredWeights($customWeights);
        $weightedScore = 0.0;
        $totalWeight = 0.0;

        foreach ($methodScores as $method => $score) {
            $weight = $weights[$method] ?? 0.0;

            if ($weight > 0.0) {
                $weightedScore += $score * $weight;
                $totalWeight += $weight;
            }
        }

        // Normalize by total weight to handle cases where not all methods were used
        return $totalWeight > 0.0 ? min(1.0, $weightedScore / $totalWeight) : 0.0;
    }

    /**
     * Calculate confidence score based on method agreement and coverage.
     *
     * @param  array<string, float>  $methodScores  Scores by detection method
     * @return float Confidence level (0.0 to 1.0)
     */
    public function calculateConfidence(array $methodScores): float
    {
        if (empty($methodScores)) {
            return 0.0;
        }

        $scores = array_values($methodScores);
        $mean = array_sum($scores) / count($scores);
        $variance = 0.0;

        foreach ($scores as $score) {
            $variance += pow($score - $mean, 2);
        }

        $variance /= count($scores);
        $standardDeviation = sqrt($variance);

        // Lower deviation = higher confidence
        // More methods = higher confidence
        $deviationFactor = max(0.0, 1.0 - ($standardDeviation * 2));
        $coverageFactor = min(1.0, count($methodScores) / 4); // Assuming 4 primary methods

        return ($deviationFactor + $coverageFactor) / 2;
    }

    /**
     * Determine if score indicates spam based on configurable threshold.
     */
    public function isSpam(float $score, ?float $customThreshold = null): bool
    {
        $threshold = $customThreshold ?? $this->getSpamThreshold();

        return $score >= $threshold;
    }

    /**
     * Get risk level based on score.
     */
    public function getRiskLevel(float $score): string
    {
        return match (true) {
            $score >= 0.8 => 'critical',
            $score >= 0.6 => 'high',
            $score >= 0.4 => 'medium',
            $score >= 0.2 => 'low',
            default => 'minimal'
        };
    }

    /**
     * Calculate score adjustments based on context factors.
     *
     * @param  array<string, mixed>  $contextFactors  Context adjustment factors
     * @return float Score adjustment (-1.0 to 1.0)
     */
    public function calculateContextAdjustments(array $contextFactors): float
    {
        $adjustment = 0.0;
        $adjustmentRules = $this->getContextAdjustmentRules();

        foreach ($contextFactors as $factor => $value) {
            if (! isset($adjustmentRules[$factor])) {
                continue;
            }

            $rule = $adjustmentRules[$factor];
            $factorAdjustment = $this->applyAdjustmentRule($rule, $value);
            $adjustment += $factorAdjustment;
        }

        return max(-1.0, min(1.0, $adjustment));
    }

    /**
     * Calculate method priority scores for early exit optimization.
     *
     * @param  array<string, float>  $methodScores  Scores by detection method
     * @return array<string, array> Methods sorted by priority with metadata
     */
    public function calculateMethodPriorities(array $methodScores): array
    {
        $priorities = [];
        $weights = $this->getConfiguredWeights();

        foreach ($methodScores as $method => $score) {
            $weight = $weights[$method] ?? 0.0;
            $detectionMethod = DetectionMethod::tryFrom($method);

            if (! $detectionMethod) {
                continue;
            }

            $priorities[$method] = [
                'score' => $score,
                'weight' => $weight,
                'weighted_score' => $score * $weight,
                'processing_category' => $detectionMethod->getProcessingTimeCategory(),
                'complexity' => $detectionMethod->getComplexity(),
                'can_early_exit' => $this->canEarlyExit($method, $score),
            ];
        }

        // Sort by weighted score (highest first)
        uasort($priorities, fn ($a, $b) => $b['weighted_score'] <=> $a['weighted_score']);

        return $priorities;
    }

    /**
     * Determine early exit conditions.
     */
    public function shouldEarlyExit(array $methodScores, float $currentScore): bool
    {
        $earlyExitThreshold = $this->config->get('spam_detection.early_exit_threshold', 0.8);
        $minimumMethods = $this->config->get('spam_detection.minimum_methods_before_exit', 2);

        return $currentScore >= $earlyExitThreshold &&
               count($methodScores) >= $minimumMethods;
    }

    /**
     * Calculate performance score for method effectiveness.
     *
     * @param  array<string, array>  $methodMetrics  Performance metrics by method
     * @return array<string, float> Performance scores by method
     */
    public function calculatePerformanceScores(array $methodMetrics): array
    {
        $performanceScores = [];

        foreach ($methodMetrics as $method => $metrics) {
            $accuracy = $metrics['accuracy'] ?? 0.0;
            $speed = $metrics['avg_processing_time'] ?? 100.0;
            $reliability = $metrics['reliability'] ?? 0.0;

            // Normalize speed (lower is better)
            $speedScore = max(0.0, 1.0 - ($speed / 100.0));

            // Combine metrics
            $performanceScores[$method] = ($accuracy * 0.5) +
                                       ($speedScore * 0.3) +
                                       ($reliability * 0.2);
        }

        return $performanceScores;
    }

    /**
     * Get spam threshold from configuration.
     */
    protected function getSpamThreshold(): float
    {
        return $this->config->get('spam_detection.spam_threshold', 0.5);
    }

    /**
     * Get configured weights for detection methods.
     */
    protected function getConfiguredWeights(array $customWeights = []): array
    {
        $configWeights = $this->config->get('spam_detection.method_weights', []);

        return array_merge($this->defaultWeights, $configWeights, $customWeights);
    }

    /**
     * Get context adjustment rules.
     */
    protected function getContextAdjustmentRules(): array
    {
        return $this->config->get('spam_detection.context_adjustments', [
            'high_frequency_submission' => ['type' => 'add', 'value' => 0.2],
            'missing_user_agent' => ['type' => 'add', 'value' => 0.1],
            'tor_network' => ['type' => 'add', 'value' => 0.3],
            'vpn_detected' => ['type' => 'add', 'value' => 0.1],
            'authenticated_user' => ['type' => 'subtract', 'value' => 0.1],
            'repeated_content' => ['type' => 'add', 'value' => 0.3],
            'suspicious_timing' => ['type' => 'add', 'value' => 0.2],
        ]);
    }

    /**
     * Apply adjustment rule to a context factor.
     */
    protected function applyAdjustmentRule(array $rule, mixed $value): float
    {
        $ruleType = $rule['type'] ?? 'add';
        $ruleValue = $rule['value'] ?? 0.0;

        // For boolean factors
        if (is_bool($value)) {
            return $value ? $ruleValue : 0.0;
        }

        // For numeric factors
        if (is_numeric($value)) {
            $multiplier = min(1.0, (float) $value);

            return match ($ruleType) {
                'add' => $ruleValue * $multiplier,
                'subtract' => -($ruleValue * $multiplier),
                'multiply' => ($ruleValue - 1.0) * $multiplier,
                default => 0.0,
            };
        }

        return 0.0;
    }

    /**
     * Check if method can trigger early exit.
     */
    protected function canEarlyExit(string $method, float $score): bool
    {
        $earlyExitMethods = $this->config->get('spam_detection.early_exit_methods', [
            DetectionMethod::REGEX->value,
            DetectionMethod::PATTERN->value,
            DetectionMethod::RATE_LIMIT->value,
        ]);

        $earlyExitScore = $this->config->get('spam_detection.method_early_exit_threshold', 0.9);

        return in_array($method, $earlyExitMethods, true) && $score >= $earlyExitScore;
    }

    /**
     * Validate method scores for consistency.
     */
    public function validateScores(array $methodScores): array
    {
        $errors = [];

        foreach ($methodScores as $method => $score) {
            // Check valid method
            if (! DetectionMethod::tryFrom($method)) {
                $errors[] = "Invalid detection method: {$method}";

                continue;
            }

            // Check score range
            if ($score < 0.0 || $score > 1.0) {
                $errors[] = "Score out of range for {$method}: {$score}";
            }

            // Check for NaN or infinite values
            if (! is_finite($score)) {
                $errors[] = "Invalid score value for {$method}: {$score}";
            }
        }

        return $errors;
    }

    /**
     * Get scoring statistics and recommendations.
     */
    public function getScoringStatistics(array $recentResults): array
    {
        if (empty($recentResults)) {
            return [
                'total_analyses' => 0,
                'average_score' => 0.0,
                'spam_rate' => 0.0,
                'method_effectiveness' => [],
                'recommendations' => [],
            ];
        }

        $totalScore = 0.0;
        $spamCount = 0;
        $methodScores = [];

        foreach ($recentResults as $result) {
            if (! $result instanceof SpamDetectionResult) {
                continue;
            }

            $totalScore += $result->overallScore;

            if ($result->isSpam) {
                $spamCount++;
            }

            foreach ($result->methodScores as $method => $score) {
                if (! isset($methodScores[$method])) {
                    $methodScores[$method] = [];
                }
                $methodScores[$method][] = $score;
            }
        }

        $count = count($recentResults);
        $averageScore = $count > 0 ? $totalScore / $count : 0.0;
        $spamRate = $count > 0 ? $spamCount / $count : 0.0;

        // Calculate method effectiveness
        $methodEffectiveness = [];
        foreach ($methodScores as $method => $scores) {
            $methodEffectiveness[$method] = [
                'average_score' => array_sum($scores) / count($scores),
                'usage_count' => count($scores),
                'max_score' => max($scores),
                'min_score' => min($scores),
            ];
        }

        return [
            'total_analyses' => $count,
            'average_score' => round($averageScore, 3),
            'spam_rate' => round($spamRate, 3),
            'method_effectiveness' => $methodEffectiveness,
            'recommendations' => $this->generateScoringRecommendations($averageScore, $spamRate, $methodEffectiveness),
        ];
    }

    /**
     * Generate recommendations based on scoring statistics.
     */
    protected function generateScoringRecommendations(float $averageScore, float $spamRate, array $methodEffectiveness): array
    {
        $recommendations = [];

        if ($spamRate > 0.8) {
            $recommendations[] = 'High spam rate detected - consider strengthening detection methods';
        }

        if ($spamRate < 0.1) {
            $recommendations[] = 'Low spam rate - methods may be too aggressive';
        }

        if ($averageScore > 0.6) {
            $recommendations[] = 'High average scores - review detection sensitivity';
        }

        foreach ($methodEffectiveness as $method => $stats) {
            if ($stats['average_score'] > 0.8) {
                $recommendations[] = "Method {$method} showing high effectiveness";
            } elseif ($stats['average_score'] < 0.1) {
                $recommendations[] = "Method {$method} may need tuning";
            }
        }

        return $recommendations;
    }
}
