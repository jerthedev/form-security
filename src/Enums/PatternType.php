<?php

declare(strict_types=1);

/**
 * Enum File: PatternType.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: PHP 8.2+ enum for spam pattern types providing type safety
 * and consistent pattern classification across the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Enums;

/**
 * PatternType Enum
 *
 * Defines the possible types of spam detection patterns.
 * Uses PHP 8.2+ enum features for type safety and better IDE support.
 */
enum PatternType: string
{
    case REGEX = 'regex';
    case KEYWORD = 'keyword';
    case PHRASE = 'phrase';
    case EMAIL_PATTERN = 'email_pattern';
    case URL_PATTERN = 'url_pattern';
    case BEHAVIORAL = 'behavioral';
    case CONTENT_LENGTH = 'content_length';
    case SUBMISSION_RATE = 'submission_rate';

    /**
     * Get a human-readable description of the pattern type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::REGEX => 'Regular expression pattern matching',
            self::KEYWORD => 'Single keyword detection',
            self::PHRASE => 'Multi-word phrase detection',
            self::EMAIL_PATTERN => 'Email address pattern analysis',
            self::URL_PATTERN => 'URL and link pattern detection',
            self::BEHAVIORAL => 'Behavioral analysis patterns',
            self::CONTENT_LENGTH => 'Content length-based detection',
            self::SUBMISSION_RATE => 'Submission rate analysis',
        };
    }

    /**
     * Get the complexity level of the pattern type
     */
    public function getComplexity(): string
    {
        return match ($this) {
            self::KEYWORD, self::CONTENT_LENGTH => 'low',
            self::PHRASE, self::EMAIL_PATTERN, self::URL_PATTERN => 'medium',
            self::REGEX, self::BEHAVIORAL, self::SUBMISSION_RATE => 'high',
        };
    }

    /**
     * Get the default processing priority
     */
    public function getDefaultPriority(): int
    {
        return match ($this) {
            self::BEHAVIORAL, self::SUBMISSION_RATE => 10, // Highest priority
            self::REGEX => 20,
            self::EMAIL_PATTERN, self::URL_PATTERN => 30,
            self::PHRASE => 40,
            self::KEYWORD => 50,
            self::CONTENT_LENGTH => 60, // Lowest priority
        };
    }

    /**
     * Check if this pattern type requires preprocessing
     */
    public function requiresPreprocessing(): bool
    {
        return match ($this) {
            self::REGEX, self::BEHAVIORAL, self::SUBMISSION_RATE => true,
            self::KEYWORD, self::PHRASE, self::EMAIL_PATTERN, self::URL_PATTERN, self::CONTENT_LENGTH => false,
        };
    }

    /**
     * Get the expected performance impact
     */
    public function getPerformanceImpact(): string
    {
        return match ($this) {
            self::KEYWORD, self::CONTENT_LENGTH => 'minimal',
            self::PHRASE, self::EMAIL_PATTERN, self::URL_PATTERN => 'low',
            self::REGEX => 'medium',
            self::BEHAVIORAL, self::SUBMISSION_RATE => 'high',
        };
    }

    /**
     * Get all pattern types as an array
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value', 'name');
    }

    /**
     * Get all pattern types with descriptions
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
