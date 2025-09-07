<?php

declare(strict_types=1);

/**
 * Contract File: SpamPatternRepositoryContract.php
 *
 * EPIC: EPIC-002-core-spam-detection-engine
 * SPEC: SPEC-004-pattern-based-spam-detection
 * SPRINT: Sprint-007-Epic-002-Foundation-Setup
 * TICKET: 2011-spam-pattern-model-repository
 *
 * Description: Repository contract interface for spam pattern data access providing
 * standardized methods for pattern management, caching, and performance optimization.
 *
 * @see docs/Planning/Epics/EPIC-002-core-spam-detection-engine.md
 * @see docs/Planning/Specs/Core-Detection-Engine/SPEC-004-pattern-based-spam-detection.md
 * @see docs/Planning/Tickets/Core-Spam-Detection-Engine/Implementation/2011-spam-pattern-model-repository.md
 */

namespace JTD\FormSecurity\Contracts;

use Illuminate\Database\Eloquent\Collection;
use JTD\FormSecurity\Enums\PatternAction;
use JTD\FormSecurity\Enums\PatternType;
use JTD\FormSecurity\Models\SpamPattern;
use JTD\FormSecurity\ValueObjects\PatternEffectiveness;

/**
 * SpamPatternRepositoryContract
 *
 * Defines the contract for spam pattern data access operations including
 * pattern management, caching integration, and performance optimization.
 */
interface SpamPatternRepositoryContract
{
    // Basic CRUD Operations

    /**
     * Find a pattern by ID with caching
     */
    public function find(int $id): ?SpamPattern;

    /**
     * Find a pattern by name
     */
    public function findByName(string $name): ?SpamPattern;

    /**
     * Get all active patterns with caching
     */
    public function getActivePatterns(): Collection;

    /**
     * Get patterns by type
     */
    public function getPatternsByType(PatternType $type): Collection;

    /**
     * Get patterns by action
     */
    public function getPatternsByAction(PatternAction $action): Collection;

    /**
     * Create a new pattern
     */
    public function create(array $data): SpamPattern;

    /**
     * Update an existing pattern
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a pattern
     */
    public function delete(int $id): bool;

    // Pattern Management Operations

    /**
     * Get high priority patterns for execution
     */
    public function getHighPriorityPatterns(int $minPriority = 8): Collection;

    /**
     * Get high accuracy patterns
     */
    public function getHighAccuracyPatterns(float $minAccuracy = 0.9): Collection;

    /**
     * Get fast processing patterns
     */
    public function getFastProcessingPatterns(int $maxMs = 10): Collection;

    /**
     * Get patterns for form field
     */
    public function getPatternsForField(string $field): Collection;

    /**
     * Get patterns for form identifier
     */
    public function getPatternsForForm(string $formIdentifier): Collection;

    /**
     * Get patterns by category
     */
    public function getPatternsByCategory(string $category): Collection;

    /**
     * Get patterns by language
     */
    public function getPatternsByLanguage(string $language): Collection;

    /**
     * Get patterns by region
     */
    public function getPatternsByRegion(string $region): Collection;

    // Performance and Analytics Operations

    /**
     * Get pattern effectiveness metrics
     */
    public function getPatternEffectiveness(int $id): ?PatternEffectiveness;

    /**
     * Get patterns needing optimization
     */
    public function getPatternsNeedingOptimization(): Collection;

    /**
     * Get recently matched patterns
     */
    public function getRecentlyMatchedPatterns(int $hours = 24): Collection;

    /**
     * Get patterns by risk score range
     */
    public function getPatternsByRiskRange(int $minScore, int $maxScore = 100): Collection;

    /**
     * Update pattern statistics
     */
    public function updatePatternStatistics(int $id, bool $isFalsePositive = false, ?int $processingTime = null): bool;

    /**
     * Reset pattern statistics
     */
    public function resetPatternStatistics(int $id): bool;

    /**
     * Optimize pattern based on performance metrics
     */
    public function optimizePattern(int $id): bool;

    // Bulk Operations

    /**
     * Bulk activate patterns
     */
    public function activatePatterns(array $ids): int;

    /**
     * Bulk deactivate patterns
     */
    public function deactivatePatterns(array $ids): int;

    /**
     * Bulk update pattern priorities
     */
    public function updatePatternPriorities(array $priorityMap): int;

    /**
     * Get patterns for bulk evaluation (optimized query)
     */
    public function getPatternsForBulkEvaluation(): Collection;

    // Import/Export Operations

    /**
     * Import patterns from array
     */
    public function importPatterns(array $patterns): array;

    /**
     * Export patterns to array
     */
    public function exportPatterns(?array $ids = null): array;

    /**
     * Export patterns by criteria
     */
    public function exportPatternsByCriteria(array $criteria): array;

    // Versioning and History Operations

    /**
     * Create pattern version backup
     */
    public function createPatternVersion(int $id, ?string $reason = null): bool;

    /**
     * Rollback pattern to previous version
     */
    public function rollbackPattern(int $id, int $versionId): bool;

    /**
     * Get pattern version history
     */
    public function getPatternVersionHistory(int $id): Collection;

    // Cache Management Operations

    /**
     * Warm pattern cache
     */
    public function warmPatternCache(): bool;

    /**
     * Invalidate pattern cache
     */
    public function invalidatePatternCache(?int $id = null): bool;

    /**
     * Get cached pattern statistics
     */
    public function getCachedPatternStatistics(): array;

    // Validation and Testing Operations

    /**
     * Validate pattern syntax
     */
    public function validatePatternSyntax(string $pattern, PatternType $type): array;

    /**
     * Test pattern against content
     */
    public function testPattern(int $id, string $content, array $context = []): array;

    /**
     * Batch test patterns
     */
    public function batchTestPatterns(array $patternIds, string $content, array $context = []): array;

    // Analytics and Reporting Operations

    /**
     * Get pattern performance summary
     */
    public function getPatternPerformanceSummary(int $id): array;

    /**
     * Get pattern matching trends
     */
    public function getPatternMatchingTrends(int $id, int $days = 30): array;

    /**
     * Get top performing patterns
     */
    public function getTopPerformingPatterns(int $limit = 10): Collection;

    /**
     * Get patterns requiring attention
     */
    public function getPatternsRequiringAttention(): Collection;

    /**
     * Generate pattern analytics report
     */
    public function generatePatternAnalyticsReport(array $options = []): array;
}
