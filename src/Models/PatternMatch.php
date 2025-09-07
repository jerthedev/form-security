<?php

declare(strict_types=1);

/**
 * Model File: PatternMatch.php
 *
 * EPIC: EPIC-002-core-spam-detection-engine
 * SPEC: SPEC-004-pattern-based-spam-detection
 * SPRINT: Sprint-007-Epic-002-Foundation-Setup
 * TICKET: 2011-spam-pattern-model-repository
 *
 * Description: Eloquent model for tracking individual pattern matches in form submissions
 * with detailed analytics, performance metrics, and relationship management.
 *
 * @see docs/Planning/Epics/EPIC-002-core-spam-detection-engine.md
 * @see docs/Planning/Specs/Core-Detection-Engine/SPEC-004-pattern-based-spam-detection.md
 * @see docs/Planning/Tickets/Core-Spam-Detection-Engine/Implementation/2011-spam-pattern-model-repository.md
 */

namespace JTD\FormSecurity\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JTD\FormSecurity\Enums\MatchType;

/**
 * PatternMatch Model
 *
 * Tracks individual pattern matches in form submissions with detailed analytics,
 * performance metrics, and debugging information.
 *
 * @property int $id
 * @property int $submission_id
 * @property int $pattern_id
 * @property int $match_score
 * @property float $confidence_level
 * @property array|null $match_context
 * @property string|null $matched_content
 * @property array|null $match_positions
 * @property int $processing_time_ms
 * @property MatchType $match_type
 * @property array|null $debug_info
 * @property bool $is_false_positive
 * @property string|null $false_positive_reason
 * @property Carbon $matched_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PatternMatch extends BaseModel
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'pattern_matches';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'submission_id',
        'pattern_id',
        'match_score',
        'confidence_level',
        'match_context',
        'matched_content',
        'match_positions',
        'processing_time_ms',
        'match_type',
        'debug_info',
        'is_false_positive',
        'false_positive_reason',
        'matched_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'match_context' => 'array',
        'match_positions' => 'array',
        'debug_info' => 'array',
        'match_score' => 'integer',
        'processing_time_ms' => 'integer',
        'confidence_level' => 'decimal:4',
        'is_false_positive' => 'boolean',
        'match_type' => MatchType::class,
        'matched_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    /**
     * Get the blocked submission this match belongs to
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(BlockedSubmission::class, 'submission_id');
    }

    /**
     * Get the spam pattern that was matched
     */
    public function pattern(): BelongsTo
    {
        return $this->belongsTo(SpamPattern::class, 'pattern_id');
    }

    // Query Scopes

    /**
     * Query scope: High confidence matches
     */
    public function scopeHighConfidence(Builder $query, float $threshold = 0.8): Builder
    {
        return $query->where('confidence_level', '>=', $threshold);
    }

    /**
     * Query scope: High score matches
     */
    public function scopeHighScore(Builder $query, int $threshold = 70): Builder
    {
        return $query->where('match_score', '>=', $threshold);
    }

    /**
     * Query scope: Fast processing matches
     */
    public function scopeFastProcessing(Builder $query, int $maxMs = 10): Builder
    {
        return $query->where('processing_time_ms', '<=', $maxMs);
    }

    /**
     * Query scope: False positives
     */
    public function scopeFalsePositives(Builder $query): Builder
    {
        return $query->where('is_false_positive', true);
    }

    /**
     * Query scope: Verified matches (not false positives)
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_false_positive', false);
    }

    /**
     * Query scope: By match type
     */
    public function scopeByType(Builder $query, MatchType $type): Builder
    {
        return $query->where('match_type', $type);
    }

    /**
     * Query scope: Recent matches
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('matched_at', '>=', now()->subHours($hours));
    }

    /**
     * Query scope: By pattern
     */
    public function scopeForPattern(Builder $query, int $patternId): Builder
    {
        return $query->where('pattern_id', $patternId);
    }

    /**
     * Query scope: By submission
     */
    public function scopeForSubmission(Builder $query, int $submissionId): Builder
    {
        return $query->where('submission_id', $submissionId);
    }

    // Business Logic Methods

    /**
     * Mark as false positive
     */
    public function markAsFalsePositive(?string $reason = null): bool
    {
        return $this->update([
            'is_false_positive' => true,
            'false_positive_reason' => $reason,
        ]);
    }

    /**
     * Clear false positive flag
     */
    public function clearFalsePositiveFlag(): bool
    {
        return $this->update([
            'is_false_positive' => false,
            'false_positive_reason' => null,
        ]);
    }

    /**
     * Check if match is high quality (high confidence and score)
     */
    public function isHighQuality(float $confidenceThreshold = 0.8, int $scoreThreshold = 70): bool
    {
        return $this->confidence_level >= $confidenceThreshold && $this->match_score >= $scoreThreshold;
    }

    /**
     * Check if processing was fast
     */
    public function isFastProcessing(int $threshold = 10): bool
    {
        return $this->processing_time_ms <= $threshold;
    }

    /**
     * Get match quality grade
     */
    public function getQualityGrade(): string
    {
        $score = ($this->confidence_level * 0.6) + (($this->match_score / 100) * 0.4);

        return match (true) {
            $score >= 0.9 => 'A',
            $score >= 0.8 => 'B',
            $score >= 0.7 => 'C',
            $score >= 0.6 => 'D',
            default => 'F',
        };
    }

    /**
     * Get match summary
     */
    public function getSummary(): array
    {
        return [
            'pattern_name' => $this->pattern->name ?? 'Unknown',
            'pattern_type' => $this->pattern->pattern_type->value ?? 'unknown',
            'match_score' => $this->match_score,
            'confidence_level' => $this->confidence_level,
            'match_type' => $this->match_type->value,
            'processing_time_ms' => $this->processing_time_ms,
            'quality_grade' => $this->getQualityGrade(),
            'is_false_positive' => $this->is_false_positive,
            'matched_at' => $this->matched_at->toDateTimeString(),
        ];
    }
}
