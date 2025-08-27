<?php

declare(strict_types=1);

/**
 * Factory File: GeoLite2LocationFactory.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-019-geolite2-database-management
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Factory for generating realistic GeoLite2Location test data
 * following Laravel 12 factory patterns with geographic data.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Specialized-Features/SPEC-019-geolite2-database-management.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JTD\FormSecurity\Models\GeoLite2Location;

/**
 * GeoLite2LocationFactory Class
 *
 * Generates realistic test data for GeoLite2Location models with proper
 * geographic hierarchies, coordinates, and location metadata.
 */
class GeoLite2LocationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model
     */
    protected $model = GeoLite2Location::class;

    /**
     * Major world cities with their coordinates
     */
    private array $majorCities = [
        ['name' => 'New York', 'lat' => 40.7128, 'lng' => -74.0060, 'country' => 'US', 'continent' => 'NA'],
        ['name' => 'London', 'lat' => 51.5074, 'lng' => -0.1278, 'country' => 'GB', 'continent' => 'EU'],
        ['name' => 'Tokyo', 'lat' => 35.6762, 'lng' => 139.6503, 'country' => 'JP', 'continent' => 'AS'],
        ['name' => 'Paris', 'lat' => 48.8566, 'lng' => 2.3522, 'country' => 'FR', 'continent' => 'EU'],
        ['name' => 'Sydney', 'lat' => -33.8688, 'lng' => 151.2093, 'country' => 'AU', 'continent' => 'OC'],
        ['name' => 'São Paulo', 'lat' => -23.5505, 'lng' => -46.6333, 'country' => 'BR', 'continent' => 'SA'],
        ['name' => 'Mumbai', 'lat' => 19.0760, 'lng' => 72.8777, 'country' => 'IN', 'continent' => 'AS'],
        ['name' => 'Cairo', 'lat' => 30.0444, 'lng' => 31.2357, 'country' => 'EG', 'continent' => 'AF'],
    ];

    /**
     * Continent codes and names
     */
    private array $continents = [
        'AF' => 'Africa',
        'AN' => 'Antarctica',
        'AS' => 'Asia',
        'EU' => 'Europe',
        'NA' => 'North America',
        'OC' => 'Oceania',
        'SA' => 'South America',
    ];

    /**
     * EU member countries
     */
    private array $euCountries = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    /**
     * Define the model's default state
     */
    public function definition(): array
    {
        $continentCode = $this->faker->randomElement(array_keys($this->continents));
        $continentName = $this->continents[$continentCode];
        $countryCode = $this->faker->countryCode();
        $isInEu = in_array($countryCode, $this->euCountries);

        return [
            'geoname_id' => $this->faker->unique()->numberBetween(1000000, 9999999),
            'locale_code' => $this->faker->randomElement(['en', 'es', 'fr', 'de', 'pt', 'ru', 'zh']),
            'continent_code' => $continentCode,
            'continent_name' => $continentName,
            'country_iso_code' => $countryCode,
            'country_name' => $this->faker->country(),
            'subdivision_1_iso_code' => $this->faker->optional(0.8)->stateAbbr(),
            'subdivision_1_name' => $this->faker->optional(0.8)->state(),
            'subdivision_2_iso_code' => $this->faker->optional(0.3)->regexify('[A-Z]{2,3}'),
            'subdivision_2_name' => $this->faker->optional(0.3)->city(),
            'city_name' => $this->faker->optional(0.9)->city(),
            'metro_code' => $this->faker->optional(0.2)->numberBetween(500, 900),
            'time_zone' => $this->faker->timezone(),
            'is_in_european_union' => $isInEu,
            'postal_codes' => $this->generatePostalCodes(),
            'latitude' => $this->faker->optional(0.85)->latitude(),
            'longitude' => $this->faker->optional(0.85)->longitude(),
            'accuracy_radius' => $this->faker->numberBetween(1, 1000),
            'data_version' => $this->faker->regexify('20[0-9]{6}'),
            'data_updated_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'created_at' => $this->faker->dateTimeBetween('-2 years', '-1 year'),
            'updated_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Generate realistic postal codes
     */
    private function generatePostalCodes(): array
    {
        if ($this->faker->boolean(70)) { // 70% chance of having postal codes
            return $this->faker->randomElements([
                $this->faker->postcode(),
                $this->faker->postcode(),
                $this->faker->postcode(),
            ], $this->faker->numberBetween(1, 3));
        }

        return [];
    }

    /**
     * Create a major city location
     */
    public function majorCity(): static
    {
        return $this->state(function (array $attributes) {
            $city = $this->faker->randomElement($this->majorCities);

            return [
                'city_name' => $city['name'],
                'latitude' => $city['lat'] + $this->faker->randomFloat(4, -0.1, 0.1), // Add small variation
                'longitude' => $city['lng'] + $this->faker->randomFloat(4, -0.1, 0.1),
                'country_iso_code' => $city['country'],
                'continent_code' => $city['continent'],
                'continent_name' => $this->continents[$city['continent']],
                'accuracy_radius' => $this->faker->numberBetween(1, 50), // High accuracy for major cities
                'postal_codes' => $this->faker->randomElements([
                    $this->faker->postcode(),
                    $this->faker->postcode(),
                    $this->faker->postcode(),
                    $this->faker->postcode(),
                ], $this->faker->numberBetween(2, 4)),
            ];
        });
    }

    /**
     * Create a European Union location
     */
    public function europeanUnion(): static
    {
        return $this->state(function (array $attributes) {
            $countryCode = $this->faker->randomElement($this->euCountries);

            return [
                'continent_code' => 'EU',
                'continent_name' => 'Europe',
                'country_iso_code' => $countryCode,
                'is_in_european_union' => true,
                'time_zone' => $this->faker->randomElement([
                    'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Europe/Rome',
                    'Europe/Madrid', 'Europe/Amsterdam', 'Europe/Brussels', 'Europe/Vienna',
                ]),
            ];
        });
    }

    /**
     * Create a location with high accuracy coordinates
     */
    public function highAccuracy(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'latitude' => $this->faker->latitude(),
                'longitude' => $this->faker->longitude(),
                'accuracy_radius' => $this->faker->numberBetween(1, 25), // Very accurate
            ];
        });
    }

    /**
     * Create a location without coordinates
     */
    public function noCoordinates(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'latitude' => null,
                'longitude' => null,
                'accuracy_radius' => null,
            ];
        });
    }

    /**
     * Create a country-level location (no city)
     */
    public function countryLevel(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'city_name' => null,
                'subdivision_1_iso_code' => null,
                'subdivision_1_name' => null,
                'subdivision_2_iso_code' => null,
                'subdivision_2_name' => null,
                'metro_code' => null,
                'postal_codes' => [],
                'accuracy_radius' => $this->faker->numberBetween(100, 1000), // Lower accuracy for country level
            ];
        });
    }

    /**
     * Create a US location with metro code
     */
    public function unitedStates(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'continent_code' => 'NA',
                'continent_name' => 'North America',
                'country_iso_code' => 'US',
                'country_name' => 'United States',
                'subdivision_1_iso_code' => $this->faker->stateAbbr(),
                'subdivision_1_name' => $this->faker->state(),
                'metro_code' => $this->faker->numberBetween(500, 900),
                'time_zone' => $this->faker->randomElement([
                    'America/New_York', 'America/Chicago', 'America/Denver',
                    'America/Los_Angeles', 'America/Anchorage', 'Pacific/Honolulu',
                ]),
                'is_in_european_union' => false,
            ];
        });
    }

    /**
     * Create an Asian location
     */
    public function asia(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'continent_code' => 'AS',
                'continent_name' => 'Asia',
                'country_iso_code' => $this->faker->randomElement(['CN', 'JP', 'IN', 'KR', 'TH', 'SG', 'MY']),
                'time_zone' => $this->faker->randomElement([
                    'Asia/Shanghai', 'Asia/Tokyo', 'Asia/Kolkata', 'Asia/Seoul',
                    'Asia/Bangkok', 'Asia/Singapore', 'Asia/Kuala_Lumpur',
                ]),
                'is_in_european_union' => false,
            ];
        });
    }

    /**
     * Create a recently updated location
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
