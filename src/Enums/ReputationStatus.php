<?php

declare(strict_types=1);

/**
 * Enum File: ReputationStatus.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: PHP 8.2+ enum for IP reputation status providing type safety
 * and consistent reputation classification across the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Enums;

/**
 * ReputationStatus Enum
 *
 * Defines the possible reputation statuses for IP addresses.
 * Uses PHP 8.2+ enum features for type safety and better IDE support.
 */
enum ReputationStatus: string
{
    case TRUSTED = 'trusted';
    case NEUTRAL = 'neutral';
    case SUSPICIOUS = 'suspicious';
    case MALICIOUS = 'malicious';
    case BLOCKED = 'blocked';

    /**
     * Get a human-readable description of the reputation status
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::TRUSTED => 'Trusted IP with good reputation',
            self::NEUTRAL => 'Neutral IP with no reputation data',
            self::SUSPICIOUS => 'Suspicious IP with concerning activity',
            self::MALICIOUS => 'Malicious IP with confirmed threats',
            self::BLOCKED => 'Blocked IP that should be denied access',
        };
    }

    /**
     * Get the color code for UI display
     */
    public function getColorCode(): string
    {
        return match ($this) {
            self::TRUSTED => '#28a745',    // Green
            self::NEUTRAL => '#6c757d',    // Gray
            self::SUSPICIOUS => '#ffc107', // Yellow
            self::MALICIOUS => '#fd7e14',  // Orange
            self::BLOCKED => '#dc3545',    // Red
        };
    }

    /**
     * Get the default reputation score range for this status
     */
    public function getScoreRange(): array
    {
        return match ($this) {
            self::TRUSTED => [80, 100],
            self::NEUTRAL => [40, 79],
            self::SUSPICIOUS => [20, 39],
            self::MALICIOUS => [5, 19],
            self::BLOCKED => [0, 4],
        };
    }

    /**
     * Check if this status allows access
     */
    public function allowsAccess(): bool
    {
        return match ($this) {
            self::TRUSTED, self::NEUTRAL => true,
            self::SUSPICIOUS, self::MALICIOUS, self::BLOCKED => false,
        };
    }

    /**
     * Check if this status requires monitoring
     */
    public function requiresMonitoring(): bool
    {
        return match ($this) {
            self::SUSPICIOUS, self::MALICIOUS => true,
            self::TRUSTED, self::NEUTRAL, self::BLOCKED => false,
        };
    }

    /**
     * Get reputation status from score
     */
    public static function fromScore(int|float $score): self
    {
        return match (true) {
            $score >= 80 => self::TRUSTED,
            $score >= 40 => self::NEUTRAL,
            $score >= 20 => self::SUSPICIOUS,
            $score >= 5 => self::MALICIOUS,
            default => self::BLOCKED,
        };
    }

    /**
     * Get all reputation statuses as an array
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value', 'name');
    }

    /**
     * Get all reputation statuses with descriptions
     *
     * @return array<string, string>
     */
    public static function toSelectArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->getDescription();
        }

        return $result;
    }
}
