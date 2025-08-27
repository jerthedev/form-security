<?php

declare(strict_types=1);

/**
 * Enum File: BlockReason.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-008-blocked-submissions-tracking
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: PHP 8.2+ enum for blocked submission reasons providing type safety
 * and consistent values across the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Data-Management-Analytics/SPEC-008-blocked-submissions-tracking.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Enums;

/**
 * BlockReason Enum
 *
 * Defines the possible reasons why a form submission was blocked.
 * Uses PHP 8.2+ enum features for type safety and better IDE support.
 */
enum BlockReason: string
{
    case SPAM_PATTERN = 'spam_pattern';
    case IP_REPUTATION = 'ip_reputation';
    case RATE_LIMIT = 'rate_limit';
    case GEOLOCATION = 'geolocation';
    case HONEYPOT = 'honeypot';
    case CUSTOM_RULE = 'custom_rule';

    /**
     * Get a human-readable description of the block reason
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::SPAM_PATTERN => 'Blocked due to spam pattern detection',
            self::IP_REPUTATION => 'Blocked due to poor IP reputation',
            self::RATE_LIMIT => 'Blocked due to rate limiting',
            self::GEOLOCATION => 'Blocked due to geographic restrictions',
            self::HONEYPOT => 'Blocked due to honeypot field detection',
            self::CUSTOM_RULE => 'Blocked due to custom rule violation',
        };
    }

    /**
     * Get the severity level of the block reason
     */
    public function getSeverity(): string
    {
        return match ($this) {
            self::SPAM_PATTERN => 'high',
            self::IP_REPUTATION => 'high',
            self::RATE_LIMIT => 'medium',
            self::GEOLOCATION => 'low',
            self::HONEYPOT => 'critical',
            self::CUSTOM_RULE => 'medium',
        };
    }

    /**
     * Get the default risk score for this block reason
     */
    public function getDefaultRiskScore(): int
    {
        return match ($this) {
            self::SPAM_PATTERN => 75,
            self::IP_REPUTATION => 80,
            self::RATE_LIMIT => 50,
            self::GEOLOCATION => 30,
            self::HONEYPOT => 95,
            self::CUSTOM_RULE => 60,
        };
    }

    /**
     * Check if this block reason indicates automated behavior
     */
    public function isAutomatedThreat(): bool
    {
        return match ($this) {
            self::SPAM_PATTERN, self::HONEYPOT, self::RATE_LIMIT => true,
            self::IP_REPUTATION, self::GEOLOCATION, self::CUSTOM_RULE => false,
        };
    }

    /**
     * Get all block reasons as an array
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value', 'name');
    }

    /**
     * Get all block reasons with descriptions
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
