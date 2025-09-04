<?php

declare(strict_types=1);

/**
 * Enum File: PatternAction.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: PHP 8.2+ enum for spam pattern actions providing type safety
 * and consistent action handling across the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Enums;

/**
 * PatternAction Enum
 *
 * Defines the possible actions to take when a spam pattern is matched.
 * Uses PHP 8.2+ enum features for type safety and better IDE support.
 */
enum PatternAction: string
{
    case BLOCK = 'block';
    case FLAG = 'flag';
    case SCORE_ONLY = 'score_only';
    case HONEYPOT = 'honeypot';
    case DELAY = 'delay';
    case CAPTCHA = 'captcha';

    /**
     * Get a human-readable description of the action
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::BLOCK => 'Block the submission immediately',
            self::FLAG => 'Flag for manual review',
            self::SCORE_ONLY => 'Add to risk score only',
            self::HONEYPOT => 'Trigger honeypot detection',
            self::DELAY => 'Add processing delay',
            self::CAPTCHA => 'Require CAPTCHA verification',
        };
    }

    /**
     * Get the severity level of the action
     */
    public function getSeverity(): string
    {
        return match ($this) {
            self::BLOCK => 'critical',
            self::HONEYPOT => 'high',
            self::CAPTCHA => 'medium',
            self::FLAG, self::DELAY => 'low',
            self::SCORE_ONLY => 'minimal',
        };
    }

    /**
     * Check if this action prevents submission
     */
    public function preventsSubmission(): bool
    {
        return match ($this) {
            self::BLOCK, self::HONEYPOT => true,
            self::FLAG, self::SCORE_ONLY, self::DELAY, self::CAPTCHA => false,
        };
    }

    /**
     * Check if this action requires user interaction
     */
    public function requiresUserInteraction(): bool
    {
        return match ($this) {
            self::CAPTCHA => true,
            self::BLOCK, self::FLAG, self::SCORE_ONLY, self::HONEYPOT, self::DELAY => false,
        };
    }

    /**
     * Get the default risk score contribution
     */
    public function getDefaultRiskScore(): int
    {
        return match ($this) {
            self::BLOCK => 90,
            self::HONEYPOT => 95,
            self::CAPTCHA => 60,
            self::FLAG => 40,
            self::DELAY => 30,
            self::SCORE_ONLY => 20,
        };
    }

    /**
     * Get all actions as an array
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value', 'name');
    }

    /**
     * Get all actions with descriptions
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
