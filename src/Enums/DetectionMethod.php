<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Enums;

/**
 * Detection method enumeration for spam detection analyzers.
 *
 * Defines the different types of spam detection methods used in the hybrid
 * detection algorithm with their weights and characteristics.
 */
enum DetectionMethod: string
{
    case BAYESIAN = 'bayesian';
    case REGEX = 'regex';
    case BEHAVIORAL = 'behavioral';
    case AI = 'ai';
    case KEYWORD = 'keyword';
    case PATTERN = 'pattern';
    case RATE_LIMIT = 'rate_limit';
    case IP_REPUTATION = 'ip_reputation';
    case CONTENT_ANALYSIS = 'content_analysis';
    case GEOLOCATION = 'geolocation';

    /**
     * Get the default weight for this detection method in the hybrid algorithm.
     */
    public function getDefaultWeight(): float
    {
        return match ($this) {
            self::BAYESIAN => 0.40,        // 40% - Primary statistical analysis
            self::REGEX => 0.30,           // 30% - Pattern matching
            self::BEHAVIORAL => 0.20,      // 20% - User behavior analysis
            self::AI => 0.10,              // 10% - AI-powered analysis
            self::KEYWORD => 0.15,         // Supplementary methods
            self::PATTERN => 0.25,
            self::RATE_LIMIT => 0.35,
            self::IP_REPUTATION => 0.20,
            self::CONTENT_ANALYSIS => 0.15,
            self::GEOLOCATION => 0.10,
        };
    }

    /**
     * Get human-readable description of the detection method.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::BAYESIAN => 'Bayesian statistical spam analysis',
            self::REGEX => 'Regular expression pattern matching',
            self::BEHAVIORAL => 'User behavior and submission analysis',
            self::AI => 'AI-powered content analysis',
            self::KEYWORD => 'Keyword-based detection',
            self::PATTERN => 'Advanced pattern matching',
            self::RATE_LIMIT => 'Submission rate limiting',
            self::IP_REPUTATION => 'IP address reputation checking',
            self::CONTENT_ANALYSIS => 'Content structure analysis',
            self::GEOLOCATION => 'Geographic location analysis',
        };
    }

    /**
     * Get the complexity level of this detection method.
     */
    public function getComplexity(): string
    {
        return match ($this) {
            self::BAYESIAN => 'high',
            self::AI => 'high',
            self::BEHAVIORAL => 'medium',
            self::REGEX => 'medium',
            self::PATTERN => 'medium',
            self::CONTENT_ANALYSIS => 'medium',
            self::KEYWORD => 'low',
            self::RATE_LIMIT => 'low',
            self::IP_REPUTATION => 'low',
            self::GEOLOCATION => 'low',
        };
    }

    /**
     * Get expected processing time category.
     */
    public function getProcessingTimeCategory(): string
    {
        return match ($this) {
            self::AI => 'slow',             // >50ms
            self::BAYESIAN => 'medium',     // 10-50ms
            self::BEHAVIORAL => 'medium',
            self::CONTENT_ANALYSIS => 'medium',
            self::REGEX => 'fast',          // <10ms
            self::PATTERN => 'fast',
            self::KEYWORD => 'fast',
            self::RATE_LIMIT => 'fast',
            self::IP_REPUTATION => 'fast',
            self::GEOLOCATION => 'fast',
        };
    }

    /**
     * Check if this method requires external services.
     */
    public function requiresExternalService(): bool
    {
        return match ($this) {
            self::AI => true,
            self::GEOLOCATION => true,
            self::IP_REPUTATION => true,
            default => false,
        };
    }

    /**
     * Check if this method can be cached.
     */
    public function isCacheable(): bool
    {
        return match ($this) {
            self::RATE_LIMIT => false,    // Time-sensitive
            self::BEHAVIORAL => false,    // Context-sensitive
            default => true,
        };
    }

    /**
     * Get primary detection methods for the hybrid algorithm.
     */
    public static function getPrimaryMethods(): array
    {
        return [
            self::BAYESIAN,
            self::REGEX,
            self::BEHAVIORAL,
            self::AI,
        ];
    }

    /**
     * Get all available detection methods.
     */
    public static function getAllMethods(): array
    {
        return array_map(fn ($case) => $case, self::cases());
    }

    /**
     * Get methods sorted by default weight (highest first).
     */
    public static function getMethodsByWeight(): array
    {
        $methods = self::getAllMethods();

        usort($methods, fn ($a, $b) => $b->getDefaultWeight() <=> $a->getDefaultWeight());

        return $methods;
    }

    /**
     * Get fast processing methods for early exit optimization.
     */
    public static function getFastMethods(): array
    {
        return array_filter(
            self::getAllMethods(),
            fn ($method) => $method->getProcessingTimeCategory() === 'fast'
        );
    }

    /**
     * Get methods that don't require external services.
     */
    public static function getLocalMethods(): array
    {
        return array_filter(
            self::getAllMethods(),
            fn ($method) => ! $method->requiresExternalService()
        );
    }

    /**
     * Get methods suitable for caching.
     */
    public static function getCacheableMethods(): array
    {
        return array_filter(
            self::getAllMethods(),
            fn ($method) => $method->isCacheable()
        );
    }
}
