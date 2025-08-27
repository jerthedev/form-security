<?php

declare(strict_types=1);

/**
 * Test File: SpamDetectionServiceTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: N/A - Spam detection service testing
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1020-service-provider-tests
 *
 * Description: Tests for SpamDetectionService functionality.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1020-service-provider-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use JTD\FormSecurity\Contracts\SpamDetectionContract;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-002')]
#[Group('ticket-1020')]
#[Group('spam-detection-service')]
#[Group('unit')]
class SpamDetectionServiceTest extends TestCase
{
    private SpamDetectionContract $spamDetectionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spamDetectionService = $this->app->make(SpamDetectionContract::class);
        Cache::flush(); // Clear cache for each test
    }

    #[Test]
    public function analyze_spam_returns_valid_structure(): void
    {
        $data = $this->createSampleFormData();
        $result = $this->spamDetectionService->analyzeSpam($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('threats', $result);
        $this->assertArrayHasKey('details', $result);
        $this->assertArrayHasKey('timestamp', $result);

        $this->assertIsFloat($result['score']);
        $this->assertIsArray($result['threats']);
        $this->assertIsArray($result['details']);
        $this->assertIsString($result['timestamp']);
    }

    #[Test]
    public function analyze_spam_with_context(): void
    {
        $data = $this->createSampleFormData();
        $context = ['submission_frequency' => 10, 'user_agent' => ''];

        $result = $this->spamDetectionService->analyzeSpam($data, $context);

        $this->assertIsArray($result);
        $this->assertContains('high_frequency_submission', $result['threats']);
        $this->assertContains('missing_user_agent', $result['threats']);
    }

    #[Test]
    public function calculate_spam_score_for_clean_content(): void
    {
        $cleanContent = 'This is a normal message from a legitimate user.';
        $score = $this->spamDetectionService->calculateSpamScore($cleanContent);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(1.0, $score);
        $this->assertLessThan(0.5, $score); // Should be low for clean content
    }

    #[Test]
    public function calculate_spam_score_for_spam_content(): void
    {
        $spamContent = 'Buy cheap viagra online! Casino gambling poker! Make money fast!';
        $score = $this->spamDetectionService->calculateSpamScore($spamContent);

        $this->assertIsFloat($score);
        $this->assertGreaterThan(0.2, $score); // Should be higher for spam content
    }

    #[Test]
    public function calculate_spam_score_penalizes_short_content(): void
    {
        $shortContent = 'Hi';
        $score = $this->spamDetectionService->calculateSpamScore($shortContent);

        $this->assertGreaterThanOrEqual(0.2, $score); // Should add 0.2 for short content
    }

    #[Test]
    public function calculate_spam_score_penalizes_long_content(): void
    {
        $longContent = str_repeat('This is a very long message. ', 200); // > 5000 chars
        $score = $this->spamDetectionService->calculateSpamScore($longContent);

        $this->assertGreaterThanOrEqual(0.3, $score); // Should add 0.3 for long content
    }

    #[Test]
    public function calculate_spam_score_detects_excessive_links(): void
    {
        $contentWithLinks = 'Check out https://example1.com and https://example2.com and https://example3.com and https://example4.com';
        $score = $this->spamDetectionService->calculateSpamScore($contentWithLinks);

        $this->assertGreaterThanOrEqual(0.3, $score); // Should add 0.3 for excessive links
    }

    #[Test]
    public function calculate_spam_score_detects_excessive_capitalization(): void
    {
        $capsContent = 'THIS IS ALL CAPS CONTENT WHICH LOOKS LIKE SPAM';
        $score = $this->spamDetectionService->calculateSpamScore($capsContent);

        $this->assertGreaterThanOrEqual(0.2, $score); // Should add 0.2 for excessive caps
    }

    #[Test]
    public function check_spam_patterns_detects_default_patterns(): void
    {
        $spamContent = 'Buy cheap viagra online!';
        $matches = $this->spamDetectionService->checkSpamPatterns($spamContent);

        $this->assertIsArray($matches);
        $this->assertNotEmpty($matches);

        $match = $matches[0];
        $this->assertArrayHasKey('pattern', $match);
        $this->assertArrayHasKey('match', $match);
        $this->assertArrayHasKey('confidence', $match);
    }

    #[Test]
    public function check_spam_patterns_with_custom_patterns(): void
    {
        // Set custom patterns
        config(['form-security.patterns.spam' => ['/\bcustom_spam\b/i' => 0.9]]);

        $content = 'This contains custom_spam keyword';
        $matches = $this->spamDetectionService->checkSpamPatterns($content);

        $this->assertNotEmpty($matches);
        $this->assertEquals(0.9, $matches[0]['confidence']);
    }

    #[Test]
    public function check_rate_limit_allows_within_limits(): void
    {
        $identifier = 'test_user';

        $result = $this->spamDetectionService->checkRateLimit($identifier);

        $this->assertTrue($result);
    }

    #[Test]
    public function check_rate_limit_blocks_when_exceeded(): void
    {
        $identifier = 'test_user_blocked';
        $limits = ['max_attempts' => 2, 'window_minutes' => 60];

        // First two attempts should pass
        $this->assertTrue($this->spamDetectionService->checkRateLimit($identifier, $limits));
        $this->assertTrue($this->spamDetectionService->checkRateLimit($identifier, $limits));

        // Third attempt should be blocked
        $this->assertFalse($this->spamDetectionService->checkRateLimit($identifier, $limits));
    }

    #[Test]
    public function check_rate_limit_uses_default_limits(): void
    {
        $identifier = 'test_default_limits';

        // Should use default limits from config
        $result = $this->spamDetectionService->checkRateLimit($identifier);

        $this->assertTrue($result);
    }

    #[Test]
    public function update_spam_patterns_returns_true(): void
    {
        $patterns = ['/\bnew_spam_pattern\b/i' => 0.8];

        $result = $this->spamDetectionService->updateSpamPatterns($patterns);

        $this->assertTrue($result);
    }

    #[Test]
    public function get_detection_stats_returns_statistics(): void
    {
        $stats = $this->spamDetectionService->getDetectionStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_analyzed', $stats);
        $this->assertArrayHasKey('spam_detected', $stats);
        $this->assertArrayHasKey('false_positives', $stats);
        $this->assertArrayHasKey('average_processing_time', $stats);
        $this->assertArrayHasKey('patterns_count', $stats);

        $this->assertIsInt($stats['total_analyzed']);
        $this->assertIsInt($stats['spam_detected']);
        $this->assertIsInt($stats['false_positives']);
        $this->assertIsFloat($stats['average_processing_time']);
        $this->assertIsInt($stats['patterns_count']);
    }

    #[Test]
    public function analyze_field_detects_invalid_email(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('analyzeField');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'email', 'invalid-email');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('threats', $result);
        $this->assertContains('invalid_email', $result['threats']);
        $this->assertGreaterThanOrEqual(0.3, $result['score']);
    }

    #[Test]
    public function analyze_field_detects_invalid_url(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('analyzeField');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'url', 'invalid-url');

        $this->assertIsArray($result);
        $this->assertContains('invalid_url', $result['threats']);
        $this->assertGreaterThanOrEqual(0.2, $result['score']);
    }

    #[Test]
    public function analyze_field_handles_valid_email(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('analyzeField');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'email', 'valid@example.com');

        $this->assertIsArray($result);
        $this->assertNotContains('invalid_email', $result['threats']);
    }

    #[Test]
    public function analyze_context_detects_high_frequency(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('analyzeContext');
        $method->setAccessible(true);

        $context = ['submission_frequency' => 10];
        $result = $method->invoke($service, $context);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score_adjustment', $result);
        $this->assertArrayHasKey('threats', $result);
        $this->assertContains('high_frequency_submission', $result['threats']);
        $this->assertGreaterThanOrEqual(0.2, $result['score_adjustment']);
    }

    #[Test]
    public function analyze_context_detects_missing_user_agent(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('analyzeContext');
        $method->setAccessible(true);

        $context = ['user_agent' => ''];
        $result = $method->invoke($service, $context);

        $this->assertContains('missing_user_agent', $result['threats']);
        $this->assertGreaterThanOrEqual(0.1, $result['score_adjustment']);
    }

    #[Test]
    public function analyze_context_handles_normal_context(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('analyzeContext');
        $method->setAccessible(true);

        $context = ['submission_frequency' => 1, 'user_agent' => 'Mozilla/5.0'];
        $result = $method->invoke($service, $context);

        $this->assertEquals(0.0, $result['score_adjustment']);
        $this->assertEmpty($result['threats']);
    }

    #[Test]
    public function service_performance_meets_requirements(): void
    {
        $data = $this->createSpamFormData();

        $startTime = microtime(true);
        $this->spamDetectionService->analyzeSpam($data);
        $endTime = microtime(true);

        $processingTime = $endTime - $startTime;
        $this->assertPerformanceRequirement($processingTime, 'SpamDetectionService analysis');
    }
}
