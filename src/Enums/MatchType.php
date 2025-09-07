<?php

declare(strict_types=1);

/**
 * Enum File: MatchType.php
 *
 * EPIC: EPIC-002-core-spam-detection-engine
 * SPEC: SPEC-004-pattern-based-spam-detection
 * SPRINT: Sprint-007-Epic-002-Foundation-Setup
 * TICKET: 2011-spam-pattern-model-repository
 *
 * Description: PHP 8.2+ enum for pattern match types providing type safety
 * and consistent match classification across the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-002-core-spam-detection-engine.md
 * @see docs/Planning/Specs/Core-Detection-Engine/SPEC-004-pattern-based-spam-detection.md
 * @see docs/Planning/Tickets/Core-Spam-Detection-Engine/Implementation/2011-spam-pattern-model-repository.md
 */

namespace JTD\FormSecurity\Enums;

/**
 * MatchType Enum
 *
 * Defines the possible types of pattern matches.
 * Uses PHP 8.2+ enum features for type safety and better IDE support.
 */
enum MatchType: string
{
    case EXACT = 'exact';
    case PARTIAL = 'partial';
    case FUZZY = 'fuzzy';
    case REGEX = 'regex';
    case KEYWORD = 'keyword';
    case BEHAVIORAL = 'behavioral';
    case THRESHOLD = 'threshold';

    /**
     * Get a human-readable description of the match type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::EXACT => 'Exact string match',
            self::PARTIAL => 'Partial string match',
            self::FUZZY => 'Fuzzy/similarity match',
            self::REGEX => 'Regular expression match',
            self::KEYWORD => 'Keyword-based match',
            self::BEHAVIORAL => 'Behavioral pattern match',
            self::THRESHOLD => 'Threshold-based match',
        };
    }

    /**
     * Get the confidence level for this match type
     */
    public function getDefaultConfidence(): float
    {
        return match ($this) {
            self::EXACT => 1.0,
            self::REGEX => 0.95,
            self::KEYWORD => 0.9,
            self::THRESHOLD => 0.85,
            self::PARTIAL => 0.8,
            self::BEHAVIORAL => 0.75,
            self::FUZZY => 0.7,
        };
    }

    /**
     * Check if this match type is high precision
     */
    public function isHighPrecision(): bool
    {
        return match ($this) {
            self::EXACT, self::REGEX, self::KEYWORD => true,
            self::PARTIAL, self::FUZZY, self::BEHAVIORAL, self::THRESHOLD => false,
        };
    }

    /**
     * Get all match types as an array
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value', 'name');
    }

    /**
     * Get all match types with descriptions
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
