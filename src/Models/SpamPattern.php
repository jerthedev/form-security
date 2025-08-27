<?php

declare(strict_types=1);

/**
 * Model File: SpamPattern.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Eloquent model for spam pattern management with configurable detection
 * rules, accuracy tracking, and performance optimization for the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace JTD\FormSecurity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

/**
 * SpamPattern Model
 *
 * Manages configurable spam detection patterns with accuracy tracking, performance
 * monitoring, and flexible pattern matching for comprehensive form security.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $pattern_type
 * @property string $pattern
 * @property array|null $pattern_config
 * @property bool $case_sensitive
 * @property bool $whole_word_only
 * @property array|null $target_fields
 * @property array|null $target_forms
 * @property string $scope
 * @property int $risk_score
 * @property string $action
 * @property array|null $action_config
 * @property int $match_count
 * @property int $false_positive_count
 * @property float $accuracy_rate
 * @property int $processing_time_ms
 * @property bool $is_active
 * @property bool $is_learning
 * @property int $priority
 * @property Carbon|null $last_matched
 * @property array|null $categories
 * @property array|null $languages
 * @property array|null $regions
 * @property string $version
 * @property string $source
 * @property Carbon|null $last_updated_at
 * @property string|null $updated_by
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SpamPattern extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'spam_patterns';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'pattern_type',
        'pattern',
        'pattern_config',
        'case_sensitive',
        'whole_word_only',
        'target_fields',
        'target_forms',
        'scope',
        'risk_score',
        'action',
        'action_config',
        'match_count',
        'false_positive_count',
        'accuracy_rate',
        'processing_time_ms',
        'is_active',
        'is_learning',
        'priority',
        'last_matched',
        'categories',
        'languages',
        'regions',
        'version',
        'source',
        'last_updated_at',
        'updated_by',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'pattern_config' => 'array',
        'target_fields' => 'array',
        'target_forms' => 'array',
        'action_config' => 'array',
        'categories' => 'array',
        'languages' => 'array',
        'regions' => 'array',
        'metadata' => 'array',
        'case_sensitive' => 'boolean',
        'whole_word_only' => 'boolean',
        'is_active' => 'boolean',
        'is_learning' => 'boolean',
        'risk_score' => 'integer',
        'match_count' => 'integer',
        'false_positive_count' => 'integer',
        'processing_time_ms' => 'integer',
        'priority' => 'integer',
        'accuracy_rate' => 'decimal:4',
        'last_matched' => 'datetime',
        'last_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Query scope: Active patterns only
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Query scope: Filter by pattern type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('pattern_type', $type);
    }

    /**
     * Query scope: Filter by action
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Query scope: Filter by scope
     */
    public function scopeByScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope', $scope);
    }

    /**
     * Query scope: High priority patterns
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->where('priority', '>=', 8);
    }

    /**
     * Query scope: High accuracy patterns
     */
    public function scopeHighAccuracy(Builder $query, float $minAccuracy = 0.9): Builder
    {
        return $query->where('accuracy_rate', '>=', $minAccuracy);
    }

    /**
     * Query scope: Recently matched patterns
     */
    public function scopeRecentlyMatched(Builder $query, int $hours = 24): Builder
    {
        return $query->where('last_matched', '>=', now()->subHours($hours));
    }

    /**
     * Query scope: Learning patterns
     */
    public function scopeLearning(Builder $query): Builder
    {
        return $query->where('is_learning', true);
    }

    /**
     * Query scope: Filter by risk score range
     */
    public function scopeByRiskScore(Builder $query, int $minScore, int $maxScore = 100): Builder
    {
        return $query->whereBetween('risk_score', [$minScore, $maxScore]);
    }

    /**
     * Query scope: Fast processing patterns
     */
    public function scopeFastProcessing(Builder $query, int $maxMs = 10): Builder
    {
        return $query->where('processing_time_ms', '<=', $maxMs);
    }

    /**
     * Query scope: Order by priority
     */
    public function scopeOrderByPriority(Builder $query): Builder
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Query scope: Order by accuracy
     */
    public function scopeOrderByAccuracy(Builder $query): Builder
    {
        return $query->orderBy('accuracy_rate', 'desc');
    }

    /**
     * Check if pattern is high accuracy
     */
    public function isHighAccuracy(): bool
    {
        return $this->accuracy_rate >= 0.9;
    }

    /**
     * Check if pattern is high priority
     */
    public function isHighPriority(): bool
    {
        return $this->priority >= 8;
    }

    /**
     * Check if pattern is fast processing
     */
    public function isFastProcessing(): bool
    {
        return $this->processing_time_ms <= 10;
    }

    /**
     * Check if pattern has been recently matched
     */
    public function isRecentlyMatched(int $hours = 24): bool
    {
        return $this->last_matched && $this->last_matched->isAfter(now()->subHours($hours));
    }

    /**
     * Get the effectiveness score (accuracy weighted by usage)
     */
    public function getEffectivenessScore(): float
    {
        if ($this->match_count === 0) {
            return 0.0;
        }

        $usageWeight = min($this->match_count / 100, 1.0); // Cap at 100 matches for full weight
        return $this->accuracy_rate * $usageWeight;
    }

    /**
     * Update match statistics
     */
    public function recordMatch(bool $isFalsePositive = false, int $processingTimeMs = null): void
    {
        $this->increment('match_count');
        
        if ($isFalsePositive) {
            $this->increment('false_positive_count');
        }

        $this->last_matched = now();

        // Update accuracy rate
        $totalMatches = $this->match_count;
        $truePositives = $totalMatches - $this->false_positive_count;
        $this->accuracy_rate = $totalMatches > 0 ? $truePositives / $totalMatches : 1.0;

        // Update processing time (rolling average)
        if ($processingTimeMs !== null) {
            $this->processing_time_ms = (int) (($this->processing_time_ms + $processingTimeMs) / 2);
        }

        $this->save();
    }

    /**
     * Reset statistics
     */
    public function resetStatistics(): void
    {
        $this->update([
            'match_count' => 0,
            'false_positive_count' => 0,
            'accuracy_rate' => 1.0,
            'processing_time_ms' => 0,
            'last_matched' => null,
        ]);
    }

    /**
     * Get pattern performance summary
     */
    public function getPerformanceSummary(): array
    {
        return [
            'matches' => $this->match_count,
            'false_positives' => $this->false_positive_count,
            'accuracy' => $this->accuracy_rate,
            'effectiveness' => $this->getEffectivenessScore(),
            'avg_processing_time' => $this->processing_time_ms,
            'last_matched' => $this->last_matched?->toDateTimeString(),
            'is_high_performance' => $this->isHighAccuracy() && $this->isFastProcessing(),
        ];
    }
}
