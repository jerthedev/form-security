<?php

declare(strict_types=1);

/**
 * Test File: FormSecurityServiceTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: N/A - FormSecurity service testing
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1020-service-provider-tests
 *
 * Description: Tests for FormSecurityService functionality.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1020-service-provider-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Services;

use JTD\FormSecurity\Contracts\FormSecurityContract;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-002')]
#[Group('ticket-1020')]
#[Group('form-security-service')]
#[Group('unit')]
class FormSecurityServiceTest extends TestCase
{
    private FormSecurityContract $formSecurityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formSecurityService = $this->app->make(FormSecurityContract::class);
    }

    #[Test]
    public function analyze_submission_returns_valid_structure(): void
    {
        $data = $this->createSampleFormData();
        $result = $this->formSecurityService->analyzeSubmission($data);
        
        $this->assertValidAnalysisStructure($result);
    }

    #[Test]
    public function analyze_submission_handles_empty_data(): void
    {
        $result = $this->formSecurityService->analyzeSubmission([]);
        
        $this->assertFalse($result['valid']);
        $this->assertEquals(0.0, $result['score']);
        $this->assertContains('empty_submission', $result['threats']);
        $this->assertContains('Provide form data for analysis', $result['recommendations']);
        $this->assertIsFloat($result['processing_time']);
    }

    #[Test]
    public function analyze_submission_with_options(): void
    {
        $data = $this->createSampleFormData();
        $options = ['strict_mode' => true];
        
        $result = $this->formSecurityService->analyzeSubmission($data, $options);
        
        $this->assertValidAnalysisStructure($result);
        $this->assertArrayHasKey('analysis_details', $result);
    }

    #[Test]
    public function validate_submission_returns_boolean(): void
    {
        $validData = $this->createSampleFormData();
        $result = $this->formSecurityService->validateSubmission($validData);
        
        $this->assertIsBool($result);
    }

    #[Test]
    public function validate_submission_handles_empty_data(): void
    {
        $result = $this->formSecurityService->validateSubmission([]);
        
        $this->assertFalse($result);
    }

    #[Test]
    public function validate_submission_with_rate_limiting_enabled(): void
    {
        // Enable rate limiting
        config(['form-security.features.rate_limiting' => true]);
        
        $data = $this->createSampleFormData();
        $result = $this->formSecurityService->validateSubmission($data);
        
        $this->assertIsBool($result);
    }

    #[Test]
    public function validate_submission_with_custom_rules(): void
    {
        $data = $this->createSampleFormData();
        $rules = ['custom_rule' => 'value'];
        
        $result = $this->formSecurityService->validateSubmission($data, $rules);
        
        $this->assertIsBool($result);
    }

    #[Test]
    public function is_ip_blocked_validates_ip_format(): void
    {
        // Valid IP should not be blocked by default
        $this->assertFalse($this->formSecurityService->isIpBlocked('192.168.1.1'));
        
        // Invalid IP should be blocked
        $this->assertTrue($this->formSecurityService->isIpBlocked('invalid-ip'));
        $this->assertTrue($this->formSecurityService->isIpBlocked('999.999.999.999'));
    }

    #[Test]
    public function is_ip_blocked_checks_blocked_list(): void
    {
        // Add IP to blocked list
        config(['form-security.blocked_ips' => ['192.168.1.100', '10.0.0.1']]);
        
        $this->assertTrue($this->formSecurityService->isIpBlocked('192.168.1.100'));
        $this->assertTrue($this->formSecurityService->isIpBlocked('10.0.0.1'));
        $this->assertFalse($this->formSecurityService->isIpBlocked('192.168.1.1'));
    }

    #[Test]
    public function is_ip_blocked_with_reputation_checking(): void
    {
        // Enable IP reputation checking
        config(['form-security.features.ip_reputation' => true]);
        
        $result = $this->formSecurityService->isIpBlocked('8.8.8.8');
        
        $this->assertIsBool($result);
    }

    #[Test]
    public function get_config_returns_specific_value(): void
    {
        $enabled = $this->formSecurityService->getConfig('enabled');
        $this->assertIsBool($enabled);
        
        $threshold = $this->formSecurityService->getConfig('spam_threshold');
        $this->assertIsFloat($threshold);
    }

    #[Test]
    public function get_config_returns_default_value(): void
    {
        $value = $this->formSecurityService->getConfig('non_existent_key', 'default');
        $this->assertEquals('default', $value);
    }

    #[Test]
    public function get_config_returns_all_config(): void
    {
        $allConfig = $this->formSecurityService->getConfig();
        
        $this->assertIsArray($allConfig);
        $this->assertArrayHasKey('enabled', $allConfig);
    }

    #[Test]
    public function toggle_feature_enables_and_disables(): void
    {
        $result = $this->formSecurityService->toggleFeature('test_feature', true);
        $this->assertTrue($result);
        
        $result = $this->formSecurityService->toggleFeature('test_feature', false);
        $this->assertTrue($result);
    }

    #[Test]
    public function get_package_info_returns_complete_info(): void
    {
        $info = $this->formSecurityService->getPackageInfo();
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('version', $info);
        $this->assertArrayHasKey('enabled', $info);
        $this->assertArrayHasKey('features', $info);
        $this->assertArrayHasKey('performance', $info);
        $this->assertArrayHasKey('statistics', $info);
        
        $this->assertEquals('JTD FormSecurity', $info['name']);
        $this->assertEquals('1.0.0', $info['version']);
        $this->assertIsBool($info['enabled']);
        $this->assertIsArray($info['features']);
        $this->assertIsArray($info['performance']);
        $this->assertIsArray($info['statistics']);
    }

    #[Test]
    public function generate_recommendations_for_high_spam_score(): void
    {
        $service = $this->formSecurityService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('generateRecommendations');
        $method->setAccessible(true);
        
        $analysis = ['score' => 0.9, 'threats' => ['spam_keywords']];
        $recommendations = $method->invoke($service, $analysis);
        
        $this->assertIsArray($recommendations);
        $this->assertContains('Block this submission - high spam probability', $recommendations);
        $this->assertContains('Address detected threats: spam_keywords', $recommendations);
    }

    #[Test]
    public function generate_recommendations_for_medium_spam_score(): void
    {
        $service = $this->formSecurityService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('generateRecommendations');
        $method->setAccessible(true);
        
        $analysis = ['score' => 0.6];
        $recommendations = $method->invoke($service, $analysis);
        
        $this->assertIsArray($recommendations);
        $this->assertContains('Review this submission manually', $recommendations);
        $this->assertContains('Consider additional verification steps', $recommendations);
    }

    #[Test]
    public function generate_recommendations_for_low_spam_score(): void
    {
        $service = $this->formSecurityService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('generateRecommendations');
        $method->setAccessible(true);
        
        $analysis = ['score' => 0.4];
        $recommendations = $method->invoke($service, $analysis);
        
        $this->assertIsArray($recommendations);
        $this->assertContains('Monitor this user for future submissions', $recommendations);
    }

    #[Test]
    public function generate_recommendations_for_clean_submission(): void
    {
        $service = $this->formSecurityService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('generateRecommendations');
        $method->setAccessible(true);
        
        $analysis = ['score' => 0.1];
        $recommendations = $method->invoke($service, $analysis);
        
        $this->assertIsArray($recommendations);
        $this->assertEmpty($recommendations);
    }

    #[Test]
    public function get_submission_identifier_uses_ip_when_available(): void
    {
        $service = $this->formSecurityService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getSubmissionIdentifier');
        $method->setAccessible(true);
        
        $data = ['_ip' => '192.168.1.1', 'name' => 'test'];
        $identifier = $method->invoke($service, $data);
        
        $this->assertEquals('192.168.1.1', $identifier);
    }

    #[Test]
    public function get_submission_identifier_uses_hash_when_no_ip(): void
    {
        $service = $this->formSecurityService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getSubmissionIdentifier');
        $method->setAccessible(true);
        
        $data = ['name' => 'test', 'email' => 'test@example.com'];
        $identifier = $method->invoke($service, $data);
        
        $this->assertIsString($identifier);
        $this->assertEquals(32, strlen($identifier)); // MD5 hash length
    }

    #[Test]
    public function check_ip_reputation_returns_false_by_default(): void
    {
        $service = $this->formSecurityService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('checkIpReputation');
        $method->setAccessible(true);
        
        $result = $method->invoke($service, '8.8.8.8');
        
        $this->assertFalse($result);
    }

    #[Test]
    public function service_performance_meets_requirements(): void
    {
        $data = $this->createSampleFormData();
        
        $startTime = microtime(true);
        $this->formSecurityService->analyzeSubmission($data);
        $endTime = microtime(true);
        
        $processingTime = $endTime - $startTime;
        $this->assertPerformanceRequirement($processingTime, 'FormSecurityService analysis');
    }
}
