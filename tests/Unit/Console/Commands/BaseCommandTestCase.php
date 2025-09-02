<?php

declare(strict_types=1);

/**
 * Base Test Case for CLI Commands
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-017-console-commands-cli
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1024-cli-command-tests
 *
 * Description: Base test case providing common functionality for CLI command testing
 * including mocking, output validation, and shared test utilities.
 *
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1024-cli-command-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Console\Commands;

use JTD\FormSecurity\Tests\TestCase;
use JTD\FormSecurity\Services\ConfigurationManager;
use JTD\FormSecurity\Services\CacheManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Input\ArrayInput;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('ticket-1024')]
#[Group('cli')]
#[Group('commands')]
#[Group('unit')]
abstract class BaseCommandTestCase extends TestCase
{
    protected ConfigurationManager $mockConfigManager;
    protected CacheManager $mockCacheManager;
    protected BufferedOutput $output;
    protected ArrayInput $input;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock services
        $this->mockConfigManager = Mockery::mock(ConfigurationManager::class);
        $this->mockCacheManager = Mockery::mock(CacheManager::class);
        
        // Set up console I/O
        $this->output = new BufferedOutput();
        $this->input = new ArrayInput([]);
        
        // Bind mocks to container
        $this->app->instance(ConfigurationManager::class, $this->mockConfigManager);
        $this->app->instance(CacheManager::class, $this->mockCacheManager);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Assert that command output contains expected text.
     */
    protected function assertOutputContains(string $expected, string $message = ''): void
    {
        $output = $this->getCommandOutput();
        $this->assertStringContainsString($expected, $output, $message);
    }

    /**
     * Assert that command output does not contain text.
     */
    protected function assertOutputNotContains(string $unexpected, string $message = ''): void
    {
        $output = $this->getCommandOutput();
        $this->assertStringNotContainsString($unexpected, $output, $message);
    }

    /**
     * Assert that command output matches pattern.
     */
    protected function assertOutputMatches(string $pattern, string $message = ''): void
    {
        $output = $this->getCommandOutput();
        $this->assertMatchesRegularExpression($pattern, $output, $message);
    }

    /**
     * Get the command output as string.
     */
    protected function getCommandOutput(): string
    {
        return Artisan::output();
    }

    /**
     * Create a command instance with mocked dependencies.
     */
    protected function createCommandInstance(string $commandClass): Command
    {
        return new $commandClass($this->mockConfigManager, $this->mockCacheManager);
    }

    /**
     * Execute command with given arguments and options using Artisan testing.
     */
    protected function executeCommand(Command $command, array $arguments = [], array $options = []): int
    {
        $commandName = $command->getName();
        $parameters = array_merge($arguments, $options);

        // Use the TestCase artisan method for better testing integration
        $result = $this->artisan($commandName, $parameters);
        $result->run();
        return $result->getExitCode();
    }

    /**
     * Mock successful configuration manager operations.
     */
    protected function mockConfigManagerSuccess(): void
    {
        $this->mockConfigManager
            ->shouldReceive('isConfigured')
            ->andReturn(true);
            
        $this->mockConfigManager
            ->shouldReceive('validateConfiguration')
            ->andReturn(true);
    }

    /**
     * Mock failed configuration manager operations.
     */
    protected function mockConfigManagerFailure(): void
    {
        $this->mockConfigManager
            ->shouldReceive('isConfigured')
            ->andReturn(false);
            
        $this->mockConfigManager
            ->shouldReceive('validateConfiguration')
            ->andReturn(false);
    }

    /**
     * Mock successful cache manager operations.
     */
    protected function mockCacheManagerSuccess(): void
    {
        $this->mockCacheManager
            ->shouldReceive('isHealthy')
            ->andReturn(true);
            
        $this->mockCacheManager
            ->shouldReceive('getStats')
            ->andReturn([
                'hits' => 100,
                'misses' => 10,
                'hit_ratio' => 90.9,
                'memory_usage' => 1024,
                'entries' => 50
            ]);
    }

    /**
     * Mock failed cache manager operations.
     */
    protected function mockCacheManagerFailure(): void
    {
        $this->mockCacheManager
            ->shouldReceive('isHealthy')
            ->andReturn(false);
            
        $this->mockCacheManager
            ->shouldReceive('getStats')
            ->andThrow(new \Exception('Cache unavailable'));
    }

    /**
     * Assert command displays proper header.
     */
    protected function assertCommandHeader(): void
    {
        $this->assertOutputContains('JTD FormSecurity');
        $this->assertOutputContains('CLI Management Tool');
    }

    /**
     * Assert command displays proper footer.
     */
    protected function assertCommandFooter(): void
    {
        $this->assertOutputContains('Status:');
        $this->assertOutputContains('Execution Time:');
    }

    /**
     * Assert command displays error message.
     */
    protected function assertCommandError(string $expectedError = ''): void
    {
        $this->assertOutputContains('Error:');
        if ($expectedError) {
            $this->assertOutputContains($expectedError);
        }
    }

    /**
     * Assert command displays success message.
     */
    protected function assertCommandSuccess(string $expectedMessage = ''): void
    {
        $this->assertOutputContains('Success');
        if ($expectedMessage) {
            $this->assertOutputContains($expectedMessage);
        }
    }

    /**
     * Create test data for command testing.
     */
    protected function createTestData(): array
    {
        return [
            'test_key' => 'test_value',
            'timestamp' => time(),
            'random' => rand(1000, 9999)
        ];
    }

    /**
     * Simulate user confirmation input.
     */
    protected function simulateUserConfirmation(bool $confirm = true): void
    {
        // This would be used with Laravel Prompts in actual implementation
        // For unit tests, we'll mock the confirmation behavior
    }

    /**
     * Assert progress bar was displayed.
     */
    protected function assertProgressBarDisplayed(): void
    {
        // Check for progress bar indicators in output
        $output = $this->getCommandOutput();
        $this->assertTrue(
            str_contains($output, 'Progress:') || 
            str_contains($output, '[') || 
            str_contains($output, '%'),
            'Progress bar should be displayed'
        );
    }

    /**
     * Assert table output format.
     */
    protected function assertTableOutput(array $expectedHeaders = []): void
    {
        $output = $this->getCommandOutput();
        
        // Check for table formatting characters
        $this->assertStringContainsString('|', $output, 'Table should contain column separators');
        $this->assertStringContainsString('-', $output, 'Table should contain row separators');
        
        // Check for expected headers if provided
        foreach ($expectedHeaders as $header) {
            $this->assertStringContainsString($header, $output, "Table should contain header: {$header}");
        }
    }
}
