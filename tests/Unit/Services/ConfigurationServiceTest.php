<?php

declare(strict_types=1);

/**
 * Test File: ConfigurationServiceTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: N/A - Configuration service testing
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1020-service-provider-tests
 *
 * Description: Tests for ConfigurationService functionality.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1020-service-provider-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Services;

use JTD\FormSecurity\Contracts\ConfigurationContract;
use JTD\FormSecurity\Services\ConfigurationService;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-002')]
#[Group('ticket-1020')]
#[Group('configuration-service')]
#[Group('unit')]
class ConfigurationServiceTest extends TestCase
{
    private ConfigurationContract $configService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configService = $this->app->make(ConfigurationContract::class);
    }

    #[Test]
    public function get_returns_configuration_value(): void
    {
        $enabled = $this->configService->get('enabled');
        $this->assertIsBool($enabled);
        
        $threshold = $this->configService->get('spam_threshold');
        $this->assertIsFloat($threshold);
    }

    #[Test]
    public function get_returns_default_for_missing_key(): void
    {
        $value = $this->configService->get('non_existent_key', 'default');
        $this->assertEquals('default', $value);
    }

    #[Test]
    public function get_returns_all_config_for_empty_key(): void
    {
        $allConfig = $this->configService->get('');
        $this->assertIsArray($allConfig);
        $this->assertArrayHasKey('enabled', $allConfig);
    }

    #[Test]
    public function set_updates_configuration_value(): void
    {
        $result = $this->configService->set('test_key', 'test_value');
        $this->assertTrue($result);
        
        $value = $this->configService->get('test_key');
        $this->assertEquals('test_value', $value);
    }

    #[Test]
    public function is_feature_enabled_returns_correct_status(): void
    {
        // Test enabled feature
        $this->configService->set('features.test_feature', true);
        $this->assertTrue($this->configService->isFeatureEnabled('test_feature'));
        
        // Test disabled feature
        $this->configService->set('features.disabled_feature', false);
        $this->assertFalse($this->configService->isFeatureEnabled('disabled_feature'));
        
        // Test non-existent feature (should default to false)
        $this->assertFalse($this->configService->isFeatureEnabled('non_existent'));
    }

    #[Test]
    public function toggle_feature_enables_and_disables_features(): void
    {
        // Enable feature
        $result = $this->configService->toggleFeature('toggle_test', true);
        $this->assertTrue($result);
        $this->assertTrue($this->configService->isFeatureEnabled('toggle_test'));
        
        // Disable feature
        $result = $this->configService->toggleFeature('toggle_test', false);
        $this->assertTrue($result);
        $this->assertFalse($this->configService->isFeatureEnabled('toggle_test'));
    }

    #[Test]
    public function validate_config_validates_required_fields(): void
    {
        $validConfig = [
            'enabled' => true,
            'features' => [],
            'spam_threshold' => 0.7,
            'rate_limit' => [],
        ];
        
        $result = $this->configService->validateConfig($validConfig);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function validate_config_detects_missing_required_fields(): void
    {
        $invalidConfig = [
            'enabled' => true,
            // Missing required fields
        ];
        
        $result = $this->configService->validateConfig($invalidConfig);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertArrayHasKey('features', $result['errors']);
    }

    #[Test]
    public function validate_config_detects_type_mismatches(): void
    {
        $invalidConfig = [
            'enabled' => 'not_boolean', // Should be boolean
            'features' => [],
            'spam_threshold' => 'not_float', // Should be float
            'rate_limit' => [],
        ];
        
        $result = $this->configService->validateConfig($invalidConfig);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('enabled', $result['errors']);
        $this->assertArrayHasKey('spam_threshold', $result['errors']);
    }

    #[Test]
    public function get_enabled_features_returns_only_enabled_features(): void
    {
        // Set up test features
        $this->configService->set('features.feature1', true);
        $this->configService->set('features.feature2', false);
        $this->configService->set('features.feature3', true);
        $this->configService->set('features.feature4', 0); // Falsy
        $this->configService->set('features.feature5', 1); // Truthy
        
        $enabledFeatures = $this->configService->getEnabledFeatures();
        
        $this->assertContains('feature1', $enabledFeatures);
        $this->assertNotContains('feature2', $enabledFeatures);
        $this->assertContains('feature3', $enabledFeatures);
        $this->assertNotContains('feature4', $enabledFeatures);
        $this->assertContains('feature5', $enabledFeatures);
    }

    #[Test]
    public function refresh_returns_true(): void
    {
        $result = $this->configService->refresh();
        $this->assertTrue($result);
    }

    #[Test]
    public function get_schema_returns_configuration_schema(): void
    {
        $schema = $this->configService->getSchema();
        
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('enabled', $schema);
        $this->assertArrayHasKey('features', $schema);
        $this->assertArrayHasKey('spam_threshold', $schema);
        
        // Check schema structure
        $this->assertEquals('boolean', $schema['enabled']['type']);
        $this->assertTrue($schema['enabled']['required']);
    }

    #[Test]
    public function validate_type_validates_boolean_correctly(): void
    {
        $service = new ConfigurationService($this->app->make('config'));
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('validateType');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($service, true, 'boolean'));
        $this->assertTrue($method->invoke($service, false, 'boolean'));
        $this->assertFalse($method->invoke($service, 'true', 'boolean'));
        $this->assertFalse($method->invoke($service, 1, 'boolean'));
    }

    #[Test]
    public function validate_type_validates_integer_correctly(): void
    {
        $service = new ConfigurationService($this->app->make('config'));
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('validateType');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($service, 123, 'integer'));
        $this->assertTrue($method->invoke($service, 0, 'integer'));
        $this->assertFalse($method->invoke($service, 123.45, 'integer'));
        $this->assertFalse($method->invoke($service, '123', 'integer'));
    }

    #[Test]
    public function validate_type_validates_float_correctly(): void
    {
        $service = new ConfigurationService($this->app->make('config'));
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('validateType');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($service, 123.45, 'float'));
        $this->assertTrue($method->invoke($service, 123, 'float')); // Integers are valid floats
        $this->assertFalse($method->invoke($service, '123.45', 'float'));
    }

    #[Test]
    public function validate_type_validates_string_correctly(): void
    {
        $service = new ConfigurationService($this->app->make('config'));
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('validateType');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($service, 'test', 'string'));
        $this->assertTrue($method->invoke($service, '', 'string'));
        $this->assertFalse($method->invoke($service, 123, 'string'));
    }

    #[Test]
    public function validate_type_validates_array_correctly(): void
    {
        $service = new ConfigurationService($this->app->make('config'));
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('validateType');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($service, [], 'array'));
        $this->assertTrue($method->invoke($service, [1, 2, 3], 'array'));
        $this->assertFalse($method->invoke($service, 'array', 'array'));
    }

    #[Test]
    public function validate_type_validates_mixed_correctly(): void
    {
        $service = new ConfigurationService($this->app->make('config'));
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('validateType');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($service, 'anything', 'mixed'));
        $this->assertTrue($method->invoke($service, 123, 'mixed'));
        $this->assertTrue($method->invoke($service, [], 'mixed'));
        $this->assertTrue($method->invoke($service, null, 'mixed'));
    }

    #[Test]
    public function validate_type_returns_false_for_unknown_type(): void
    {
        $service = new ConfigurationService($this->app->make('config'));
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('validateType');
        $method->setAccessible(true);
        
        $this->assertFalse($method->invoke($service, 'test', 'unknown_type'));
    }
}
