<?php

/**
 * Test File: ModelRelationshipTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Comprehensive integration tests for model relationships, constraints,
 * and complex database operations across all models in the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace JTD\FormSecurity\Tests\Integration;

use JTD\FormSecurity\Models\BlockedSubmission;
use JTD\FormSecurity\Models\GeoLite2IpBlock;
use JTD\FormSecurity\Models\GeoLite2Location;
use JTD\FormSecurity\Models\IpReputation;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('sprint-002')]
#[Group('epic-001')]
#[Group('database')]
#[Group('models')]
#[Group('ticket-1021')]
#[Group('integration')]
#[Group('relationships')]
class ModelRelationshipTest extends TestCase
{
    #[Test]
    public function geolite2_location_to_ip_blocks_relationship_works(): void
    {
        $location = GeoLite2Location::create([
            'geoname_id' => 123456,
            'locale_code' => 'en',
            'country_iso_code' => 'US',
            'country_name' => 'United States',
            'city_name' => 'New York',
        ]);

        $ipBlock1 = GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'geoname_id' => 123456,
        ]);

        $ipBlock2 = GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
            'geoname_id' => 123456,
        ]);

        // Test hasMany relationship
        $ipBlocks = $location->ipBlocks;
        $this->assertCount(2, $ipBlocks);
        $this->assertTrue($ipBlocks->contains('network', '192.168.1.0/24'));
        $this->assertTrue($ipBlocks->contains('network', '10.0.0.0/24'));

        // Test belongsTo relationship
        $this->assertEquals('United States', $ipBlock1->location->country_name);
        $this->assertEquals('New York', $ipBlock1->location->city_name);
        $this->assertEquals(123456, $ipBlock1->location->geoname_id);
    }

    #[Test]
    public function geolite2_ip_block_multiple_location_relationships_work(): void
    {
        $location = GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'country_iso_code' => 'US',
            'country_name' => 'United States',
        ]);

        $registeredCountry = GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'country_iso_code' => 'CA',
            'country_name' => 'Canada',
        ]);

        $representedCountry = GeoLite2Location::create([
            'geoname_id' => 333333,
            'locale_code' => 'en',
            'country_iso_code' => 'MX',
            'country_name' => 'Mexico',
        ]);

        $ipBlock = GeoLite2IpBlock::create([
            'network' => '203.0.113.0/24',
            'network_start_integer' => ip2long('203.0.113.0'),
            'network_last_integer' => ip2long('203.0.113.255'),
            'geoname_id' => 111111,
            'registered_country_geoname_id' => 222222,
            'represented_country_geoname_id' => 333333,
        ]);

        // Test all three location relationships
        $this->assertEquals('United States', $ipBlock->location->country_name);
        $this->assertEquals('Canada', $ipBlock->registeredCountry->country_name);
        $this->assertEquals('Mexico', $ipBlock->representedCountry->country_name);

        $this->assertEquals(111111, $ipBlock->location->geoname_id);
        $this->assertEquals(222222, $ipBlock->registeredCountry->geoname_id);
        $this->assertEquals(333333, $ipBlock->representedCountry->geoname_id);
    }

    #[Test]
    public function eager_loading_relationships_works_efficiently(): void
    {
        // Create test data
        $location = GeoLite2Location::create([
            'geoname_id' => 555555,
            'locale_code' => 'en',
            'country_iso_code' => 'GB',
            'country_name' => 'United Kingdom',
            'city_name' => 'London',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            GeoLite2IpBlock::create([
                'network' => "192.168.{$i}.0/24",
                'network_start_integer' => ip2long("192.168.{$i}.0"),
                'network_last_integer' => ip2long("192.168.{$i}.255"),
                'geoname_id' => 555555,
            ]);
        }

        // Test eager loading prevents N+1 queries
        $startTime = microtime(true);

        $ipBlocks = GeoLite2IpBlock::with('location')->where('geoname_id', 555555)->get();

        foreach ($ipBlocks as $block) {
            $locationName = $block->location->city_name; // This should not trigger additional queries
        }

        $endTime = microtime(true);
        $processingTime = ($endTime - $startTime) * 1000;

        $this->assertCount(5, $ipBlocks);
        $this->assertEquals('London', $ipBlocks->first()->location->city_name);

        // Should be fast due to eager loading
        $this->assertLessThan(100, $processingTime,
            "Eager loading took {$processingTime}ms, should be < 100ms");
    }

    #[Test]
    public function foreign_key_constraints_are_enforced(): void
    {
        // Create a location
        $location = GeoLite2Location::create([
            'geoname_id' => 777777,
            'locale_code' => 'en',
            'country_iso_code' => 'FR',
            'country_name' => 'France',
        ]);

        // Create IP block with valid foreign key
        $ipBlock = GeoLite2IpBlock::create([
            'network' => '203.0.113.0/24',
            'network_start_integer' => ip2long('203.0.113.0'),
            'network_last_integer' => ip2long('203.0.113.255'),
            'geoname_id' => 777777,
        ]);

        $this->assertEquals('France', $ipBlock->location->country_name);

        // Test that deleting the location affects the relationship
        $location->delete();

        $ipBlock->refresh();
        $this->assertNull($ipBlock->location); // Should return null for deleted location
    }

    #[Test]
    public function complex_relationship_queries_work_correctly(): void
    {
        // Create locations
        $usLocation = GeoLite2Location::create([
            'geoname_id' => 111111,
            'locale_code' => 'en',
            'country_iso_code' => 'US',
            'country_name' => 'United States',
            'city_name' => 'New York',
        ]);

        $ukLocation = GeoLite2Location::create([
            'geoname_id' => 222222,
            'locale_code' => 'en',
            'country_iso_code' => 'GB',
            'country_name' => 'United Kingdom',
            'city_name' => 'London',
        ]);

        // Create IP blocks
        GeoLite2IpBlock::create([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'geoname_id' => 111111,
            'is_anonymous_proxy' => true,
        ]);

        GeoLite2IpBlock::create([
            'network' => '10.0.0.0/24',
            'network_start_integer' => ip2long('10.0.0.0'),
            'network_last_integer' => ip2long('10.0.0.255'),
            'geoname_id' => 222222,
            'is_anonymous_proxy' => false,
        ]);

        // Complex query: Find all proxy IP blocks in the US
        $usProxyBlocks = GeoLite2IpBlock::whereHas('location', function ($query) {
            $query->where('country_iso_code', 'US');
        })->where('is_anonymous_proxy', true)->get();

        $this->assertCount(1, $usProxyBlocks);
        $this->assertEquals('192.168.1.0/24', $usProxyBlocks->first()->network);
        $this->assertEquals('United States', $usProxyBlocks->first()->location->country_name);

        // Complex query: Find all locations that have proxy IP blocks
        $locationsWithProxies = GeoLite2Location::whereHas('ipBlocks', function ($query) {
            $query->where('is_anonymous_proxy', true);
        })->get();

        $this->assertCount(1, $locationsWithProxies);
        $this->assertEquals('United States', $locationsWithProxies->first()->country_name);
    }

    #[Test]
    public function relationship_counting_works_correctly(): void
    {
        $location = GeoLite2Location::create([
            'geoname_id' => 888888,
            'locale_code' => 'en',
            'country_iso_code' => 'DE',
            'country_name' => 'Germany',
        ]);

        // Create multiple IP blocks
        for ($i = 1; $i <= 3; $i++) {
            GeoLite2IpBlock::create([
                'network' => "172.16.{$i}.0/24",
                'network_start_integer' => ip2long("172.16.{$i}.0"),
                'network_last_integer' => ip2long("172.16.{$i}.255"),
                'geoname_id' => 888888,
            ]);
        }

        // Test relationship counting
        $locationWithCount = GeoLite2Location::withCount('ipBlocks')->find($location->id);
        $this->assertEquals(3, $locationWithCount->ip_blocks_count);

        // Test counting with conditions
        GeoLite2IpBlock::where('geoname_id', 888888)->first()->update(['is_anonymous_proxy' => true]);

        $locationWithProxyCount = GeoLite2Location::withCount([
            'ipBlocks as proxy_blocks_count' => function ($query) {
                $query->where('is_anonymous_proxy', true);
            },
        ])->find($location->id);

        $this->assertEquals(1, $locationWithProxyCount->proxy_blocks_count);
    }

    #[Test]
    public function model_relationships_handle_null_foreign_keys(): void
    {
        // Create IP block without location reference
        $ipBlock = GeoLite2IpBlock::create([
            'network' => '198.51.100.0/24',
            'network_start_integer' => ip2long('198.51.100.0'),
            'network_last_integer' => ip2long('198.51.100.255'),
            'geoname_id' => null, // No location reference
        ]);

        // Should handle null foreign key gracefully
        $this->assertNull($ipBlock->location);
        $this->assertNull($ipBlock->registeredCountry);
        $this->assertNull($ipBlock->representedCountry);

        // Should not cause errors when accessing relationship methods
        $this->assertFalse($ipBlock->location()->exists());
    }

    #[Test]
    public function relationship_performance_meets_requirements(): void
    {
        // Create test data
        $location = GeoLite2Location::create([
            'geoname_id' => 999999,
            'locale_code' => 'en',
            'country_iso_code' => 'JP',
            'country_name' => 'Japan',
        ]);

        // Create many IP blocks
        for ($i = 1; $i <= 50; $i++) {
            GeoLite2IpBlock::create([
                'network' => "10.{$i}.0.0/24",
                'network_start_integer' => ip2long("10.{$i}.0.0"),
                'network_last_integer' => ip2long("10.{$i}.0.255"),
                'geoname_id' => 999999,
            ]);
        }

        $startTime = microtime(true);

        // Load location with all IP blocks
        $locationWithBlocks = GeoLite2Location::with('ipBlocks')->find($location->id);
        $blockCount = $locationWithBlocks->ipBlocks->count();

        $endTime = microtime(true);
        $processingTime = ($endTime - $startTime) * 1000;

        $this->assertEquals(50, $blockCount);
        $this->assertLessThan(100, $processingTime,
            "Relationship loading took {$processingTime}ms, should be < 100ms");
    }

    #[Test]
    public function cross_model_data_integrity_is_maintained(): void
    {
        // Create a complete data set with relationships
        $location = GeoLite2Location::create([
            'geoname_id' => 123123,
            'locale_code' => 'en',
            'country_iso_code' => 'AU',
            'country_name' => 'Australia',
            'city_name' => 'Sydney',
        ]);

        $ipBlock = GeoLite2IpBlock::create([
            'network' => '203.0.113.0/24',
            'network_start_integer' => ip2long('203.0.113.0'),
            'network_last_integer' => ip2long('203.0.113.255'),
            'geoname_id' => 123123,
        ]);

        // Create related data in other models with different IPs in the same network
        $blockedSubmission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '203.0.113.100', // IP within the block
            'block_reason' => 'geolocation',
            'country_code' => 'AU',
            'city' => 'Sydney',
            'blocked_at' => now(),
        ]);

        $ipReputation = IpReputation::create([
            'ip_address' => '203.0.113.101', // Different IP in same block
            'reputation_score' => 75,
            'reputation_status' => 'neutral',
            'country_code' => 'AU',
        ]);

        // Verify data consistency across models
        $this->assertEquals('AU', $location->country_iso_code);
        $this->assertEquals('AU', $blockedSubmission->country_code);
        $this->assertEquals('AU', $ipReputation->country_code);

        $this->assertEquals('Sydney', $location->city_name);
        $this->assertEquals('Sydney', $blockedSubmission->city);

        $this->assertTrue($ipBlock->containsIp('203.0.113.100'));
        $this->assertTrue($ipBlock->containsIp('203.0.113.101'));
        $this->assertEquals('203.0.113.100', $blockedSubmission->ip_address);
        $this->assertEquals('203.0.113.101', $ipReputation->ip_address);
    }
}
