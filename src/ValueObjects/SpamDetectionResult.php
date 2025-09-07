<?php

declare(strict_types=1);

namespace JTD\FormSecurity\ValueObjects;

use Carbon\Carbon;
use JTD\FormSecurity\Enums\DetectionMethod;

/**
 * Spam detection result value object.
 *
 * Represents the complete result of spam detection analysis including
 * scores, threats, confidence levels, and detailed analysis data.
 */
readonly class SpamDetectionResult
{
    /**
     * Create a new spam detection result.
     *
     * @param  float  $overallScore  Overall spam score (0.0 to 1.0)
     * @param  array<string>  $threats  Array of detected threats
     * @param  array<string, float>  $methodScores  Scores by detection method
     * @param  array<string, mixed>  $details  Detailed analysis results
     * @param  bool  $isSpam  Whether content is classified as spam
     * @param  float  $confidence  Confidence level in the result (0.0 to 1.0)
     * @param  array<string, mixed>  $metadata  Additional metadata
     * @param  float  $processingTimeMs  Total processing time in milliseconds
     * @param  Carbon  $timestamp  When the analysis was performed
     * @param  string|null  $recommendation  Recommended action
     * @param  array<string, mixed>  $analyzedContent  Content that was analyzed
     * @param  array<string, mixed>  $context  Analysis context information
     */
    public function __construct(
        public float $overallScore,
        public array $threats,
        public array $methodScores,
        public array $details,
        public bool $isSpam,
        public float $confidence,
        public array $metadata = [],
        public float $processingTimeMs = 0.0,
        public Carbon $timestamp = new Carbon,
        public ?string $recommendation = null,
        public array $analyzedContent = [],
        public array $context = []
    ) {
        // Ensure timestamp is set
        if (! $this->timestamp) {
            $this->timestamp = now();
        }
    }

    /**
     * Create a spam result.
     */
    public static function spam(
        float $score,
        array $threats = [],
        array $methodScores = [],
        array $details = [],
        float $confidence = 1.0,
        array $metadata = []
    ): self {
        return new self(
            overallScore: $score,
            threats: $threats,
            methodScores: $methodScores,
            details: $details,
            isSpam: true,
            confidence: $confidence,
            metadata: $metadata,
            recommendation: 'block'
        );
    }

    /**
     * Create a clean (non-spam) result.
     */
    public static function clean(
        float $score = 0.0,
        array $methodScores = [],
        array $details = [],
        float $confidence = 1.0,
        array $metadata = []
    ): self {
        return new self(
            overallScore: $score,
            threats: [],
            methodScores: $methodScores,
            details: $details,
            isSpam: false,
            confidence: $confidence,
            metadata: $metadata,
            recommendation: 'allow'
        );
    }

    /**
     * Create a suspicious result requiring review.
     */
    public static function suspicious(
        float $score,
        array $threats = [],
        array $methodScores = [],
        array $details = [],
        float $confidence = 0.7,
        array $metadata = []
    ): self {
        return new self(
            overallScore: $score,
            threats: $threats,
            methodScores: $methodScores,
            details: $details,
            isSpam: false, // Not definitively spam
            confidence: $confidence,
            metadata: $metadata,
            recommendation: 'review'
        );
    }

    /**
     * Create a result from exception/error.
     */
    public static function error(
        string $error,
        array $metadata = [],
        float $processingTime = 0.0
    ): self {
        return new self(
            overallScore: 0.0,
            threats: ['analysis_error'],
            methodScores: [],
            details: ['error' => $error],
            isSpam: false,
            confidence: 0.0,
            metadata: array_merge($metadata, ['has_error' => true]),
            processingTimeMs: $processingTime,
            recommendation: 'allow_with_logging'
        );
    }

    /**
     * Get the highest scoring detection method.
     */
    public function getTopMethod(): ?DetectionMethod
    {
        if (empty($this->methodScores)) {
            return null;
        }

        $topMethodName = array_key_first(
            array_slice($this->methodScores, 0, 1, true)
        );

        return DetectionMethod::tryFrom($topMethodName);
    }

    /**
     * Get methods sorted by score (highest first).
     */
    public function getMethodsByScore(): array
    {
        $sorted = $this->methodScores;
        arsort($sorted);

        return $sorted;
    }

    /**
     * Get the primary threat category.
     */
    public function getPrimaryThreat(): ?string
    {
        return ! empty($this->threats) ? $this->threats[0] : null;
    }

    /**
     * Check if result has specific threat.
     */
    public function hasThreat(string $threat): bool
    {
        return in_array($threat, $this->threats, true);
    }

    /**
     * Check if result should be blocked.
     */
    public function shouldBlock(): bool
    {
        return $this->recommendation === 'block' ||
               ($this->isSpam && $this->confidence > 0.8);
    }

    /**
     * Check if result should be reviewed.
     */
    public function shouldReview(): bool
    {
        return $this->recommendation === 'review' ||
               ($this->overallScore > 0.4 && $this->confidence < 0.8);
    }

    /**
     * Check if processing was fast (<10ms).
     */
    public function isFastProcessing(): bool
    {
        return $this->processingTimeMs < 10.0;
    }

    /**
     * Check if result has high confidence.
     */
    public function hasHighConfidence(): bool
    {
        return $this->confidence >= 0.8;
    }

    /**
     * Get result severity level.
     */
    public function getSeverity(): string
    {
        return match (true) {
            $this->overallScore >= 0.8 => 'high',
            $this->overallScore >= 0.5 => 'medium',
            $this->overallScore >= 0.2 => 'low',
            default => 'minimal'
        };
    }

    /**
     * Get performance metrics.
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'processing_time_ms' => $this->processingTimeMs,
            'is_fast_processing' => $this->isFastProcessing(),
            'methods_analyzed' => count($this->methodScores),
            'threats_detected' => count($this->threats),
            'confidence_level' => $this->confidence,
            'timestamp' => $this->timestamp->toISOString(),
        ];
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'score' => round($this->overallScore, 3), // Tests expect 'score', not 'overall_score'
            'threats' => $this->threats,
            'details' => $this->details,
            'timestamp' => $this->timestamp->toISOString(),
            'method_scores' => array_map(
                fn ($score) => round($score, 3),
                $this->methodScores
            ),
            'is_spam' => $this->isSpam,
            'confidence' => round($this->confidence, 3),
            'metadata' => $this->metadata,
            'processing_time_ms' => round($this->processingTimeMs, 2),
            'recommendation' => $this->recommendation,
            'severity' => $this->getSeverity(),
            'top_method' => $this->getTopMethod()?->value,
            'primary_threat' => $this->getPrimaryThreat(),
        ];
    }

    /**
     * Get JSON representation.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES);
    }

    /**
     * Create result with updated score.
     */
    public function withScore(float $score): self
    {
        return new self(
            overallScore: $score,
            threats: $this->threats,
            methodScores: $this->methodScores,
            details: $this->details,
            isSpam: $score >= 0.5,
            confidence: $this->confidence,
            metadata: $this->metadata,
            processingTimeMs: $this->processingTimeMs,
            timestamp: $this->timestamp,
            recommendation: $this->recommendation,
            analyzedContent: $this->analyzedContent,
            context: $this->context
        );
    }

    /**
     * Create result with additional threat.
     */
    public function withThreat(string $threat): self
    {
        $threats = $this->threats;
        if (! in_array($threat, $threats, true)) {
            $threats[] = $threat;
        }

        return new self(
            overallScore: $this->overallScore,
            threats: $threats,
            methodScores: $this->methodScores,
            details: $this->details,
            isSpam: $this->isSpam,
            confidence: $this->confidence,
            metadata: $this->metadata,
            processingTimeMs: $this->processingTimeMs,
            timestamp: $this->timestamp,
            recommendation: $this->recommendation,
            analyzedContent: $this->analyzedContent,
            context: $this->context
        );
    }

    /**
     * Create result with updated processing time.
     */
    public function withProcessingTime(float $timeMs): self
    {
        return new self(
            overallScore: $this->overallScore,
            threats: $this->threats,
            methodScores: $this->methodScores,
            details: $this->details,
            isSpam: $this->isSpam,
            confidence: $this->confidence,
            metadata: $this->metadata,
            processingTimeMs: $timeMs,
            timestamp: $this->timestamp,
            recommendation: $this->recommendation,
            analyzedContent: $this->analyzedContent,
            context: $this->context
        );
    }

    /**
     * Merge with another result (useful for combining analyzer results).
     */
    public function mergeWith(self $other): self
    {
        return new self(
            overallScore: max($this->overallScore, $other->overallScore),
            threats: array_unique(array_merge($this->threats, $other->threats)),
            methodScores: array_merge($this->methodScores, $other->methodScores),
            details: array_merge($this->details, $other->details),
            isSpam: $this->isSpam || $other->isSpam,
            confidence: min($this->confidence, $other->confidence),
            metadata: array_merge($this->metadata, $other->metadata),
            processingTimeMs: $this->processingTimeMs + $other->processingTimeMs,
            timestamp: $this->timestamp->isAfter($other->timestamp) ? $this->timestamp : $other->timestamp,
            recommendation: $this->isSpam ? $this->recommendation : $other->recommendation,
            analyzedContent: array_merge($this->analyzedContent, $other->analyzedContent),
            context: array_merge($this->context, $other->context)
        );
    }
}
