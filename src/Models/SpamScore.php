<?php

declare(strict_types=1);

/**
 * Model File: SpamScore.php
 *
 * EPIC: EPIC-002-core-spam-detection-engine
 * SPEC: SPEC-004-pattern-based-spam-detection
 * SPRINT: Sprint-007-Epic-002-Foundation-Setup
 * TICKET: 2011-spam-pattern-model-repository
 *
 * Description: Eloquent model for tracking comprehensive spam scores and analytics
 * with detailed breakdowns, machine learning integration, and verification tracking.
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
use JTD\FormSecurity\Enums\PatternAction;
use JTD\FormSecurity\Enums\VerificationResult;

/**
 * SpamScore Model
 *
 * Tracks comprehensive spam detection scores with detailed breakdowns,
 * analytics, and machine learning integration.
 *
 * @property int $id
 * @property string $submission_hash
 * @property int|null $submission_id
 * @property int $total_score
 * @property array $component_scores
 * @property int $threshold_used
 * @property PatternAction $final_action
 * @property array|null $detection_methods
 * @property array|null $pattern_matches
 * @property array|null $risk_factors
 * @property string|null $detection_reason
 * @property int $processing_time_ms
 * @property int $patterns_checked
 * @property int $patterns_matched
 * @property float $confidence_level
 * @property string|null $form_identifier
 * @property string|null $ip_address
 * @property string|null $user_agent_hash
 * @property array|null $form_field_analysis
 * @property string|null $country_code
 * @property array|null $geolocation_factors
 * @property array|null $ip_reputation_factors
 * @property array|null $ai_analysis
 * @property float|null $ml_confidence
 * @property string|null $model_version
 * @property bool $is_training_data
 * @property bool $is_verified
 * @property VerificationResult|null $verification_result
 * @property string|null $verification_notes
 * @property Carbon $detected_at
 * @property Carbon|null $verified_at
 * @property string|null $verified_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SpamScore extends BaseModel
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'spam_scores';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'submission_hash',
        'submission_id',
        'total_score',
        'component_scores',
        'threshold_used',
        'final_action',
        'detection_methods',
        'pattern_matches',
        'risk_factors',
        'detection_reason',
        'processing_time_ms',
        'patterns_checked',
        'patterns_matched',
        'confidence_level',
        'form_identifier',
        'ip_address',
        'user_agent_hash',
        'form_field_analysis',
        'country_code',
        'geolocation_factors',
        'ip_reputation_factors',
        'ai_analysis',
        'ml_confidence',
        'model_version',
        'is_training_data',
        'is_verified',
        'verification_result',
        'verification_notes',
        'detected_at',
        'verified_at',
        'verified_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'component_scores' => 'array',
        'detection_methods' => 'array',
        'pattern_matches' => 'array',
        'risk_factors' => 'array',
        'form_field_analysis' => 'array',
        'geolocation_factors' => 'array',
        'ip_reputation_factors' => 'array',
        'ai_analysis' => 'array',
        'total_score' => 'integer',
        'threshold_used' => 'integer',
        'processing_time_ms' => 'integer',
        'patterns_checked' => 'integer',
        'patterns_matched' => 'integer',
        'confidence_level' => 'decimal:4',
        'ml_confidence' => 'decimal:4',
        'is_training_data' => 'boolean',
        'is_verified' => 'boolean',
        'final_action' => PatternAction::class,
        'verification_result' => VerificationResult::class,
        'detected_at' => 'datetime',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    /**
     * Get the blocked submission this score belongs to
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(BlockedSubmission::class, 'submission_id');
    }

    // Query Scopes

    /**
     * Query scope: High scores
     */
    public function scopeHighScore(Builder $query, int $threshold = 70): Builder
    {
        return $query->where('total_score', '>=', $threshold);
    }

    /**
     * Query scope: By final action
     */
    public function scopeByAction(Builder $query, PatternAction $action): Builder
    {
        return $query->where('final_action', $action);
    }

    /**
     * Query scope: Blocked submissions
     */
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->whereIn('final_action', [PatternAction::BLOCK, PatternAction::HONEYPOT]);
    }

    /**
     * Query scope: Flagged submissions
     */
    public function scopeFlagged(Builder $query): Builder
    {
        return $query->whereIn('final_action', [PatternAction::FLAG, PatternAction::CAPTCHA]);
    }

    /**
     * Query scope: High confidence scores
     */
    public function scopeHighConfidence(Builder $query, float $threshold = 0.8): Builder
    {
        return $query->where('confidence_level', '>=', $threshold);
    }

    /**
     * Query scope: Fast processing
     */
    public function scopeFastProcessing(Builder $query, int $maxMs = 50): Builder
    {
        return $query->where('processing_time_ms', '<=', $maxMs);
    }

    /**
     * Query scope: Recent scores
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('detected_at', '>=', now()->subHours($hours));
    }

    /**
     * Query scope: By form
     */
    public function scopeByForm(Builder $query, string $formIdentifier): Builder
    {
        return $query->where('form_identifier', $formIdentifier);
    }

    /**
     * Query scope: By country
     */
    public function scopeByCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Query scope: Verified scores
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    /**
     * Query scope: Training data
     */
    public function scopeTrainingData(Builder $query): Builder
    {
        return $query->where('is_training_data', true);
    }

    /**
     * Query scope: By verification result
     */
    public function scopeByVerificationResult(Builder $query, VerificationResult $result): Builder
    {
        return $query->where('verification_result', $result);
    }

    /**
     * Query scope: False positives
     */
    public function scopeFalsePositives(Builder $query): Builder
    {
        return $query->where('verification_result', VerificationResult::FALSE_POSITIVE);
    }

    /**
     * Query scope: False negatives
     */
    public function scopeFalseNegatives(Builder $query): Builder
    {
        return $query->where('verification_result', VerificationResult::FALSE_NEGATIVE);
    }

    // Business Logic Methods

    /**
     * Verify the score accuracy
     */
    public function verify(VerificationResult $result, ?string $notes = null, ?string $verifiedBy = null): bool
    {
        return $this->update([
            'is_verified' => true,
            'verification_result' => $result,
            'verification_notes' => $notes,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ]);
    }

    /**
     * Mark as training data
     */
    public function markAsTrainingData(): bool
    {
        return $this->update(['is_training_data' => true]);
    }

    /**
     * Get detection efficiency
     */
    public function getDetectionEfficiency(): float
    {
        return $this->patterns_checked > 0 ? $this->patterns_matched / $this->patterns_checked : 0.0;
    }

    /**
     * Get score breakdown
     */
    public function getScoreBreakdown(): array
    {
        return [
            'total_score' => $this->total_score,
            'threshold_used' => $this->threshold_used,
            'final_action' => $this->final_action->value,
            'component_scores' => $this->component_scores,
            'confidence_level' => $this->confidence_level,
            'detection_efficiency' => $this->getDetectionEfficiency(),
            'processing_time_ms' => $this->processing_time_ms,
            'patterns_checked' => $this->patterns_checked,
            'patterns_matched' => $this->patterns_matched,
        ];
    }

    /**
     * Check if score indicates spam
     */
    public function isSpam(): bool
    {
        return $this->total_score >= $this->threshold_used;
    }

    /**
     * Check if action prevents submission
     */
    public function preventsSubmission(): bool
    {
        return $this->final_action->preventsSubmission();
    }

    /**
     * Get risk level based on score
     */
    public function getRiskLevel(): string
    {
        return match (true) {
            $this->total_score >= 90 => 'critical',
            $this->total_score >= 70 => 'high',
            $this->total_score >= 50 => 'medium',
            $this->total_score >= 25 => 'low',
            default => 'minimal',
        };
    }

    /**
     * Get performance summary
     */
    public function getPerformanceSummary(): array
    {
        return [
            'total_score' => $this->total_score,
            'risk_level' => $this->getRiskLevel(),
            'final_action' => $this->final_action->getDescription(),
            'confidence_level' => $this->confidence_level,
            'processing_time_ms' => $this->processing_time_ms,
            'detection_efficiency' => round($this->getDetectionEfficiency(), 4),
            'is_verified' => $this->is_verified,
            'verification_result' => $this->verification_result?->value,
            'detected_at' => $this->detected_at->toDateTimeString(),
        ];
    }
}
