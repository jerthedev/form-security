<?php

declare(strict_types=1);

/**
 * Factory File: IpReputationFactory.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Factory for generating realistic IpReputation test data
 * following Laravel 12 factory patterns with threat intelligence data.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JTD\FormSecurity\Enums\ReputationStatus;
use JTD\FormSecurity\Models\IpReputation;

/**
 * IpReputationFactory Class
 *
 * Generates realistic test data for IpReputation models with proper
 * threat intelligence, reputation scoring, and network classification.
 */
class IpReputationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model
     */
    protected $model = IpReputation::class;

    /**
     * Common threat categories
     */
    private array $threatCategories = [
        'malware', 'botnet', 'spam', 'phishing', 'scanning',
        'brute_force', 'ddos', 'proxy', 'suspicious',
    ];

    /**
     * Common threat sources
     */
    private array $threatSources = [
        'VirusTotal', 'AbuseIPDB', 'Spamhaus', 'SURBL', 'Malware Domain List',
        'PhishTank', 'OpenPhish', 'URLVoid', 'Hybrid Analysis', 'ThreatCrowd',
    ];

    /**
     * Define the model's default state
     */
    public function definition(): array
    {
        $reputationScore = $this->faker->numberBetween(0, 100);
        $reputationStatus = ReputationStatus::fromScore($reputationScore);
        $submissionCount = $this->faker->numberBetween(1, 1000);
        $blockedCount = $this->faker->numberBetween(0, $submissionCount);
        $blockRate = $submissionCount > 0 ? $blockedCount / $submissionCount : 0;

        $firstSeen = $this->faker->dateTimeBetween('-1 year', '-1 month');
        $lastSeen = $this->faker->dateTimeBetween($firstSeen, 'now');
        $lastBlocked = $blockedCount > 0
            ? $this->faker->dateTimeBetween($firstSeen, $lastSeen)
            : null;

        return [
            'ip_address' => function () {
                // Generate truly unique IP using UUID-based approach
                $uuid = uniqid('', true);
                $hash = crc32($uuid . microtime(true) . mt_rand());

                // Convert hash to IP components ensuring uniqueness
                $octet1 = 10; // Private IP range
                $octet2 = (abs($hash) >> 16) & 255;
                $octet3 = (abs($hash) >> 8) & 255;
                $octet4 = abs($hash) & 255;

                // Ensure valid IP ranges (avoid .0 and .255 for last octet)
                $octet2 = max(0, min(255, $octet2));
                $octet3 = max(0, min(255, $octet3));
                $octet4 = max(1, min(254, $octet4));

                return "{$octet1}.{$octet2}.{$octet3}.{$octet4}";
            },
            'reputation_score' => $reputationScore,
            'reputation_status' => $reputationStatus->value,
            'country_code' => $this->faker->countryCode(),
            'region' => $this->faker->state(),
            'city' => $this->faker->city(),
            'isp' => $this->faker->company().' Internet Services',
            'organization' => $this->faker->company(),
            'submission_count' => $submissionCount,
            'blocked_count' => $blockedCount,
            'block_rate' => round($blockRate, 4),
            'is_tor' => $this->faker->boolean(5), // 5% chance
            'is_proxy' => $this->faker->boolean(10), // 10% chance
            'is_vpn' => $this->faker->boolean(15), // 15% chance
            'is_hosting' => $this->faker->boolean(20), // 20% chance
            'is_malware' => $this->faker->boolean(8), // 8% chance
            'is_botnet' => $this->faker->boolean(6), // 6% chance
            'is_whitelisted' => $this->faker->boolean(5), // 5% chance
            'is_blacklisted' => $this->faker->boolean(10), // 10% chance
            'first_seen' => $firstSeen,
            'last_seen' => $lastSeen,
            'last_blocked' => $lastBlocked,
            'cache_expires_at' => $this->faker->dateTimeBetween('now', '+24 hours'),
            'threat_sources' => $this->generateThreatSources(),
            'threat_categories' => $this->generateThreatCategories($reputationScore),
            'metadata' => $this->generateMetadata(),
            'created_at' => $firstSeen,
            'updated_at' => $lastSeen,
        ];
    }

    /**
     * Generate realistic threat sources
     */
    private function generateThreatSources(): array
    {
        if ($this->faker->boolean(70)) { // 70% chance of having threat sources
            return $this->faker->randomElements(
                $this->threatSources,
                $this->faker->numberBetween(1, 3)
            );
        }

        return [];
    }

    /**
     * Generate threat categories based on reputation score
     */
    private function generateThreatCategories(int $reputationScore): array
    {
        if ($reputationScore > 70) {
            return []; // Good reputation, no threat categories
        }

        $categoryCount = match (true) {
            $reputationScore <= 20 => $this->faker->numberBetween(2, 4),
            $reputationScore <= 50 => $this->faker->numberBetween(1, 3),
            default => $this->faker->numberBetween(0, 2),
        };

        if ($categoryCount === 0) {
            return [];
        }

        return $this->faker->randomElements($this->threatCategories, $categoryCount);
    }

    /**
     * Generate realistic metadata
     */
    private function generateMetadata(): array
    {
        return [
            'asn' => 'AS'.$this->faker->numberBetween(1000, 99999),
            'asn_name' => $this->faker->company().' Network',
            'domain' => $this->faker->optional(0.6)->domainName(),
            'hostname' => $this->faker->optional(0.4)->domainWord().'.example.com',
            'usage_type' => $this->faker->randomElement([
                'residential', 'business', 'hosting', 'mobile', 'education', 'government',
            ]),
            'threat_intelligence' => [
                'last_updated' => $this->faker->dateTimeBetween('-7 days', 'now')->format('Y-m-d H:i:s'),
                'confidence_score' => $this->faker->randomFloat(2, 0.1, 1.0),
                'severity_level' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            ],
            'geolocation' => [
                'accuracy_radius' => $this->faker->numberBetween(1, 1000),
                'timezone' => $this->faker->timezone(),
                'postal_code' => $this->faker->optional(0.7)->postcode(),
            ],
        ];
    }

    /**
     * Create a trusted IP reputation
     */
    public function trusted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'reputation_score' => $this->faker->numberBetween(80, 100),
                'reputation_status' => ReputationStatus::TRUSTED->value,
                'is_whitelisted' => true,
                'is_blacklisted' => false,
                'block_rate' => $this->faker->randomFloat(4, 0, 0.1),
                'threat_categories' => [],
                'threat_sources' => [],
            ];
        });
    }

    /**
     * Create a malicious IP reputation
     */
    public function malicious(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'reputation_score' => $this->faker->numberBetween(0, 30),
                'reputation_status' => ReputationStatus::MALICIOUS->value,
                'is_blacklisted' => true,
                'is_whitelisted' => false,
                'block_rate' => $this->faker->randomFloat(4, 0.7, 1.0),
                'is_malware' => $this->faker->boolean(60),
                'is_botnet' => $this->faker->boolean(40),
                'threat_categories' => $this->faker->randomElements($this->threatCategories, 3),
                'threat_sources' => $this->faker->randomElements($this->threatSources, 2),
            ];
        });
    }

    /**
     * Create a Tor exit node reputation
     */
    public function torNode(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_tor' => true,
                'is_proxy' => false,
                'is_vpn' => false,
                'reputation_score' => $this->faker->numberBetween(20, 50),
                'reputation_status' => ReputationStatus::SUSPICIOUS->value,
                'isp' => 'Tor Network',
                'organization' => 'The Tor Project',
                'threat_categories' => ['proxy'],
            ];
        });
    }

    /**
     * Create a VPN service reputation
     */
    public function vpnService(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_vpn' => true,
                'is_tor' => false,
                'is_proxy' => false,
                'reputation_score' => $this->faker->numberBetween(40, 70),
                'reputation_status' => ReputationStatus::NEUTRAL->value,
                'isp' => $this->faker->randomElement([
                    'NordVPN', 'ExpressVPN', 'Surfshark', 'CyberGhost', 'Private Internet Access',
                ]),
                'organization' => 'VPN Service Provider',
            ];
        });
    }

    /**
     * Create a hosting provider reputation
     */
    public function hostingProvider(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_hosting' => true,
                'reputation_score' => $this->faker->numberBetween(30, 80),
                'isp' => $this->faker->randomElement([
                    'Amazon Web Services', 'Google Cloud', 'Microsoft Azure',
                    'DigitalOcean', 'Linode', 'Vultr',
                ]),
                'organization' => 'Cloud Hosting Provider',
                'metadata' => array_merge($this->generateMetadata(), [
                    'usage_type' => 'hosting',
                    'datacenter' => $this->faker->city().' DC',
                ]),
            ];
        });
    }

    /**
     * Create recently active reputation
     */
    public function recentlyActive(): static
    {
        return $this->state(function (array $attributes) {
            $lastSeen = $this->faker->dateTimeBetween('-24 hours', 'now');

            return [
                'last_seen' => $lastSeen,
                'updated_at' => $lastSeen,
                'cache_expires_at' => $this->faker->dateTimeBetween('now', '+6 hours'),
            ];
        });
    }

    /**
     * Create high-volume reputation (many submissions)
     */
    public function highVolume(): static
    {
        return $this->state(function (array $attributes) {
            $submissionCount = $this->faker->numberBetween(1000, 10000);
            $blockedCount = $this->faker->numberBetween(100, $submissionCount);

            return [
                'submission_count' => $submissionCount,
                'blocked_count' => $blockedCount,
                'block_rate' => round($blockedCount / $submissionCount, 4),
            ];
        });
    }
}
