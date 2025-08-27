<?php

declare(strict_types=1);

/**
 * Factory File: GeoLite2IpBlockFactory.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-019-geolite2-database-management
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Factory for generating realistic GeoLite2IpBlock test data
 * following Laravel 12 factory patterns with IP range data.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Specialized-Features/SPEC-019-geolite2-database-management.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JTD\FormSecurity\Models\GeoLite2IpBlock;

/**
 * GeoLite2IpBlockFactory Class
 *
 * Generates realistic test data for GeoLite2IpBlock models with proper
 * IP ranges, CIDR notation, and network classification data.
 */
class GeoLite2IpBlockFactory extends Factory
{
    /**
     * The name of the factory's corresponding model
     */
    protected $model = GeoLite2IpBlock::class;

    /**
     * Common network prefixes for different types
     */
    private array $networkPrefixes = [
        'residential' => ['192.168', '10.0', '172.16'],
        'business' => ['203.0', '202.12', '61.8'],
        'hosting' => ['104.16', '172.67', '198.41'],
        'mobile' => ['100.64', '198.18', '203.113'],
    ];

    /**
     * Define the model's default state
     */
    public function definition(): array
    {
        // Generate a realistic IP range
        $baseIp = $this->faker->ipv4();
        $prefixLength = $this->faker->randomElement([16, 20, 24, 28]);
        $network = $this->generateNetworkFromIp($baseIp, $prefixLength);

        return [
            'network' => $network['cidr'],
            'network_start_integer' => $network['start_integer'],
            'network_last_integer' => $network['end_integer'],
            'geoname_id' => $this->faker->optional(0.8)->numberBetween(1000000, 9999999),
            'registered_country_geoname_id' => $this->faker->optional(0.9)->numberBetween(1000000, 9999999),
            'represented_country_geoname_id' => $this->faker->optional(0.1)->numberBetween(1000000, 9999999),
            'is_anonymous_proxy' => $this->faker->boolean(5), // 5% chance
            'is_satellite_provider' => $this->faker->boolean(2), // 2% chance
            'is_anycast' => $this->faker->boolean(1), // 1% chance
            'postal_code' => $this->faker->optional(0.6)->postcode(),
            'latitude' => $this->faker->optional(0.7)->latitude(),
            'longitude' => $this->faker->optional(0.7)->longitude(),
            'accuracy_radius' => $this->faker->numberBetween(1, 1000),
            'metadata' => $this->generateMetadata(),
            'data_version' => $this->faker->regexify('20[0-9]{6}'),
            'data_updated_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'created_at' => $this->faker->dateTimeBetween('-2 years', '-1 year'),
            'updated_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Generate network range from IP and prefix length
     */
    private function generateNetworkFromIp(string $ip, int $prefixLength): array
    {
        $ipLong = ip2long($ip);
        $mask = -1 << (32 - $prefixLength);
        $networkLong = $ipLong & $mask;
        $broadcastLong = $networkLong | (~$mask & 0xFFFFFFFF);

        return [
            'cidr' => long2ip($networkLong).'/'.$prefixLength,
            'start_integer' => $networkLong,
            'end_integer' => $broadcastLong,
        ];
    }

    /**
     * Generate realistic metadata
     */
    private function generateMetadata(): array
    {
        return [
            'asn' => 'AS'.$this->faker->numberBetween(1000, 99999),
            'asn_name' => $this->faker->company().' Network',
            'isp' => $this->faker->company().' Internet Services',
            'organization' => $this->faker->company(),
            'usage_type' => $this->faker->randomElement([
                'residential', 'business', 'hosting', 'mobile', 'education', 'government',
            ]),
            'connection_type' => $this->faker->randomElement([
                'broadband', 'dialup', 'cellular', 'satellite', 'cable', 'dsl',
            ]),
        ];
    }

    /**
     * Create an anonymous proxy IP block
     */
    public function anonymousProxy(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_anonymous_proxy' => true,
                'is_satellite_provider' => false,
                'is_anycast' => false,
                'metadata' => array_merge($this->generateMetadata(), [
                    'usage_type' => 'proxy',
                    'organization' => 'Anonymous Proxy Service',
                ]),
            ];
        });
    }

    /**
     * Create a satellite provider IP block
     */
    public function satelliteProvider(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_satellite_provider' => true,
                'is_anonymous_proxy' => false,
                'is_anycast' => false,
                'accuracy_radius' => $this->faker->numberBetween(500, 2000), // Lower accuracy for satellite
                'metadata' => array_merge($this->generateMetadata(), [
                    'connection_type' => 'satellite',
                    'organization' => 'Satellite Internet Provider',
                ]),
            ];
        });
    }

    /**
     * Create an anycast IP block
     */
    public function anycast(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_anycast' => true,
                'is_anonymous_proxy' => false,
                'is_satellite_provider' => false,
                'metadata' => array_merge($this->generateMetadata(), [
                    'usage_type' => 'hosting',
                    'organization' => 'CDN Provider',
                ]),
            ];
        });
    }

    /**
     * Create a residential IP block
     */
    public function residential(): static
    {
        return $this->state(function (array $attributes) {
            $prefix = $this->faker->randomElement($this->networkPrefixes['residential']);
            $network = $this->generateNetworkFromIp($prefix.'.1.1', 24);

            return [
                'network' => $network['cidr'],
                'network_start_integer' => $network['start_integer'],
                'network_last_integer' => $network['end_integer'],
                'is_anonymous_proxy' => false,
                'is_satellite_provider' => false,
                'is_anycast' => false,
                'metadata' => array_merge($this->generateMetadata(), [
                    'usage_type' => 'residential',
                    'connection_type' => $this->faker->randomElement(['broadband', 'cable', 'dsl']),
                ]),
            ];
        });
    }

    /**
     * Create a hosting provider IP block
     */
    public function hostingProvider(): static
    {
        return $this->state(function (array $attributes) {
            $prefix = $this->faker->randomElement($this->networkPrefixes['hosting']);
            $network = $this->generateNetworkFromIp($prefix.'.1.1', 20);

            return [
                'network' => $network['cidr'],
                'network_start_integer' => $network['start_integer'],
                'network_last_integer' => $network['end_integer'],
                'metadata' => array_merge($this->generateMetadata(), [
                    'usage_type' => 'hosting',
                    'organization' => $this->faker->randomElement([
                        'Amazon Web Services', 'Google Cloud', 'Microsoft Azure',
                        'DigitalOcean', 'Linode', 'Vultr',
                    ]),
                ]),
            ];
        });
    }

    /**
     * Create a mobile network IP block
     */
    public function mobileNetwork(): static
    {
        return $this->state(function (array $attributes) {
            $prefix = $this->faker->randomElement($this->networkPrefixes['mobile']);
            $network = $this->generateNetworkFromIp($prefix.'.1.1', 16);

            return [
                'network' => $network['cidr'],
                'network_start_integer' => $network['start_integer'],
                'network_last_integer' => $network['end_integer'],
                'accuracy_radius' => $this->faker->numberBetween(100, 500), // Lower accuracy for mobile
                'metadata' => array_merge($this->generateMetadata(), [
                    'usage_type' => 'mobile',
                    'connection_type' => 'cellular',
                    'organization' => $this->faker->randomElement([
                        'Verizon Wireless', 'AT&T Mobility', 'T-Mobile', 'Sprint',
                    ]),
                ]),
            ];
        });
    }

    /**
     * Create a large network block
     */
    public function largeNetwork(): static
    {
        return $this->state(function (array $attributes) {
            $baseIp = $this->faker->ipv4();
            $prefixLength = $this->faker->randomElement([8, 12, 16]); // Large networks
            $network = $this->generateNetworkFromIp($baseIp, $prefixLength);

            return [
                'network' => $network['cidr'],
                'network_start_integer' => $network['start_integer'],
                'network_last_integer' => $network['end_integer'],
            ];
        });
    }

    /**
     * Create a small network block
     */
    public function smallNetwork(): static
    {
        return $this->state(function (array $attributes) {
            $baseIp = $this->faker->ipv4();
            $prefixLength = $this->faker->randomElement([28, 30, 32]); // Small networks
            $network = $this->generateNetworkFromIp($baseIp, $prefixLength);

            return [
                'network' => $network['cidr'],
                'network_start_integer' => $network['start_integer'],
                'network_last_integer' => $network['end_integer'],
            ];
        });
    }

    /**
     * Create a high accuracy IP block
     */
    public function highAccuracy(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'latitude' => $this->faker->latitude(),
                'longitude' => $this->faker->longitude(),
                'accuracy_radius' => $this->faker->numberBetween(1, 50),
                'postal_code' => $this->faker->postcode(),
            ];
        });
    }

    /**
     * Create an IP block without location data
     */
    public function noLocation(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'geoname_id' => null,
                'latitude' => null,
                'longitude' => null,
                'accuracy_radius' => null,
                'postal_code' => null,
            ];
        });
    }

    /**
     * Create a recently updated IP block
     */
    public function recentlyUpdated(): static
    {
        return $this->state(function (array $attributes) {
            $updatedAt = $this->faker->dateTimeBetween('-30 days', 'now');

            return [
                'data_updated_at' => $updatedAt,
                'updated_at' => $updatedAt,
                'data_version' => now()->format('Ymd'),
            ];
        });
    }
}
