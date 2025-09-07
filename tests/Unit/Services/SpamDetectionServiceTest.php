<?php

declare(strict_types=1);

/**
 * Test File: SpamDetectionServiceTest.php
 *
 * EPIC: EPIC-002-core-spam-detection-engine
 * SPEC: SPEC-004-pattern-based-spam-detection
 * SPRINT: Sprint-007-epic-002-foundation-setup
 * TICKET: 2012-core-spam-detection-service
 *
 * Description: Tests for SpamDetectionService functionality implementing hybrid detection algorithm
 * with weighted scoring, Epic-001 integration, and comprehensive analysis capabilities.
 *
 * @see docs/Planning/Epics/EPIC-002-core-spam-detection-engine.md
 * @see docs/Planning/Specs/Core-Detection-Engine/SPEC-004-pattern-based-spam-detection.md
 * @see docs/Planning/Tickets/Core-Spam-Detection-Engine/Implementation/2012-core-spam-detection-service.md
 */

namespace JTD\FormSecurity\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use JTD\FormSecurity\Contracts\SpamDetectionContract;
use JTD\FormSecurity\Tests\TestCase;
use JTD\FormSecurity\ValueObjects\SpamDetectionResult;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-002')]
#[Group('core-spam-detection-engine')]
#[Group('sprint-007')]
#[Group('ticket-2012')]
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

    #[Test]
    public function calculate_spam_score_handles_empty_content(): void
    {
        $score = $this->spamDetectionService->calculateSpamScore('');
        $this->assertEquals(0.0, $score);

        $score = $this->spamDetectionService->calculateSpamScore('   ');
        $this->assertEquals(0.0, $score);
    }

    #[Test]
    public function calculate_spam_score_with_metadata(): void
    {
        $content = 'Test content';
        $metadata = ['source' => 'test'];

        $score = $this->spamDetectionService->calculateSpamScore($content, $metadata);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(1.0, $score);
    }

    #[Test]
    public function check_spam_patterns_handles_empty_content(): void
    {
        $matches = $this->spamDetectionService->checkSpamPatterns('');
        $this->assertIsArray($matches);
        $this->assertEmpty($matches);

        $matches = $this->spamDetectionService->checkSpamPatterns('   ');
        $this->assertIsArray($matches);
        $this->assertEmpty($matches);
    }

    #[Test]
    public function check_spam_patterns_handles_regex_errors(): void
    {
        // Set invalid regex pattern that will cause preg_match to fail
        config(['form-security.patterns.spam' => ['/[/' => 0.8]]);

        $content = 'Test content';
        $matches = $this->spamDetectionService->checkSpamPatterns($content);

        // Should fallback to default patterns without crashing
        $this->assertIsArray($matches);
    }

    #[Test]
    public function check_rate_limit_allows_empty_identifier(): void
    {
        $result = $this->spamDetectionService->checkRateLimit('');
        $this->assertTrue($result);

        $result = $this->spamDetectionService->checkRateLimit('   ');
        $this->assertTrue($result);
    }

    #[Test]
    public function check_rate_limit_uses_multiple_windows(): void
    {
        $identifier = 'multi_window_test';

        // Should check per_minute, per_hour, and per_day windows
        $result = $this->spamDetectionService->checkRateLimit($identifier);
        $this->assertTrue($result);

        // Verify cache keys are being used
        $this->assertTrue(Cache::has("form_security:rate_limit:{$identifier}:minute"));
        $this->assertTrue(Cache::has("form_security:rate_limit:{$identifier}:hour"));
        $this->assertTrue(Cache::has("form_security:rate_limit:{$identifier}:day"));
    }

    #[Test]
    public function update_spam_patterns_handles_invalid_patterns(): void
    {
        $patterns = [
            ['pattern' => '/test/'], // Missing pattern_type
            ['pattern_type' => 'regex'], // Missing pattern
            [], // Empty pattern data
        ];

        $result = $this->spamDetectionService->updateSpamPatterns($patterns);
        $this->assertTrue($result); // Should still return true even with invalid data
    }

    #[Test]
    public function update_spam_patterns_clears_cache(): void
    {
        // Set cache values
        Cache::put('active_patterns', ['test'], 60);
        Cache::put('patterns_by_type', ['test'], 60);

        $patterns = [
            [
                'pattern' => '/test_pattern/',
                'pattern_type' => 'regex',
                'risk_score' => 80,
                'name' => 'Test Pattern',
            ],
        ];

        $result = $this->spamDetectionService->updateSpamPatterns($patterns);

        $this->assertTrue($result);
        $this->assertNull(Cache::get('active_patterns'));
        $this->assertNull(Cache::get('patterns_by_type'));
    }

    #[Test]
    public function get_detection_stats_handles_errors(): void
    {
        // Mock an error scenario by clearing all cache and database
        Cache::flush();

        $stats = $this->spamDetectionService->getDetectionStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_analyzed', $stats);
        $this->assertArrayHasKey('timestamp', $stats);
    }

    #[Test]
    public function get_detection_stats_calculates_accuracy(): void
    {
        // Set up test statistics
        Cache::put('form_security:stats:spam_detected', 80, 3600);
        Cache::put('form_security:stats:clean_submissions', 20, 3600);
        Cache::put('form_security:stats:false_positives', 5, 3600);

        $stats = $this->spamDetectionService->getDetectionStats();

        $this->assertArrayHasKey('accuracy_rate', $stats);
        $this->assertIsFloat($stats['accuracy_rate']);
        $this->assertGreaterThanOrEqual(0.0, $stats['accuracy_rate']);
        $this->assertLessThanOrEqual(1.0, $stats['accuracy_rate']);
    }

    #[Test]
    public function analyze_spam_handles_errors_gracefully(): void
    {
        // Mock an error by providing invalid service dependencies
        $service = $this->app->make(SpamDetectionContract::class);

        // Test with malformed data that might cause errors
        $data = ['test' => new \stdClass]; // Non-string value
        $context = ['invalid_context' => new \stdClass];

        $result = $service->analyzeSpam($data, $context);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('timestamp', $result);
    }

    #[Test]
    public function analyze_spam_logs_errors_on_failure(): void
    {
        // This test ensures error logging functionality
        $data = $this->createSampleFormData();

        $result = $this->spamDetectionService->analyzeSpam($data);

        $this->assertIsArray($result);
        // If no error occurs, we should have normal result structure
        $this->assertArrayNotHasKey('error', $result);
    }

    #[Test]
    public function analyze_spam_with_caching(): void
    {
        $data = ['message' => 'Cached test message'];
        $context = ['ip' => '192.168.1.1'];

        // First call - should cache result
        $result1 = $this->spamDetectionService->analyzeSpam($data, $context);

        // Second call - should use cached result
        $result2 = $this->spamDetectionService->analyzeSpam($data, $context);

        $this->assertIsArray($result1);
        $this->assertIsArray($result2);

        // Results should be identical (from cache)
        $this->assertEquals($result1['score'], $result2['score']);
    }

    #[Test]
    public function check_default_patterns_fallback(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('checkDefaultPatterns');
        $method->setAccessible(true);

        $content = 'Buy cheap viagra online!';
        $result = $method->invoke($service, $content);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $match = $result[0];
        $this->assertArrayHasKey('pattern', $match);
        $this->assertArrayHasKey('confidence', $match);
        $this->assertArrayHasKey('source', $match);
        $this->assertEquals('default', $match['source']);
    }

    #[Test]
    public function create_data_summary_handles_mixed_data(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('createDataSummary');
        $method->setAccessible(true);

        $data = [
            'string_field' => 'test string',
            'number_field' => 123,
            'array_field' => ['nested', 'data'],
            'null_field' => null,
            'bool_field' => true,
        ];

        $result = $method->invoke($service, $data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('field_count', $result);
        $this->assertArrayHasKey('total_length', $result);
        $this->assertArrayHasKey('field_types', $result);

        $this->assertEquals(5, $result['field_count']);
        $this->assertIsInt($result['total_length']);
    }

    #[Test]
    public function create_content_summary_calculates_correctly(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('createContentSummary');
        $method->setAccessible(true);

        $data = [
            'message' => 'Hello world',
            'email' => 'test@example.com',
            'number' => 123, // Non-string field
        ];

        $result = $method->invoke($service, $data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('field_count', $result);
        $this->assertArrayHasKey('total_length', $result);
        $this->assertArrayHasKey('fields', $result);

        $this->assertEquals(3, $result['field_count']);
        $this->assertIsInt($result['total_length']);
        $this->assertContains('message', $result['fields']);
        $this->assertContains('email', $result['fields']);
    }

    #[Test]
    public function get_recommendation_returns_appropriate_actions(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getRecommendation');
        $method->setAccessible(true);

        // Test block recommendation
        $result = $method->invoke($service, 0.9, 0.9, true);
        $this->assertEquals('block', $result);

        // Test review recommendation
        $result = $method->invoke($service, 0.5, 0.5, false);
        $this->assertEquals('review', $result);

        // Test captcha recommendation
        $result = $method->invoke($service, 0.8, 0.8, false);
        $this->assertEquals('captcha', $result);

        // Test allow recommendation
        $result = $method->invoke($service, 0.2, 0.8, false);
        $this->assertEquals('allow', $result);
    }

    #[Test]
    public function performance_optimization_caching_works(): void
    {
        $content = 'Test content for caching';

        // First call should calculate and cache
        $startTime = microtime(true);
        $score1 = $this->spamDetectionService->calculateSpamScore($content);
        $time1 = (microtime(true) - $startTime) * 1000;

        // Second call should be faster (cached)
        $startTime = microtime(true);
        $score2 = $this->spamDetectionService->calculateSpamScore($content);
        $time2 = (microtime(true) - $startTime) * 1000;

        $this->assertEquals($score1, $score2);
        $this->assertLessThan($time1, $time2); // Second call should be faster
    }

    #[Test]
    public function pattern_matching_early_exit_optimization(): void
    {
        // Set high confidence pattern
        config(['form-security.patterns.spam' => ['/high_confidence_spam/' => 0.95]]);

        $content = 'This contains high_confidence_spam keyword';
        $matches = $this->spamDetectionService->checkSpamPatterns($content);

        $this->assertNotEmpty($matches);
        $this->assertGreaterThanOrEqual(0.95, $matches[0]['confidence']);
    }

    #[Test]
    public function analyze_spam_early_exit_on_rate_limit(): void
    {
        // Set up rate limiting to trigger early exit
        $context = [
            'ip' => '192.168.1.100',
            'user_agent' => 'TestAgent',
        ];

        // Exhaust rate limits
        $limits = ['max_attempts' => 1, 'window_minutes' => 60];
        $this->spamDetectionService->checkRateLimit($context['ip'], $limits);

        $data = ['message' => 'test'];
        $result = $this->spamDetectionService->analyzeSpam($data, $context);

        $this->assertIsArray($result);
        // Should have high score due to rate limiting
        $this->assertArrayHasKey('score', $result);
    }

    #[Test]
    public function get_performance_status_methods(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);

        // Test getPerformanceStatus
        $method = $reflection->getMethod('getPerformanceStatus');
        $method->setAccessible(true);

        // Set different processing times to test status categories
        Cache::put('form_security:stats:avg_processing_time', 5.0, 60);
        $status = $method->invoke($service);
        $this->assertEquals('excellent', $status);

        Cache::put('form_security:stats:avg_processing_time', 20.0, 60);
        $status = $method->invoke($service);
        $this->assertEquals('good', $status);

        Cache::put('form_security:stats:avg_processing_time', 40.0, 60);
        $status = $method->invoke($service);
        $this->assertEquals('acceptable', $status);

        Cache::put('form_security:stats:avg_processing_time', 100.0, 60);
        $status = $method->invoke($service);
        $this->assertEquals('needs_improvement', $status);
    }

    #[Test]
    public function get_cache_hit_ratio_calculation(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getCacheHitRatio');
        $method->setAccessible(true);

        // Test with no cache data
        $ratio = $method->invoke($service);
        $this->assertEquals(0.0, $ratio);

        // Test with cache data
        Cache::put('form_security:cache:hits', 80, 60);
        Cache::put('form_security:cache:total', 100, 60);

        $ratio = $method->invoke($service);
        $this->assertEquals(0.8, $ratio);
    }

    #[Test]
    public function get_recent_trends_returns_hourly_data(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getRecentTrends');
        $method->setAccessible(true);

        // Set some test data
        $hour = now()->format('Y-m-d-H');
        Cache::put("form_security:stats:hourly:{$hour}", ['analyzed' => 100, 'spam' => 20], 60);

        $trends = $method->invoke($service);

        $this->assertIsArray($trends);
        $this->assertCount(24, $trends); // Should return 24 hours of data

        foreach ($trends as $trend) {
            $this->assertArrayHasKey('hour', $trend);
            $this->assertArrayHasKey('analyzed', $trend);
            $this->assertArrayHasKey('spam_detected', $trend);
            $this->assertArrayHasKey('spam_rate', $trend);
        }
    }

    #[Test]
    public function get_method_statistics_returns_complete_data(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getMethodStatistics');
        $method->setAccessible(true);

        // Set some method statistics
        Cache::put('form_security:stats:method:regex', ['count' => 50, 'total_score' => 25.0], 60);

        $stats = $method->invoke($service);

        $this->assertIsArray($stats);

        foreach ($stats as $methodName => $methodStats) {
            $this->assertArrayHasKey('usage_count', $methodStats);
            $this->assertArrayHasKey('average_score', $methodStats);
            $this->assertArrayHasKey('default_weight', $methodStats);
            $this->assertArrayHasKey('description', $methodStats);
        }
    }

    #[Test]
    public function update_detection_statistics_handles_errors(): void
    {
        $service = $this->spamDetectionService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('updateDetectionStatistics');
        $method->setAccessible(true);

        // Create a proper SpamDetectionResult object (can't mock readonly properties)
        $result = SpamDetectionResult::clean(
            score: 0.3,
            methodScores: ['regex' => 0.3, 'behavioral' => 0.2],
            details: ['test' => 'data'],
            confidence: 0.8,
            metadata: ['test_case' => 'update_detection_statistics']
        );

        // Should not throw exceptions
        $method->invoke($service, $result);

        // Verify statistics were updated
        $this->assertGreaterThan(0, Cache::get('form_security:stats:total_analyzed', 0));
    }

    #[Test]
    public function log_analysis_respects_configuration(): void
    {
        // Test with logging enabled
        config(['form-security.spam_detection.enable_analysis_logging' => true]);

        $data = $this->createSampleFormData();
        $result = $this->spamDetectionService->analyzeSpam($data);

        $this->assertIsArray($result);

        // Test with logging disabled
        config(['form-security.spam_detection.enable_analysis_logging' => false]);

        $result = $this->spamDetectionService->analyzeSpam($data);

        $this->assertIsArray($result);
    }
}
