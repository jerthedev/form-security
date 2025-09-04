<?php

declare(strict_types=1);

/**
 * Test File: HealthCheckCommandTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-017-console-commands-cli
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: Code Coverage Gap
 *
 * Description: Unit tests for HealthCheckCommand testing command instantiation
 * and basic functionality without complex mocking.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-017-console-commands-cli.md
 * @see docs/Planning/Sprints/004-caching-cli-integration.md
 */

namespace JTD\FormSecurity\Tests\Unit\Console\Commands;

use JTD\FormSecurity\Console\Commands\HealthCheckCommand;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('unit')]
#[Group('console-commands')]
class HealthCheckCommandTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated(): void
    {
        $command = app(HealthCheckCommand::class);
        $this->assertInstanceOf(HealthCheckCommand::class, $command);
    }

    #[Test]
    public function it_has_correct_signature(): void
    {
        $command = app(HealthCheckCommand::class);
        $this->assertStringContainsString('form-security:health-check', $command->getName());
    }

    #[Test]
    public function it_has_description(): void
    {
        $command = app(HealthCheckCommand::class);
        $description = $command->getDescription();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
        $this->assertStringContainsString('health', strtolower($description));
    }

    #[Test]
    public function it_defines_expected_options(): void
    {
        $command = app(HealthCheckCommand::class);
        $definition = $command->getDefinition();

        // Should have expected options
        $expectedOptions = ['detailed', 'fix', 'export'];

        foreach ($expectedOptions as $optionName) {
            $this->assertTrue($definition->hasOption($optionName), "Missing option: {$optionName}");
        }
    }

    #[Test]
    public function it_has_detailed_option_as_boolean(): void
    {
        $command = app(HealthCheckCommand::class);
        $definition = $command->getDefinition();

        $detailedOption = $definition->getOption('detailed');
        $this->assertFalse($detailedOption->acceptValue());
    }

    #[Test]
    public function it_has_fix_option_as_boolean(): void
    {
        $command = app(HealthCheckCommand::class);
        $definition = $command->getDefinition();

        $fixOption = $definition->getOption('fix');
        $this->assertFalse($fixOption->acceptValue());
    }

    #[Test]
    public function it_has_export_option_with_value(): void
    {
        $command = app(HealthCheckCommand::class);
        $definition = $command->getDefinition();

        $exportOption = $definition->getOption('export');
        $this->assertTrue($exportOption->acceptValue());
    }

    #[Test]
    public function it_extends_form_security_command(): void
    {
        $command = app(HealthCheckCommand::class);
        $this->assertInstanceOf(\JTD\FormSecurity\Console\Commands\FormSecurityCommand::class, $command);
    }

    #[Test]
    public function it_is_registered_as_console_command(): void
    {
        $command = app(HealthCheckCommand::class);
        $this->assertInstanceOf(\Illuminate\Console\Command::class, $command);
    }
}
