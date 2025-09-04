<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
#[Group('compatibility')]
#[Group('ticket-1021')]
class DatabaseCompatibilityTest extends TestCase
{
    #[Test]
    public function database_schema_uses_compatible_column_types(): void
    {
        // Test that our schema uses column types compatible across databases

        // Check blocked_submissions table
        $this->assertTrue(Schema::hasTable('blocked_submissions'));

        // Verify key columns exist and can handle expected data
        $this->assertTrue(Schema::hasColumn('blocked_submissions', 'ip_address'));
        $this->assertTrue(Schema::hasColumn('blocked_submissions', 'latitude'));
        $this->assertTrue(Schema::hasColumn('blocked_submissions', 'longitude'));

        // Test inserting data with various formats
        DB::table('blocked_submissions')->insert([
            'form_identifier' => 'compatibility-test',
            'ip_address' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334', // IPv6
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'block_reason' => 'spam_pattern',
            'risk_score' => 75,
            'blocked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('blocked_submissions', [
            'form_identifier' => 'compatibility-test',
        ]);
    }

    #[Test]
    public function json_columns_work_across_databases(): void
    {
        // Test JSON column compatibility
        $jsonData = [
            'pattern_matches' => ['viagra', 'casino'],
            'confidence' => 0.85,
            'source' => 'automated',
        ];

        DB::table('blocked_submissions')->insert([
            'form_identifier' => 'json-test',
            'ip_address' => '192.168.1.200',
            'block_reason' => 'spam_pattern',
            'block_details' => json_encode($jsonData),
            'metadata' => json_encode(['test' => true]),
            'risk_score' => 80,
            'blocked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = DB::table('blocked_submissions')
            ->where('form_identifier', 'json-test')
            ->first();

        $this->assertNotNull($record);
        $decodedDetails = json_decode($record->block_details, true);
        $this->assertEquals($jsonData, $decodedDetails);
    }

    #[Test]
    public function enum_columns_work_with_fallback_strategy(): void
    {
        // Test enum-like functionality (using string columns with validation)
        $validReasons = ['spam_pattern', 'ip_reputation', 'rate_limit', 'geolocation', 'honeypot', 'custom_rule'];

        foreach ($validReasons as $reason) {
            DB::table('blocked_submissions')->insert([
                'form_identifier' => 'enum-test-'.$reason,
                'ip_address' => '10.0.0.1',
                'block_reason' => $reason,
                'risk_score' => 50,
                'blocked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $count = DB::table('blocked_submissions')
            ->whereIn('block_reason', $validReasons)
            ->where('form_identifier', 'like', 'enum-test-%')
            ->count();

        $this->assertEquals(count($validReasons), $count);
    }

    #[Test]
    public function decimal_precision_works_across_databases(): void
    {
        // Test decimal precision for coordinates and rates
        DB::table('ip_reputation')->insert([
            'ip_address' => '203.0.113.1',
            'reputation_score' => 42,
            'reputation_status' => 'suspicious',
            'block_rate' => 0.3333, // Test decimal precision
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('geolite2_locations')->insert([
            'geoname_id' => 888888,
            'locale_code' => 'en',
            'country_iso_code' => 'US',
            'country_name' => 'United States',
            'latitude' => 40.12345678, // Test decimal precision
            'longitude' => -74.12345678,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reputation = DB::table('ip_reputation')
            ->where('ip_address', '203.0.113.1')
            ->first();

        $location = DB::table('geolite2_locations')
            ->where('geoname_id', 888888)
            ->first();

        $this->assertNotNull($reputation);
        $this->assertNotNull($location);
        $this->assertEquals(0.3333, $reputation->block_rate);
    }

    #[Test]
    public function foreign_key_constraints_work_properly(): void
    {
        // Insert parent record first
        DB::table('geolite2_locations')->insert([
            'geoname_id' => 777777,
            'locale_code' => 'en',
            'country_iso_code' => 'CA',
            'country_name' => 'Canada',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert child record with foreign key reference
        DB::table('geolite2_ipv4_blocks')->insert([
            'network' => '198.51.100.0/24',
            'network_start_integer' => ip2long('198.51.100.0'),
            'network_last_integer' => ip2long('198.51.100.255'),
            'geoname_id' => 777777, // References parent
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify the relationship works
        $result = DB::table('geolite2_ipv4_blocks')
            ->join('geolite2_locations', 'geolite2_ipv4_blocks.geoname_id', '=', 'geolite2_locations.geoname_id')
            ->where('geolite2_ipv4_blocks.network', '198.51.100.0/24')
            ->select('geolite2_locations.country_name')
            ->first();

        $this->assertNotNull($result);
        $this->assertEquals('Canada', $result->country_name);
    }

    #[Test]
    public function large_integer_values_work_correctly(): void
    {
        // Test large integer values for IP ranges
        $largeStartInt = 3232235776; // 192.168.1.0 as integer
        $largeEndInt = 3232236031;   // 192.168.1.255 as integer

        DB::table('geolite2_ipv4_blocks')->insert([
            'network' => '192.168.1.0/24',
            'network_start_integer' => $largeStartInt,
            'network_last_integer' => $largeEndInt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = DB::table('geolite2_ipv4_blocks')
            ->where('network', '192.168.1.0/24')
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals($largeStartInt, $record->network_start_integer);
        $this->assertEquals($largeEndInt, $record->network_last_integer);
    }

    #[Test]
    public function text_columns_handle_large_content(): void
    {
        // Test text columns can handle large content
        $largeText = str_repeat('This is a test spam message with lots of content. ', 100);

        DB::table('spam_patterns')->insert([
            'name' => 'Large Content Test',
            'pattern_type' => 'phrase',
            'pattern' => $largeText,
            'description' => $largeText,
            'is_active' => true,
            'priority' => 50,
            'risk_score' => 60,
            'action' => 'flag',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = DB::table('spam_patterns')
            ->where('name', 'Large Content Test')
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals($largeText, $record->pattern);
    }

    #[Test]
    public function timestamp_columns_work_consistently(): void
    {
        $testTime = now();

        DB::table('blocked_submissions')->insert([
            'form_identifier' => 'timestamp-test',
            'ip_address' => '192.0.2.1',
            'block_reason' => 'spam_pattern',
            'risk_score' => 75,
            'blocked_at' => $testTime,
            'created_at' => $testTime,
            'updated_at' => $testTime,
        ]);

        $record = DB::table('blocked_submissions')
            ->where('form_identifier', 'timestamp-test')
            ->first();

        $this->assertNotNull($record);
        // Verify timestamp was stored and retrieved correctly
        $this->assertNotNull($record->blocked_at);
        $this->assertNotNull($record->created_at);
        $this->assertNotNull($record->updated_at);
    }

    #[Test]
    public function boolean_columns_work_consistently(): void
    {
        // Test boolean columns work across different databases
        DB::table('ip_reputation')->insert([
            'ip_address' => '192.0.2.100',
            'reputation_score' => 30,
            'reputation_status' => 'suspicious',
            'is_tor' => true,
            'is_proxy' => false,
            'is_vpn' => true,
            'is_whitelisted' => false,
            'is_blacklisted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = DB::table('ip_reputation')
            ->where('ip_address', '192.0.2.100')
            ->first();

        $this->assertNotNull($record);
        $this->assertTrue((bool) $record->is_tor);
        $this->assertFalse((bool) $record->is_proxy);
        $this->assertTrue((bool) $record->is_vpn);
        $this->assertFalse((bool) $record->is_whitelisted);
        $this->assertTrue((bool) $record->is_blacklisted);
    }

    #[Test]
    public function eloquent_models_work_across_databases(): void
    {
        // Test BlockedSubmission model
        $submission = BlockedSubmission::create([
            'form_identifier' => 'model-compatibility-test',
            'ip_address' => '203.0.113.50',
            'block_reason' => 'spam_pattern',
            'risk_score' => 85,
            'is_tor' => true,
            'is_proxy' => false,
            'is_vpn' => true,
            'blocked_at' => now(),
            'metadata' => ['test' => 'compatibility'],
        ]);

        $this->assertInstanceOf(BlockedSubmission::class, $submission);
        $this->assertEquals('model-compatibility-test', $submission->form_identifier);
        $this->assertTrue($submission->is_tor);
        $this->assertIsArray($submission->metadata);

        // Test IpReputation model
        $ipReputation = IpReputation::create([
            'ip_address' => '203.0.113.51',
            'reputation_score' => 25,
            'reputation_status' => 'malicious',
            'is_blacklisted' => true,
            'block_rate' => 0.8500,
            'threat_sources' => ['abuseipdb', 'virustotal'],
            'threat_categories' => ['malware', 'spam'],
        ]);

        $this->assertInstanceOf(IpReputation::class, $ipReputation);
        $this->assertEquals(25, $ipReputation->reputation_score);
        $this->assertTrue($ipReputation->is_blacklisted);
        $this->assertIsArray($ipReputation->threat_sources);
    }

    #[Test]
    public function model_relationships_work_across_databases(): void
    {
        // Create location
        $location = GeoLite2Location::create([
            'geoname_id' => 555555,
            'locale_code' => 'en',
            'country_iso_code' => 'GB',
            'country_name' => 'United Kingdom',
            'city_name' => 'London',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
        ]);

        // Create IP block with relationship
        $ipBlock = GeoLite2IpBlock::create([
            'network' => '203.0.113.0/24',
            'network_start_integer' => ip2long('203.0.113.0'),
            'network_last_integer' => ip2long('203.0.113.255'),
            'geoname_id' => 555555,
            'latitude' => 51.5074,
            'longitude' => -0.1278,
        ]);

        // Test relationship loading
        $loadedBlock = GeoLite2IpBlock::with('location')->find($ipBlock->id);
        $this->assertNotNull($loadedBlock->location);
        $this->assertEquals('United Kingdom', $loadedBlock->location->country_name);
        $this->assertEquals('London', $loadedBlock->location->city_name);

        // Test reverse relationship
        $loadedLocation = GeoLite2Location::with('ipBlocks')->find($location->id);
        $this->assertGreaterThan(0, $loadedLocation->ipBlocks->count());
        $this->assertEquals('203.0.113.0/24', $loadedLocation->ipBlocks->first()->network);
    }

    #[Test]
    public function model_scopes_work_across_databases(): void
    {
        // Create test data
        BlockedSubmission::create([
            'form_identifier' => 'scope-test-1',
            'ip_address' => '203.0.113.100',
            'block_reason' => 'spam_pattern',
            'risk_score' => 95,
            'country_code' => 'US',
            'blocked_at' => now()->subHours(2),
        ]);

        BlockedSubmission::create([
            'form_identifier' => 'scope-test-2',
            'ip_address' => '203.0.113.101',
            'block_reason' => 'rate_limit',
            'risk_score' => 45,
            'country_code' => 'CA',
            'blocked_at' => now()->subHours(25),
        ]);

        // Test scopes work correctly
        $highRisk = BlockedSubmission::highRisk()->get();
        $this->assertGreaterThan(0, $highRisk->count());

        $recent = BlockedSubmission::recentBlocks(24)->get();
        $this->assertGreaterThan(0, $recent->count());

        $usSubmissions = BlockedSubmission::byCountry('US')->get();
        $this->assertGreaterThan(0, $usSubmissions->count());
    }

    #[Test]
    public function model_casting_works_across_databases(): void
    {
        // Test attribute casting works consistently
        $submission = BlockedSubmission::create([
            'form_identifier' => 'casting-test',
            'ip_address' => '203.0.113.200',
            'block_reason' => 'spam_pattern',
            'risk_score' => '75', // String that should be cast to int
            'latitude' => '40.7128', // String that should be cast to decimal
            'longitude' => '-74.0060',
            'is_tor' => '1', // String that should be cast to boolean
            'is_proxy' => '0',
            'blocked_at' => '2025-01-27 12:00:00', // String that should be cast to Carbon
            'metadata' => ['key' => 'value'], // Array that should be cast to JSON
        ]);

        // Verify casting worked correctly
        $this->assertIsInt($submission->risk_score);
        $this->assertTrue(is_float($submission->latitude) || is_string($submission->latitude)); // SQLite may return as string
        $this->assertTrue(is_float($submission->longitude) || is_string($submission->longitude));
        $this->assertIsBool($submission->is_tor);
        $this->assertIsBool($submission->is_proxy);
        $this->assertInstanceOf(\Carbon\Carbon::class, $submission->blocked_at);
        $this->assertIsArray($submission->metadata);

        $this->assertEquals(75, $submission->risk_score);
        $this->assertEquals(40.7128, $submission->latitude);
        $this->assertEquals(-74.0060, $submission->longitude);
        $this->assertTrue($submission->is_tor);
        $this->assertFalse($submission->is_proxy);
        $this->assertEquals(['key' => 'value'], $submission->metadata);
    }
}
