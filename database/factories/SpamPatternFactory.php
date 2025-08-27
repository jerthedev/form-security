<?php

declare(strict_types=1);

/**
 * Factory File: SpamPatternFactory.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Factory for generating realistic SpamPattern test data
 * following Laravel 12 factory patterns with pattern matching data.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JTD\FormSecurity\Enums\PatternAction;
use JTD\FormSecurity\Enums\PatternType;
use JTD\FormSecurity\Models\SpamPattern;

/**
 * SpamPatternFactory Class
 *
 * Generates realistic test data for SpamPattern models with proper
 * pattern definitions, performance metrics, and configuration data.
 */
class SpamPatternFactory extends Factory
{
    /**
     * The name of the factory's corresponding model
     */
    protected $model = SpamPattern::class;

    /**
     * Common spam keywords for pattern generation
     */
    private array $spamKeywords = [
        'viagra', 'cialis', 'casino', 'lottery', 'winner', 'congratulations',
        'free money', 'click here', 'buy now', 'limited time', 'act now',
        'guaranteed', 'risk free', 'no obligation', 'call now',
    ];

    /**
     * Common regex patterns for spam detection
     */
    private array $regexPatterns = [
        '/\b(viagra|cialis|levitra)\b/i',
        '/\b(casino|gambling|poker)\b/i',
        '/\b(lottery|winner|congratulations)\b/i',
        '/https?:\/\/[^\s]+\.(tk|ml|ga|cf)/i',
        '/\b\d{10,}\b/', // Long numbers (phone/credit card)
        '/[A-Z]{5,}/', // Excessive caps
        '/(.)\1{4,}/', // Repeated characters
    ];

    /**
     * Common form fields to target
     */
    private array $targetFields = [
        'message', 'comment', 'description', 'content', 'body',
        'email', 'name', 'subject', 'title', 'url', 'website',
    ];

    /**
     * Define the model's default state
     */
    public function definition(): array
    {
        $patternType = $this->faker->randomElement(PatternType::cases());
        $matchCount = $this->faker->numberBetween(0, 1000);
        $falsePositiveCount = $this->faker->numberBetween(0, (int) ($matchCount * 0.2));
        $accuracyRate = $matchCount > 0 ? ($matchCount - $falsePositiveCount) / $matchCount : 1.0;

        return [
            'name' => $this->generatePatternName($patternType),
            'description' => $this->generatePatternDescription($patternType),
            'pattern_type' => $patternType->value,
            'pattern' => $this->generatePattern($patternType),
            'pattern_config' => $this->generatePatternConfig($patternType),
            'case_sensitive' => $this->faker->boolean(30), // 30% case sensitive
            'whole_word_only' => $this->faker->boolean(40), // 40% whole word only
            'target_fields' => $this->faker->randomElements($this->targetFields, $this->faker->numberBetween(1, 3)),
            'target_forms' => $this->generateTargetForms(),
            'scope' => $this->faker->randomElement(['global', 'form_specific', 'field_specific']),
            'risk_score' => $this->faker->numberBetween(10, 90),
            'action' => $this->faker->randomElement(PatternAction::cases())->value,
            'action_config' => $this->generateActionConfig(),
            'categories' => $this->generateCategories(),
            'languages' => $this->generateLanguages(),
            'regions' => $this->generateRegions(),
            'is_active' => $this->faker->boolean(85), // 85% active
            'is_learning' => $this->faker->boolean(20), // 20% in learning mode
            'priority' => $this->faker->numberBetween(1, 10),
            'match_count' => $matchCount,
            'false_positive_count' => $falsePositiveCount,
            'accuracy_rate' => round($accuracyRate, 4),
            'processing_time_ms' => $this->faker->numberBetween(1, 50),
            'last_matched' => $matchCount > 0 ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'last_updated_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'metadata' => $this->generateMetadata(),
            'created_at' => $this->faker->dateTimeBetween('-1 year', '-1 month'),
            'updated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Generate pattern name based on type
     */
    private function generatePatternName(PatternType $type): string
    {
        return match ($type) {
            PatternType::REGEX => 'Regex: '.$this->faker->words(3, true),
            PatternType::KEYWORD => 'Keyword: '.$this->faker->word(),
            PatternType::PHRASE => 'Phrase: '.$this->faker->words(2, true),
            PatternType::EMAIL_PATTERN => 'Email Pattern: '.$this->faker->words(2, true),
            PatternType::URL_PATTERN => 'URL Pattern: '.$this->faker->words(2, true),
            PatternType::BEHAVIORAL => 'Behavioral: '.$this->faker->words(2, true),
            PatternType::CONTENT_LENGTH => 'Content Length: '.$this->faker->words(2, true),
            PatternType::SUBMISSION_RATE => 'Submission Rate: '.$this->faker->words(2, true),
        };
    }

    /**
     * Generate pattern description
     */
    private function generatePatternDescription(PatternType $type): string
    {
        return match ($type) {
            PatternType::REGEX => 'Regular expression pattern for detecting spam content',
            PatternType::KEYWORD => 'Single keyword detection for spam filtering',
            PatternType::PHRASE => 'Multi-word phrase detection for spam content',
            PatternType::EMAIL_PATTERN => 'Email address pattern analysis for spam detection',
            PatternType::URL_PATTERN => 'URL and link pattern detection for spam filtering',
            PatternType::BEHAVIORAL => 'Behavioral analysis pattern for detecting automated submissions',
            PatternType::CONTENT_LENGTH => 'Content length-based detection for spam filtering',
            PatternType::SUBMISSION_RATE => 'Submission rate analysis for detecting spam bursts',
        };
    }

    /**
     * Generate pattern based on type
     */
    private function generatePattern(PatternType $type): string
    {
        return match ($type) {
            PatternType::REGEX => $this->faker->randomElement($this->regexPatterns),
            PatternType::KEYWORD => $this->faker->randomElement($this->spamKeywords),
            PatternType::PHRASE => $this->faker->randomElements($this->spamKeywords, 2, false)[0].' '.
                                   $this->faker->randomElements($this->spamKeywords, 2, false)[1],
            PatternType::EMAIL_PATTERN => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
            PatternType::URL_PATTERN => '/https?:\/\/[^\s]+/',
            PatternType::BEHAVIORAL => 'rapid_submission_pattern',
            PatternType::CONTENT_LENGTH => 'length_analysis',
            PatternType::SUBMISSION_RATE => 'rate_analysis',
        };
    }

    /**
     * Generate pattern configuration
     */
    private function generatePatternConfig(PatternType $type): array
    {
        return match ($type) {
            PatternType::CONTENT_LENGTH => [
                'min_length' => $this->faker->numberBetween(0, 10),
                'max_length' => $this->faker->numberBetween(1000, 10000),
            ],
            PatternType::SUBMISSION_RATE => [
                'max_submissions' => $this->faker->numberBetween(5, 50),
                'time_window_minutes' => $this->faker->numberBetween(1, 60),
            ],
            PatternType::BEHAVIORAL => [
                'min_form_time_seconds' => $this->faker->numberBetween(1, 10),
                'max_form_time_seconds' => $this->faker->numberBetween(300, 3600),
            ],
            default => [],
        };
    }

    /**
     * Generate action configuration
     */
    private function generateActionConfig(): array
    {
        return [
            'delay_seconds' => $this->faker->numberBetween(1, 10),
            'redirect_url' => $this->faker->optional(0.3)->url(),
            'custom_message' => $this->faker->optional(0.5)->sentence(),
        ];
    }

    /**
     * Generate categories
     */
    private function generateCategories(): array
    {
        $categories = ['spam', 'malware', 'phishing', 'advertising', 'adult', 'gambling'];

        return $this->faker->randomElements($categories, $this->faker->numberBetween(1, 3));
    }

    /**
     * Generate languages
     */
    private function generateLanguages(): array
    {
        $languages = ['en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'zh', 'ja', 'ko'];

        return $this->faker->randomElements($languages, $this->faker->numberBetween(1, 2));
    }

    /**
     * Generate regions
     */
    private function generateRegions(): array
    {
        $regions = ['US', 'EU', 'APAC', 'LATAM', 'EMEA', 'Global'];

        return $this->faker->randomElements($regions, $this->faker->numberBetween(1, 2));
    }

    /**
     * Generate target forms
     */
    private function generateTargetForms(): array
    {
        $forms = ['contact-form', 'comment-form', 'registration-form', 'newsletter-signup'];

        return $this->faker->randomElements($forms, $this->faker->numberBetween(1, 2));
    }

    /**
     * Generate metadata
     */
    private function generateMetadata(): array
    {
        return [
            'created_by' => $this->faker->name(),
            'version' => $this->faker->semver(),
            'tags' => $this->faker->words(3),
            'performance_notes' => $this->faker->optional(0.4)->sentence(),
        ];
    }

    /**
     * Create a high-accuracy pattern
     */
    public function highAccuracy(): static
    {
        return $this->state(function (array $attributes) {
            $matchCount = $this->faker->numberBetween(100, 1000);
            $falsePositiveCount = $this->faker->numberBetween(0, (int) ($matchCount * 0.05)); // Max 5% false positives

            return [
                'match_count' => $matchCount,
                'false_positive_count' => $falsePositiveCount,
                'accuracy_rate' => round(($matchCount - $falsePositiveCount) / $matchCount, 4),
                'is_learning' => false,
                'priority' => $this->faker->numberBetween(7, 10),
            ];
        });
    }

    /**
     * Create a learning pattern
     */
    public function learning(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_learning' => true,
                'match_count' => $this->faker->numberBetween(0, 50),
                'false_positive_count' => $this->faker->numberBetween(0, 10),
                'priority' => $this->faker->numberBetween(1, 5),
            ];
        });
    }

    /**
     * Create a regex pattern
     */
    public function regex(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'pattern_type' => PatternType::REGEX->value,
                'pattern' => $this->faker->randomElement($this->regexPatterns),
                'case_sensitive' => false,
                'processing_time_ms' => $this->faker->numberBetween(5, 25),
            ];
        });
    }

    /**
     * Create a keyword pattern
     */
    public function keyword(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'pattern_type' => PatternType::KEYWORD->value,
                'pattern' => $this->faker->randomElement($this->spamKeywords),
                'processing_time_ms' => $this->faker->numberBetween(1, 5),
            ];
        });
    }

    /**
     * Create an active pattern
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => true,
                'last_matched' => $this->faker->dateTimeBetween('-7 days', 'now'),
            ];
        });
    }

    /**
     * Create an inactive pattern
     */
    public function inactive(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
                'match_count' => 0,
                'last_matched' => null,
            ];
        });
    }
}
