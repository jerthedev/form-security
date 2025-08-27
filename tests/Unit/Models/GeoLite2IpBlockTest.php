<?php

/**
 * Test File: GeoLite2IpBlockTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-019-geolite2-database-management
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Comprehensive unit tests for the GeoLite2IpBlock model including
 * IP lookup functionality, network calculations, relationships, and performance validation.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Specialized-Features/SPEC-019-geolite2-database-management.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Models;

use JTD\FormSecurity\Models\GeoLite2IpBlock;
use JTD\FormSecurity\Models\GeoLite2Location;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;

#[Group('sprint-002')]
#[Group('epic-001')]
#[Group('database')]
#[Group('models')]
#[Group('ticket-1021')]
#[Group('geolite2-ipblock')]
class GeoLite2IpBlockTest extends TestCase
{
    #[Test]
    public function it_can_create_geolite2_ip_block_with_required_fields(): void
    {
        $ipBlock = GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
        ]);

        $this->assertInstanceOf(GeoLite2IpBlock::class, $ipBlock);
        $this->assertEquals('192.168.1.0/24', $ipBlock->network);
        $this->assertEquals(ip2long('192.168.1.0'), $ipBlock->network_start_integer);
        $this->assertEquals(ip2long('192.168.1.255'), $ipBlock->network_last_integer);
    }

    #[Test]
    public function it_casts_attributes_correctly(): void
    {
        $ipBlock = GeoLite2IpBlock::create([
            'network' => '10.0.0.0/16',
            'network_start_integer' => '167772160', // ip2long('10.0.0.0')
            'network_last_integer' => '167837695',  // ip2long('10.0.255.255')
            'geoname_id' => '123456',
            'registered_country_geoname_id' => '789012',
            'represented_country_geoname_id' => '345678',
            'accuracy_radius' => '100',
            'is_anonymous_proxy' => '1',
            'is_satellite_provider' => '0',
            'is_anycast' => 'true',
            'latitude' => '40.7128',
            'longitude' => '-74.0060',
            'data_updated_at' => '2025-01-27 12:00:00',
            'metadata' => ['source' => 'maxmind', 'version' => '2025.01'],
        ]);

        $this->assertIsInt($ipBlock->network_start_integer);
        $this->assertIsInt($ipBlock->network_last_integer);
        $this->assertIsInt($ipBlock->geoname_id);
        $this->assertIsInt($ipBlock->registered_country_geoname_id);
        $this->assertIsInt($ipBlock->represented_country_geoname_id);
        $this->assertIsInt($ipBlock->accuracy_radius);
        $this->assertIsBool($ipBlock->is_anonymous_proxy);
        $this->assertIsBool($ipBlock->is_satellite_provider);
        $this->assertIsBool($ipBlock->is_anycast);
        $this->assertTrue(is_float($ipBlock->latitude) || is_string($ipBlock->latitude));
        $this->assertTrue(is_float($ipBlock->longitude) || is_string($ipBlock->longitude));
        $this->assertInstanceOf(Carbon::class, $ipBlock->data_updated_at);
        $this->assertIsArray($ipBlock->metadata);

        $this->assertEquals(167772160, $ipBlock->network_start_integer);
        $this->assertEquals(167837695, $ipBlock->network_last_integer);
        $this->assertEquals(123456, $ipBlock->geoname_id);
        $this->assertEquals(100, $ipBlock->accuracy_radius);
        $this->assertTrue($ipBlock->is_anonymous_proxy);
        $this->assertFalse($ipBlock->is_satellite_provider);
        $this->assertTrue($ipBlock->is_anycast);
    }

    #[Test]
    public function it_has_location_relationships(): void
    {
        $location = GeoLite2Location::create([
            'geoname_id' => 555555,
            'locale_code' => 'en',
            'country_iso_code' => 'US',
            'country_name' => 'United States',
        ]);

        $registeredCountry = GeoLite2Location::create([
            'geoname_id' => 666666,
            'locale_code' => 'en',
            'country_iso_code' => 'CA',
            'country_name' => 'Canada',
        ]);

        $ipBlock = GeoLite2IpBlock::create([
            'network' => '203.0.113.0/24',
            'network_start_integer' => ip2long('203.0.113.0'),
            'network_last_integer' => ip2long('203.0.113.255'),
            'geoname_id' => 555555,
            'registered_country_geoname_id' => 666666,
        ]);

        $this->assertInstanceOf(GeoLite2Location::class, $ipBlock->location);
        $this->assertEquals('United States', $ipBlock->location->country_name);
        
        $this->assertInstanceOf(GeoLite2Location::class, $ipBlock->registeredCountry);
        $this->assertEquals('Canada', $ipBlock->registeredCountry->country_name);
    }

    #[Test]
    public function scope_containing_ip_finds_correct_block(): void
    {
        GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
        ]);

        GeoLite2IpBlock::create([
            'network' => '10.0.0.0/16',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.255.255'),
        ]);

        $block = GeoLite2IpBlock::containingIp('192.168.1.100')->first();
        
        $this->assertNotNull($block);
        $this->assertEquals('192.168.1.0/24', $block->network);
        $this->assertTrue($block->containsIp('192.168.1.100'));

        $block2 = GeoLite2IpBlock::containingIp('10.0.50.25')->first();
        
        $this->assertNotNull($block2);
        $this->assertEquals('10.0.0.0/16', $block2->network);
        $this->assertTrue($block2->containsIp('10.0.50.25'));

        // Test IP not in any block
        $noBlock = GeoLite2IpBlock::containingIp('8.8.8.8')->first();
        $this->assertNull($noBlock);
    }

    #[Test]
    public function scope_by_geoname_id_filters_correctly(): void
    {
        GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'geoname_id' => 111111,
        ]);

        GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
            'geoname_id' => 222222,
        ]);

        $blocks = GeoLite2IpBlock::byGeonameId(111111)->get();

        $this->assertCount(1, $blocks);
        $this->assertEquals('192.168.1.0/24', $blocks->first()->network);
        $this->assertEquals(111111, $blocks->first()->geoname_id);
    }

    #[Test]
    public function scope_by_network_type_filters_correctly(): void
    {
        GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'is_anonymous_proxy' => true,
            'is_satellite_provider' => false,
            'is_anycast' => false,
        ]);

        GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
            'is_anonymous_proxy' => false,
            'is_satellite_provider' => true,
            'is_anycast' => false,
        ]);

        GeoLite2IpBlock::create([
            'network' => '172.16.0.0/24',
            'network_start_integer' => ip2long('172.16.0.0'),
            'network_last_integer' => ip2long('172.16.0.255'),
            'is_anonymous_proxy' => false,
            'is_satellite_provider' => false,
            'is_anycast' => true,
        ]);

        $proxyBlocks = GeoLite2IpBlock::byNetworkType('proxy')->get();
        $satelliteBlocks = GeoLite2IpBlock::byNetworkType('satellite')->get();
        $anycastBlocks = GeoLite2IpBlock::byNetworkType('anycast')->get();

        $this->assertCount(1, $proxyBlocks);
        $this->assertCount(1, $satelliteBlocks);
        $this->assertCount(1, $anycastBlocks);
        
        $this->assertTrue($proxyBlocks->first()->is_anonymous_proxy);
        $this->assertTrue($satelliteBlocks->first()->is_satellite_provider);
        $this->assertTrue($anycastBlocks->first()->is_anycast);
    }

    #[Test]
    public function scope_anonymous_proxy_filters_correctly(): void
    {
        GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'is_anonymous_proxy' => true,
        ]);

        GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
            'is_anonymous_proxy' => false,
        ]);

        $proxyBlocks = GeoLite2IpBlock::anonymousProxy()->get();

        $this->assertCount(1, $proxyBlocks);
        $this->assertTrue($proxyBlocks->first()->is_anonymous_proxy);
    }

    #[Test]
    public function scope_with_coordinates_filters_correctly(): void
    {
        GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
            'latitude' => null,
            'longitude' => null,
        ]);

        $blocksWithCoords = GeoLite2IpBlock::withCoordinates()->get();

        $this->assertCount(1, $blocksWithCoords);
        $this->assertNotNull($blocksWithCoords->first()->latitude);
        $this->assertNotNull($blocksWithCoords->first()->longitude);
    }

    #[Test]
    public function contains_ip_method_works_correctly(): void
    {
        $ipBlock = GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
        ]);

        $this->assertTrue($ipBlock->containsIp('192.168.1.0'));   // Start of range
        $this->assertTrue($ipBlock->containsIp('192.168.1.100')); // Middle of range
        $this->assertTrue($ipBlock->containsIp('192.168.1.255')); // End of range
        
        $this->assertFalse($ipBlock->containsIp('192.168.0.255')); // Just before range
        $this->assertFalse($ipBlock->containsIp('192.168.2.0'));   // Just after range
        $this->assertFalse($ipBlock->containsIp('10.0.0.1'));      // Completely different
    }

    #[Test]
    public function get_network_size_calculates_correctly(): void
    {
        $block24 = GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
        ]);

        $block16 = GeoLite2IpBlock::create([
            'network' => '10.0.0.0/16',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.255.255'),
        ]);

        $this->assertEquals(256, $block24->getNetworkSize());    // /24 = 256 IPs
        $this->assertEquals(65536, $block16->getNetworkSize());  // /16 = 65536 IPs
    }

    #[Test]
    public function get_cidr_prefix_calculates_correctly(): void
    {
        $block24 = GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
        ]);

        $block16 = GeoLite2IpBlock::create([
            'network' => '10.0.0.0/16',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.255.255'),
        ]);

        $this->assertEquals(24, $block24->getCidrPrefix());
        $this->assertEquals(16, $block16->getCidrPrefix());
    }

    #[Test]
    public function get_network_and_broadcast_addresses_work_correctly(): void
    {
        $ipBlock = GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
        ]);

        $this->assertEquals('192.168.1.0', $ipBlock->getNetworkAddress());
        $this->assertEquals('192.168.1.255', $ipBlock->getBroadcastAddress());
    }

    #[Test]
    public function is_special_network_method_works_correctly(): void
    {
        $normalBlock = GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'is_anonymous_proxy' => false,
            'is_satellite_provider' => false,
            'is_anycast' => false,
        ]);

        $specialBlock = GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
            'is_anonymous_proxy' => true,
            'is_satellite_provider' => false,
            'is_anycast' => false,
        ]);

        $this->assertFalse($normalBlock->isSpecialNetwork());
        $this->assertTrue($specialBlock->isSpecialNetwork());
    }

    #[Test]
    public function get_network_type_description_returns_correct_values(): void
    {
        $normalBlock = GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'is_anonymous_proxy' => false,
            'is_satellite_provider' => false,
            'is_anycast' => false,
        ]);

        $multiTypeBlock = GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
            'is_anonymous_proxy' => true,
            'is_satellite_provider' => true,
            'is_anycast' => false,
        ]);

        $this->assertEquals('Standard', $normalBlock->getNetworkTypeDescription());
        $this->assertEquals('Anonymous Proxy, Satellite Provider', $multiTypeBlock->getNetworkTypeDescription());
    }

    #[Test]
    public function has_coordinates_method_works_correctly(): void
    {
        $withCoordinates = GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $withoutCoordinates = GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->assertTrue($withCoordinates->hasCoordinates());
        $this->assertFalse($withoutCoordinates->hasCoordinates());
    }

    #[Test]
    public function get_coordinates_returns_correct_data(): void
    {
        $withCoordinates = GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy_radius' => 100,
        ]);

        $withoutCoordinates = GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
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
    public function get_network_info_returns_comprehensive_data(): void
    {
        $ipBlock = GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'geoname_id' => 123456,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'is_anonymous_proxy' => true,
        ]);

        $networkInfo = $ipBlock->getNetworkInfo();

        $this->assertIsArray($networkInfo);
        $this->assertArrayHasKey('network', $networkInfo);
        $this->assertArrayHasKey('start_ip', $networkInfo);
        $this->assertArrayHasKey('end_ip', $networkInfo);
        $this->assertArrayHasKey('size', $networkInfo);
        $this->assertArrayHasKey('cidr_prefix', $networkInfo);
        $this->assertArrayHasKey('type', $networkInfo);
        $this->assertArrayHasKey('has_location', $networkInfo);
        $this->assertArrayHasKey('has_coordinates', $networkInfo);

        $this->assertEquals('192.168.1.0/24', $networkInfo['network']);
        $this->assertEquals('192.168.1.0', $networkInfo['start_ip']);
        $this->assertEquals('192.168.1.255', $networkInfo['end_ip']);
        $this->assertEquals(256, $networkInfo['size']);
        $this->assertEquals(24, $networkInfo['cidr_prefix']);
        $this->assertEquals('Anonymous Proxy', $networkInfo['type']);
        $this->assertTrue($networkInfo['has_location']);
        $this->assertTrue($networkInfo['has_coordinates']);
    }

    #[Test]
    public function find_by_ip_static_method_works_correctly(): void
    {
        GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
        ]);

        GeoLite2IpBlock::create([
            'network' => '10.0.0.0/16',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.255.255'),
        ]);

        $block = GeoLite2IpBlock::findByIp('192.168.1.100');

        $this->assertInstanceOf(GeoLite2IpBlock::class, $block);
        $this->assertEquals('192.168.1.0/24', $block->network);
        $this->assertTrue($block->containsIp('192.168.1.100'));

        $noBlock = GeoLite2IpBlock::findByIp('8.8.8.8');
        $this->assertNull($noBlock);
    }

    #[Test]
    public function scope_within_bounds_filters_correctly(): void
    {
        GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'latitude' => 40.7128,  // New York
            'longitude' => -74.0060,
        ]);

        GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
            'latitude' => 34.0522,  // Los Angeles
            'longitude' => -118.2437,
        ]);

        // Bounds around New York area
        $blocksInBounds = GeoLite2IpBlock::withinBounds(40.0, 41.0, -75.0, -73.0)->get();

        $this->assertCount(1, $blocksInBounds);
        $this->assertEquals('192.168.1.0/24', $blocksInBounds->first()->network);
    }

    #[Test]
    public function scope_by_postal_code_filters_correctly(): void
    {
        GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'postal_code' => '10001',
        ]);

        GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
            'postal_code' => '90210',
        ]);

        $blocks = GeoLite2IpBlock::byPostalCode('10001')->get();

        $this->assertCount(1, $blocks);
        $this->assertEquals('192.168.1.0/24', $blocks->first()->network);
        $this->assertEquals('10001', $blocks->first()->postal_code);
    }

    #[Test]
    public function scope_recently_updated_filters_correctly(): void
    {
        GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'data_updated_at' => now()->subDays(5),
        ]);

        GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
            'data_updated_at' => now()->subDays(35),
        ]);

        $recentlyUpdated = GeoLite2IpBlock::recentlyUpdated(30)->get();

        $this->assertCount(1, $recentlyUpdated);
        $this->assertTrue($recentlyUpdated->first()->data_updated_at->isAfter(now()->subDays(30)));
    }

    #[Test]
    public function ip_lookup_performance_is_acceptable(): void
    {
        // Create multiple IP blocks for performance testing
        for ($i = 1; $i <= 100; $i++) {
            GeoLite2IpBlock::create([
                'network' => "192.168.{$i}.0/24",
                'network_start_integer' => ip2long("192.168.{$i}.0"),
                'network_last_integer' => ip2long("192.168.{$i}.255"),
            ]);
        }

        $startTime = microtime(true);

        // Perform multiple IP lookups
        for ($i = 1; $i <= 10; $i++) {
            $block = GeoLite2IpBlock::findByIp("192.168.{$i}.100");
            $this->assertNotNull($block);
        }

        $endTime = microtime(true);
        $processingTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Should complete within 100ms as per project requirements
        $this->assertLessThan(100, $processingTime,
            "IP lookup performance took {$processingTime}ms, should be < 100ms");
    }
}
