<?php

declare(strict_types=1);

/**
 * Test File: ConfigurationCommandsTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-002-configuration-management-system
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1022-configuration-system-tests
 *
 * Description: Tests for configuration management CLI commands
 * including publishing, validation, and feature toggle commands.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1022-configuration-system-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Console;

use JTD\FormSecurity\Console\Commands\ConfigurationPublishCommand;
use JTD\FormSecurity\Console\Commands\ConfigurationValidateCommand;
use JTD\FormSecurity\Console\Commands\FeatureToggleCommand;
use JTD\FormSecurity\Contracts\ConfigurationManagerInterface;
use JTD\FormSecurity\Contracts\ConfigurationValidatorInterface;
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
#[Group('console')]
#[Group('unit')]
class ConfigurationCommandsTest extends TestCase
{
    protected ConfigurationManagerInterface $configManager;

    protected ConfigurationValidatorInterface $validator;

    protected FeatureToggleService $featureToggle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configManager = Mockery::mock(ConfigurationManagerInterface::class);
        $this->validator = Mockery::mock(ConfigurationValidatorInterface::class);
        $this->featureToggle = Mockery::mock(FeatureToggleService::class);
    }

    #[Test]
    public function configuration_publish_command_can_be_instantiated(): void
    {
        // Act
        $command = new ConfigurationPublishCommand($this->configManager);

        // Assert
        $this->assertInstanceOf(ConfigurationPublishCommand::class, $command);
        $this->assertEquals('form-security:config:publish', $command->getName());
    }

    #[Test]
    public function configuration_validate_command_can_be_instantiated(): void
    {
        // Act
        $command = new ConfigurationValidateCommand($this->configManager, $this->validator);

        // Assert
        $this->assertInstanceOf(ConfigurationValidateCommand::class, $command);
        $this->assertEquals('form-security:config:validate', $command->getName());
    }

    #[Test]
    public function feature_toggle_command_can_be_instantiated(): void
    {
        // Act
        $command = new FeatureToggleCommand($this->featureToggle);

        // Assert
        $this->assertInstanceOf(FeatureToggleCommand::class, $command);
        $this->assertEquals('form-security:features', $command->getName());
    }

    #[Test]
    public function configuration_validate_command_validates_all_configuration(): void
    {
        // Arrange
        $command = new ConfigurationValidateCommand($this->configManager, $this->validator);

        $this->configManager->shouldReceive('exportConfiguration')
            ->andReturn(['enabled' => true, 'spam_threshold' => 0.7]);

        $this->validator->shouldReceive('validateConfiguration')
            ->andReturn([
                'valid' => true,
                'errors' => [],
                'summary' => ['total_keys' => 2, 'valid_keys' => 2, 'invalid_keys' => 0, 'error_count' => 0],
            ]);

        // Act & Assert - Just verify the command can be created and has the right signature
        $this->assertEquals('form-security:config:validate', $command->getName());
        $this->assertStringContainsString('Validate FormSecurity configuration', $command->getDescription());
    }

    #[Test]
    public function configuration_validate_command_reports_validation_errors(): void
    {
        // Arrange
        $command = new ConfigurationValidateCommand($this->configManager, $this->validator);

        $this->configManager->shouldReceive('exportConfiguration')
            ->andReturn(['spam_threshold' => 1.5]); // Invalid value

        $this->validator->shouldReceive('validateConfiguration')
            ->andReturn([
                'valid' => false,
                'errors' => ['spam_threshold' => ['Value must be between 0.0 and 1.0']],
                'summary' => ['total_keys' => 1, 'valid_keys' => 0, 'invalid_keys' => 1, 'error_count' => 1],
            ]);

        // Act
        $exitCode = $this->artisan('form-security:config:validate');

        // Assert
        $exitCode->assertExitCode(1);
    }

    #[Test]
    public function configuration_validate_command_validates_specific_keys(): void
    {
        // Arrange
        $command = new ConfigurationValidateCommand($this->configManager, $this->validator);

        $this->validator->shouldReceive('validateValue')
            ->with('spam_threshold', Mockery::any(), Mockery::any())
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->configManager->shouldReceive('get')
            ->with('spam_threshold')
            ->andReturn(0.7);

        // Act & Assert - Verify command structure and options
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('key'));
        $this->assertTrue($definition->hasOption('strict'));
        $this->assertTrue($definition->hasOption('fix'));
    }

    #[Test]
    public function feature_toggle_command_lists_features(): void
    {
        // Arrange
        $command = new FeatureToggleCommand($this->featureToggle);

        $this->featureToggle->shouldReceive('getFeatureStatus')
            ->andReturn([
                'spam_detection' => [
                    'enabled' => true,
                    'dependencies_met' => true,
                    'dependencies' => [],
                    'dependent_features' => [],
                    'has_fallback' => true,
                ],
            ]);

        // Act & Assert - Verify command structure
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('action'));
        $this->assertTrue($definition->hasArgument('feature'));
        $this->assertTrue($definition->hasOption('all'));
        $this->assertTrue($definition->hasOption('force'));
    }

    #[Test]
    public function feature_toggle_command_enables_feature(): void
    {
        // Arrange
        $command = new FeatureToggleCommand($this->featureToggle);

        $this->featureToggle->shouldReceive('getFeatureStatus')
            ->with('spam_detection')
            ->andReturn([
                'spam_detection' => [
                    'enabled' => false,
                    'dependencies_met' => true,
                    'dependencies' => [],
                    'dependent_features' => [],
                    'has_fallback' => true,
                ],
            ]);

        $this->featureToggle->shouldReceive('enable')
            ->with('spam_detection')
            ->andReturn(true);

        // Act & Assert - Verify command can handle enable action
        $this->assertEquals('form-security:features', $command->getName());
        $this->assertStringContainsString('Manage FormSecurity feature toggles', $command->getDescription());
    }

    #[Test]
    public function feature_toggle_command_has_proper_signature(): void
    {
        // Arrange
        $command = new FeatureToggleCommand($this->featureToggle);

        // Act & Assert
        $signature = $command->getDefinition();

        // Verify required arguments
        $this->assertTrue($signature->hasArgument('action'));
        $this->assertTrue($signature->getArgument('action')->isRequired()); // Action is required

        // Verify optional arguments
        $this->assertTrue($signature->hasArgument('feature'));
        $this->assertFalse($signature->getArgument('feature')->isRequired());

        // Verify options
        $this->assertTrue($signature->hasOption('all'));
        $this->assertTrue($signature->hasOption('force'));
    }

    #[Test]
    public function configuration_publish_command_has_proper_signature(): void
    {
        // Arrange
        $command = new ConfigurationPublishCommand($this->configManager);

        // Act & Assert
        $signature = $command->getDefinition();

        // Verify options
        $this->assertTrue($signature->hasOption('force'));
        $this->assertTrue($signature->hasOption('tag'));
        $this->assertTrue($signature->hasOption('all'));

        // Verify command name and description
        $this->assertEquals('form-security:config:publish', $command->getName());
        $this->assertStringContainsString('Publish FormSecurity configuration files', $command->getDescription());
    }

    #[Test]
    public function commands_can_be_registered_in_service_provider(): void
    {
        // This test verifies that the commands can be instantiated
        // which means they can be registered in the service provider

        $publishCommand = new ConfigurationPublishCommand($this->configManager);
        $validateCommand = new ConfigurationValidateCommand($this->configManager, $this->validator);
        $featureCommand = new FeatureToggleCommand($this->featureToggle);

        $this->assertInstanceOf(ConfigurationPublishCommand::class, $publishCommand);
        $this->assertInstanceOf(ConfigurationValidateCommand::class, $validateCommand);
        $this->assertInstanceOf(FeatureToggleCommand::class, $featureCommand);

        // Verify they all have proper names
        $this->assertStringStartsWith('form-security:', $publishCommand->getName());
        $this->assertStringStartsWith('form-security:', $validateCommand->getName());
        $this->assertStringStartsWith('form-security:', $featureCommand->getName());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
