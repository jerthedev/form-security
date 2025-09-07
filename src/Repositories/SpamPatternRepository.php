<?php

declare(strict_types=1);

/**
 * Repository File: SpamPatternRepository.php
 *
 * EPIC: EPIC-002-core-spam-detection-engine
 * SPEC: SPEC-004-pattern-based-spam-detection
 * SPRINT: Sprint-007-Epic-002-Foundation-Setup
 * TICKET: 2011-spam-pattern-model-repository
 *
 * Description: Repository implementation for spam pattern data access with
 * comprehensive pattern management, caching integration, and performance optimization.
 *
 * @see docs/Planning/Epics/EPIC-002-core-spam-detection-engine.md
 * @see docs/Planning/Specs/Core-Detection-Engine/SPEC-004-pattern-based-spam-detection.md
 * @see docs/Planning/Tickets/Core-Spam-Detection-Engine/Implementation/2011-spam-pattern-model-repository.md
 */

namespace JTD\FormSecurity\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JTD\FormSecurity\Contracts\CacheManagerInterface;
use JTD\FormSecurity\Contracts\SpamPatternRepositoryContract;
use JTD\FormSecurity\Enums\PatternAction;
use JTD\FormSecurity\Enums\PatternType;
use JTD\FormSecurity\Models\SpamPattern;
use JTD\FormSecurity\ValueObjects\CacheKey;
use JTD\FormSecurity\ValueObjects\PatternEffectiveness;

/**
 * SpamPatternRepository
 *
 * Repository implementation providing comprehensive spam pattern data access
 * with caching integration and performance optimization.
 */
class SpamPatternRepository implements SpamPatternRepositoryContract
{
    private const CACHE_TTL = 3600; // 1 hour

    private const CACHE_TAG = 'spam_patterns';

    public function __construct(
        private CacheManagerInterface $cacheManager
    ) {}

    // Basic CRUD Operations

    public function find(int $id): ?SpamPattern
    {
        $cacheKey = new CacheKey("spam_pattern:{$id}", self::CACHE_TAG);

        return $this->cacheManager->remember($cacheKey, function () use ($id) {
            return SpamPattern::find($id);
        }, self::CACHE_TTL);
    }

    public function findByName(string $name): ?SpamPattern
    {
        $cacheKey = new CacheKey("spam_pattern_name:{$name}", self::CACHE_TAG);

        return $this->cacheManager->remember($cacheKey, function () use ($name) {
            return SpamPattern::where('name', $name)->first();
        }, self::CACHE_TTL);
    }

    public function getActivePatterns(): Collection
    {
        $cacheKey = new CacheKey('active_patterns', self::CACHE_TAG);

        return $this->cacheManager->remember($cacheKey, function () {
            return SpamPattern::active()
                ->orderBy('priority')
                ->orderBy('risk_score', 'desc')
                ->get();
        }, self::CACHE_TTL);
    }

    public function getPatternsByType(PatternType $type): Collection
    {
        $cacheKey = new CacheKey("patterns_type:{$type->value}", self::CACHE_TAG);

        return $this->cacheManager->remember($cacheKey, function () use ($type) {
            return SpamPattern::active()
                ->byType($type->value)
                ->orderBy('priority')
                ->get();
        }, self::CACHE_TTL);
    }

    public function getPatternsByAction(PatternAction $action): Collection
    {
        $cacheKey = new CacheKey("patterns_action:{$action->value}", self::CACHE_TAG);

        return $this->cacheManager->remember($cacheKey, function () use ($action) {
            return SpamPattern::active()
                ->byAction($action->value)
                ->orderBy('priority')
                ->get();
        }, self::CACHE_TTL);
    }

    public function create(array $data): SpamPattern
    {
        $pattern = SpamPattern::create($data);
        $this->invalidatePatternCache();

        Log::info('SpamPattern created', ['id' => $pattern->id, 'name' => $pattern->name]);

        return $pattern;
    }

    public function update(int $id, array $data): bool
    {
        $pattern = SpamPattern::find($id);
        if (! $pattern) {
            return false;
        }

        $result = $pattern->update($data);

        if ($result) {
            $this->invalidatePatternCache($id);
            Log::info('SpamPattern updated', ['id' => $id]);
        }

        return $result;
    }

    public function delete(int $id): bool
    {
        $pattern = SpamPattern::find($id);
        if (! $pattern) {
            return false;
        }

        $result = $pattern->delete();

        if ($result) {
            $this->invalidatePatternCache($id);
            Log::info('SpamPattern deleted', ['id' => $id]);
        }

        return $result;
    }

    // Pattern Management Operations

    public function getHighPriorityPatterns(int $minPriority = 8): Collection
    {
        $cacheKey = new CacheKey("high_priority_patterns:{$minPriority}", self::CACHE_TAG);

        return $this->cacheManager->remember($cacheKey, function () use ($minPriority) {
            return SpamPattern::active()
                ->where('priority', '>=', $minPriority)
                ->orderByPriority()
                ->get();
        }, self::CACHE_TTL);
    }

    public function getHighAccuracyPatterns(float $minAccuracy = 0.9): Collection
    {
        $cacheKey = new CacheKey("high_accuracy_patterns:{$minAccuracy}", self::CACHE_TAG);

        return $this->cacheManager->remember($cacheKey, function () use ($minAccuracy) {
            return SpamPattern::active()
                ->highAccuracy($minAccuracy)
                ->orderByAccuracy()
                ->get();
        }, self::CACHE_TTL);
    }

    public function getFastProcessingPatterns(int $maxMs = 10): Collection
    {
        $cacheKey = new CacheKey("fast_patterns:{$maxMs}", self::CACHE_TAG);

        return $this->cacheManager->remember($cacheKey, function () use ($maxMs) {
            return SpamPattern::active()
                ->fastProcessing($maxMs)
                ->orderBy('processing_time_ms')
                ->get();
        }, self::CACHE_TTL);
    }

    public function getPatternsForField(string $field): Collection
    {
        $cacheKey = new CacheKey("patterns_field:{$field}", self::CACHE_TAG);

        return $this->cacheManager->remember($cacheKey, function () use ($field) {
            return SpamPattern::active()
                ->byTargetField($field)
                ->orderByPriority()
                ->get();
        }, self::CACHE_TTL);
    }

    public function getPatternsForForm(string $formIdentifier): Collection
    {
        $cacheKey = new CacheKey("patterns_form:{$formIdentifier}", self::CACHE_TAG);

        return $this->cacheManager->remember($cacheKey, function () use ($formIdentifier) {
            return SpamPattern::active()
                ->byTargetForm($formIdentifier)
                ->orderByPriority()
                ->get();
        }, self::CACHE_TTL);
    }

    public function getPatternsByCategory(string $category): Collection
    {
        return SpamPattern::active()
            ->byCategory($category)
            ->orderByPriority()
            ->get();
    }

    public function getPatternsByLanguage(string $language): Collection
    {
        return SpamPattern::active()
            ->byLanguage($language)
            ->orderByPriority()
            ->get();
    }

    public function getPatternsByRegion(string $region): Collection
    {
        return SpamPattern::active()
            ->byRegion($region)
            ->orderByPriority()
            ->get();
    }

    // Performance and Analytics Operations

    public function getPatternEffectiveness(int $id): ?PatternEffectiveness
    {
        $pattern = $this->find($id);
        if (! $pattern) {
            return null;
        }

        return PatternEffectiveness::fromSpamPattern($pattern);
    }

    public function getPatternsNeedingOptimization(): Collection
    {
        return SpamPattern::active()
            ->where(function ($query) {
                $query->where('accuracy_rate', '<', 0.8)
                    ->orWhere('processing_time_ms', '>', 25)
                    ->orWhereRaw('false_positive_count / MAX(match_count, 1) > 0.2');
            })
            ->orderBy('accuracy_rate')
            ->get();
    }

    public function getRecentlyMatchedPatterns(int $hours = 24): Collection
    {
        return SpamPattern::active()
            ->recentlyMatched($hours)
            ->orderBy('last_matched', 'desc')
            ->get();
    }

    public function getPatternsByRiskRange(int $minScore, int $maxScore = 100): Collection
    {
        return SpamPattern::active()
            ->byRiskScore($minScore, $maxScore)
            ->orderBy('risk_score', 'desc')
            ->get();
    }

    public function updatePatternStatistics(int $id, bool $isFalsePositive = false, ?int $processingTime = null): bool
    {
        $pattern = SpamPattern::find($id);
        if (! $pattern) {
            return false;
        }

        $pattern->recordMatch($isFalsePositive, $processingTime);
        $this->invalidatePatternCache($id);

        return true;
    }

    public function resetPatternStatistics(int $id): bool
    {
        $pattern = SpamPattern::find($id);
        if (! $pattern) {
            return false;
        }

        $pattern->resetStatistics();
        $this->invalidatePatternCache($id);

        return true;
    }

    public function optimizePattern(int $id): bool
    {
        $pattern = SpamPattern::find($id);
        if (! $pattern) {
            return false;
        }

        $result = $pattern->optimizePattern();

        if ($result) {
            $this->invalidatePatternCache($id);
        }

        return $result;
    }

    // Bulk Operations

    public function activatePatterns(array $ids): int
    {
        $count = SpamPattern::whereIn('id', $ids)->update(['is_active' => true]);
        $this->invalidatePatternCache();

        Log::info('Bulk pattern activation', ['count' => $count, 'ids' => $ids]);

        return $count;
    }

    public function deactivatePatterns(array $ids): int
    {
        $count = SpamPattern::whereIn('id', $ids)->update(['is_active' => false]);
        $this->invalidatePatternCache();

        Log::info('Bulk pattern deactivation', ['count' => $count, 'ids' => $ids]);

        return $count;
    }

    public function updatePatternPriorities(array $priorityMap): int
    {
        $count = 0;

        DB::transaction(function () use ($priorityMap, &$count) {
            foreach ($priorityMap as $id => $priority) {
                if (SpamPattern::where('id', $id)->update(['priority' => $priority])) {
                    $count++;
                }
            }
        });

        $this->invalidatePatternCache();

        return $count;
    }

    public function getPatternsForBulkEvaluation(): Collection
    {
        $cacheKey = new CacheKey('bulk_evaluation_patterns', self::CACHE_TAG);

        return $this->cacheManager->remember($cacheKey, function () {
            return SpamPattern::optimizedBulkEvaluation()->get();
        }, self::CACHE_TTL);
    }

    // Import/Export Operations

    public function importPatterns(array $patterns): array
    {
        $results = [
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($patterns, &$results) {
            foreach ($patterns as $patternData) {
                try {
                    $existing = null;

                    if (isset($patternData['name'])) {
                        $existing = SpamPattern::where('name', $patternData['name'])->first();
                    }

                    if ($existing) {
                        $existing->update($patternData);
                        $results['updated']++;
                    } else {
                        SpamPattern::create($patternData);
                        $results['created']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'pattern' => $patternData['name'] ?? 'Unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        $this->invalidatePatternCache();

        Log::info('Pattern import completed', $results);

        return $results;
    }

    public function exportPatterns(?array $ids = null): array
    {
        $query = SpamPattern::query();

        if ($ids) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->toArray();
    }

    public function exportPatternsByCriteria(array $criteria): array
    {
        $query = SpamPattern::query();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->get()->toArray();
    }

    // Versioning and History Operations (Simplified implementation)

    public function createPatternVersion(int $id, ?string $reason = null): bool
    {
        // This would typically create a version in a pattern_versions table
        // For now, we'll just log the action
        Log::info('Pattern version created', ['id' => $id, 'reason' => $reason]);

        return true;
    }

    public function rollbackPattern(int $id, int $versionId): bool
    {
        // This would typically restore from a pattern_versions table
        // For now, we'll just log the action
        Log::info('Pattern rollback requested', ['id' => $id, 'version_id' => $versionId]);

        return false; // Not implemented yet
    }

    public function getPatternVersionHistory(int $id): Collection
    {
        // This would typically return from a pattern_versions table
        // For now, return empty collection
        return new Collection;
    }

    // Cache Management Operations

    public function warmPatternCache(): bool
    {
        try {
            // Pre-load frequently accessed patterns
            $this->getActivePatterns();
            $this->getHighPriorityPatterns();
            $this->getHighAccuracyPatterns();
            $this->getFastProcessingPatterns();

            Log::info('Pattern cache warmed successfully');

            return true;
        } catch (\Exception $e) {
            Log::error('Pattern cache warming failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function invalidatePatternCache(?int $id = null): bool
    {
        try {
            if ($id) {
                $this->cacheManager->forget(new CacheKey("spam_pattern:{$id}", self::CACHE_TAG));
            } else {
                $this->cacheManager->flush([self::CACHE_TAG]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Pattern cache invalidation failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getCachedPatternStatistics(): array
    {
        // This would typically return cache hit rates and statistics
        return [
            'cache_hits' => 0,
            'cache_misses' => 0,
            'hit_rate' => 0.0,
        ];
    }

    // Validation and Testing Operations

    public function validatePatternSyntax(string $pattern, PatternType $type): array
    {
        $errors = [];

        try {
            switch ($type) {
                case PatternType::REGEX:
                    if (@preg_match($pattern, '') === false) {
                        $errors[] = 'Invalid regular expression syntax';
                    }
                    break;

                case PatternType::KEYWORD:
                    if (empty(trim($pattern))) {
                        $errors[] = 'Keyword cannot be empty';
                    }
                    break;

                case PatternType::PHRASE:
                    if (empty(trim($pattern))) {
                        $errors[] = 'Phrase cannot be empty';
                    }
                    break;

                default:
                    // Other types may have specific validation rules
                    break;
            }
        } catch (\Exception $e) {
            $errors[] = 'Pattern validation error: '.$e->getMessage();
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function testPattern(int $id, string $content, array $context = []): array
    {
        $pattern = $this->find($id);
        if (! $pattern) {
            return ['error' => 'Pattern not found'];
        }

        return $pattern->testPattern($content, $context);
    }

    public function batchTestPatterns(array $patternIds, string $content, array $context = []): array
    {
        $results = [];

        foreach ($patternIds as $id) {
            $results[$id] = $this->testPattern($id, $content, $context);
        }

        return $results;
    }

    // Analytics and Reporting Operations

    public function getPatternPerformanceSummary(int $id): array
    {
        $pattern = $this->find($id);
        if (! $pattern) {
            return [];
        }

        return $pattern->getPerformanceSummary();
    }

    public function getPatternMatchingTrends(int $id, int $days = 30): array
    {
        $pattern = $this->find($id);
        if (! $pattern) {
            return [];
        }

        return $pattern->analyzeMatchingTrends($days);
    }

    public function getTopPerformingPatterns(int $limit = 10): Collection
    {
        return SpamPattern::active()
            ->highAccuracy(0.8)
            ->orderByAccuracy()
            ->limit($limit)
            ->get();
    }

    public function getPatternsRequiringAttention(): Collection
    {
        return SpamPattern::active()
            ->where(function ($query) {
                $query->where('accuracy_rate', '<', 0.7)
                    ->orWhere('processing_time_ms', '>', 50)
                    ->orWhere('match_count', 0);
            })
            ->orderBy('accuracy_rate')
            ->get();
    }

    public function generatePatternAnalyticsReport(array $options = []): array
    {
        $report = [
            'summary' => [
                'total_patterns' => SpamPattern::count(),
                'active_patterns' => SpamPattern::active()->count(),
                'high_accuracy_patterns' => SpamPattern::active()->highAccuracy()->count(),
                'fast_processing_patterns' => SpamPattern::active()->fastProcessing()->count(),
            ],
            'performance' => [
                'top_performers' => $this->getTopPerformingPatterns(5)->toArray(),
                'needs_attention' => $this->getPatternsRequiringAttention()->toArray(),
            ],
            'generated_at' => now()->toDateTimeString(),
        ];

        if (isset($options['include_trends'])) {
            $report['trends'] = [
                'recent_matches' => $this->getRecentlyMatchedPatterns(24)->count(),
            ];
        }

        return $report;
    }
}
