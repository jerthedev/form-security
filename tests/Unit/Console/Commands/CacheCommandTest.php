<?php

declare(strict_types=1);

/**
 * Test File: CacheCommandTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-017-console-commands-cli
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: Code Coverage Gap
 *
 * Description: Unit tests for CacheCommand testing command instantiation
 * and basic functionality without complex mocking.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-017-console-commands-cli.md
 * @see docs/Planning/Sprints/004-caching-cli-integration.md
 */

namespace JTD\FormSecurity\Tests\Unit\Console\Commands;

use JTD\FormSecurity\Console\Commands\CacheCommand;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('unit')]
#[Group('console-commands')]
class CacheCommandTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated(): void
    {
        $command = app(CacheCommand::class);
        $this->assertInstanceOf(CacheCommand::class, $command);
    }

    #[Test]
    public function it_has_correct_signature(): void
    {
        $command = app(CacheCommand::class);
        $this->assertStringContainsString('form-security:cache', $command->getName());
    }

    #[Test]
    public function it_has_description(): void
    {
        $command = app(CacheCommand::class);
        $description = $command->getDescription();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
        $this->assertStringContainsString('cache', strtolower($description));
    }

    #[Test]
    public function it_defines_expected_arguments(): void
    {
        $command = app(CacheCommand::class);
        $definition = $command->getDefinition();
        
        // Should have 'action' argument
        $this->assertTrue($definition->hasArgument('action'));
        
        $actionArgument = $definition->getArgument('action');
        $this->assertEquals('action', $actionArgument->getName());
        $this->assertTrue($actionArgument->isRequired());
    }

    #[Test]
    public function it_defines_expected_options(): void
    {
        $command = app(CacheCommand::class);
        $definition = $command->getDefinition();
        
        // Should have expected options
        $expectedOptions = ['level', 'force', 'detailed'];
        
        foreach ($expectedOptions as $optionName) {
            $this->assertTrue($definition->hasOption($optionName), "Missing option: {$optionName}");
        }
    }

    #[Test]
    public function it_has_level_option_as_array(): void
    {
        $command = app(CacheCommand::class);
        $definition = $command->getDefinition();
        
        $levelOption = $definition->getOption('level');
        $this->assertTrue($levelOption->isArray());
    }

    #[Test]
    public function it_has_force_option_as_boolean(): void
    {
        $command = app(CacheCommand::class);
        $definition = $command->getDefinition();
        
        $forceOption = $definition->getOption('force');
        $this->assertFalse($forceOption->acceptValue());
    }

    #[Test]
    public function it_has_detailed_option_as_boolean(): void
    {
        $command = app(CacheCommand::class);
        $definition = $command->getDefinition();
        
        $detailedOption = $definition->getOption('detailed');
        $this->assertFalse($detailedOption->acceptValue());
    }

    #[Test]
    public function it_extends_form_security_command(): void
    {
        $command = app(CacheCommand::class);
        $this->assertInstanceOf(\JTD\FormSecurity\Console\Commands\FormSecurityCommand::class, $command);
    }

    #[Test]
    public function it_is_registered_as_console_command(): void
    {
        $command = app(CacheCommand::class);
        $this->assertInstanceOf(\Illuminate\Console\Command::class, $command);
    }
}
