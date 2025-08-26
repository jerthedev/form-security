<?php

declare(strict_types=1);

/**
 * Test File: FacadeTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: N/A - Facade testing
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1010-service-provider-package-registration
 *
 * Description: Tests for the FormSecurity facade including static method
 * access, service resolution, and convenience methods.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1010-service-provider-package-registration.md
 */

namespace JTD\FormSecurity\Tests\Unit;

use JTD\FormSecurity\Contracts\FormSecurityContract;
use JTD\FormSecurity\Facades\FormSecurity;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-002')]
#[Group('ticket-1010')]
#[Group('facade')]
#[Group('unit')]
class FacadeTest extends TestCase
{
    #[Test]
    public function facade_resolves_to_correct_service(): void
    {
        // Test that the facade resolves to the FormSecurityContract
        $service = FormSecurity::getFacadeRoot();
        $this->assertInstanceOf(FormSecurityContract::class, $service);
    }

    #[Test]
    public function facade_provides_static_access_to_analyze_submission(): void
    {
        // Test static access to analyzeSubmission method
        $data = $this->createSampleFormData();
        $result = FormSecurity::analyzeSubmission($data);

        $this->assertValidAnalysisStructure($result);
        $this->assertIsFloat($result['score']);
        $this->assertGreaterThanOrEqual(0.0, $result['score']);
        $this->assertLessThanOrEqual(1.0, $result['score']);
    }

    #[Test]
    public function facade_provides_static_access_to_validate_submission(): void
    {
        // Test static access to validateSubmission method
        $validData = $this->createSampleFormData();
        $this->assertTrue(FormSecurity::validateSubmission($validData));

        $spamData = $this->createSpamFormData();
        $this->assertFalse(FormSecurity::validateSubmission($spamData));
    }

    #[Test]
    public function facade_provides_static_access_to_is_ip_blocked(): void
    {
        // Test static access to isIpBlocked method
        $validIp = '192.168.1.1';
        $this->assertFalse(FormSecurity::isIpBlocked($validIp));

        $invalidIp = 'invalid-ip';
        $this->assertTrue(FormSecurity::isIpBlocked($invalidIp));
    }

    #[Test]
    public function facade_provides_static_access_to_get_config(): void
    {
        // Test static access to getConfig method
        $enabled = FormSecurity::getConfig('enabled');
        $this->assertIsBool($enabled);

        $threshold = FormSecurity::getConfig('spam_threshold');
        $this->assertIsFloat($threshold);

        $nonExistent = FormSecurity::getConfig('non_existent_key', 'default');
        $this->assertEquals('default', $nonExistent);
    }

    #[Test]
    public function facade_provides_static_access_to_toggle_feature(): void
    {
        // Test static access to toggleFeature method
        $result = FormSecurity::toggleFeature('test_feature', true);
        $this->assertTrue($result);

        $enabled = FormSecurity::getConfig('features.test_feature');
        $this->assertTrue($enabled);
    }

    #[Test]
    public function facade_provides_static_access_to_get_package_info(): void
    {
        // Test static access to getPackageInfo method
        $info = FormSecurity::getPackageInfo();

        $this->assertIsArray($info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('version', $info);
        $this->assertArrayHasKey('enabled', $info);
        $this->assertArrayHasKey('features', $info);
    }

    #[Test]
    public function facade_convenience_method_is_spam_works(): void
    {
        // Test the isSpam convenience method
        $cleanData = $this->createSampleFormData();
        $this->assertFalse(FormSecurity::isSpam($cleanData));

        $spamData = $this->createSpamFormData();
        $this->assertTrue(FormSecurity::isSpam($spamData));

        // Test with custom threshold
        $this->assertFalse(FormSecurity::isSpam($spamData, 0.95)); // Very high threshold
    }

    #[Test]
    public function facade_convenience_method_is_valid_works(): void
    {
        // Test the isValid convenience method
        $validData = $this->createSampleFormData();
        $this->assertTrue(FormSecurity::isValid($validData));

        $invalidData = $this->createSpamFormData();
        $this->assertFalse(FormSecurity::isValid($invalidData));
    }

    #[Test]
    public function facade_convenience_method_get_spam_score_works(): void
    {
        // Test the getSpamScore convenience method
        $data = $this->createSampleFormData();
        $score = FormSecurity::getSpamScore($data);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(1.0, $score);
    }

    #[Test]
    public function facade_convenience_method_is_enabled_works(): void
    {
        // Test the isEnabled convenience method
        $enabled = FormSecurity::isEnabled();
        $this->assertIsBool($enabled);
        $this->assertTrue($enabled); // Should be true in test environment
    }

    #[Test]
    public function facade_convenience_method_is_feature_enabled_works(): void
    {
        // Test the isFeatureEnabled convenience method
        $spamDetection = FormSecurity::isFeatureEnabled('spam_detection');
        $this->assertTrue($spamDetection); // Should be enabled in test config

        $nonExistent = FormSecurity::isFeatureEnabled('non_existent_feature');
        $this->assertFalse($nonExistent);
    }

    #[Test]
    public function facade_convenience_method_version_works(): void
    {
        // Test the version convenience method
        $version = FormSecurity::version();
        $this->assertIsString($version);
        $this->assertNotEmpty($version);
    }

    #[Test]
    public function facade_convenience_method_get_stats_works(): void
    {
        // Test the getStats convenience method
        $stats = FormSecurity::getStats();
        $this->assertIsArray($stats);
    }

    #[Test]
    public function facade_convenience_method_analyze_batch_works(): void
    {
        // Test the analyzeBatch convenience method
        $submissions = [
            $this->createSampleFormData(['name' => 'User 1']),
            $this->createSampleFormData(['name' => 'User 2']),
            $this->createSpamFormData(['name' => 'Spammer']),
        ];

        $results = FormSecurity::analyzeBatch($submissions);

        $this->assertIsArray($results);
        $this->assertCount(3, $results);

        foreach ($results as $result) {
            $this->assertValidAnalysisStructure($result);
        }
    }

    #[Test]
    public function facade_convenience_method_middleware_works(): void
    {
        // Test the middleware convenience method
        $middlewareClass = FormSecurity::middleware();
        $this->assertIsString($middlewareClass);
        $this->assertStringContainsString('FormSecurityMiddleware', $middlewareClass);
    }

    #[Test]
    public function facade_convenience_method_rule_works(): void
    {
        // Test the rule convenience method
        $rule = FormSecurity::rule();
        $this->assertInstanceOf(\JTD\FormSecurity\Rules\SpamDetectionRule::class, $rule);

        $ruleWithOptions = FormSecurity::rule(['threshold' => 0.5]);
        $this->assertInstanceOf(\JTD\FormSecurity\Rules\SpamDetectionRule::class, $ruleWithOptions);
    }

    #[Test]
    public function facade_convenience_method_debug_works(): void
    {
        // Test the debug convenience method
        $result = FormSecurity::debug(true);
        $this->assertTrue($result);

        $debugEnabled = FormSecurity::isFeatureEnabled('debug');
        $this->assertTrue($debugEnabled);

        FormSecurity::debug(false);
        $debugDisabled = FormSecurity::isFeatureEnabled('debug');
        $this->assertFalse($debugDisabled);
    }

    #[Test]
    public function facade_convenience_method_clear_cache_works(): void
    {
        // Test the clearCache convenience method
        $result = FormSecurity::clearCache();
        $this->assertTrue($result);
    }

    #[Test]
    public function facade_convenience_method_get_summary_works(): void
    {
        // Test the getSummary convenience method
        $cleanData = $this->createSampleFormData();
        $cleanSummary = FormSecurity::getSummary($cleanData);
        $this->assertIsString($cleanSummary);
        $this->assertStringContainsString('Clean submission', $cleanSummary);

        $spamData = $this->createSpamFormData();
        $spamSummary = FormSecurity::getSummary($spamData);
        $this->assertIsString($spamSummary);
        $this->assertStringContainsString('spam probability', $spamSummary);
    }

    #[Test]
    public function facade_maintains_singleton_behavior(): void
    {
        // Test that facade maintains singleton behavior
        $service1 = FormSecurity::getFacadeRoot();
        $service2 = FormSecurity::getFacadeRoot();

        $this->assertSame($service1, $service2);
    }

    #[Test]
    public function facade_performance_meets_requirements(): void
    {
        // Test that facade method calls meet performance requirements
        $data = $this->createSampleFormData();

        $startTime = microtime(true);
        FormSecurity::analyzeSubmission($data);
        $endTime = microtime(true);

        $processingTime = $endTime - $startTime;
        $this->assertPerformanceRequirement($processingTime, 'facade method call');
    }
}
