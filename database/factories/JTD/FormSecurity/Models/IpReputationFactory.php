<?php

declare(strict_types=1);

/**
 * Factory File: IpReputationFactory.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Model factory for generating realistic IpReputation test data
 * with comprehensive threat intelligence and activity tracking attributes.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace Database\Factories\JTD\FormSecurity\Models;

use JTD\FormSecurity\Models\IpReputation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\JTD\FormSecurity\Models\IpReputation>
 */
class IpReputationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = IpReputation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reputationStatuses = ['trusted', 'neutral', 'suspicious', 'malicious', 'blocked'];
        $countryCodes = ['US', 'CA', 'GB', 'DE', 'FR', 'JP', 'AU', 'BR', 'IN', 'CN', 'RU'];
        $threatSources = ['abuseipdb', 'virustotal', 'spamhaus', 'malwaredomainlist', 'emergingthreats'];
        $threatCategories = ['malware', 'spam', 'phishing', 'botnet', 'scanning', 'brute_force'];

        $submissionCount = $this->faker->numberBetween(0, 1000);
        $blockedCount = $this->faker->numberBetween(0, min($submissionCount, 500));
        $blockRate = $submissionCount > 0 ? $blockedCount / $submissionCount : 0;

        return [
            'ip_address' => $this->faker->unique()->ipv4(),
            'reputation_score' => $this->faker->numberBetween(0, 100),
            'reputation_status' => $this->faker->randomElement($reputationStatuses),
            'is_tor' => $this->faker->boolean(5), // 5% chance
            'is_proxy' => $this->faker->boolean(10), // 10% chance
            'is_vpn' => $this->faker->boolean(15), // 15% chance
            'is_hosting' => $this->faker->boolean(8), // 8% chance
            'is_malware' => $this->faker->boolean(3), // 3% chance
            'is_botnet' => $this->faker->boolean(2), // 2% chance
            'country_code' => $this->faker->randomElement($countryCodes),
            'region' => $this->faker->optional(0.8)->state(),
            'city' => $this->faker->optional(0.8)->city(),
            'isp' => $this->faker->optional(0.9)->company(),
            'organization' => $this->faker->optional(0.7)->company(),
            'submission_count' => $submissionCount,
            'blocked_count' => $blockedCount,
            'block_rate' => round($blockRate, 4),
            'first_seen' => $this->faker->optional(0.9)->dateTimeBetween('-1 year', '-1 day'),
            'last_seen' => $this->faker->optional(0.9)->dateTimeBetween('-1 week', 'now'),
            'last_blocked' => $this->faker->optional(0.6)->dateTimeBetween('-1 month', 'now'),
            'threat_sources' => $this->faker->optional(0.4)->randomElements($threatSources, $this->faker->numberBetween(1, 3)),
            'threat_categories' => $this->faker->optional(0.3)->randomElements($threatCategories, $this->faker->numberBetween(1, 2)),
            'notes' => $this->faker->optional(0.2)->sentence(),
            'cache_expires_at' => $this->faker->optional(0.8)->dateTimeBetween('now', '+7 days'),
            'is_whitelisted' => $this->faker->boolean(5), // 5% chance
            'is_blacklisted' => $this->faker->boolean(3), // 3% chance
            'metadata' => $this->faker->optional(0.3)->randomElements([
                'asn' => $this->faker->numberBetween(1000, 99999),
                'as_name' => $this->faker->company(),
                'usage_type' => $this->faker->randomElement(['residential', 'commercial', 'hosting', 'mobile']),
                'domain' => $this->faker->domainName(),
            ], $this->faker->numberBetween(1, 4), false),
        ];
    }

    /**
     * Indicate that the IP is malicious.
     */
    public function malicious(): static
    {
        return $this->state(fn (array $attributes) => [
            'reputation_score' => $this->faker->numberBetween(0, 30),
            'reputation_status' => $this->faker->randomElement(['malicious', 'blocked']),
            'is_malware' => $this->faker->boolean(60),
            'is_botnet' => $this->faker->boolean(40),
            'threat_sources' => $this->faker->randomElements(['abuseipdb', 'virustotal', 'spamhaus'], $this->faker->numberBetween(1, 3)),
            'threat_categories' => $this->faker->randomElements(['malware', 'botnet', 'spam'], $this->faker->numberBetween(1, 2)),
        ]);
    }

    /**
     * Indicate that the IP is suspicious.
     */
    public function suspicious(): static
    {
        return $this->state(fn (array $attributes) => [
            'reputation_score' => $this->faker->numberBetween(31, 60),
            'reputation_status' => 'suspicious',
            'is_proxy' => $this->faker->boolean(50),
            'is_vpn' => $this->faker->boolean(40),
            'threat_sources' => $this->faker->optional(0.6)->randomElements(['abuseipdb', 'emergingthreats'], 1),
        ]);
    }

    /**
     * Indicate that the IP is trusted.
     */
    public function trusted(): static
    {
        return $this->state(fn (array $attributes) => [
            'reputation_score' => $this->faker->numberBetween(80, 100),
            'reputation_status' => 'trusted',
            'is_tor' => false,
            'is_proxy' => false,
            'is_vpn' => false,
            'is_malware' => false,
            'is_botnet' => false,
            'block_rate' => $this->faker->randomFloat(4, 0, 0.1), // Very low block rate
        ]);
    }

    /**
     * Indicate that the IP is whitelisted.
     */
    public function whitelisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_whitelisted' => true,
            'is_blacklisted' => false,
            'reputation_score' => $this->faker->numberBetween(70, 100),
            'reputation_status' => 'trusted',
        ]);
    }

    /**
     * Indicate that the IP is blacklisted.
     */
    public function blacklisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_blacklisted' => true,
            'is_whitelisted' => false,
            'reputation_score' => $this->faker->numberBetween(0, 20),
            'reputation_status' => 'blocked',
        ]);
    }

    /**
     * Indicate that the IP cache is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'cache_expires_at' => $this->faker->dateTimeBetween('-7 days', '-1 hour'),
        ]);
    }

    /**
     * Indicate that the IP cache is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'cache_expires_at' => $this->faker->dateTimeBetween('+1 hour', '+7 days'),
        ]);
    }

    /**
     * Indicate that the IP is from a suspicious network type.
     */
    public function suspiciousNetwork(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_tor' => $this->faker->boolean(40),
            'is_proxy' => $this->faker->boolean(50),
            'is_vpn' => $this->faker->boolean(60),
            'is_hosting' => $this->faker->boolean(30),
        ]);
    }

    /**
     * Indicate that the IP has high activity.
     */
    public function highActivity(): static
    {
        $submissionCount = $this->faker->numberBetween(500, 2000);
        $blockedCount = $this->faker->numberBetween(50, min($submissionCount, 800));
        
        return $this->state(fn (array $attributes) => [
            'submission_count' => $submissionCount,
            'blocked_count' => $blockedCount,
            'block_rate' => round($blockedCount / $submissionCount, 4),
            'first_seen' => $this->faker->dateTimeBetween('-6 months', '-1 month'),
            'last_seen' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ]);
    }

    /**
     * Indicate that the IP has high block rate.
     */
    public function highBlockRate(): static
    {
        $submissionCount = $this->faker->numberBetween(20, 100);
        $blockedCount = $this->faker->numberBetween((int)($submissionCount * 0.8), $submissionCount);
        
        return $this->state(fn (array $attributes) => [
            'submission_count' => $submissionCount,
            'blocked_count' => $blockedCount,
            'block_rate' => round($blockedCount / $submissionCount, 4),
            'reputation_score' => $this->faker->numberBetween(0, 40),
        ]);
    }

    /**
     * Indicate that the IP is from a specific country.
     */
    public function fromCountry(string $countryCode): static
    {
        return $this->state(fn (array $attributes) => [
            'country_code' => $countryCode,
        ]);
    }

    /**
     * Indicate that the IP has comprehensive threat intelligence.
     */
    public function withThreatIntelligence(): static
    {
        return $this->state(fn (array $attributes) => [
            'threat_sources' => $this->faker->randomElements(['abuseipdb', 'virustotal', 'spamhaus', 'malwaredomainlist'], $this->faker->numberBetween(2, 4)),
            'threat_categories' => $this->faker->randomElements(['malware', 'spam', 'phishing', 'botnet'], $this->faker->numberBetween(1, 3)),
            'notes' => 'Threat intelligence data from multiple sources',
            'metadata' => [
                'asn' => $this->faker->numberBetween(1000, 99999),
                'as_name' => $this->faker->company(),
                'usage_type' => $this->faker->randomElement(['hosting', 'commercial', 'residential']),
                'domain' => $this->faker->domainName(),
                'last_threat_update' => $this->faker->dateTimeBetween('-7 days', 'now')->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
