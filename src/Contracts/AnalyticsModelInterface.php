<?php

declare(strict_types=1);

/**
 * Contract File: AnalyticsModelInterface.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Contract interface for models that provide analytics capabilities
 * including date range filtering, aggregation, and reporting functionality.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Contracts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * AnalyticsModelInterface Contract
 *
 * Defines the interface for models that support analytics operations such as
 * date range filtering, aggregation queries, and reporting functionality.
 * This is implemented by models like BlockedSubmission and IpReputation.
 */
interface AnalyticsModelInterface extends ModelInterface
{
    /**
     * Scope query to filter by date range
     */
    public function scopeByDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder;

    /**
     * Scope query to filter recent records within specified hours
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder;

    /**
     * Get count of records for the specified date range
     */
    public static function getCountByDateRange(Carbon $startDate, Carbon $endDate): int;

    /**
     * Get aggregated data grouped by date
     *
     * @return array<string, mixed>
     */
    public static function getAggregatedByDate(Carbon $startDate, Carbon $endDate, string $groupBy = 'day'): array;

    /**
     * Get top records by specified field within date range
     *
     * @return array<string, mixed>
     */
    public static function getTopByField(string $field, Carbon $startDate, Carbon $endDate, int $limit = 10): array;

    /**
     * Get analytics summary for the specified period
     *
     * @return array<string, mixed>
     */
    public static function getAnalyticsSummary(Carbon $startDate, Carbon $endDate): array;
}
