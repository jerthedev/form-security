<?php

declare(strict_types=1);

/**
 * Factory File: BlockedSubmissionFactory.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-008-blocked-submissions-tracking
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Model factory for generating realistic BlockedSubmission test data
 * with comprehensive attributes and relationships for testing scenarios.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Data-Management-Analytics/SPEC-008-blocked-submissions-tracking.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace Database\Factories\JTD\FormSecurity\Models;

use JTD\FormSecurity\Models\BlockedSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\JTD\FormSecurity\Models\BlockedSubmission>
 */
class BlockedSubmissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = BlockedSubmission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $blockReasons = ['spam_pattern', 'ip_reputation', 'rate_limit', 'geolocation', 'honeypot', 'custom_rule'];
        $countryCodes = ['US', 'CA', 'GB', 'DE', 'FR', 'JP', 'AU', 'BR', 'IN', 'CN'];
        $formIdentifiers = ['contact_form', 'newsletter_signup', 'user_registration', 'comment_form', 'feedback_form'];

        return [
            'form_identifier' => $this->faker->randomElement($formIdentifiers),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'referer' => $this->faker->optional(0.7)->url(),
            'block_reason' => $this->faker->randomElement($blockReasons),
            'block_details' => $this->faker->optional(0.6)->sentence(),
            'form_data_hash' => $this->faker->optional(0.8)->sha256(),
            'form_field_count' => $this->faker->numberBetween(1, 15),
            'country_code' => $this->faker->randomElement($countryCodes),
            'region' => $this->faker->optional(0.8)->state(),
            'city' => $this->faker->optional(0.8)->city(),
            'latitude' => $this->faker->optional(0.7)->latitude(),
            'longitude' => $this->faker->optional(0.7)->longitude(),
            'timezone' => $this->faker->optional(0.8)->timezone(),
            'isp' => $this->faker->optional(0.7)->company(),
            'organization' => $this->faker->optional(0.6)->company(),
            'risk_score' => $this->faker->numberBetween(0, 100),
            'is_tor' => $this->faker->boolean(10), // 10% chance
            'is_proxy' => $this->faker->boolean(15), // 15% chance
            'is_vpn' => $this->faker->boolean(20), // 20% chance
            'blocked_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'session_id' => $this->faker->optional(0.8)->uuid(),
            'fingerprint' => $this->faker->optional(0.7)->sha256(),
            'metadata' => $this->faker->optional(0.5)->randomElements([
                'browser_language' => $this->faker->languageCode(),
                'screen_resolution' => $this->faker->randomElement(['1920x1080', '1366x768', '1440x900']),
                'timezone_offset' => $this->faker->numberBetween(-12, 12),
                'plugins_count' => $this->faker->numberBetween(0, 20),
            ], $this->faker->numberBetween(1, 4), false),
        ];
    }

    /**
     * Indicate that the submission is high risk.
     */
    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_score' => $this->faker->numberBetween(80, 100),
            'block_reason' => $this->faker->randomElement(['spam_pattern', 'ip_reputation']),
        ]);
    }

    /**
     * Indicate that the submission is low risk.
     */
    public function lowRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_score' => $this->faker->numberBetween(0, 30),
            'block_reason' => $this->faker->randomElement(['rate_limit', 'honeypot']),
        ]);
    }

    /**
     * Indicate that the submission is from a suspicious network.
     */
    public function suspiciousNetwork(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_tor' => $this->faker->boolean(60),
            'is_proxy' => $this->faker->boolean(70),
            'is_vpn' => $this->faker->boolean(80),
            'risk_score' => $this->faker->numberBetween(60, 95),
        ]);
    }

    /**
     * Indicate that the submission is from a specific country.
     */
    public function fromCountry(string $countryCode): static
    {
        return $this->state(fn (array $attributes) => [
            'country_code' => $countryCode,
        ]);
    }

    /**
     * Indicate that the submission is from a specific form.
     */
    public function fromForm(string $formIdentifier): static
    {
        return $this->state(fn (array $attributes) => [
            'form_identifier' => $formIdentifier,
        ]);
    }

    /**
     * Indicate that the submission was blocked for spam patterns.
     */
    public function spamPattern(): static
    {
        return $this->state(fn (array $attributes) => [
            'block_reason' => 'spam_pattern',
            'risk_score' => $this->faker->numberBetween(70, 100),
            'block_details' => 'Matched spam pattern: ' . $this->faker->word(),
        ]);
    }

    /**
     * Indicate that the submission was blocked for rate limiting.
     */
    public function rateLimit(): static
    {
        return $this->state(fn (array $attributes) => [
            'block_reason' => 'rate_limit',
            'risk_score' => $this->faker->numberBetween(40, 70),
            'block_details' => 'Rate limit exceeded: ' . $this->faker->numberBetween(5, 20) . ' attempts',
        ]);
    }

    /**
     * Indicate that the submission has geolocation data.
     */
    public function withGeolocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'country_code' => $this->faker->countryCode(),
            'region' => $this->faker->state(),
            'city' => $this->faker->city(),
            'timezone' => $this->faker->timezone(),
        ]);
    }

    /**
     * Indicate that the submission has no geolocation data.
     */
    public function withoutGeolocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => null,
            'longitude' => null,
            'country_code' => null,
            'region' => null,
            'city' => null,
            'timezone' => null,
        ]);
    }

    /**
     * Indicate that the submission is recent.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'blocked_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    /**
     * Indicate that the submission is old.
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'blocked_at' => $this->faker->dateTimeBetween('-1 year', '-1 month'),
        ]);
    }

    /**
     * Indicate that the submission has comprehensive metadata.
     */
    public function withMetadata(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => [
                'browser_language' => $this->faker->languageCode(),
                'screen_resolution' => $this->faker->randomElement(['1920x1080', '1366x768', '1440x900', '1280x720']),
                'timezone_offset' => $this->faker->numberBetween(-12, 12),
                'plugins_count' => $this->faker->numberBetween(0, 25),
                'cookies_enabled' => $this->faker->boolean(90),
                'javascript_enabled' => $this->faker->boolean(95),
                'referrer_policy' => $this->faker->randomElement(['strict-origin', 'no-referrer', 'same-origin']),
                'connection_type' => $this->faker->randomElement(['wifi', 'cellular', 'ethernet']),
            ],
        ]);
    }
}
