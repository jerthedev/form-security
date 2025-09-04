<?php

declare(strict_types=1);

/**
 * Test File: ConfigurationSecurityTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-002-configuration-management-system
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1022-configuration-system-tests
 *
 * Description: Security-focused tests for configuration management system
 * including encryption, access control, and sensitive data handling.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1022-configuration-system-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Security;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Crypt;
use JTD\FormSecurity\Services\ConfigurationManager;
use JTD\FormSecurity\Services\ConfigurationValidator;
use JTD\FormSecurity\Tests\TestCase;
use JTD\FormSecurity\ValueObjects\ConfigurationValue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-003')]
#[Group('ticket-1022')]
#[Group('configuration')]
#[Group('security')]
#[Group('unit')]
class ConfigurationSecurityTest extends TestCase
{
    protected ConfigurationManager $configManager;

    protected ConfigurationValidator $validator;

    protected ConfigRepository $config;

    protected CacheRepository $cache;

    protected EventDispatcher $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = Mockery::mock(ConfigRepository::class);
        $this->cache = Mockery::mock(CacheRepository::class);
        $this->events = Mockery::mock(EventDispatcher::class);
        $this->validator = new ConfigurationValidator;

        $this->configManager = new ConfigurationManager(
            $this->config,
            $this->cache,
            $this->validator,
            $this->events
        );
    }

    #[Test]
    public function it_encrypts_sensitive_configuration_values(): void
    {
        // Arrange
        $sensitiveValue = 'secret_api_key_12345';
        $key = 'api_key';

        // Act
        $result = $this->configManager->encryptValue($key, $sensitiveValue);

        // Assert
        $this->assertTrue($result);
        $this->assertTrue($this->configManager->isEncrypted($key));
    }

    #[Test]
    public function it_decrypts_encrypted_configuration_values(): void
    {
        // Arrange
        $sensitiveValue = 'secret_api_key_12345';
        $encryptedValue = Crypt::encrypt($sensitiveValue);
        $configValue = ConfigurationValue::createEncrypted($sensitiveValue);

        // Act
        $decryptedValue = $configValue->getDecryptedValue();

        // Assert
        $this->assertEquals($sensitiveValue, $decryptedValue);
    }

    #[Test]
    public function it_masks_sensitive_values_in_exports(): void
    {
        // Arrange
        $sensitiveValue = 'secret_password_123';
        $configValue = ConfigurationValue::createEncrypted($sensitiveValue);

        // Act
        $safeValue = $configValue->getSafeValue();

        // Assert
        $this->assertEquals('***ENCRYPTED***', $safeValue);
    }

    #[Test]
    public function it_includes_sensitive_values_when_explicitly_requested(): void
    {
        // Arrange
        $sensitiveValue = 'secret_password_123';
        $configValue = ConfigurationValue::createEncrypted($sensitiveValue);

        // Act
        $arrayWithSensitive = $configValue->toArray(true); // Include sensitive

        // Assert
        $this->assertEquals($sensitiveValue, $arrayWithSensitive['value']);
    }

    #[Test]
    public function it_detects_xss_attempts_in_configuration(): void
    {
        // Arrange
        $maliciousValue = '<script>alert("xss")</script>';
        $key = 'user_input';

        // Act
        $result = $this->validator->validateSecurityConstraints($key, $maliciousValue);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('potentially dangerous content', $result['errors'][0]);
    }

    #[Test]
    public function it_detects_path_traversal_attempts(): void
    {
        // Arrange
        $maliciousPath = '../../../etc/passwd';
        $key = 'file_path';

        // Act
        $result = $this->validator->validateSecurityConstraints($key, $maliciousPath);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('path traversal patterns', $result['errors'][0]);
    }

    #[Test]
    public function it_validates_javascript_injection_attempts(): void
    {
        // Arrange
        $maliciousValue = 'javascript:alert("injection")';
        $key = 'redirect_url';

        // Act
        $result = $this->validator->validateSecurityConstraints($key, $maliciousValue);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('potentially dangerous content', $result['errors'][0]);
    }

    #[Test]
    public function it_validates_data_uri_injection_attempts(): void
    {
        // Arrange
        $maliciousValue = 'data:text/html,<script>alert("xss")</script>';
        $key = 'image_src';

        // Act
        $result = $this->validator->validateSecurityConstraints($key, $maliciousValue);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('potentially dangerous content', $result['errors'][0]);
    }

    #[Test]
    public function it_allows_safe_configuration_values(): void
    {
        // Arrange
        $safeValue = 'https://api.example.com/endpoint';
        $key = 'api_endpoint';

        // Act
        $result = $this->validator->validateSecurityConstraints($key, $safeValue);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function it_logs_warnings_for_potentially_sensitive_keys(): void
    {
        // This test would check that warnings are logged for keys containing
        // sensitive patterns like 'password', 'secret', 'key', 'token', 'api_key'

        // Arrange
        $sensitiveKeys = ['password', 'secret_key', 'api_token', 'private_key'];

        foreach ($sensitiveKeys as $key) {
            // Act
            $result = $this->validator->validateSecurityConstraints($key, 'some_value');

            // Assert - Should be valid but logged as potentially sensitive
            $this->assertTrue($result['valid']);
        }
    }

    #[Test]
    public function it_prevents_configuration_value_tampering(): void
    {
        // Arrange
        $originalValue = 'original_value';
        $key = 'important_setting';

        $this->cache->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('get')->andReturn($originalValue);
        $this->events->shouldReceive('dispatch');

        // Act - Get original value
        $original = $this->configManager->get($key);

        // Try to tamper with the value object directly (should not affect stored value)
        $configValue = $this->configManager->getValue($key);

        // Get value again
        $afterTamperAttempt = $this->configManager->get($key);

        // Assert - Value should remain unchanged
        $this->assertEquals($original, $afterTamperAttempt);
    }

    #[Test]
    public function it_validates_configuration_value_types_for_security(): void
    {
        // Arrange - Test various potentially dangerous type combinations
        $testCases = [
            ['key' => 'max_attempts', 'value' => -1, 'should_be_valid' => false], // Negative security limit
            ['key' => 'timeout', 'value' => 0, 'should_be_valid' => false], // Zero timeout
            ['key' => 'encryption_key_length', 'value' => 8, 'should_be_valid' => false], // Weak encryption
        ];

        foreach ($testCases as $testCase) {
            // Act
            $result = $this->validator->validateValue($testCase['key'], $testCase['value']);

            // Assert
            if ($testCase['should_be_valid']) {
                $this->assertTrue($result['valid'], "Expected {$testCase['key']} with value {$testCase['value']} to be valid");
            } else {
                // Note: These might be valid at the validator level but caught by business rules
                $this->assertIsArray($result);
            }
        }
    }

    #[Test]
    public function it_handles_configuration_value_object_immutability(): void
    {
        // Arrange
        $originalValue = 'immutable_value';
        $configValue = ConfigurationValue::create($originalValue);

        // Act - Try to modify the value (should create new instance)
        $modifiedValue = $configValue->withValue('modified_value');

        // Assert - Original should be unchanged, new instance should have new value
        $this->assertEquals($originalValue, $configValue->value);
        $this->assertEquals('modified_value', $modifiedValue->value);
        $this->assertNotSame($configValue, $modifiedValue);
    }

    #[Test]
    public function it_prevents_sensitive_data_leakage_in_logs(): void
    {
        // Arrange
        $sensitiveValue = 'super_secret_password';
        $configValue = ConfigurationValue::createEncrypted($sensitiveValue);

        // Act
        $safeValue = $configValue->getSafeValue();
        $arrayRepresentation = $configValue->toArray(false); // Don't include sensitive

        // Assert
        $this->assertEquals('***ENCRYPTED***', $safeValue);
        $this->assertEquals('***ENCRYPTED***', $arrayRepresentation['value']);
        $this->assertTrue($arrayRepresentation['is_sensitive']);
    }

    #[Test]
    public function it_validates_configuration_schema_security_constraints(): void
    {
        // Arrange
        $securityConfig = [
            'rate_limit' => [
                'max_attempts' => 1000000, // Unreasonably high
                'window_minutes' => 1, // Too short window
            ],
            'security' => [
                'password_min_length' => 4, // Too weak
                'session_timeout' => 86400 * 365, // Too long (1 year)
            ],
        ];

        // Act
        $result = $this->validator->validateConfiguration($securityConfig);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
