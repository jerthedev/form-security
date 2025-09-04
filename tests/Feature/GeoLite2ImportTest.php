<?php

declare(strict_types=1);

/**
 * Test File: GeoLite2ImportTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-019-geolite2-database-management
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Comprehensive feature tests for GeoLite2 database import functionality
 * including chunked imports, memory efficiency validation, and data integrity checks.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Specialized-Features/SPEC-019-geolite2-database-management.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace JTD\FormSecurity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use JTD\FormSecurity\Models\GeoLite2IpBlock;
use JTD\FormSecurity\Models\GeoLite2Location;
use JTD\FormSecurity\Services\GeoLite2ImportService;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('sprint-002')]
#[Group('epic-001')]
#[Group('database')]
#[Group('models')]
#[Group('ticket-1021')]
#[Group('geolite2')]
#[Group('import')]
class GeoLite2ImportTest extends TestCase
{
    private GeoLite2ImportService $importService;

    private string $testDataPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importService = app(GeoLite2ImportService::class);
        $this->testDataPath = __DIR__.'/../storage/GeoLite2-City-CSV_20250722';

        // Ensure test data exists
        if (! is_dir($this->testDataPath)) {
            $this->markTestSkipped('GeoLite2 test data not available');
        }
    }

    #[Test]
    public function it_can_import_geolite2_locations_from_csv(): void
    {
        // Create a small test CSV file instead of using the full database
        // Use exact format from real GeoLite2 file, ensuring all fields are present
        $testCsvContent = "geoname_id,locale_code,continent_code,continent_name,country_iso_code,country_name,subdivision_1_iso_code,subdivision_1_name,subdivision_2_iso_code,subdivision_2_name,city_name,metro_code,time_zone,is_in_european_union\n";
        $testCsvContent .= "5128581,en,NA,North America,US,United States,NY,New York,061,New York County,New York,501,America/New_York,0\n";
        $testCsvContent .= "2643743,en,EU,Europe,GB,United Kingdom,ENG,England,GLA,Greater London,London,\"\",Europe/London,0\n";
        $testCsvContent .= "6167865,en,NA,North America,CA,Canada,ON,Ontario,\"\",\"\",Toronto,416,America/Toronto,0\n";

        $testFile = sys_get_temp_dir().'/test_locations.csv';
        file_put_contents($testFile, $testCsvContent);

        // Import locations
        $result = $this->importService->importLocations($testFile);

        $this->assertIsArray($result, 'Location import should return array');
        $this->assertGreaterThan(0, $result['imported'], 'Should have imported some locations');
        $this->assertEquals(3, $result['imported'], 'Should have imported exactly 3 test locations');

        // Verify data was imported
        $locationCount = GeoLite2Location::count();
        $this->assertEquals(3, $locationCount, 'Should have imported 3 location records');

        // Verify sample location data
        $sampleLocation = GeoLite2Location::where('city_name', 'New York')->first();
        $this->assertNotNull($sampleLocation, 'Should have New York location');
        $this->assertEquals(5128581, $sampleLocation->geoname_id);
        $this->assertEquals('US', $sampleLocation->country_iso_code);
        $this->assertEquals('New York', $sampleLocation->city_name);

        // Clean up
        unlink($testFile);
    }

    #[Test]
    public function it_can_import_geolite2_ip_blocks_from_csv(): void
    {
        // First create and import test locations
        $testLocationsContent = "geoname_id,locale_code,continent_code,continent_name,country_iso_code,country_name,subdivision_1_iso_code,subdivision_1_name,subdivision_2_iso_code,subdivision_2_name,city_name,metro_code,time_zone,is_in_european_union\n";
        $testLocationsContent .= "5128581,en,NA,\"North America\",US,\"United States\",NY,\"New York\",061,\"New York County\",\"New York\",501,\"America/New_York\",0\n";
        $testLocationsContent .= "2643743,en,EU,Europe,GB,\"United Kingdom\",ENG,England,GLA,\"Greater London\",London,,\"Europe/London\",0\n";

        $testLocationsFile = sys_get_temp_dir().'/test_locations.csv';
        file_put_contents($testLocationsFile, $testLocationsContent);
        $this->importService->importLocations($testLocationsFile);

        // Create test IP blocks CSV
        $testIpBlocksContent = "network,geoname_id,registered_country_geoname_id,represented_country_geoname_id,is_anonymous_proxy,is_satellite_provider,postal_code,latitude,longitude,accuracy_radius,is_anycast\n";
        $testIpBlocksContent .= "1.0.0.0/24,5128581,5128581,,0,0,10001,40.7128,-74.0060,100,0\n";
        $testIpBlocksContent .= "2.0.0.0/24,2643743,2643743,,0,0,SW1A,51.5074,-0.1278,50,0\n";
        $testIpBlocksContent .= "3.0.0.0/24,,,5128581,1,0,,,,,1\n";

        $testIpBlocksFile = sys_get_temp_dir().'/test_ipblocks.csv';
        file_put_contents($testIpBlocksFile, $testIpBlocksContent);

        // Import IP blocks
        $result = $this->importService->importIPv4Blocks($testIpBlocksFile);

        $this->assertIsArray($result, 'IP blocks import should return array');
        $this->assertGreaterThan(0, $result['imported'], 'Should have imported some IP blocks');
        $this->assertEquals(3, $result['imported'], 'Should have imported exactly 3 test IP blocks');

        // Verify data was imported
        $blockCount = GeoLite2IpBlock::count();
        $this->assertEquals(3, $blockCount, 'Should have imported 3 IP block records');

        // Verify sample IP block data
        $sampleBlock = GeoLite2IpBlock::where('network', '1.0.0.0/24')->first();
        $this->assertNotNull($sampleBlock, 'Should have 1.0.0.0/24 block');
        $this->assertEquals('1.0.0.0/24', $sampleBlock->network);
        $this->assertEquals(5128581, $sampleBlock->geoname_id);
        $this->assertEquals(ip2long('1.0.0.0'), $sampleBlock->network_start_integer);
        $this->assertEquals(ip2long('1.0.0.255'), $sampleBlock->network_last_integer);

        // Clean up
        unlink($testLocationsFile);
        unlink($testIpBlocksFile);
    }

    #[Test]
    public function it_can_perform_chunked_import_with_memory_efficiency(): void
    {
        // Create small test files for chunked import testing
        $testLocationsContent = "geoname_id,locale_code,continent_code,continent_name,country_iso_code,country_name,subdivision_1_iso_code,subdivision_1_name,subdivision_2_iso_code,subdivision_2_name,city_name,metro_code,time_zone,is_in_european_union\n";
        for ($i = 1; $i <= 10; $i++) {
            $testLocationsContent .= "{$i},en,NA,\"North America\",US,\"United States\",NY,\"New York\",061,\"New York County\",\"Test City {$i}\",501,\"America/New_York\",0\n";
        }

        $testIpBlocksContent = "network,geoname_id,registered_country_geoname_id,represented_country_geoname_id,is_anonymous_proxy,is_satellite_provider,postal_code,latitude,longitude,accuracy_radius,is_anycast\n";
        for ($i = 1; $i <= 10; $i++) {
            $testIpBlocksContent .= "{$i}.0.0.0/24,{$i},{$i},,0,0,1000{$i},40.{$i},-74.{$i},100,0\n";
        }

        $locationsFile = sys_get_temp_dir().'/test_chunked_locations.csv';
        $ipBlocksFile = sys_get_temp_dir().'/test_chunked_ipblocks.csv';
        file_put_contents($locationsFile, $testLocationsContent);
        file_put_contents($ipBlocksFile, $testIpBlocksContent);

        // Test chunked import with small batch size to verify chunking works
        $batchSize = 3; // Small batch size to test chunking

        $startMemory = memory_get_usage(true);

        // Import locations in chunks
        $result = $this->importService->importLocationsChunked($locationsFile, $batchSize);
        $this->assertTrue($result, 'Chunked locations import should succeed');

        // Import IP blocks in chunks
        $result = $this->importService->importIpBlocksChunked($ipBlocksFile, $batchSize);
        $this->assertTrue($result, 'Chunked IP blocks import should succeed');

        $endMemory = memory_get_usage(true);
        $memoryUsed = $endMemory - $startMemory;

        // Memory usage should be reasonable (less than 50MB as per project requirements)
        $maxMemoryMB = 50;
        $memoryUsedMB = $memoryUsed / 1024 / 1024;

        $this->assertLessThan(
            $maxMemoryMB,
            $memoryUsedMB,
            "Memory usage ({$memoryUsedMB}MB) should be less than {$maxMemoryMB}MB"
        );

        // Verify data integrity
        $locationCount = GeoLite2Location::count();
        $blockCount = GeoLite2IpBlock::count();

        $this->assertEquals(10, $locationCount, 'Should have imported 10 locations');
        $this->assertEquals(10, $blockCount, 'Should have imported 10 IP blocks');

        // Clean up
        unlink($locationsFile);
        unlink($ipBlocksFile);
    }

    #[Test]
    public function it_validates_data_integrity_after_import(): void
    {
        // Create test data with known integrity constraints
        $testLocationsContent = "geoname_id,locale_code,continent_code,continent_name,country_iso_code,country_name,subdivision_1_iso_code,subdivision_1_name,subdivision_2_iso_code,subdivision_2_name,city_name,metro_code,time_zone,is_in_european_union\n";
        $testLocationsContent .= "100,en,NA,\"North America\",US,\"United States\",NY,\"New York\",061,\"New York County\",\"New York\",501,\"America/New_York\",0\n";
        $testLocationsContent .= "200,en,EU,Europe,GB,\"United Kingdom\",ENG,England,GLA,\"Greater London\",London,,\"Europe/London\",0\n";

        $testIpBlocksContent = "network,geoname_id,registered_country_geoname_id,represented_country_geoname_id,is_anonymous_proxy,is_satellite_provider,postal_code,latitude,longitude,accuracy_radius,is_anycast\n";
        $testIpBlocksContent .= "1.0.0.0/24,100,100,,0,0,10001,40.7128,-74.0060,100,0\n";
        $testIpBlocksContent .= "2.0.0.0/24,200,200,,0,0,SW1A,51.5074,-0.1278,50,0\n";
        $testIpBlocksContent .= "3.0.0.0/24,,,100,1,0,,,,,1\n"; // Block without location but with represented country

        $locationsFile = sys_get_temp_dir().'/test_integrity_locations.csv';
        $ipBlocksFile = sys_get_temp_dir().'/test_integrity_ipblocks.csv';
        file_put_contents($locationsFile, $testLocationsContent);
        file_put_contents($ipBlocksFile, $testIpBlocksContent);

        // Import data
        $this->importService->importLocations($locationsFile);
        $this->importService->importIPv4Blocks($ipBlocksFile);

        // Test data integrity constraints

        // 1. All locations should have unique geoname_ids
        $totalLocations = GeoLite2Location::count();
        $uniqueGeonameIds = GeoLite2Location::distinct('geoname_id')->count();
        $this->assertEquals($totalLocations, $uniqueGeonameIds, 'All locations should have unique geoname_ids');
        $this->assertEquals(2, $totalLocations, 'Should have imported 2 locations');

        // 2. All IP blocks should have unique networks
        $totalBlocks = GeoLite2IpBlock::count();
        $uniqueNetworks = GeoLite2IpBlock::distinct('network')->count();
        $this->assertEquals($totalBlocks, $uniqueNetworks, 'All IP blocks should have unique networks');
        $this->assertEquals(3, $totalBlocks, 'Should have imported 3 IP blocks');

        // 3. IP block ranges should be valid (start <= end)
        $invalidRanges = GeoLite2IpBlock::whereColumn('network_start_integer', '>', 'network_last_integer')->count();
        $this->assertEquals(0, $invalidRanges, 'All IP block ranges should be valid');

        // 4. Foreign key relationships should be valid for blocks that have geoname_id
        $blocksWithInvalidGeonameId = GeoLite2IpBlock::whereNotNull('geoname_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('geolite2_locations')
                    ->whereColumn('geolite2_locations.geoname_id', 'geolite2_ipv4_blocks.geoname_id');
            })->count();

        $this->assertEquals(0, $blocksWithInvalidGeonameId, 'All IP blocks with geoname_id should reference valid locations');

        // Clean up
        unlink($locationsFile);
        unlink($ipBlocksFile);
    }

    #[Test]
    public function it_can_lookup_ip_geolocation_after_import(): void
    {
        // Create test data with known IP ranges
        $testLocationsContent = "geoname_id,locale_code,continent_code,continent_name,country_iso_code,country_name,subdivision_1_iso_code,subdivision_1_name,subdivision_2_iso_code,subdivision_2_name,city_name,metro_code,time_zone,is_in_european_union\n";
        $testLocationsContent .= "300,en,NA,\"North America\",US,\"United States\",NY,\"New York\",061,\"New York County\",\"New York\",501,\"America/New_York\",0\n";
        $testLocationsContent .= "400,en,EU,Europe,GB,\"United Kingdom\",ENG,England,GLA,\"Greater London\",London,,\"Europe/London\",0\n";

        $testIpBlocksContent = "network,geoname_id,registered_country_geoname_id,represented_country_geoname_id,is_anonymous_proxy,is_satellite_provider,postal_code,latitude,longitude,accuracy_radius,is_anycast\n";
        $testIpBlocksContent .= "8.8.8.0/24,300,300,,0,0,10001,40.7128,-74.0060,100,0\n";  // Contains 8.8.8.8
        $testIpBlocksContent .= "1.1.1.0/24,400,400,,0,0,SW1A,51.5074,-0.1278,50,0\n";    // Contains 1.1.1.1

        $locationsFile = sys_get_temp_dir().'/test_lookup_locations.csv';
        $ipBlocksFile = sys_get_temp_dir().'/test_lookup_ipblocks.csv';
        file_put_contents($locationsFile, $testLocationsContent);
        file_put_contents($ipBlocksFile, $testIpBlocksContent);

        // Import data
        $this->importService->importLocations($locationsFile);
        $this->importService->importIPv4Blocks($ipBlocksFile);

        // Test IP lookup functionality
        $testCases = [
            ['ip' => '8.8.8.8', 'should_find' => true, 'expected_location' => 'New York'],
            ['ip' => '1.1.1.1', 'should_find' => true, 'expected_location' => 'London'],
            ['ip' => '192.168.1.1', 'should_find' => false, 'expected_location' => null], // Not in our test data
        ];

        foreach ($testCases as $testCase) {
            $ipBlock = GeoLite2IpBlock::findByIp($testCase['ip']);

            if ($testCase['should_find']) {
                $this->assertNotNull($ipBlock, "Should find IP block for {$testCase['ip']}");

                // Verify the IP is actually within the block range
                $this->assertTrue(
                    $ipBlock->containsIp($testCase['ip']),
                    "IP {$testCase['ip']} should be within the found block range"
                );

                // Verify location relationship
                $this->assertNotNull($ipBlock->geoname_id, 'Block should have geoname_id');
                $location = $ipBlock->location;
                $this->assertNotNull($location, 'Block should have associated location');
                $this->assertEquals($ipBlock->geoname_id, $location->geoname_id);
                $this->assertEquals($testCase['expected_location'], $location->city_name);
            } else {
                $this->assertNull($ipBlock, "Should not find IP block for {$testCase['ip']}");
            }
        }

        // Clean up
        unlink($locationsFile);
        unlink($ipBlocksFile);
    }

    #[Test]
    public function it_handles_import_performance_requirements(): void
    {
        // Create a moderately sized test file to test performance
        $testLocationsContent = "geoname_id,locale_code,continent_code,continent_name,country_iso_code,country_name,subdivision_1_iso_code,subdivision_1_name,subdivision_2_iso_code,subdivision_2_name,city_name,metro_code,time_zone,is_in_european_union\n";
        for ($i = 1; $i <= 100; $i++) {
            $testLocationsContent .= "{$i},en,NA,\"North America\",US,\"United States\",NY,\"New York\",061,\"New York County\",\"Test City {$i}\",501,\"America/New_York\",0\n";
        }

        $locationsFile = sys_get_temp_dir().'/test_performance_locations.csv';
        file_put_contents($locationsFile, $testLocationsContent);

        // Test import performance (should complete within reasonable time)
        $startTime = microtime(true);

        $result = $this->importService->importLocationsChunked($locationsFile, 25);

        $endTime = microtime(true);
        $processingTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $this->assertTrue($result, 'Import should succeed');

        // Performance should be reasonable for the test dataset size
        $maxProcessingTimeMs = 5000; // 5 seconds max for 100 records

        $this->assertLessThan(
            $maxProcessingTimeMs,
            $processingTime,
            "Import processing time ({$processingTime}ms) should be less than {$maxProcessingTimeMs}ms"
        );

        // Verify correct number of records imported
        $this->assertEquals(100, GeoLite2Location::count(), 'Should have imported 100 locations');

        // Clean up
        unlink($locationsFile);
    }

    #[Test]
    public function it_can_clear_and_reimport_data(): void
    {
        // Create test data
        $testLocationsContent = "geoname_id,locale_code,continent_code,continent_name,country_iso_code,country_name,subdivision_1_iso_code,subdivision_1_name,subdivision_2_iso_code,subdivision_2_name,city_name,metro_code,time_zone,is_in_european_union\n";
        $testLocationsContent .= "500,en,NA,\"North America\",US,\"United States\",NY,\"New York\",061,\"New York County\",\"New York\",501,\"America/New_York\",0\n";
        $testLocationsContent .= "600,en,EU,Europe,GB,\"United Kingdom\",ENG,England,GLA,\"Greater London\",London,,\"Europe/London\",0\n";

        $locationsFile = sys_get_temp_dir().'/test_clear_locations.csv';
        file_put_contents($locationsFile, $testLocationsContent);

        // Initial import
        $this->importService->importLocations($locationsFile);
        $initialCount = GeoLite2Location::count();
        $this->assertEquals(2, $initialCount, 'Should have imported 2 locations');

        // Clear data
        $this->importService->clearData();
        $this->assertEquals(0, GeoLite2Location::count(), 'Data should be cleared');
        $this->assertEquals(0, GeoLite2IpBlock::count(), 'IP blocks should be cleared');

        // Reimport
        $this->importService->importLocations($locationsFile);
        $finalCount = GeoLite2Location::count();

        $this->assertEquals($initialCount, $finalCount, 'Reimport should restore same amount of data');
        $this->assertEquals(2, $finalCount, 'Should have reimported 2 locations');

        // Clean up
        unlink($locationsFile);
    }

    #[Test]
    public function it_provides_accurate_import_statistics(): void
    {
        // Create test data
        $testLocationsContent = "geoname_id,locale_code,continent_code,continent_name,country_iso_code,country_name,subdivision_1_iso_code,subdivision_1_name,subdivision_2_iso_code,subdivision_2_name,city_name,metro_code,time_zone,is_in_european_union\n";
        $testLocationsContent .= "700,en,NA,\"North America\",US,\"United States\",NY,\"New York\",061,\"New York County\",\"New York\",501,\"America/New_York\",0\n";
        $testLocationsContent .= "800,en,EU,Europe,GB,\"United Kingdom\",ENG,England,GLA,\"Greater London\",London,,\"Europe/London\",0\n";

        $testIpBlocksContent = "network,geoname_id,registered_country_geoname_id,represented_country_geoname_id,is_anonymous_proxy,is_satellite_provider,postal_code,latitude,longitude,accuracy_radius,is_anycast\n";
        $testIpBlocksContent .= "7.0.0.0/24,700,700,,0,0,10001,40.7128,-74.0060,100,0\n";
        $testIpBlocksContent .= "8.0.0.0/24,800,800,,0,0,SW1A,51.5074,-0.1278,50,0\n";
        $testIpBlocksContent .= "9.0.0.0/24,,,700,1,0,,,,,1\n";

        $locationsFile = sys_get_temp_dir().'/test_stats_locations.csv';
        $ipBlocksFile = sys_get_temp_dir().'/test_stats_ipblocks.csv';
        file_put_contents($locationsFile, $testLocationsContent);
        file_put_contents($ipBlocksFile, $testIpBlocksContent);

        // Import data
        $this->importService->importLocations($locationsFile);
        $this->importService->importIPv4Blocks($ipBlocksFile);

        // Get import statistics
        $stats = $this->importService->getImportStats();

        $this->assertIsArray($stats, 'Statistics should be an array');
        $this->assertArrayHasKey('locations_count', $stats);
        $this->assertArrayHasKey('ipv4_blocks_count', $stats);
        $this->assertArrayHasKey('last_updated', $stats);

        $this->assertEquals(2, $stats['locations_count'], 'Should have 2 locations');
        $this->assertEquals(3, $stats['ipv4_blocks_count'], 'Should have 3 IP blocks');

        // Verify statistics match actual database counts
        $this->assertEquals(GeoLite2Location::count(), $stats['locations_count']);
        $this->assertEquals(GeoLite2IpBlock::count(), $stats['ipv4_blocks_count']);

        // Clean up
        unlink($locationsFile);
        unlink($ipBlocksFile);
    }
}
