<?php

/**
 * Test File: GeoLite2LocationTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-019-geolite2-database-management
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Comprehensive unit tests for the GeoLite2Location model including
 * geographic queries, relationships, coordinate calculations, and hierarchy methods.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Specialized-Features/SPEC-019-geolite2-database-management.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Models;

use JTD\FormSecurity\Models\GeoLite2Location;
use JTD\FormSecurity\Models\GeoLite2IpBlock;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;

#[Group('sprint-002')]
#[Group('epic-001')]
#[Group('database')]
#[Group('models')]
#[Group('ticket-1021')]
#[Group('geolite2-location')]
class GeoLite2LocationTest extends TestCase
{
    #[Test]
    public function it_can_create_geolite2_location_with_required_fields(): void
    {
        $location = GeoLite2Location::create([
            'geoname_id' => 123456,
            'locale_code' => 'en',
            'country_iso_code' => 'US',
            'country_name' => 'United States',
        ]);

        $this->assertInstanceOf(GeoLite2Location::class, $location);
        $this->assertEquals(123456, $location->geoname_id);
        $this->assertEquals('en', $location->locale_code);
        $this->assertEquals('US', $location->country_iso_code);
        $this->assertEquals('United States', $location->country_name);
    }

    #[Test]
    public function it_casts_attributes_correctly(): void
    {
        $location = GeoLite2Location::create([
            'geoname_id' => '789012',
            'locale_code' => 'en',
            'continent_code' => 'NA',
            'continent_name' => 'North America',
            'country_iso_code' => 'CA',
            'country_name' => 'Canada',
            'subdivision_1_iso_code' => 'ON',
            'subdivision_1_name' => 'Ontario',
            'city_name' => 'Toronto',
            'metro_code' => '532',
            'time_zone' => 'America/Toronto',
            'latitude' => '43.6532',
            'longitude' => '-79.3832',
            'accuracy_radius' => '100',
            'is_in_european_union' => '0',
            'postal_codes' => ['M5V', 'M5H', 'M5J'],
            'data_updated_at' => '2025-01-27 12:00:00',
            'metadata' => ['source' => 'maxmind', 'version' => '2025.01'],
        ]);

        $this->assertIsInt($location->geoname_id);
        $this->assertIsInt($location->metro_code);
        $this->assertIsInt($location->accuracy_radius);
        $this->assertTrue(is_float($location->latitude) || is_string($location->latitude));
        $this->assertTrue(is_float($location->longitude) || is_string($location->longitude));
        $this->assertIsBool($location->is_in_european_union);
        $this->assertIsArray($location->postal_codes);
        $this->assertInstanceOf(Carbon::class, $location->data_updated_at);
        $this->assertIsArray($location->metadata);

        $this->assertEquals(789012, $location->geoname_id);
        $this->assertEquals(532, $location->metro_code);
        $this->assertEquals(100, $location->accuracy_radius);
        $this->assertFalse($location->is_in_european_union);
        $this->assertEquals(['M5V', 'M5H', 'M5J'], $location->postal_codes);
        $this->assertEquals(['source' => 'maxmind', 'version' => '2025.01'], $location->metadata);
    }

    #[Test]
    public function it_has_ip_blocks_relationship(): void
    {
        $location = GeoLite2Location::create([
            'geoname_id' => 555555,
            'locale_code' => 'en',
            'country_iso_code' => 'GB',
            'country_name' => 'United Kingdom',
        ]);

        GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'geoname_id' => 555555,
        ]);

        GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
            'geoname_id' => 555555,
        ]);

        $ipBlocks = $location->ipBlocks;
        
        $this->assertCount(2, $ipBlocks);
        $this->assertEquals('192.168.1.0/24', $ipBlocks->first()->network);
        $this->assertEquals(555555, $ipBlocks->first()->geoname_id);
    }

    #[Test]
    public function scope_by_geoname_id_filters_correctly(): void
    {
        GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'country_iso_code' => 'US',
        ]);

        GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'country_iso_code' => 'CA',
        ]);

        $location = GeoLite2Location::byGeonameId(111111)->first();

        $this->assertNotNull($location);
        $this->assertEquals(111111, $location->geoname_id);
        $this->assertEquals('US', $location->country_iso_code);
    }

    #[Test]
    public function scope_by_country_filters_correctly(): void
    {
        GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'country_iso_code' => 'US',
            'country_name' => 'United States',
        ]);

        GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'country_iso_code' => 'CA',
            'country_name' => 'Canada',
        ]);

        $usLocations = GeoLite2Location::byCountry('US')->get();
        $caLocations = GeoLite2Location::byCountry('CA')->get();

        $this->assertCount(1, $usLocations);
        $this->assertCount(1, $caLocations);
        $this->assertEquals('United States', $usLocations->first()->country_name);
        $this->assertEquals('Canada', $caLocations->first()->country_name);
    }

    #[Test]
    public function scope_by_continent_filters_correctly(): void
    {
        GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'continent_code' => 'NA',
            'continent_name' => 'North America',
        ]);

        GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'continent_code' => 'EU',
            'continent_name' => 'Europe',
        ]);

        $naLocations = GeoLite2Location::byContinent('NA')->get();
        $euLocations = GeoLite2Location::byContinent('EU')->get();

        $this->assertCount(1, $naLocations);
        $this->assertCount(1, $euLocations);
        $this->assertEquals('North America', $naLocations->first()->continent_name);
        $this->assertEquals('Europe', $euLocations->first()->continent_name);
    }

    #[Test]
    public function scope_by_city_filters_correctly(): void
    {
        GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'city_name' => 'New York',
        ]);

        GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'city_name' => 'Los Angeles',
        ]);

        $nyLocations = GeoLite2Location::byCity('New York')->get();
        $partialSearch = GeoLite2Location::byCity('York')->get();

        $this->assertCount(1, $nyLocations);
        $this->assertCount(1, $partialSearch); // Should match "New York"
        $this->assertEquals('New York', $nyLocations->first()->city_name);
    }

    #[Test]
    public function scope_european_union_filters_correctly(): void
    {
        GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'country_iso_code' => 'DE',
            'is_in_european_union' => true,
        ]);

        GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'country_iso_code' => 'US',
            'is_in_european_union' => false,
        ]);

        $euLocations = GeoLite2Location::europeanUnion()->get();

        $this->assertCount(1, $euLocations);
        $this->assertEquals('DE', $euLocations->first()->country_iso_code);
        $this->assertTrue($euLocations->first()->is_in_european_union);
    }

    #[Test]
    public function scope_with_coordinates_filters_correctly(): void
    {
        GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'latitude' => null,
            'longitude' => null,
        ]);

        $locationsWithCoords = GeoLite2Location::withCoordinates()->get();

        $this->assertCount(1, $locationsWithCoords);
        $this->assertNotNull($locationsWithCoords->first()->latitude);
        $this->assertNotNull($locationsWithCoords->first()->longitude);
    }

    #[Test]
    public function scope_within_bounds_filters_correctly(): void
    {
        GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'city_name' => 'New York',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'city_name' => 'Los Angeles',
            'latitude' => 34.0522,
            'longitude' => -118.2437,
        ]);

        // Bounds around New York area
        $locationsInBounds = GeoLite2Location::withinBounds(40.0, 41.0, -75.0, -73.0)->get();

        $this->assertCount(1, $locationsInBounds);
        $this->assertEquals('New York', $locationsInBounds->first()->city_name);
    }

    #[Test]
    public function get_full_location_name_formats_correctly(): void
    {
        $location = GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'city_name' => 'Toronto',
            'subdivision_1_name' => 'Ontario',
            'country_name' => 'Canada',
        ]);

        $this->assertEquals('Toronto, Ontario, Canada', $location->getFullLocationName());

        $partialLocation = GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'country_name' => 'United States',
        ]);

        $this->assertEquals('United States', $partialLocation->getFullLocationName());

        $unknownLocation = GeoLite2Location::create([
            'geoname_id' => 333333,
            'locale_code' => 'en',
        ]);

        $this->assertEquals('Unknown Location', $unknownLocation->getFullLocationName());
    }

    #[Test]
    public function get_short_location_name_returns_correct_value(): void
    {
        $cityLocation = GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'city_name' => 'Paris',
            'country_name' => 'France',
        ]);

        $this->assertEquals('Paris', $cityLocation->getShortLocationName());

        $countryOnlyLocation = GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'country_name' => 'Japan',
        ]);

        $this->assertEquals('Japan', $countryOnlyLocation->getShortLocationName());

        $unknownLocation = GeoLite2Location::create([
            'geoname_id' => 333333,
            'locale_code' => 'en',
        ]);

        $this->assertEquals('Unknown', $unknownLocation->getShortLocationName());
    }

    #[Test]
    public function has_coordinates_method_works_correctly(): void
    {
        $withCoordinates = GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
        ]);

        $withoutCoordinates = GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'latitude' => null,
            'longitude' => null,
        ]);

        $partialCoordinates = GeoLite2Location::create([
            'geoname_id' => 333333,
            'locale_code' => 'en',
            'latitude' => 40.7128,
            'longitude' => null,
        ]);

        $this->assertTrue($withCoordinates->hasCoordinates());
        $this->assertFalse($withoutCoordinates->hasCoordinates());
        $this->assertFalse($partialCoordinates->hasCoordinates());
    }

    #[Test]
    public function distance_to_calculates_correctly(): void
    {
        $newYork = GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'city_name' => 'New York',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $london = GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'city_name' => 'London',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
        ]);

        $noCoordinates = GeoLite2Location::create([
            'geoname_id' => 333333,
            'locale_code' => 'en',
            'city_name' => 'Unknown',
        ]);

        $distance = $newYork->distanceTo($london);

        $this->assertIsFloat($distance);
        $this->assertGreaterThan(5000, $distance); // Should be roughly 5585 km
        $this->assertLessThan(6000, $distance);

        // Test with location without coordinates
        $this->assertNull($newYork->distanceTo($noCoordinates));
        $this->assertNull($noCoordinates->distanceTo($london));
    }

    #[Test]
    public function get_hierarchy_returns_correct_structure(): void
    {
        $location = GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'continent_code' => 'NA',
            'continent_name' => 'North America',
            'country_iso_code' => 'US',
            'country_name' => 'United States',
            'subdivision_1_iso_code' => 'NY',
            'subdivision_1_name' => 'New York',
            'subdivision_2_iso_code' => 'NYC',
            'subdivision_2_name' => 'New York City',
            'city_name' => 'Manhattan',
        ]);

        $hierarchy = $location->getHierarchy();

        $this->assertIsArray($hierarchy);
        $this->assertArrayHasKey('continent', $hierarchy);
        $this->assertArrayHasKey('country', $hierarchy);
        $this->assertArrayHasKey('subdivision_1', $hierarchy);
        $this->assertArrayHasKey('subdivision_2', $hierarchy);
        $this->assertArrayHasKey('city', $hierarchy);

        $this->assertEquals(['code' => 'NA', 'name' => 'North America'], $hierarchy['continent']);
        $this->assertEquals(['code' => 'US', 'name' => 'United States'], $hierarchy['country']);
        $this->assertEquals(['code' => 'NY', 'name' => 'New York'], $hierarchy['subdivision_1']);
        $this->assertEquals(['code' => 'NYC', 'name' => 'New York City'], $hierarchy['subdivision_2']);
        $this->assertEquals('Manhattan', $hierarchy['city']);
    }

    #[Test]
    public function get_coordinates_returns_correct_data(): void
    {
        $withCoordinates = GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy_radius' => 100,
        ]);

        $withoutCoordinates = GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
        ]);

        $coordinates = $withCoordinates->getCoordinates();

        $this->assertIsArray($coordinates);
        $this->assertArrayHasKey('latitude', $coordinates);
        $this->assertArrayHasKey('longitude', $coordinates);
        $this->assertArrayHasKey('accuracy_radius', $coordinates);

        $this->assertEquals(40.7128, $coordinates['latitude']);
        $this->assertEquals(-74.0060, $coordinates['longitude']);
        $this->assertEquals(100, $coordinates['accuracy_radius']);

        $this->assertNull($withoutCoordinates->getCoordinates());
    }

    #[Test]
    public function is_in_region_method_works_correctly(): void
    {
        $euLocation = GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'continent_code' => 'EU',
            'is_in_european_union' => true,
        ]);

        $naLocation = GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'continent_code' => 'NA',
            'is_in_european_union' => false,
        ]);

        $asLocation = GeoLite2Location::create([
            'geoname_id' => 333333,
            'locale_code' => 'en',
            'continent_code' => 'AS',
            'is_in_european_union' => false,
        ]);

        $this->assertTrue($euLocation->isInRegion('eu'));
        $this->assertTrue($euLocation->isInRegion('european_union'));
        $this->assertTrue($euLocation->isInRegion('europe'));

        $this->assertTrue($naLocation->isInRegion('north_america'));
        $this->assertFalse($naLocation->isInRegion('eu'));

        $this->assertTrue($asLocation->isInRegion('asia'));
        $this->assertFalse($asLocation->isInRegion('europe'));
        $this->assertFalse($asLocation->isInRegion('north_america'));
    }

    #[Test]
    public function scope_by_subdivision_filters_correctly(): void
    {
        GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'subdivision_1_iso_code' => 'CA',
            'subdivision_1_name' => 'California',
        ]);

        GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'subdivision_1_iso_code' => 'NY',
            'subdivision_1_name' => 'New York',
            'subdivision_2_iso_code' => 'NYC',
            'subdivision_2_name' => 'New York City',
        ]);

        $caLocations = GeoLite2Location::bySubdivision('CA', 1)->get();
        $nycLocations = GeoLite2Location::bySubdivision('NYC', 2)->get();

        $this->assertCount(1, $caLocations);
        $this->assertCount(1, $nycLocations);
        $this->assertEquals('California', $caLocations->first()->subdivision_1_name);
        $this->assertEquals('New York City', $nycLocations->first()->subdivision_2_name);
    }

    #[Test]
    public function scope_by_timezone_filters_correctly(): void
    {
        GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'time_zone' => 'America/New_York',
        ]);

        GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'time_zone' => 'Europe/London',
        ]);

        $nyTimezone = GeoLite2Location::byTimezone('America/New_York')->get();
        $londonTimezone = GeoLite2Location::byTimezone('Europe/London')->get();

        $this->assertCount(1, $nyTimezone);
        $this->assertCount(1, $londonTimezone);
        $this->assertEquals('America/New_York', $nyTimezone->first()->time_zone);
        $this->assertEquals('Europe/London', $londonTimezone->first()->time_zone);
    }

    #[Test]
    public function scope_recently_updated_filters_correctly(): void
    {
        GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'data_updated_at' => now()->subDays(5),
        ]);

        GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'data_updated_at' => now()->subDays(35),
        ]);

        $recentlyUpdated = GeoLite2Location::recentlyUpdated(30)->get();

        $this->assertCount(1, $recentlyUpdated);
        $this->assertTrue($recentlyUpdated->first()->data_updated_at->isAfter(now()->subDays(30)));
    }
}
