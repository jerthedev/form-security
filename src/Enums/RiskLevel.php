<?php

declare(strict_types=1);

/**
 * Enum File: RiskLevel.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-008-blocked-submissions-tracking
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: PHP 8.2+ enum for risk levels providing type safety and consistent
 * risk assessment across the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Data-Management-Analytics/SPEC-008-blocked-submissions-tracking.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Enums;

/**
 * RiskLevel Enum
 *
 * Defines risk levels based on risk scores for consistent risk assessment.
 * Uses PHP 8.2+ enum features for type safety and better IDE support.
 */
enum RiskLevel: string
{
    case MINIMAL = 'minimal';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    /**
     * Get the minimum risk score for this level
     */
    public function getMinScore(): int
    {
        return match ($this) {
            self::MINIMAL => 0,
            self::LOW => 20,
            self::MEDIUM => 40,
            self::HIGH => 70,
            self::CRITICAL => 90,
        };
    }

    /**
     * Get the maximum risk score for this level
     */
    public function getMaxScore(): int
    {
        return match ($this) {
            self::MINIMAL => 19,
            self::LOW => 39,
            self::MEDIUM => 69,
            self::HIGH => 89,
            self::CRITICAL => 100,
        };
    }

    /**
     * Get the color code for UI display
     */
    public function getColorCode(): string
    {
        return match ($this) {
            self::MINIMAL => '#28a745',  // Green
            self::LOW => '#17a2b8',      // Info blue
            self::MEDIUM => '#ffc107',   // Warning yellow
            self::HIGH => '#fd7e14',     // Orange
            self::CRITICAL => '#dc3545', // Danger red
        };
    }

    /**
     * Get the priority level for processing
     */
    public function getPriority(): int
    {
        return match ($this) {
            self::MINIMAL => 1,
            self::LOW => 2,
            self::MEDIUM => 3,
            self::HIGH => 4,
            self::CRITICAL => 5,
        };
    }

    /**
     * Check if this risk level requires immediate action
     */
    public function requiresImmediateAction(): bool
    {
        return match ($this) {
            self::CRITICAL, self::HIGH => true,
            self::MEDIUM, self::LOW, self::MINIMAL => false,
        };
    }

    /**
     * Get risk level from score
     */
    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 90 => self::CRITICAL,
            $score >= 70 => self::HIGH,
            $score >= 40 => self::MEDIUM,
            $score >= 20 => self::LOW,
            default => self::MINIMAL,
        };
    }

    /**
     * Get all risk levels as an array
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value', 'name');
    }
}
