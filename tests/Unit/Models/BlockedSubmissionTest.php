<?php

/**
 * Test File: BlockedSubmissionTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-008-blocked-submissions-tracking
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Comprehensive unit tests for the BlockedSubmission model including
 * relationships, scopes, methods, and data integrity validation.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Data-Management-Analytics/SPEC-008-blocked-submissions-tracking.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Models;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JTD\FormSecurity\Enums\BlockReason;
use JTD\FormSecurity\Enums\RiskLevel;
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
#[Group('blocked-submission')]
class BlockedSubmissionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_blocked_submission_with_required_fields(): void
    {
        $submission = BlockedSubmission::create([
            'form_identifier' => 'contact_form',
            'ip_address' => '192.168.1.100',
            'block_reason' => 'spam_pattern',
            'risk_score' => 85,
            'blocked_at' => now(),
        ]);

        $this->assertInstanceOf(BlockedSubmission::class, $submission);
        $this->assertEquals('contact_form', $submission->form_identifier);
        $this->assertEquals('192.168.1.100', $submission->ip_address);
        $this->assertEquals(BlockReason::SPAM_PATTERN, $submission->block_reason);
        $this->assertEquals(85, $submission->risk_score);
        $this->assertInstanceOf(Carbon::class, $submission->blocked_at);
    }

    #[Test]
    public function it_casts_attributes_correctly(): void
    {
        $submission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.1',
            'block_reason' => 'rate_limit',
            'latitude' => '40.7128',
            'longitude' => '-74.0060',
            'risk_score' => '75',
            'form_field_count' => '5',
            'is_tor' => '1',
            'is_proxy' => '0',
            'is_vpn' => 'true',
            'blocked_at' => '2025-01-27 12:00:00',
            'metadata' => ['key' => 'value'],
        ]);

        $this->assertTrue(is_float($submission->latitude) || is_string($submission->latitude)); // SQLite may return as string
        $this->assertTrue(is_float($submission->longitude) || is_string($submission->longitude));
        $this->assertIsInt($submission->risk_score);
        $this->assertIsInt($submission->form_field_count);
        $this->assertIsBool($submission->is_tor);
        $this->assertIsBool($submission->is_proxy);
        $this->assertIsBool($submission->is_vpn);
        $this->assertInstanceOf(Carbon::class, $submission->blocked_at);
        $this->assertIsArray($submission->metadata);

        $this->assertEquals('40.71280000', (string) $submission->latitude);
        $this->assertEquals('-74.00600000', (string) $submission->longitude);
        $this->assertEquals(75, $submission->risk_score);
        $this->assertEquals(5, $submission->form_field_count);
        $this->assertTrue($submission->is_tor);
        $this->assertFalse($submission->is_proxy);
        $this->assertTrue($submission->is_vpn);
        $this->assertEquals(['key' => 'value'], $submission->metadata);
    }

    #[Test]
    public function it_hides_sensitive_attributes(): void
    {
        $submission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.1',
            'block_reason' => 'spam_pattern',
            'form_data_hash' => 'sensitive_hash',
            'fingerprint' => 'browser_fingerprint',
            'blocked_at' => now(),
        ]);

        $array = $submission->toArray();

        $this->assertArrayNotHasKey('form_data_hash', $array);
        $this->assertArrayNotHasKey('fingerprint', $array);
        $this->assertArrayHasKey('form_identifier', $array);
        $this->assertArrayHasKey('ip_address', $array);
    }

    #[Test]
    public function scope_by_form_identifier_filters_correctly(): void
    {
        BlockedSubmission::create([
            'form_identifier' => 'contact_form',
            'ip_address' => '10.0.0.1',
            'block_reason' => 'spam_pattern',
            'blocked_at' => now(),
        ]);

        BlockedSubmission::create([
            'form_identifier' => 'newsletter_form',
            'ip_address' => '10.0.0.2',
            'block_reason' => 'rate_limit',
            'blocked_at' => now(),
        ]);

        $contactSubmissions = BlockedSubmission::byFormIdentifier('contact_form')->get();
        $newsletterSubmissions = BlockedSubmission::byFormIdentifier('newsletter_form')->get();

        $this->assertCount(1, $contactSubmissions);
        $this->assertCount(1, $newsletterSubmissions);
        $this->assertEquals('contact_form', $contactSubmissions->first()->form_identifier);
        $this->assertEquals('newsletter_form', $newsletterSubmissions->first()->form_identifier);
    }

    #[Test]
    public function scope_by_block_reason_filters_correctly(): void
    {
        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.1',
            'block_reason' => 'spam_pattern',
            'blocked_at' => now(),
        ]);

        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.2',
            'block_reason' => 'rate_limit',
            'blocked_at' => now(),
        ]);

        $spamBlocks = BlockedSubmission::byBlockReason('spam_pattern')->get();
        $rateLimitBlocks = BlockedSubmission::byBlockReason('rate_limit')->get();

        $this->assertCount(1, $spamBlocks);
        $this->assertCount(1, $rateLimitBlocks);
        $this->assertEquals(BlockReason::SPAM_PATTERN, $spamBlocks->first()->block_reason);
        $this->assertEquals(BlockReason::RATE_LIMIT, $rateLimitBlocks->first()->block_reason);
    }

    #[Test]
    public function scope_by_country_filters_correctly(): void
    {
        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.1',
            'block_reason' => 'spam_pattern',
            'country_code' => 'US',
            'blocked_at' => now(),
        ]);

        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.2',
            'block_reason' => 'spam_pattern',
            'country_code' => 'CA',
            'blocked_at' => now(),
        ]);

        $usSubmissions = BlockedSubmission::byCountry('US')->get();
        $caSubmissions = BlockedSubmission::byCountry('CA')->get();

        $this->assertCount(1, $usSubmissions);
        $this->assertCount(1, $caSubmissions);
        $this->assertEquals('US', $usSubmissions->first()->country_code);
        $this->assertEquals('CA', $caSubmissions->first()->country_code);
    }

    #[Test]
    public function scope_by_risk_score_filters_correctly(): void
    {
        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.1',
            'block_reason' => 'spam_pattern',
            'risk_score' => 25,
            'blocked_at' => now(),
        ]);

        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.2',
            'block_reason' => 'spam_pattern',
            'risk_score' => 75,
            'blocked_at' => now(),
        ]);

        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.3',
            'block_reason' => 'spam_pattern',
            'risk_score' => 95,
            'blocked_at' => now(),
        ]);

        $lowRisk = BlockedSubmission::byRiskScore(0, 50)->get();
        $highRisk = BlockedSubmission::byRiskScore(80, 100)->get();

        $this->assertCount(1, $lowRisk);
        $this->assertCount(1, $highRisk);
        $this->assertEquals(25, $lowRisk->first()->risk_score);
        $this->assertEquals(95, $highRisk->first()->risk_score);
    }

    #[Test]
    public function scope_high_risk_filters_correctly(): void
    {
        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.1',
            'block_reason' => 'spam_pattern',
            'risk_score' => 75,
            'blocked_at' => now(),
        ]);

        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.2',
            'block_reason' => 'spam_pattern',
            'risk_score' => 85,
            'blocked_at' => now(),
        ]);

        $highRiskSubmissions = BlockedSubmission::highRisk()->get();

        $this->assertCount(1, $highRiskSubmissions);
        $this->assertEquals(85, $highRiskSubmissions->first()->risk_score);
    }

    #[Test]
    public function scope_recent_blocks_filters_correctly(): void
    {
        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.1',
            'block_reason' => 'spam_pattern',
            'blocked_at' => now()->subHours(2),
        ]);

        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.2',
            'block_reason' => 'spam_pattern',
            'blocked_at' => now()->subHours(25),
        ]);

        $recentBlocks = BlockedSubmission::recentBlocks(24)->get();

        $this->assertCount(1, $recentBlocks);
        $this->assertTrue($recentBlocks->first()->blocked_at->isAfter(now()->subHours(24)));
    }

    #[Test]
    public function scope_by_proxy_type_filters_correctly(): void
    {
        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.1',
            'block_reason' => 'spam_pattern',
            'is_tor' => true,
            'is_proxy' => false,
            'is_vpn' => false,
            'blocked_at' => now(),
        ]);

        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.2',
            'block_reason' => 'spam_pattern',
            'is_tor' => false,
            'is_proxy' => true,
            'is_vpn' => false,
            'blocked_at' => now(),
        ]);

        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.3',
            'block_reason' => 'spam_pattern',
            'is_tor' => false,
            'is_proxy' => false,
            'is_vpn' => true,
            'blocked_at' => now(),
        ]);

        $torBlocks = BlockedSubmission::byProxyType('tor')->get();
        $proxyBlocks = BlockedSubmission::byProxyType('proxy')->get();
        $vpnBlocks = BlockedSubmission::byProxyType('vpn')->get();
        $anyBlocks = BlockedSubmission::byProxyType('any')->get();

        $this->assertCount(1, $torBlocks);
        $this->assertCount(1, $proxyBlocks);
        $this->assertCount(1, $vpnBlocks);
        $this->assertCount(3, $anyBlocks);

        $this->assertTrue($torBlocks->first()->is_tor);
        $this->assertTrue($proxyBlocks->first()->is_proxy);
        $this->assertTrue($vpnBlocks->first()->is_vpn);
    }

    #[Test]
    public function is_suspicious_network_method_works_correctly(): void
    {
        $normalSubmission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.1',
            'block_reason' => 'spam_pattern',
            'is_tor' => false,
            'is_proxy' => false,
            'is_vpn' => false,
            'blocked_at' => now(),
        ]);

        $suspiciousSubmission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.2',
            'block_reason' => 'spam_pattern',
            'is_tor' => true,
            'is_proxy' => false,
            'is_vpn' => false,
            'blocked_at' => now(),
        ]);

        $this->assertFalse($normalSubmission->isSuspiciousNetwork());
        $this->assertTrue($suspiciousSubmission->isSuspiciousNetwork());
    }

    #[Test]
    public function get_risk_level_method_returns_correct_levels(): void
    {
        $testCases = [
            ['score' => 95, 'expected' => RiskLevel::CRITICAL],
            ['score' => 85, 'expected' => RiskLevel::HIGH],
            ['score' => 55, 'expected' => RiskLevel::MEDIUM],
            ['score' => 25, 'expected' => RiskLevel::LOW],
            ['score' => 5, 'expected' => RiskLevel::MINIMAL],
        ];

        foreach ($testCases as $case) {
            $submission = BlockedSubmission::create([
                'form_identifier' => 'test_form',
                'ip_address' => '10.0.0.1',
                'block_reason' => 'spam_pattern',
                'risk_score' => $case['score'],
                'blocked_at' => now(),
            ]);

            $this->assertEquals($case['expected'], $submission->getRiskLevel());
        }
    }

    #[Test]
    public function get_location_string_method_formats_correctly(): void
    {
        $submission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.1',
            'block_reason' => 'spam_pattern',
            'city' => 'New York',
            'region' => 'NY',
            'country_code' => 'US',
            'blocked_at' => now(),
        ]);

        $this->assertEquals('New York, NY, US', $submission->getLocationString());

        $partialSubmission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.2',
            'block_reason' => 'spam_pattern',
            'country_code' => 'CA',
            'blocked_at' => now(),
        ]);

        $this->assertEquals('CA', $partialSubmission->getLocationString());

        $unknownSubmission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.3',
            'block_reason' => 'spam_pattern',
            'blocked_at' => now(),
        ]);

        $this->assertEquals('Unknown Location', $unknownSubmission->getLocationString());
    }

    #[Test]
    public function has_geolocation_method_works_correctly(): void
    {
        $withGeolocation = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.1',
            'block_reason' => 'spam_pattern',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'blocked_at' => now(),
        ]);

        $withoutGeolocation = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.2',
            'block_reason' => 'spam_pattern',
            'blocked_at' => now(),
        ]);

        $this->assertTrue($withGeolocation->hasGeolocation());
        $this->assertFalse($withoutGeolocation->hasGeolocation());
    }

    #[Test]
    public function get_time_elapsed_method_returns_human_readable_time(): void
    {
        $submission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '10.0.0.1',
            'block_reason' => 'spam_pattern',
            'blocked_at' => now()->subHours(2),
        ]);

        $timeElapsed = $submission->getTimeElapsed();

        $this->assertIsString($timeElapsed);
        $this->assertStringContainsString('ago', $timeElapsed);
    }

    #[Test]
    public function it_casts_block_reason_to_enum(): void
    {
        $submission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '192.168.1.100',
            'block_reason' => BlockReason::SPAM_PATTERN->value,
            'blocked_at' => now(),
        ]);

        $this->assertInstanceOf(BlockReason::class, $submission->block_reason);
        $this->assertEquals(BlockReason::SPAM_PATTERN, $submission->block_reason);
    }

    #[Test]
    public function it_has_relationship_with_ip_reputation(): void
    {
        $testIp = '172.17.'.((int) (microtime(true) * 1000) % 255 + 1).'.'.mt_rand(1, 254); // Generate unique IP
        $submission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => $testIp,
            'block_reason' => 'spam_pattern',
            'blocked_at' => now(),
        ]);

        // Get the IP reputation created by the observer
        $ipReputation = IpReputation::where('ip_address', $testIp)->first();
        $this->assertNotNull($ipReputation, 'IP reputation should be created by observer');

        // Refresh the submission to ensure relationship is loaded
        $submission->refresh();

        $this->assertInstanceOf(IpReputation::class, $submission->ipReputation);
        $this->assertEquals($ipReputation->id, $submission->ipReputation->id);
    }

    #[Test]
    public function it_can_get_or_create_ip_reputation(): void
    {
        $uniqueIp = '172.16.'.((int) (microtime(true) * 1000) % 255 + 1).'.'.mt_rand(1, 254); // Generate a unique IP from private range
        $submission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => $uniqueIp,
            'block_reason' => 'spam_pattern',
            'blocked_at' => now(),
        ]);

        // The observer should have already created an IP reputation
        $this->assertDatabaseHas('ip_reputation', [
            'ip_address' => $uniqueIp,
        ]);

        $ipReputation = $submission->getOrCreateIpReputation();

        $this->assertInstanceOf(IpReputation::class, $ipReputation);
        $this->assertEquals($uniqueIp, $ipReputation->ip_address);
        $this->assertDatabaseHas('ip_reputation', [
            'ip_address' => $uniqueIp,
        ]);
    }

    #[Test]
    public function it_calculates_risk_level_correctly(): void
    {
        $highRiskSubmission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '192.168.1.100',
            'block_reason' => 'spam_pattern',
            'risk_score' => 95,
            'blocked_at' => now(),
        ]);

        $lowRiskSubmission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '192.168.1.101',
            'block_reason' => 'rate_limit',
            'risk_score' => 25,
            'blocked_at' => now(),
        ]);

        $this->assertEquals(RiskLevel::CRITICAL, $highRiskSubmission->getRiskLevel());
        $this->assertEquals(RiskLevel::LOW, $lowRiskSubmission->getRiskLevel());
    }

    #[Test]
    public function it_detects_automated_threats(): void
    {
        $honeypotSubmission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '192.168.1.100',
            'block_reason' => BlockReason::HONEYPOT->value,
            'blocked_at' => now(),
        ]);

        $geoSubmission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '192.168.1.101',
            'block_reason' => BlockReason::GEOLOCATION->value,
            'blocked_at' => now(),
        ]);

        $this->assertTrue($honeypotSubmission->isAutomatedThreat());
        $this->assertFalse($geoSubmission->isAutomatedThreat());
    }

    #[Test]
    public function it_scopes_by_risk_level(): void
    {
        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '192.168.1.100',
            'block_reason' => 'spam_pattern',
            'risk_score' => 95,
            'blocked_at' => now(),
        ]);

        BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '192.168.1.101',
            'block_reason' => 'rate_limit',
            'risk_score' => 25,
            'blocked_at' => now(),
        ]);

        $criticalSubmissions = BlockedSubmission::byRiskLevel(RiskLevel::CRITICAL)->get();
        $lowSubmissions = BlockedSubmission::byRiskLevel(RiskLevel::LOW)->get();

        $this->assertCount(1, $criticalSubmissions);
        $this->assertCount(1, $lowSubmissions);
    }

    #[Test]
    public function it_scopes_suspicious_networks(): void
    {
        $torSubmission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '192.168.1.100',
            'block_reason' => 'ip_reputation',
            'is_tor' => true,
            'blocked_at' => now(),
        ]);

        $normalSubmission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '192.168.1.101',
            'block_reason' => 'rate_limit',
            'is_tor' => false,
            'is_proxy' => false,
            'is_vpn' => false,
            'blocked_at' => now(),
        ]);

        $suspiciousSubmissions = BlockedSubmission::suspiciousNetworks()->get();

        $this->assertTrue($suspiciousSubmissions->contains($torSubmission));
        $this->assertFalse($suspiciousSubmissions->contains($normalSubmission));
    }

    #[Test]
    public function it_calculates_comprehensive_risk_score(): void
    {
        $submission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '192.168.1.100',
            'block_reason' => BlockReason::HONEYPOT->value,
            'is_tor' => true,
            'country_code' => 'CN',
            'form_field_count' => 2,
            'blocked_at' => now(),
        ]);

        $riskScore = $submission->calculateComprehensiveRiskScore();

        $this->assertGreaterThan(90, $riskScore);
        $this->assertLessThanOrEqual(100, $riskScore);
    }

    #[Test]
    public function it_generates_threat_assessment(): void
    {
        $submission = BlockedSubmission::create([
            'form_identifier' => 'test_form',
            'ip_address' => '192.168.1.100',
            'block_reason' => 'spam_pattern',
            'risk_score' => 75,
            'blocked_at' => now(),
        ]);

        $assessment = $submission->generateThreatAssessment();

        $this->assertIsArray($assessment);
        $this->assertArrayHasKey('risk_score', $assessment);
        $this->assertArrayHasKey('risk_level', $assessment);
        $this->assertArrayHasKey('threat_indicators', $assessment);
        $this->assertArrayHasKey('recommendations', $assessment);
        $this->assertArrayHasKey('submission_patterns', $assessment);
    }
}
