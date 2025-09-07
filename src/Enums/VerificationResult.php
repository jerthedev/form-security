<?php

declare(strict_types=1);

/**
 * Enum File: VerificationResult.php
 *
 * EPIC: EPIC-002-core-spam-detection-engine
 * SPEC: SPEC-004-pattern-based-spam-detection
 * SPRINT: Sprint-007-Epic-002-Foundation-Setup
 * TICKET: 2011-spam-pattern-model-repository
 *
 * Description: PHP 8.2+ enum for spam score verification results providing type safety
 * and consistent verification classification across the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-002-core-spam-detection-engine.md
 * @see docs/Planning/Specs/Core-Detection-Engine/SPEC-004-pattern-based-spam-detection.md
 * @see docs/Planning/Tickets/Core-Spam-Detection-Engine/Implementation/2011-spam-pattern-model-repository.md
 */

namespace JTD\FormSecurity\Enums;

/**
 * VerificationResult Enum
 *
 * Defines the possible outcomes of human verification for spam scores.
 * Uses PHP 8.2+ enum features for type safety and better IDE support.
 */
enum VerificationResult: string
{
    case CORRECT_POSITIVE = 'correct_positive';
    case FALSE_POSITIVE = 'false_positive';
    case CORRECT_NEGATIVE = 'correct_negative';
    case FALSE_NEGATIVE = 'false_negative';
    case UNKNOWN = 'unknown';

    /**
     * Get a human-readable description of the verification result
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::CORRECT_POSITIVE => 'Correctly identified as spam',
            self::FALSE_POSITIVE => 'Incorrectly identified as spam (false positive)',
            self::CORRECT_NEGATIVE => 'Correctly identified as not spam',
            self::FALSE_NEGATIVE => 'Incorrectly identified as not spam (false negative)',
            self::UNKNOWN => 'Verification status unknown',
        };
    }

    /**
     * Check if this is a correct classification
     */
    public function isCorrect(): bool
    {
        return match ($this) {
            self::CORRECT_POSITIVE, self::CORRECT_NEGATIVE => true,
            self::FALSE_POSITIVE, self::FALSE_NEGATIVE, self::UNKNOWN => false,
        };
    }

    /**
     * Check if this is an error (false positive or negative)
     */
    public function isError(): bool
    {
        return match ($this) {
            self::FALSE_POSITIVE, self::FALSE_NEGATIVE => true,
            self::CORRECT_POSITIVE, self::CORRECT_NEGATIVE, self::UNKNOWN => false,
        };
    }

    /**
     * Check if this represents a positive classification
     */
    public function isPositive(): bool
    {
        return match ($this) {
            self::CORRECT_POSITIVE, self::FALSE_POSITIVE => true,
            self::CORRECT_NEGATIVE, self::FALSE_NEGATIVE, self::UNKNOWN => false,
        };
    }

    /**
     * Check if this represents a negative classification
     */
    public function isNegative(): bool
    {
        return match ($this) {
            self::CORRECT_NEGATIVE, self::FALSE_NEGATIVE => true,
            self::CORRECT_POSITIVE, self::FALSE_POSITIVE, self::UNKNOWN => false,
        };
    }

    /**
     * Get the accuracy impact of this result
     */
    public function getAccuracyImpact(): float
    {
        return match ($this) {
            self::CORRECT_POSITIVE, self::CORRECT_NEGATIVE => 1.0,
            self::FALSE_POSITIVE, self::FALSE_NEGATIVE => -1.0,
            self::UNKNOWN => 0.0,
        };
    }

    /**
     * Get the severity level of errors
     */
    public function getErrorSeverity(): string
    {
        return match ($this) {
            self::FALSE_POSITIVE => 'medium', // Annoying but not dangerous
            self::FALSE_NEGATIVE => 'high',   // Dangerous - spam got through
            self::CORRECT_POSITIVE, self::CORRECT_NEGATIVE, self::UNKNOWN => 'none',
        };
    }

    /**
     * Get all verification results as an array
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value', 'name');
    }

    /**
     * Get all verification results with descriptions
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

    /**
     * Get error results only
     *
     * @return array<self>
     */
    public static function getErrors(): array
    {
        return [self::FALSE_POSITIVE, self::FALSE_NEGATIVE];
    }

    /**
     * Get correct results only
     *
     * @return array<self>
     */
    public static function getCorrect(): array
    {
        return [self::CORRECT_POSITIVE, self::CORRECT_NEGATIVE];
    }
}
