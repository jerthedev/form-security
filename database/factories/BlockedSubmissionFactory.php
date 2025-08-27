<?php

declare(strict_types=1);

/**
 * Factory File: BlockedSubmissionFactory.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-008-blocked-submissions-tracking
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Factory for generating realistic BlockedSubmission test data
 * following Laravel 12 factory patterns with comprehensive data generation.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Data-Management-Analytics/SPEC-008-blocked-submissions-tracking.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JTD\FormSecurity\Enums\BlockReason;
use JTD\FormSecurity\Models\BlockedSubmission;

/**
 * BlockedSubmissionFactory Class
 *
 * Generates realistic test data for BlockedSubmission models with proper
 * geographic data, risk scoring, and metadata generation.
 */
class BlockedSubmissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model
     */
    protected $model = BlockedSubmission::class;

    /**
     * Common form identifiers for realistic data
     */
    private array $formIdentifiers = [
        'contact-form',
        'newsletter-signup',
        'user-registration',
        'comment-form',
        'support-ticket',
        'feedback-form',
        'login-form',
        'password-reset',
        'survey-form',
        'booking-form',
    ];

    /**
     * Common ISPs for realistic data
     */
    private array $commonIsps = [
        'Comcast Cable Communications',
        'Verizon Communications',
        'AT&T Services',
        'Charter Communications',
        'Cox Communications',
        'CenturyLink',
        'Deutsche Telekom',
        'Orange S.A.',
        'Vodafone Group',
        'China Telecom',
    ];

    /**
     * Define the model's default state
     */
    public function definition(): array
    {
        $blockedAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $countryCode = $this->faker->countryCode();
        $isHighRisk = $this->faker->boolean(20); // 20% chance of high risk

        return [
            'ip_address' => $this->faker->ipv4(),
            'form_identifier' => $this->faker->randomElement($this->formIdentifiers),
            'block_reason' => $this->faker->randomElement(BlockReason::cases())->value,
            'risk_score' => $isHighRisk
                ? $this->faker->numberBetween(70, 100)
                : $this->faker->numberBetween(20, 69),
            'country_code' => $countryCode,
            'region' => $this->faker->state(),
            'city' => $this->faker->city(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'timezone' => $this->faker->timezone(),
            'isp' => $this->faker->randomElement($this->commonIsps),
            'organization' => $this->faker->company(),
            'form_field_count' => $this->faker->numberBetween(3, 15),
            'is_tor' => $this->faker->boolean(5), // 5% chance
            'is_proxy' => $this->faker->boolean(10), // 10% chance
            'is_vpn' => $this->faker->boolean(15), // 15% chance
            'blocked_at' => $blockedAt,
            'metadata' => $this->generateMetadata(),
            'created_at' => $blockedAt,
            'updated_at' => $blockedAt,
        ];
    }

    /**
     * Generate realistic metadata
     */
    private function generateMetadata(): array
    {
        return [
            'user_agent' => $this->faker->userAgent(),
            'referrer' => $this->faker->optional(0.7)->url(),
            'session_id' => $this->faker->uuid(),
            'form_data' => [
                'field_count' => $this->faker->numberBetween(3, 15),
                'has_email' => $this->faker->boolean(80),
                'has_phone' => $this->faker->boolean(40),
                'has_url' => $this->faker->boolean(20),
                'suspicious_patterns' => $this->faker->optional(0.3)->randomElements([
                    'multiple_urls', 'excessive_caps', 'suspicious_keywords', 'rapid_submission',
                ], $this->faker->numberBetween(1, 2)),
            ],
            'request_headers' => [
                'accept_language' => $this->faker->languageCode().'-'.$this->faker->countryCode(),
                'accept_encoding' => 'gzip, deflate, br',
                'connection' => 'keep-alive',
            ],
            'detection_details' => [
                'pattern_matches' => $this->faker->numberBetween(1, 5),
                'confidence_score' => $this->faker->randomFloat(2, 0.5, 1.0),
                'processing_time_ms' => $this->faker->numberBetween(5, 50),
            ],
        ];
    }

    /**
     * Create a high-risk submission
     */
    public function highRisk(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'risk_score' => $this->faker->numberBetween(80, 100),
                'block_reason' => $this->faker->randomElement([
                    BlockReason::SPAM_PATTERN,
                    BlockReason::HONEYPOT,
                    BlockReason::IP_REPUTATION,
                ])->value,
                'is_tor' => $this->faker->boolean(30),
                'is_proxy' => $this->faker->boolean(40),
            ];
        });
    }

    /**
     * Create a low-risk submission
     */
    public function lowRisk(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'risk_score' => $this->faker->numberBetween(10, 40),
                'block_reason' => $this->faker->randomElement([
                    BlockReason::RATE_LIMIT,
                    BlockReason::GEOLOCATION,
                ])->value,
                'is_tor' => false,
                'is_proxy' => false,
                'is_vpn' => $this->faker->boolean(10),
            ];
        });
    }

    /**
     * Create submission from Tor network
     */
    public function fromTor(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_tor' => true,
                'is_proxy' => false,
                'is_vpn' => false,
                'block_reason' => BlockReason::IP_REPUTATION->value,
                'risk_score' => $this->faker->numberBetween(70, 95),
                'isp' => 'Tor Network',
                'organization' => 'The Tor Project',
            ];
        });
    }

    /**
     * Create submission from specific country
     */
    public function fromCountry(string $countryCode): static
    {
        return $this->state(function (array $attributes) use ($countryCode) {
            return [
                'country_code' => $countryCode,
            ];
        });
    }

    /**
     * Create submission for specific form
     */
    public function forForm(string $formIdentifier): static
    {
        return $this->state(function (array $attributes) use ($formIdentifier) {
            return [
                'form_identifier' => $formIdentifier,
            ];
        });
    }

    /**
     * Create recent submission (within last 24 hours)
     */
    public function recent(): static
    {
        return $this->state(function (array $attributes) {
            $blockedAt = $this->faker->dateTimeBetween('-24 hours', 'now');

            return [
                'blocked_at' => $blockedAt,
                'created_at' => $blockedAt,
                'updated_at' => $blockedAt,
            ];
        });
    }

    /**
     * Create submission with honeypot detection
     */
    public function honeypot(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'block_reason' => BlockReason::HONEYPOT->value,
                'risk_score' => $this->faker->numberBetween(90, 100),
                'metadata' => array_merge($this->generateMetadata(), [
                    'honeypot_triggered' => true,
                    'honeypot_field' => $this->faker->randomElement(['website', 'url', 'homepage']),
                    'honeypot_value' => $this->faker->url(),
                ]),
            ];
        });
    }

    /**
     * Create submission with spam pattern detection
     */
    public function spamPattern(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'block_reason' => BlockReason::SPAM_PATTERN->value,
                'risk_score' => $this->faker->numberBetween(60, 90),
                'metadata' => array_merge($this->generateMetadata(), [
                    'matched_patterns' => $this->faker->randomElements([
                        'excessive_links', 'suspicious_keywords', 'repeated_text', 'all_caps',
                    ], $this->faker->numberBetween(1, 3)),
                    'pattern_confidence' => $this->faker->randomFloat(2, 0.7, 1.0),
                ]),
            ];
        });
    }
}
