<?php

namespace JTD\FormSecurity\Tests\Performance;

use Illuminate\Support\Facades\DB;
use JTD\FormSecurity\Models\BlockedSubmission;
use JTD\FormSecurity\Models\GeoLite2IpBlock;
use JTD\FormSecurity\Models\IpReputation;
use JTD\FormSecurity\Models\SpamPattern;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('sprint-002')]
#[Group('epic-001')]
#[Group('database')]
#[Group('models')]
#[Group('performance')]
#[Group('ticket-1021')]
class DatabasePerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seed some test data for performance testing
        $this->seedTestData();
    }

    #[Test]
    public function blocked_submissions_insert_performance(): void
    {
        $startTime = microtime(true);

        DB::table('blocked_submissions')->insert([
            'form_identifier' => 'contact-form',
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 Test Browser',
            'block_reason' => 'spam_pattern',
            'block_details' => json_encode(['pattern' => 'test']),
            'risk_score' => 75,
            'blocked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processingTime = microtime(true) - $startTime;

        // Assert performance requirement (100ms = 0.1 seconds)
        $this->assertLessThan(0.1, $processingTime,
            "Blocked submissions insert took {$processingTime}s, should be < 0.1s");
    }

    #[Test]
    public function blocked_submissions_query_by_ip_performance(): void
    {
        $startTime = microtime(true);

        $results = DB::table('blocked_submissions')
            ->where('ip_address', '192.168.1.1')
            ->where('blocked_at', '>=', now()->subDays(7))
            ->orderBy('blocked_at', 'desc')
            ->limit(100)
            ->get();

        $processingTime = microtime(true) - $startTime;

        $this->assertLessThan(0.1, $processingTime,
            "IP-based query took {$processingTime}s, should be < 0.1s");
    }

    #[Test]
    public function blocked_submissions_analytics_query_performance(): void
    {
        $startTime = microtime(true);

        $results = DB::table('blocked_submissions')
            ->select('block_reason', DB::raw('COUNT(*) as count'))
            ->where('blocked_at', '>=', now()->subDays(30))
            ->groupBy('block_reason')
            ->orderBy('count', 'desc')
            ->get();

        $processingTime = microtime(true) - $startTime;

        $this->assertLessThan(0.1, $processingTime,
            "Analytics query took {$processingTime}s, should be < 0.1s");
    }

    #[Test]
    public function ip_reputation_lookup_performance(): void
    {
        $startTime = microtime(true);

        $reputation = DB::table('ip_reputation')
            ->where('ip_address', '192.168.1.1')
            ->first();

        $processingTime = microtime(true) - $startTime;

        $this->assertLessThan(0.1, $processingTime,
            "IP reputation lookup took {$processingTime}s, should be < 0.1s");
    }

    #[Test]
    public function spam_patterns_active_query_performance(): void
    {
        $startTime = microtime(true);

        $patterns = DB::table('spam_patterns')
            ->where('is_active', true)
            ->orderBy('priority', 'asc')
            ->limit(50)
            ->get();

        $processingTime = microtime(true) - $startTime;

        $this->assertLessThan(0.1, $processingTime,
            "Active spam patterns query took {$processingTime}s, should be < 0.1s");
    }

    #[Test]
    public function geolite2_ip_location_lookup_performance(): void
    {
        // Convert IP to integer for range lookup
        $ipInteger = ip2long('192.168.1.100');

        $startTime = microtime(true);

        $location = DB::table('geolite2_ipv4_blocks')
            ->join('geolite2_locations', 'geolite2_ipv4_blocks.geoname_id', '=', 'geolite2_locations.geoname_id')
            ->where('geolite2_ipv4_blocks.network_start_integer', '<=', $ipInteger)
            ->where('geolite2_ipv4_blocks.network_last_integer', '>=', $ipInteger)
            ->select('geolite2_locations.country_name', 'geolite2_locations.city_name')
            ->first();

        $processingTime = microtime(true) - $startTime;

        $this->assertLessThan(0.1, $processingTime,
            "GeoLite2 IP location lookup took {$processingTime}s, should be < 0.1s");
    }

    #[Test]
    public function bulk_insert_performance(): void
    {
        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $data[] = [
                'form_identifier' => 'bulk-test-form',
                'ip_address' => '10.0.0.'.($i % 255),
                'block_reason' => 'rate_limit',
                'risk_score' => rand(1, 100),
                'blocked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $startTime = microtime(true);

        DB::table('blocked_submissions')->insert($data);

        $processingTime = microtime(true) - $startTime;

        // Bulk insert should be efficient even for 100 records
        $this->assertLessThan(0.5, $processingTime,
            "Bulk insert of 100 records took {$processingTime}s, should be < 0.5s");
    }

    #[Test]
    public function concurrent_write_simulation(): void
    {
        $startTime = microtime(true);

        // Simulate multiple concurrent writes
        for ($i = 0; $i < 10; $i++) {
            DB::table('blocked_submissions')->insert([
                'form_identifier' => 'concurrent-test-'.$i,
                'ip_address' => '172.16.0.'.$i,
                'block_reason' => 'spam_pattern',
                'risk_score' => 50,
                'blocked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $processingTime = microtime(true) - $startTime;

        $this->assertLessThan(0.2, $processingTime,
            "10 concurrent writes took {$processingTime}s, should be < 0.2s");
    }

    /**
     * Seed test data for performance testing
     */
    protected function seedTestData(): void
    {
        // Insert test IP reputation data
        DB::table('ip_reputation')->insertOrIgnore([
            'ip_address' => '192.168.1.1',
            'reputation_score' => 25,
            'reputation_status' => 'suspicious',
            'submission_count' => 10,
            'blocked_count' => 3,
            'block_rate' => 0.3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert test spam patterns
        DB::table('spam_patterns')->insertOrIgnore([
            'name' => 'Test Pattern',
            'pattern_type' => 'keyword',
            'pattern' => 'test spam',
            'is_active' => true,
            'priority' => 10,
            'risk_score' => 75,
            'action' => 'block',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert test GeoLite2 location
        DB::table('geolite2_locations')->insertOrIgnore([
            'geoname_id' => 999999,
            'locale_code' => 'en',
            'country_iso_code' => 'US',
            'country_name' => 'United States',
            'city_name' => 'Test City',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert test GeoLite2 IP block
        DB::table('geolite2_ipv4_blocks')->insertOrIgnore([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'geoname_id' => 999999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert some blocked submissions for testing
        for ($i = 1; $i <= 50; $i++) {
            DB::table('blocked_submissions')->insertOrIgnore([
                'form_identifier' => 'test-form-'.($i % 5),
                'ip_address' => '192.168.1.'.$i,
                'block_reason' => ['spam_pattern', 'rate_limit', 'ip_reputation'][($i % 3)],
                'risk_score' => rand(1, 100),
                'blocked_at' => now()->subMinutes(rand(1, 1440)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    #[Test]
    public function eloquent_model_query_performance(): void
    {
        // Test BlockedSubmission model queries
        $startTime = microtime(true);
        $submissions = BlockedSubmission::highRisk()->recentBlocks(24)->limit(50)->get();
        $processingTime = microtime(true) - $startTime;

        $this->assertLessThan(0.1, $processingTime,
            "BlockedSubmission model query took {$processingTime}s, should be < 0.1s");

        // Test IpReputation model queries
        $startTime = microtime(true);
        $maliciousIps = IpReputation::malicious()->limit(50)->get();
        $processingTime = microtime(true) - $startTime;

        $this->assertLessThan(0.1, $processingTime,
            "IpReputation model query took {$processingTime}s, should be < 0.1s");

        // Test SpamPattern model queries
        $startTime = microtime(true);
        $activePatterns = SpamPattern::active()->orderByPriority()->limit(50)->get();
        $processingTime = microtime(true) - $startTime;

        $this->assertLessThan(0.1, $processingTime,
            "SpamPattern model query took {$processingTime}s, should be < 0.1s");
    }

    #[Test]
    public function model_relationship_loading_performance(): void
    {
        // Test GeoLite2 relationship loading
        $startTime = microtime(true);

        $ipBlocks = GeoLite2IpBlock::with('location')->limit(20)->get();
        foreach ($ipBlocks as $block) {
            $locationName = $block->location?->getFullLocationName();
        }

        $processingTime = microtime(true) - $startTime;

        $this->assertLessThan(0.1, $processingTime,
            "Model relationship loading took {$processingTime}s, should be < 0.1s");
    }

    #[Test]
    public function model_factory_performance(): void
    {
        $startTime = microtime(true);

        // Create multiple models manually (factories not configured yet)
        for ($i = 0; $i < 50; $i++) {
            BlockedSubmission::create([
                'form_identifier' => 'test_form_'.$i,
                'ip_address' => '192.168.1.'.($i % 255),
                'block_reason' => 'spam_pattern',
                'risk_score' => rand(0, 100),
                'blocked_at' => now(),
            ]);
        }

        for ($i = 0; $i < 25; $i++) {
            IpReputation::create([
                'ip_address' => '10.0.1.'.$i,
                'reputation_score' => rand(0, 100),
                'reputation_status' => 'neutral',
            ]);
        }

        $processingTime = microtime(true) - $startTime;

        // Model creation should be efficient
        $this->assertLessThan(5.0, $processingTime,
            "Model creation took {$processingTime}s, should be < 5.0s");
    }

    #[Test]
    public function complex_analytics_query_performance(): void
    {
        $startTime = microtime(true);

        // Complex analytics query using models
        $analytics = BlockedSubmission::select('block_reason', 'country_code', DB::raw('COUNT(*) as count'))
            ->where('blocked_at', '>=', now()->subDays(30))
            ->where('risk_score', '>=', 50)
            ->groupBy('block_reason', 'country_code')
            ->orderBy('count', 'desc')
            ->limit(100)
            ->get();

        $processingTime = microtime(true) - $startTime;

        $this->assertLessThan(0.1, $processingTime,
            "Complex analytics query took {$processingTime}s, should be < 0.1s");
    }

    #[Test]
    public function memory_usage_stays_within_limits(): void
    {
        $startMemory = memory_get_usage(true);

        // Perform memory-intensive operations (create models manually)
        for ($i = 0; $i < 100; $i++) {
            BlockedSubmission::create([
                'form_identifier' => 'memory_test_'.$i,
                'ip_address' => '172.16.1.'.($i % 255),
                'block_reason' => 'spam_pattern',
                'risk_score' => rand(0, 100),
                'blocked_at' => now(),
            ]);
        }

        for ($i = 0; $i < 50; $i++) {
            IpReputation::create([
                'ip_address' => '10.0.2.'.$i,
                'reputation_score' => rand(0, 100),
                'reputation_status' => 'neutral',
            ]);
        }

        // Load data with relationships
        $loadedSubmissions = BlockedSubmission::limit(100)->get();
        $loadedIpReputations = IpReputation::limit(50)->get();

        $endMemory = memory_get_usage(true);
        $memoryUsedMB = ($endMemory - $startMemory) / 1024 / 1024;

        // Memory usage should stay under 50MB as per project requirements
        $this->assertLessThan(50, $memoryUsedMB,
            "Memory usage ({$memoryUsedMB}MB) should be < 50MB");
    }
}
