<?php

declare(strict_types=1);

/**
 * Model File: SpamPattern.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Eloquent model for spam pattern management with configurable detection
 * rules, accuracy tracking, and performance optimization for the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use JTD\FormSecurity\Contracts\CacheableModelInterface;
use JTD\FormSecurity\Enums\PatternAction;
use JTD\FormSecurity\Enums\PatternType;

/**
 * SpamPattern Model
 *
 * Manages configurable spam detection patterns with accuracy tracking, performance
 * monitoring, and flexible pattern matching for comprehensive form security.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property PatternType $pattern_type
 * @property string $pattern
 * @property array|null $pattern_config
 * @property bool $case_sensitive
 * @property bool $whole_word_only
 * @property array|null $target_fields
 * @property array|null $target_forms
 * @property string $scope
 * @property int $risk_score
 * @property PatternAction $action
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
class SpamPattern extends BaseModel implements CacheableModelInterface
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return new \Database\Factories\SpamPatternFactory;
    }

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
        'pattern_type' => PatternType::class,
        'action' => PatternAction::class,
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
     * Query scope: Filter by pattern types (multiple)
     */
    public function scopeByTypes(Builder $query, array $types): Builder
    {
        return $query->whereIn('pattern_type', $types);
    }

    /**
     * Query scope: Filter by actions (multiple)
     */
    public function scopeByActions(Builder $query, array $actions): Builder
    {
        return $query->whereIn('action', $actions);
    }

    /**
     * Query scope: Filter by target field
     */
    public function scopeByTargetField(Builder $query, string $field): Builder
    {
        return $query->whereJsonContains('target_fields', $field);
    }

    /**
     * Query scope: Filter by target form
     */
    public function scopeByTargetForm(Builder $query, string $form): Builder
    {
        return $query->whereJsonContains('target_forms', $form);
    }

    /**
     * Query scope: Filter by category
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->whereJsonContains('categories', $category);
    }

    /**
     * Query scope: Filter by language
     */
    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->whereJsonContains('languages', $language);
    }

    /**
     * Query scope: Filter by region
     */
    public function scopeByRegion(Builder $query, string $region): Builder
    {
        return $query->whereJsonContains('regions', $region);
    }

    /**
     * Query scope: Filter patterns that prevent submission
     */
    public function scopeBlockingPatterns(Builder $query): Builder
    {
        return $query->whereIn('action', ['block', 'honeypot']);
    }

    /**
     * Query scope: Filter patterns for review/flagging
     */
    public function scopeReviewPatterns(Builder $query): Builder
    {
        return $query->whereIn('action', ['flag', 'captcha']);
    }

    /**
     * Query scope: Filter by minimum match count
     */
    public function scopeMinMatches(Builder $query, int $minMatches): Builder
    {
        return $query->where('match_count', '>=', $minMatches);
    }

    /**
     * Query scope: Filter by maximum false positive rate
     */
    public function scopeMaxFalsePositives(Builder $query, int $maxFalsePositives): Builder
    {
        return $query->where('false_positive_count', '<=', $maxFalsePositives);
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
    public function recordMatch(bool $isFalsePositive = false, ?int $processingTimeMs = null): void
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

    /**
     * Get pattern type description
     */
    public function getPatternTypeDescription(): string
    {
        return $this->pattern_type->getDescription();
    }

    /**
     * Get action description
     */
    public function getActionDescription(): string
    {
        return $this->action->getDescription();
    }

    /**
     * Check if pattern prevents submission
     */
    public function preventsSubmission(): bool
    {
        return $this->action->preventsSubmission();
    }

    /**
     * Get pattern complexity level
     */
    public function getComplexity(): string
    {
        return $this->pattern_type->getComplexity();
    }

    // CacheableModelInterface Implementation

    /**
     * Generate a unique cache key for this model instance
     */
    public function getCacheKey(): string
    {
        return "spam_pattern:{$this->id}";
    }

    /**
     * Generate a cache key for a specific lookup value
     */
    public static function getCacheKeyFor(string $identifier): string
    {
        return "spam_pattern:{$identifier}";
    }

    /**
     * Get the cache expiration time for this model
     */
    public function getCacheExpiration(): ?Carbon
    {
        // Patterns don't have explicit expiration, use updated_at + TTL
        return $this->updated_at?->addSeconds(static::getDefaultCacheTtl());
    }

    /**
     * Check if the cached data has expired
     */
    public function isCacheExpired(): bool
    {
        $expiration = $this->getCacheExpiration();

        return $expiration && $expiration->isPast();
    }

    /**
     * Refresh the cache expiration time
     */
    public function refreshCacheExpiration(): bool
    {
        // For patterns, we just update the updated_at timestamp
        return $this->touch();
    }

    /**
     * Invalidate the cache for this model instance
     */
    public function invalidateCache(): bool
    {
        Cache::forget($this->getCacheKey());
        Cache::forget('active_patterns');
        Cache::forget('patterns_by_type');

        return true;
    }

    /**
     * Get cached data or retrieve from database
     */
    public static function getCached(string $identifier): ?static
    {
        $cacheKey = static::getCacheKeyFor($identifier);

        return Cache::remember($cacheKey, static::getDefaultCacheTtl(), function () use ($identifier) {
            return static::find($identifier);
        });
    }

    /**
     * Store model data in cache
     */
    public function storeInCache(): bool
    {
        Cache::put($this->getCacheKey(), $this, static::getDefaultCacheTtl());

        return true;
    }

    /**
     * Remove model data from cache
     */
    public function removeFromCache(): bool
    {
        return Cache::forget($this->getCacheKey());
    }

    /**
     * Get the default cache TTL in seconds
     */
    public static function getDefaultCacheTtl(): int
    {
        return 7200; // 2 hours
    }

    /**
     * Get all active patterns with caching
     */
    public static function getActivePatternsCached(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember('active_patterns', static::getDefaultCacheTtl(), function () {
            return static::active()
                ->orderBy('priority')
                ->orderBy('risk_score', 'desc')
                ->get();
        });
    }

    /**
     * Get patterns by type with caching
     */
    public static function getPatternsByTypeCached(PatternType $type): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "patterns_by_type:{$type->value}";

        return Cache::remember($cacheKey, static::getDefaultCacheTtl(), function () use ($type) {
            return static::active()
                ->where('pattern_type', $type->value)
                ->orderBy('priority')
                ->get();
        });
    }

    /**
     * Optimized scope for pattern selection using covering index
     */
    public function scopeOptimizedSelection(Builder $query): Builder
    {
        return $query->select([
            'is_active',
            'priority',
            'pattern_type',
            'scope',
            'risk_score',
            'pattern',
            'action',
        ])
            ->where('is_active', true)
            ->useIndex('idx_spam_patterns_selection_covering')
            ->orderBy('priority');
    }

    /**
     * Optimized scope for performance monitoring
     */
    public function scopeOptimizedPerformance(Builder $query): Builder
    {
        return $query->select([
            'pattern_type',
            'is_active',
            'accuracy_rate',
            'processing_time_ms',
            'match_count',
            'priority',
        ])
            ->where('is_active', true)
            ->useIndex('idx_spam_patterns_performance_covering')
            ->orderBy('accuracy_rate', 'desc');
    }

    /**
     * Optimized scope for pattern execution order
     */
    public function scopeOptimizedExecutionOrder(Builder $query): Builder
    {
        return $query->select([
            'is_active',
            'priority',
            'processing_time_ms',
            'pattern_type',
            'pattern',
            'action',
            'risk_score',
        ])
            ->where('is_active', true)
            ->useIndex('idx_spam_patterns_execution_order')
            ->orderBy('priority')
            ->orderBy('processing_time_ms');
    }

    /**
     * Optimized scope for recent activity analysis
     */
    public function scopeOptimizedRecentActivity(Builder $query, int $hours = 24): Builder
    {
        return $query->select([
            'last_matched',
            'is_active',
            'priority',
            'pattern_type',
            'match_count',
        ])
            ->where('last_matched', '>=', now()->subHours($hours))
            ->where('is_active', true)
            ->useIndex('idx_spam_patterns_recent_activity')
            ->orderBy('last_matched', 'desc');
    }

    /**
     * Optimized scope for risk-based filtering
     */
    public function scopeOptimizedByRisk(Builder $query, int $minRiskScore = 50): Builder
    {
        return $query->select([
            'risk_score',
            'action',
            'is_active',
            'pattern_type',
            'pattern',
            'priority',
        ])
            ->where('risk_score', '>=', $minRiskScore)
            ->where('is_active', true)
            ->useIndex('idx_spam_patterns_risk_action_active')
            ->orderBy('risk_score', 'desc');
    }

    /**
     * Optimized scope for bulk pattern evaluation
     */
    public function scopeOptimizedBulkEvaluation(Builder $query): Builder
    {
        return $query->select([
            'id',
            'pattern',
            'pattern_type',
            'risk_score',
            'action',
            'case_sensitive',
            'whole_word_only',
        ])
            ->where('is_active', true)
            ->orderBy('priority');
    }

    // Advanced Business Logic Methods

    /**
     * Optimize pattern performance based on accuracy and speed
     */
    public function optimizePattern(): bool
    {
        // Calculate effectiveness score
        $effectiveness = $this->getEffectivenessScore();

        // Adjust priority based on performance
        if ($effectiveness > 0.9 && $this->processing_time_ms < 5) {
            $this->priority = min(10, $this->priority + 1);
        } elseif ($effectiveness < 0.5 || $this->processing_time_ms > 50) {
            $this->priority = max(1, $this->priority - 1);
        }

        // Disable patterns with consistently poor performance
        if ($this->accuracy_rate < 0.3 && $this->match_count > 100) {
            $this->is_active = false;
        }

        // Enable learning mode for patterns with moderate performance
        if ($effectiveness >= 0.5 && $effectiveness < 0.8) {
            $this->is_learning = true;
        } elseif ($effectiveness >= 0.9) {
            $this->is_learning = false;
        }

        return $this->save();
    }

    /**
     * Analyze pattern matching trends
     */
    public function analyzeMatchingTrends(int $days = 30): array
    {
        // This would typically query a pattern_matches table
        // For now, we'll simulate based on available data

        $recentMatches = $this->match_count; // Simplified
        $recentFalsePositives = $this->false_positive_count;

        $trends = [
            'total_matches' => $recentMatches,
            'false_positives' => $recentFalsePositives,
            'accuracy_trend' => 'stable',
            'volume_trend' => 'stable',
            'effectiveness_score' => $this->getEffectivenessScore(),
            'performance_category' => $this->getPerformanceCategory(),
            'recommendations' => [],
        ];

        // Analyze trends (simplified logic)
        if ($this->accuracy_rate > 0.9) {
            $trends['accuracy_trend'] = 'improving';
        } elseif ($this->accuracy_rate < 0.5) {
            $trends['accuracy_trend'] = 'declining';
        }

        // Generate recommendations
        $trends['recommendations'] = $this->generateOptimizationRecommendations($trends);

        return $trends;
    }

    /**
     * Get performance category
     */
    public function getPerformanceCategory(): string
    {
        $effectiveness = $this->getEffectivenessScore();
        $speed = $this->processing_time_ms;

        return match (true) {
            $effectiveness > 0.9 && $speed < 5 => 'excellent',
            $effectiveness > 0.8 && $speed < 10 => 'good',
            $effectiveness > 0.6 && $speed < 25 => 'acceptable',
            $effectiveness > 0.4 => 'poor',
            default => 'critical',
        };
    }

    /**
     * Generate optimization recommendations
     */
    private function generateOptimizationRecommendations(array $trends): array
    {
        $recommendations = [];

        if ($trends['effectiveness_score'] < 0.5) {
            $recommendations[] = 'Consider disabling or rewriting this pattern';
            $recommendations[] = 'Review pattern logic and test cases';
        }

        if ($this->processing_time_ms > 25) {
            $recommendations[] = 'Optimize pattern for better performance';
            $recommendations[] = 'Consider simplifying regex or logic';
        }

        if ($this->false_positive_count > $this->match_count * 0.2) {
            $recommendations[] = 'Reduce false positive rate';
            $recommendations[] = 'Add more specific matching criteria';
        }

        if ($this->match_count < 10 && $this->getAgeInDays() > 30) {
            $recommendations[] = 'Pattern may be too specific or outdated';
            $recommendations[] = 'Consider broadening matching criteria';
        }

        if ($trends['performance_category'] === 'excellent') {
            $recommendations[] = 'Consider increasing priority';
            $recommendations[] = 'Use as template for similar patterns';
        }

        return $recommendations;
    }

    /**
     * Test pattern against sample content
     */
    public function testPattern(string $content, array $context = []): array
    {
        $startTime = microtime(true);
        $matches = false;
        $matchDetails = [];

        try {
            switch ($this->pattern_type) {
                case PatternType::REGEX:
                    $flags = $this->case_sensitive ? '' : 'i';
                    $matches = preg_match("/{$this->pattern}/{$flags}", $content, $matchDetails);
                    break;

                case PatternType::KEYWORD:
                    $searchContent = $this->case_sensitive ? $content : strtolower($content);
                    $searchPattern = $this->case_sensitive ? $this->pattern : strtolower($this->pattern);

                    if ($this->whole_word_only) {
                        $matches = preg_match("/\b".preg_quote($searchPattern, '/')."\b/", $searchContent);
                    } else {
                        $matches = strpos($searchContent, $searchPattern) !== false;
                    }
                    break;

                case PatternType::PHRASE:
                    $searchContent = $this->case_sensitive ? $content : strtolower($content);
                    $searchPattern = $this->case_sensitive ? $this->pattern : strtolower($this->pattern);
                    $matches = strpos($searchContent, $searchPattern) !== false;
                    break;

                case PatternType::EMAIL_PATTERN:
                    $matches = preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $content);
                    if ($matches && $this->pattern) {
                        $matches = preg_match("/{$this->pattern}/i", $content);
                    }
                    break;

                case PatternType::URL_PATTERN:
                    $matches = preg_match('/https?:\/\/[^\s]+/', $content);
                    if ($matches && $this->pattern) {
                        $matches = preg_match("/{$this->pattern}/i", $content);
                    }
                    break;

                case PatternType::CONTENT_LENGTH:
                    $length = strlen($content);
                    $config = $this->pattern_config ?? [];
                    $minLength = $config['min_length'] ?? 0;
                    $maxLength = $config['max_length'] ?? PHP_INT_MAX;
                    $matches = $length >= $minLength && $length <= $maxLength;
                    break;

                default:
                    $matches = false;
            }
        } catch (\Exception $e) {
            $matches = false;
            $matchDetails = ['error' => $e->getMessage()];
        }

        $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        return [
            'matches' => (bool) $matches,
            'processing_time_ms' => round($processingTime, 2),
            'match_details' => $matchDetails,
            'pattern_type' => $this->pattern_type->value,
            'pattern' => $this->pattern,
            'risk_score' => $matches ? $this->risk_score : 0,
            'action' => $matches ? $this->action->value : null,
            'action_description' => $matches ? $this->action->getDescription() : null,
        ];
    }
}
