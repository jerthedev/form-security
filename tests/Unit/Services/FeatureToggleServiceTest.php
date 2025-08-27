<?php

declare(strict_types=1);

/**
 * Test File: FeatureToggleServiceTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-002-configuration-management-system
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1022-configuration-system-tests
 *
 * Description: Comprehensive tests for the FeatureToggleService functionality
 * including graceful degradation, dependency management, and fallback strategies.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1022-configuration-system-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Services;

use JTD\FormSecurity\Contracts\ConfigurationManagerInterface;
use JTD\FormSecurity\Services\FeatureToggleService;
use JTD\FormSecurity\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-003')]
#[Group('ticket-1022')]
#[Group('configuration')]
#[Group('feature-toggles')]
#[Group('unit')]
class FeatureToggleServiceTest extends TestCase
{
    protected FeatureToggleService $featureToggle;

    protected ConfigurationManagerInterface $configManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configManager = Mockery::mock(ConfigurationManagerInterface::class);
        $this->featureToggle = new FeatureToggleService($this->configManager);
    }

    #[Test]
    public function it_checks_if_feature_is_enabled(): void
    {
        // Arrange
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('spam_detection')
            ->andReturn(true);

        // Act
        $result = $this->featureToggle->isEnabled('spam_detection');

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_checks_if_feature_is_disabled(): void
    {
        // Arrange
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('ai_analysis')
            ->andReturn(false);

        // Act
        $result = $this->featureToggle->isEnabled('ai_analysis');

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_caches_feature_status(): void
    {
        // Arrange
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('spam_detection')
            ->once()
            ->andReturn(true);

        // Act - First call should hit the config manager
        $result1 = $this->featureToggle->isEnabled('spam_detection');
        // Second call should use cache
        $result2 = $this->featureToggle->isEnabled('spam_detection');

        // Assert
        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    #[Test]
    public function it_checks_feature_dependencies(): void
    {
        // Arrange - ai_analysis depends on spam_detection
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('ai_analysis')
            ->andReturn(true);
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('spam_detection')
            ->andReturn(false); // Dependency not met

        // Act
        $result = $this->featureToggle->isEnabled('ai_analysis');

        // Assert
        $this->assertFalse($result); // Should be false due to unmet dependency
    }

    #[Test]
    public function it_enables_feature_with_dependencies(): void
    {
        // Arrange - ai_analysis depends on spam_detection
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('spam_detection')
            ->andReturn(true); // Dependency is already enabled
        $this->configManager->shouldReceive('toggleFeature')
            ->with('ai_analysis', true)
            ->andReturn(true);

        // Act
        $result = $this->featureToggle->enable('ai_analysis');

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_fails_to_enable_feature_with_missing_dependencies(): void
    {
        // Arrange - ai_analysis depends on spam_detection
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('spam_detection')
            ->andReturn(false); // Dependency is not enabled

        // Act
        $result = $this->featureToggle->enable('ai_analysis');

        // Assert
        $this->assertFalse($result); // Should fail due to missing dependency
    }

    #[Test]
    public function it_disables_feature_and_dependents(): void
    {
        // Arrange - spam_detection has ai_analysis as dependent
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('ai_analysis')
            ->andReturn(true); // Dependent feature is enabled
        $this->configManager->shouldReceive('toggleFeature')
            ->with('ai_analysis', false)
            ->andReturn(true); // Disable dependent first
        $this->configManager->shouldReceive('toggleFeature')
            ->with('spam_detection', false)
            ->andReturn(true); // Then disable the feature

        // Act
        $result = $this->featureToggle->disable('spam_detection');

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_executes_callback_when_feature_enabled(): void
    {
        // Arrange
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('spam_detection')
            ->andReturn(true);

        $callbackExecuted = false;
        $callback = function () use (&$callbackExecuted) {
            $callbackExecuted = true;
            return 'feature_result';
        };

        // Act
        $result = $this->featureToggle->when('spam_detection', $callback);

        // Assert
        $this->assertTrue($callbackExecuted);
        $this->assertEquals('feature_result', $result);
    }

    #[Test]
    public function it_executes_fallback_when_feature_disabled(): void
    {
        // Arrange
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('spam_detection')
            ->andReturn(false);

        $callbackExecuted = false;
        $fallbackExecuted = false;

        $callback = function () use (&$callbackExecuted) {
            $callbackExecuted = true;
            return 'feature_result';
        };

        $fallback = function () use (&$fallbackExecuted) {
            $fallbackExecuted = true;
            return 'fallback_result';
        };

        // Act
        $result = $this->featureToggle->when('spam_detection', $callback, $fallback);

        // Assert
        $this->assertFalse($callbackExecuted);
        $this->assertTrue($fallbackExecuted);
        $this->assertEquals('fallback_result', $result);
    }

    #[Test]
    public function it_uses_fallback_strategy_when_callback_fails(): void
    {
        // Arrange
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('spam_detection')
            ->andReturn(true);

        $callback = function () {
            throw new \Exception('Feature callback failed');
        };

        $fallback = function () {
            return 'fallback_result';
        };

        // Act
        $result = $this->featureToggle->when('spam_detection', $callback, $fallback);

        // Assert
        $this->assertEquals('fallback_result', $result);
    }

    #[Test]
    public function it_gets_enabled_features(): void
    {
        // Arrange
        $enabledFeatures = ['spam_detection', 'rate_limiting', 'logging'];
        $this->configManager->shouldReceive('getEnabledFeatures')
            ->andReturn($enabledFeatures);

        // Act
        $result = $this->featureToggle->getEnabledFeatures();

        // Assert
        $this->assertEquals($enabledFeatures, $result);
    }

    #[Test]
    public function it_gets_feature_status_with_metadata(): void
    {
        // Arrange
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('ai_analysis')
            ->andReturn(true);
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('spam_detection')
            ->andReturn(true);

        // Act
        $result = $this->featureToggle->getFeatureStatus('ai_analysis');

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('ai_analysis', $result);
        $this->assertArrayHasKey('enabled', $result['ai_analysis']);
        $this->assertArrayHasKey('dependencies', $result['ai_analysis']);
        $this->assertArrayHasKey('dependencies_met', $result['ai_analysis']);
    }

    #[Test]
    public function it_registers_fallback_strategy(): void
    {
        // Arrange
        $strategy = function () {
            return 'custom_fallback';
        };

        // Act
        $result = $this->featureToggle->registerFallbackStrategy('custom_feature', $strategy);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_clears_feature_cache(): void
    {
        // Arrange
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('spam_detection')
            ->twice() // Should be called twice after cache clear
            ->andReturn(true);

        // Act
        $this->featureToggle->isEnabled('spam_detection'); // First call
        $this->featureToggle->clearCache('spam_detection'); // Clear cache
        $this->featureToggle->isEnabled('spam_detection'); // Second call should hit config again

        // Assert - No exception means test passed
        $this->assertTrue(true);
    }

    #[Test]
    public function it_evaluates_context_rules(): void
    {
        // Arrange
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('ai_analysis')
            ->andReturn(true);
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('spam_detection')
            ->andReturn(true); // Dependency must be met

        $context = [
            'user_tier' => 'premium',
            'user_id' => 123,
        ];

        // Act
        $result = $this->featureToggle->isEnabled('ai_analysis', $context);

        // Assert
        $this->assertTrue($result); // Should be enabled for premium users
    }

    #[Test]
    public function it_handles_exceptions_gracefully(): void
    {
        // Arrange
        $this->configManager->shouldReceive('isFeatureEnabled')
            ->with('broken_feature')
            ->andThrow(new \Exception('Configuration error'));

        // Act
        $result = $this->featureToggle->isEnabled('broken_feature');

        // Assert
        $this->assertFalse($result); // Should return safe default
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
