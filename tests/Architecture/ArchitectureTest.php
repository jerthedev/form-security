<?php

declare(strict_types=1);

/**
 * Test File: ArchitectureTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-005-code-cleanup-optimization
 * TICKET: 1032-technical-debt-removal
 *
 * Description: Architecture testing to prevent technical debt accumulation.
 * Validates architectural constraints, design patterns, and code organization
 * to maintain consistent structure and prevent regression.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Code-Cleanup/1032-technical-debt-removal.md
 */

namespace JTD\FormSecurity\Tests\Architecture;

use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-005')]
#[Group('ticket-1032')]
#[Group('architecture')]
class ArchitectureTest extends TestCase
{
    #[Test]
    public function contracts_are_only_in_contracts_directory(): void
    {
        $contractFiles = $this->getFilesInDirectory('src/Contracts', '*.php');
        $serviceFiles = $this->getFilesInDirectory('src/Services', '*Interface.php');

        $this->assertEmpty(
            $serviceFiles,
            'Interface files should be in src/Contracts, not src/Services. Found: '.implode(', ', $serviceFiles)
        );

        $this->assertNotEmpty($contractFiles, 'Contracts directory should contain interface files');
    }

    #[Test]
    public function all_models_extend_base_model(): void
    {
        $modelFiles = $this->getFilesInDirectory('src/Models', '*.php', ['README.md']);

        foreach ($modelFiles as $file) {
            $className = $this->getClassNameFromFile($file);
            if (! $className || $className === 'Model') {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            $parentClass = $reflection->getParentClass();

            $this->assertNotFalse(
                $parentClass,
                "Model {$className} must extend a base model class"
            );

            $this->assertTrue(
                $parentClass->getName() === 'Illuminate\Database\Eloquent\Model' ||
                $parentClass->isSubclassOf('Illuminate\Database\Eloquent\Model'),
                "Model {$className} must extend Eloquent Model"
            );
        }
    }

    #[Test]
    public function all_services_implement_contracts(): void
    {
        $serviceFiles = $this->getFilesInDirectory('src/Services', '*.php', ['README.md']);

        foreach ($serviceFiles as $file) {
            $className = $this->getClassNameFromFile($file);
            if (! $className) {
                continue;
            }

            $reflection = new \ReflectionClass($className);

            // Skip abstract classes and traits
            if ($reflection->isAbstract() || $reflection->isTrait()) {
                continue;
            }

            // Services ending with 'Service' should implement contracts
            if (str_ends_with($className, 'Service')) {
                $interfaces = $reflection->getInterfaceNames();

                $hasContract = false;
                foreach ($interfaces as $interface) {
                    if (str_contains($interface, 'JTD\FormSecurity\Contracts')) {
                        $hasContract = true;
                        break;
                    }
                }

                $this->assertTrue(
                    $hasContract,
                    "Service {$className} should implement a contract interface"
                );
            }
        }
    }

    #[Test]
    public function no_direct_facade_usage_in_services(): void
    {
        $serviceFiles = $this->getFilesInDirectory('src/Services', '*.php');
        $forbiddenFacades = ['DB::', 'Cache::', 'Config::', 'Log::'];

        foreach ($serviceFiles as $file) {
            $content = file_get_contents($file);

            foreach ($forbiddenFacades as $facade) {
                $this->assertStringNotContainsString(
                    $facade,
                    $content,
                    "Service file {$file} should not use {$facade} facade directly. Use dependency injection instead."
                );
            }
        }
    }

    #[Test]
    public function all_classes_have_strict_types_declaration(): void
    {
        $phpFiles = $this->getAllPhpFiles();

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);

            $this->assertStringContainsString(
                'declare(strict_types=1);',
                $content,
                "File {$file} must have strict types declaration"
            );
        }
    }

    #[Test]
    public function contracts_only_contain_interfaces(): void
    {
        $contractFiles = $this->getFilesInDirectory('src/Contracts', '*.php');

        foreach ($contractFiles as $file) {
            $content = file_get_contents($file);

            $this->assertStringContainsString(
                'interface ',
                $content,
                "Contract file {$file} should only contain interfaces"
            );

            $this->assertStringNotContainsString(
                'class ',
                $content,
                "Contract file {$file} should not contain classes"
            );
        }
    }

    #[Test]
    public function enums_have_proper_namespace(): void
    {
        $enumFiles = $this->getFilesInDirectory('src/Enums', '*.php');

        foreach ($enumFiles as $file) {
            $content = file_get_contents($file);

            $this->assertStringContainsString(
                'namespace JTD\FormSecurity\Enums;',
                $content,
                "Enum file {$file} must have correct namespace"
            );

            $this->assertStringContainsString(
                'enum ',
                $content,
                "File in Enums directory {$file} should contain an enum"
            );
        }
    }

    #[Test]
    public function no_unused_use_statements(): void
    {
        // This is a basic check - in a real implementation, you'd want a more sophisticated tool
        $phpFiles = $this->getAllPhpFiles();

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);

            $useStatements = [];
            foreach ($lines as $line) {
                if (preg_match('/^use\s+([^;]+);/', trim($line), $matches)) {
                    $useStatements[] = $matches[1];
                }
            }

            // Basic check: ensure use statements are actually referenced
            foreach ($useStatements as $useStatement) {
                $className = basename(str_replace('\\', '/', $useStatement));
                if (! str_contains($content, $className)) {
                    // Allow some exceptions for common patterns
                    if (! in_array($className, ['HasFactory', 'Authenticatable', 'TestCase'])) {
                        $this->fail("Unused use statement in {$file}: {$useStatement}");
                    }
                }
            }
        }
    }

    #[Test]
    public function test_files_follow_naming_convention(): void
    {
        $testFiles = $this->getFilesInDirectory('tests', '*.php', ['TestCase.php']);

        foreach ($testFiles as $file) {
            $filename = basename($file);

            $this->assertStringEndsWith(
                'Test.php',
                $filename,
                "Test file {$filename} must end with 'Test.php'"
            );
        }
    }

    #[Test]
    public function configuration_files_have_proper_structure(): void
    {
        $configFiles = $this->getFilesInDirectory('config', '*.php');

        foreach ($configFiles as $file) {
            $content = file_get_contents($file);

            // Config files should return arrays
            $this->assertStringContainsString(
                'return [',
                $content,
                "Configuration file {$file} should return an array"
            );

            // Should not contain classes
            $this->assertStringNotContainsString(
                'class ',
                $content,
                "Configuration file {$file} should not contain classes"
            );
        }
    }

    /**
     * Get all PHP files in the project.
     *
     * @return array<string>
     */
    private function getAllPhpFiles(): array
    {
        $directories = ['src', 'tests', 'config'];
        $files = [];

        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                $files = array_merge($files, $this->getFilesInDirectory($dir, '*.php'));
            }
        }

        return $files;
    }

    /**
     * Get files in a directory matching a pattern.
     *
     * @param  array<string>  $exclude
     * @return array<string>
     */
    private function getFilesInDirectory(string $directory, string $pattern, array $exclude = []): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = glob($directory.'/**/'.$pattern, GLOB_BRACE) ?: [];

        return array_filter($files, function ($file) use ($exclude) {
            $basename = basename($file);

            return ! in_array($basename, $exclude);
        });
    }

    /**
     * Get class name from file path.
     */
    private function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        } else {
            return null;
        }

        // Extract class name
        if (preg_match('/(?:class|interface|trait|enum)\s+(\w+)/', $content, $matches)) {
            $className = $matches[1];

            return $namespace.'\\'.$className;
        }

        return null;
    }
}
