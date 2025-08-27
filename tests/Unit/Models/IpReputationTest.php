<?php

/**
 * Test File: IpReputationTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Comprehensive unit tests for the IpReputation model including
 * reputation scoring, cache management, activity tracking, and query scopes.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Models;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JTD\FormSecurity\Enums\ReputationStatus;
use JTD\FormSecurity\Models\BlockedSubmission;
use JTD\FormSecurity\Models\IpReputation;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('sprint-002')]
#[Group('epic-001')]
#[Group('database')]
#[Group('models')]
#[Group('ticket-1021')]
#[Group('ip-reputation')]
class IpReputationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_ip_reputation_with_required_fields(): void
    {
        $ipReputation = IpReputation::create([
            'ip_address' => '192.168.1.100',
            'reputation_score' => 75,
            'reputation_status' => 'neutral',
        ]);

        $this->assertInstanceOf(IpReputation::class, $ipReputation);
        $this->assertEquals('192.168.1.100', $ipReputation->ip_address);
        $this->assertEquals(75, $ipReputation->reputation_score);
        $this->assertEquals(ReputationStatus::NEUTRAL, $ipReputation->reputation_status);
    }

    #[Test]
    public function it_casts_attributes_correctly(): void
    {
        $ipReputation = IpReputation::create([
            'ip_address' => '10.0.0.1',
            'reputation_score' => '85',
            'submission_count' => '100',
            'blocked_count' => '25',
            'block_rate' => '0.2500',
            'is_tor' => '1',
            'is_proxy' => '0',
            'is_vpn' => 'true',
            'is_whitelisted' => '0',
            'is_blacklisted' => '1',
            'first_seen' => '2025-01-01 12:00:00',
            'last_seen' => '2025-01-27 12:00:00',
            'cache_expires_at' => '2025-01-28 12:00:00',
            'threat_sources' => ['source1', 'source2'],
            'threat_categories' => ['malware', 'spam'],
            'metadata' => ['key' => 'value'],
        ]);

        $this->assertIsInt($ipReputation->reputation_score);
        $this->assertIsInt($ipReputation->submission_count);
        $this->assertIsInt($ipReputation->blocked_count);
        $this->assertTrue(is_float($ipReputation->block_rate) || is_string($ipReputation->block_rate)); // SQLite may return as string
        $this->assertIsBool($ipReputation->is_tor);
        $this->assertIsBool($ipReputation->is_proxy);
        $this->assertIsBool($ipReputation->is_vpn);
        $this->assertIsBool($ipReputation->is_whitelisted);
        $this->assertIsBool($ipReputation->is_blacklisted);
        $this->assertInstanceOf(Carbon::class, $ipReputation->first_seen);
        $this->assertInstanceOf(Carbon::class, $ipReputation->last_seen);
        $this->assertInstanceOf(Carbon::class, $ipReputation->cache_expires_at);
        $this->assertIsArray($ipReputation->threat_sources);
        $this->assertIsArray($ipReputation->threat_categories);
        $this->assertIsArray($ipReputation->metadata);

        $this->assertEquals(85, $ipReputation->reputation_score);
        $this->assertEquals(100, $ipReputation->submission_count);
        $this->assertEquals(25, $ipReputation->blocked_count);
        $this->assertEquals(0.2500, $ipReputation->block_rate);
        $this->assertTrue($ipReputation->is_tor);
        $this->assertFalse($ipReputation->is_proxy);
        $this->assertTrue($ipReputation->is_vpn);
        $this->assertFalse((bool) $ipReputation->is_whitelisted);
        $this->assertTrue((bool) $ipReputation->is_blacklisted);
    }

    #[Test]
    public function scope_by_ip_address_filters_correctly(): void
    {
        IpReputation::create([
            'ip_address' => '192.168.1.1',
            'reputation_score' => 50,
        ]);

        IpReputation::create([
            'ip_address' => '192.168.1.2',
            'reputation_score' => 60,
        ]);

        $result = IpReputation::byIpAddress('192.168.1.1')->first();

        $this->assertNotNull($result);
        $this->assertEquals('192.168.1.1', $result->ip_address);
    }

    #[Test]
    public function scope_by_status_filters_correctly(): void
    {
        IpReputation::create([
            'ip_address' => '10.0.0.1',
            'reputation_status' => 'trusted',
        ]);

        IpReputation::create([
            'ip_address' => '10.0.0.2',
            'reputation_status' => 'malicious',
        ]);

        $trusted = IpReputation::byStatus('trusted')->get();
        $malicious = IpReputation::byStatus('malicious')->get();

        $this->assertCount(1, $trusted);
        $this->assertCount(1, $malicious);
        $this->assertEquals(ReputationStatus::TRUSTED, $trusted->first()->reputation_status);
        $this->assertEquals(ReputationStatus::MALICIOUS, $malicious->first()->reputation_status);
    }

    #[Test]
    public function scope_by_score_range_filters_correctly(): void
    {
        IpReputation::create([
            'ip_address' => '10.0.0.1',
            'reputation_score' => 25,
        ]);

        IpReputation::create([
            'ip_address' => '10.0.0.2',
            'reputation_score' => 75,
        ]);

        IpReputation::create([
            'ip_address' => '10.0.0.3',
            'reputation_score' => 95,
        ]);

        $lowScore = IpReputation::byScoreRange(0, 50)->get();
        $highScore = IpReputation::byScoreRange(80, 100)->get();

        $this->assertCount(1, $lowScore);
        $this->assertCount(1, $highScore);
        $this->assertEquals(25, $lowScore->first()->reputation_score);
        $this->assertEquals(95, $highScore->first()->reputation_score);
    }

    #[Test]
    public function reputation_level_scopes_work_correctly(): void
    {
        IpReputation::create([
            'ip_address' => '10.0.0.1',
            'reputation_score' => 25, // malicious
        ]);

        IpReputation::create([
            'ip_address' => '10.0.0.2',
            'reputation_score' => 45, // suspicious
        ]);

        IpReputation::create([
            'ip_address' => '10.0.0.3',
            'reputation_score' => 85, // trusted
        ]);

        $malicious = IpReputation::malicious()->get();
        $suspicious = IpReputation::suspicious()->get();
        $trusted = IpReputation::trusted()->get();

        $this->assertCount(1, $malicious);
        $this->assertCount(1, $suspicious);
        $this->assertCount(1, $trusted);

        $this->assertEquals(25, $malicious->first()->reputation_score);
        $this->assertEquals(45, $suspicious->first()->reputation_score);
        $this->assertEquals(85, $trusted->first()->reputation_score);
    }

    #[Test]
    public function whitelist_and_blacklist_scopes_work_correctly(): void
    {
        IpReputation::create([
            'ip_address' => '10.0.0.1',
            'is_whitelisted' => true,
            'is_blacklisted' => false,
        ]);

        IpReputation::create([
            'ip_address' => '10.0.0.2',
            'is_whitelisted' => false,
            'is_blacklisted' => true,
        ]);

        $whitelisted = IpReputation::whitelisted()->get();
        $blacklisted = IpReputation::blacklisted()->get();

        $this->assertCount(1, $whitelisted);
        $this->assertCount(1, $blacklisted);
        $this->assertTrue($whitelisted->first()->is_whitelisted);
        $this->assertTrue($blacklisted->first()->is_blacklisted);
    }

    #[Test]
    public function cache_expiration_scopes_work_correctly(): void
    {
        IpReputation::create([
            'ip_address' => '10.0.0.1',
            'cache_expires_at' => now()->subHour(), // expired
        ]);

        IpReputation::create([
            'ip_address' => '10.0.0.2',
            'cache_expires_at' => now()->addHour(), // active
        ]);

        $expired = IpReputation::expired()->get();
        $active = IpReputation::active()->get();

        $this->assertCount(1, $expired);
        $this->assertCount(1, $active);
        $this->assertTrue($expired->first()->cache_expires_at->isPast());
        $this->assertTrue($active->first()->cache_expires_at->isFuture());
    }

    #[Test]
    public function is_expired_method_works_correctly(): void
    {
        $expiredIp = IpReputation::create([
            'ip_address' => '10.0.0.1',
            'cache_expires_at' => now()->subHour(),
        ]);

        $activeIp = IpReputation::create([
            'ip_address' => '10.0.0.2',
            'cache_expires_at' => now()->addHour(),
        ]);

        // Test the isExpired method logic directly since observer sets default cache_expires_at
        $this->assertTrue($expiredIp->isExpired());
        $this->assertFalse($activeIp->isExpired());

        // Test null expiration logic by setting it directly
        $expiredIp->cache_expires_at = null;
        $this->assertTrue($expiredIp->isExpired());
    }

    #[Test]
    public function reputation_level_methods_work_correctly(): void
    {
        $maliciousIp = IpReputation::create([
            'ip_address' => '10.0.0.1',
            'reputation_score' => 25,
            'is_blacklisted' => false,
        ]);

        $blacklistedIp = IpReputation::create([
            'ip_address' => '10.0.0.2',
            'reputation_score' => 50,
            'is_blacklisted' => true,
        ]);

        $suspiciousIp = IpReputation::create([
            'ip_address' => '10.0.0.3',
            'reputation_score' => 45,
        ]);

        $trustedIp = IpReputation::create([
            'ip_address' => '10.0.0.4',
            'reputation_score' => 85,
            'is_whitelisted' => false,
        ]);

        $whitelistedIp = IpReputation::create([
            'ip_address' => '10.0.0.5',
            'reputation_score' => 50,
            'is_whitelisted' => true,
        ]);

        $this->assertTrue($maliciousIp->isMalicious());
        $this->assertTrue($blacklistedIp->isMalicious());
        $this->assertTrue($suspiciousIp->isSuspicious());
        $this->assertTrue($trustedIp->isTrusted());
        $this->assertTrue($whitelistedIp->isTrusted());

        $this->assertFalse($maliciousIp->isTrusted());
        $this->assertFalse($trustedIp->isMalicious());
    }

    #[Test]
    public function get_reputation_level_returns_correct_strings(): void
    {
        $testCases = [
            ['score' => 25, 'blacklisted' => false, 'whitelisted' => false, 'expected' => 'malicious'],
            ['score' => 50, 'blacklisted' => true, 'whitelisted' => false, 'expected' => 'malicious'],
            ['score' => 45, 'blacklisted' => false, 'whitelisted' => false, 'expected' => 'suspicious'],
            ['score' => 85, 'blacklisted' => false, 'whitelisted' => false, 'expected' => 'trusted'],
            ['score' => 50, 'blacklisted' => false, 'whitelisted' => true, 'expected' => 'trusted'],
            ['score' => 65, 'blacklisted' => false, 'whitelisted' => false, 'expected' => 'neutral'],
        ];

        foreach ($testCases as $index => $case) {
            $ip = IpReputation::create([
                'ip_address' => '10.0.0.'.($index + 10), // Use unique IP addresses
                'reputation_score' => $case['score'],
                'is_blacklisted' => $case['blacklisted'],
                'is_whitelisted' => $case['whitelisted'],
            ]);

            $this->assertEquals($case['expected'], $ip->getReputationLevel());
        }
    }

    #[Test]
    public function is_suspicious_network_method_works_correctly(): void
    {
        $normalIp = IpReputation::create([
            'ip_address' => '10.0.0.1',
            'is_tor' => false,
            'is_proxy' => false,
            'is_vpn' => false,
            'is_malware' => false,
            'is_botnet' => false,
        ]);

        $suspiciousIp = IpReputation::create([
            'ip_address' => '10.0.0.2',
            'is_tor' => true,
            'is_proxy' => false,
            'is_vpn' => false,
            'is_malware' => false,
            'is_botnet' => false,
        ]);

        $this->assertFalse($normalIp->isSuspiciousNetwork());
        $this->assertTrue($suspiciousIp->isSuspiciousNetwork());
    }

    #[Test]
    public function update_activity_method_works_correctly(): void
    {
        $ip = IpReputation::create([
            'ip_address' => '10.0.0.1',
            'submission_count' => 10,
            'blocked_count' => 2,
            'block_rate' => 0.2,
        ]);

        // Test normal submission
        $ip->updateActivity(false);

        $this->assertEquals(11, $ip->submission_count);
        $this->assertEquals(2, $ip->blocked_count);
        $this->assertEquals(round(2 / 11, 4), round((float) $ip->block_rate, 4));
        $this->assertNotNull($ip->last_seen);

        // Test blocked submission
        $ip->updateActivity(true);

        $this->assertEquals(12, $ip->submission_count);
        $this->assertEquals(3, $ip->blocked_count);
        $this->assertEquals(round(3 / 12, 4), round((float) $ip->block_rate, 4));
        $this->assertNotNull($ip->last_blocked);
    }

    #[Test]
    public function extend_cache_method_works_correctly(): void
    {
        $ip = IpReputation::create([
            'ip_address' => '10.0.0.1',
            'cache_expires_at' => now(),
        ]);

        $originalExpiration = $ip->cache_expires_at;

        $ip->extendCache(48);

        $this->assertTrue($ip->cache_expires_at->isAfter($originalExpiration));
        $this->assertTrue($ip->cache_expires_at->isAfter(now()->addHours(47)));
    }

    #[Test]
    public function scope_by_network_type_filters_correctly(): void
    {
        IpReputation::create([
            'ip_address' => '10.0.0.1',
            'is_tor' => true,
            'is_proxy' => false,
            'is_vpn' => false,
            'is_hosting' => false,
            'is_malware' => false,
            'is_botnet' => false,
        ]);

        IpReputation::create([
            'ip_address' => '10.0.0.2',
            'is_tor' => false,
            'is_proxy' => true,
            'is_vpn' => false,
            'is_hosting' => false,
            'is_malware' => false,
            'is_botnet' => false,
        ]);

        IpReputation::create([
            'ip_address' => '10.0.0.3',
            'is_tor' => false,
            'is_proxy' => false,
            'is_vpn' => false,
            'is_hosting' => false,
            'is_malware' => true,
            'is_botnet' => false,
        ]);

        $torIps = IpReputation::byNetworkType('tor')->get();
        $proxyIps = IpReputation::byNetworkType('proxy')->get();
        $malwareIps = IpReputation::byNetworkType('malware')->get();

        $this->assertCount(1, $torIps);
        $this->assertCount(1, $proxyIps);
        $this->assertCount(1, $malwareIps);

        $this->assertTrue($torIps->first()->is_tor);
        $this->assertTrue($proxyIps->first()->is_proxy);
        $this->assertTrue($malwareIps->first()->is_malware);
    }

    #[Test]
    public function high_activity_and_block_rate_scopes_work_correctly(): void
    {
        IpReputation::create([
            'ip_address' => '10.0.0.1',
            'submission_count' => 150,
            'blocked_count' => 135, // 135/150 = 0.9
            'block_rate' => 0.9,
        ]);

        IpReputation::create([
            'ip_address' => '10.0.0.2',
            'submission_count' => 50,
            'blocked_count' => 25, // 25/50 = 0.5
            'block_rate' => 0.5,
        ]);

        $highActivity = IpReputation::highActivity(100)->get();
        $highBlockRate = IpReputation::highBlockRate(0.8)->get();

        $this->assertCount(1, $highActivity);
        $this->assertCount(1, $highBlockRate);

        $this->assertEquals(150, $highActivity->first()->submission_count);
        $this->assertEquals(0.9, $highBlockRate->first()->block_rate);
    }

    #[Test]
    public function it_casts_reputation_status_to_enum(): void
    {
        $reputation = IpReputation::create([
            'ip_address' => '10.0.0.1',
            'reputation_score' => 50,
            'reputation_status' => ReputationStatus::NEUTRAL->value,
        ]);

        $this->assertInstanceOf(ReputationStatus::class, $reputation->reputation_status);
        $this->assertEquals(ReputationStatus::NEUTRAL, $reputation->reputation_status);
    }

    #[Test]
    public function it_has_relationship_with_blocked_submissions(): void
    {
        $reputation = IpReputation::create([
            'ip_address' => '192.168.1.100',
            'reputation_score' => 50,
            'reputation_status' => 'neutral',
        ]);

        $submission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '192.168.1.100',
            'block_reason' => 'spam_pattern',
            'blocked_at' => now(),
        ]);

        $this->assertTrue($reputation->blockedSubmissions->contains($submission));
    }

    #[Test]
    public function it_updates_reputation_score_based_on_activity(): void
    {
        $reputation = IpReputation::create([
            'ip_address' => '10.0.0.1',
            'reputation_score' => 50,
            'submission_count' => 150, // High volume to trigger score change
            'blocked_count' => 135,
            'block_rate' => 0.9, // High block rate should reduce score
            'is_malware' => true,
        ]);

        $originalScore = $reputation->reputation_score;
        $reputation->updateReputationScore();

        $this->assertNotEquals($originalScore, $reputation->fresh()->reputation_score);
    }

    #[Test]
    public function it_calculates_threat_intelligence_score(): void
    {
        $reputation = IpReputation::create([
            'ip_address' => '10.0.0.1',
            'is_malware' => true,
            'is_botnet' => true,
            'block_rate' => 0.8,
            'submission_count' => 1500,
        ]);

        $threatScore = $reputation->calculateThreatIntelligenceScore();

        $this->assertIsArray($threatScore);
        $this->assertArrayHasKey('overall_threat_level', $threatScore);
        $this->assertArrayHasKey('network_threat', $threatScore);
        $this->assertArrayHasKey('behavioral_threat', $threatScore);
        $this->assertGreaterThan(0, $threatScore['overall_threat_level']);
    }

    #[Test]
    public function it_generates_reputation_summary(): void
    {
        $reputation = IpReputation::create([
            'ip_address' => '192.168.1.100',
            'reputation_score' => 75,
            'reputation_status' => 'trusted',
        ]);

        $summary = $reputation->getReputationSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('ip_address', $summary);
        $this->assertArrayHasKey('reputation_score', $summary);
        $this->assertArrayHasKey('reputation_status', $summary);
        $this->assertArrayHasKey('allows_access', $summary);
        $this->assertArrayHasKey('threat_intelligence', $summary);
    }

    #[Test]
    public function it_checks_if_allows_access(): void
    {
        $trustedIp = IpReputation::create([
            'ip_address' => '10.0.0.1',
            'reputation_status' => ReputationStatus::TRUSTED->value,
        ]);

        $blockedIp = IpReputation::create([
            'ip_address' => '10.0.0.2',
            'reputation_status' => ReputationStatus::BLOCKED->value,
        ]);

        $this->assertTrue($trustedIp->allowsAccess());
        $this->assertFalse($blockedIp->allowsAccess());
    }

    #[Test]
    public function it_implements_cacheable_interface(): void
    {
        $reputation = IpReputation::create([
            'ip_address' => '192.168.1.100',
            'reputation_score' => 50,
        ]);

        $cacheKey = $reputation->getCacheKey();
        $this->assertStringContainsString('ip_reputation:', $cacheKey);
    }

    #[Test]
    public function it_scopes_by_network_type(): void
    {
        $torIp = IpReputation::create([
            'ip_address' => '10.0.0.1',
            'is_tor' => true,
        ]);

        $proxyIp = IpReputation::create([
            'ip_address' => '10.0.0.2',
            'is_proxy' => true,
        ]);

        $torIps = IpReputation::byNetworkType('tor')->get();
        $proxyIps = IpReputation::byNetworkType('proxy')->get();

        $this->assertTrue($torIps->contains($torIp));
        $this->assertTrue($proxyIps->contains($proxyIp));
    }
}
