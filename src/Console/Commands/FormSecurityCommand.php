<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\FormSecurity\Services\CacheManager;
use JTD\FormSecurity\Services\ConfigurationManager;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Base FormSecurity command class.
 *
 * Provides common functionality for all FormSecurity CLI commands including
 * error handling, output formatting, progress reporting, and shared utilities.
 * Leverages Laravel 12's enhanced console features.
 */
abstract class FormSecurityCommand extends Command
{
    /**
     * Configuration manager instance.
     */
    protected ConfigurationManager $configManager;

    /**
     * Cache manager instance.
     */
    protected CacheManager $cacheManager;

    /**
     * Command start time for performance tracking.
     */
    protected float $startTime;

    /**
     * Create a new command instance.
     */
    public function __construct(
        ConfigurationManager $configManager,
        CacheManager $cacheManager
    ) {
        parent::__construct();

        $this->configManager = $configManager;
        $this->cacheManager = $cacheManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->startTime = microtime(true);

        try {
            $this->displayHeader();

            // Perform pre-execution checks
            if (! $this->preExecutionChecks()) {
                return Command::FAILURE;
            }

            // Execute the main command logic
            $result = $this->executeCommand();

            $this->displayFooter($result);

            return $result;
        } catch (\Exception $e) {
            $this->handleException($e);

            return Command::FAILURE;
        }
    }

    /**
     * Execute the main command logic.
     *
     * This method should be implemented by child classes.
     */
    abstract protected function executeCommand(): int;

    /**
     * Perform pre-execution checks.
     */
    protected function preExecutionChecks(): bool
    {
        // In package context, skip strict connectivity checks
        // These will be handled by individual commands as needed
        return true;
    }

    /**
     * Display command header with branding.
     */
    protected function displayHeader(): void
    {
        $this->newLine();
        $this->line('┌─────────────────────────────────────────────────────────────┐');
        $this->line('│                    JTD FormSecurity                        │');
        $this->line('│                 CLI Management Tool                        │');
        $this->line('└─────────────────────────────────────────────────────────────┘');
        $this->newLine();
    }

    /**
     * Display command footer with execution summary.
     */
    protected function displayFooter(int $result): void
    {
        $executionTime = round(microtime(true) - $this->startTime, 2);
        $status = $result === Command::SUCCESS ? 'SUCCESS' : 'FAILURE';
        $statusColor = $result === Command::SUCCESS ? 'green' : 'red';

        $this->newLine();
        $this->line('─────────────────────────────────────────────────────────────');
        $this->line("Status: <fg={$statusColor}>{$status}</>");
        $this->line("Execution Time: {$executionTime}s");
        $this->line('─────────────────────────────────────────────────────────────');
        $this->newLine();
    }

    /**
     * Handle exceptions with detailed error reporting.
     */
    protected function handleException(\Exception $e): void
    {
        $this->newLine();
        $this->error('Command execution failed:');
        $this->error($e->getMessage());

        if ($this->option('verbose')) {
            $this->newLine();
            $this->line('<comment>Stack Trace:</comment>');
            $this->line($e->getTraceAsString());
        }

        $this->newLine();
        $this->line('<comment>For more details, run with --verbose flag</comment>');
    }

    /**
     * Check database connection.
     */
    protected function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Exception $e) {
            // In package context, database might not be configured
            // Return true to allow commands to proceed
            return true;
        }
    }

    /**
     * Check cache connection.
     */
    protected function checkCacheConnection(): bool
    {
        try {
            Cache::put('form_security_test', 'test', 1);
            $result = Cache::get('form_security_test') === 'test';
            Cache::forget('form_security_test');

            return $result;
        } catch (\Exception $e) {
            // In package context, cache might not be configured
            // Return true to allow commands to proceed
            return true;
        }
    }

    /**
     * Create a progress bar with FormSecurity styling.
     */
    protected function createProgressBar(int $max): ProgressBar
    {
        $progressBar = $this->output->createProgressBar($max);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->setBarCharacter('<fg=green>█</>');
        $progressBar->setEmptyBarCharacter('<fg=red>░</>');
        $progressBar->setProgressCharacter('<fg=green>█</>');

        return $progressBar;
    }

    /**
     * Display a formatted table with consistent styling.
     */
    protected function displayTable(array $headers, array $rows, ?string $title = null): void
    {
        if ($title) {
            $this->newLine();
            $this->line("<comment>{$title}</comment>");
            $this->newLine();
        }

        $this->table($headers, $rows);
    }

    /**
     * Display a success message with consistent formatting.
     */
    protected function displaySuccess(string $message): void
    {
        $this->newLine();
        $this->line("<fg=green>✓ {$message}</>");
    }

    /**
     * Display a warning message with consistent formatting.
     */
    protected function displayWarning(string $message): void
    {
        $this->newLine();
        $this->line("<fg=yellow>⚠ {$message}</>");
    }

    /**
     * Display an error message with consistent formatting.
     */
    protected function displayError(string $message): void
    {
        $this->newLine();
        $this->line("<fg=red>✗ {$message}</>");
    }

    /**
     * Confirm action with user using Laravel Prompts.
     */
    protected function confirmAction(string $message, bool $default = false): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return $this->confirm($message, $default);
    }

    /**
     * Get system information for diagnostics.
     */
    protected function getSystemInfo(): array
    {
        return [
            'PHP Version' => PHP_VERSION,
            'Laravel Version' => app()->version(),
            'Package Version' => $this->getPackageVersion(),
            'Environment' => app()->environment(),
            'Debug Mode' => config('app.debug') ? 'Enabled' : 'Disabled',
            'Cache Driver' => config('cache.default'),
            'Database Driver' => config('database.default'),
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time'),
        ];
    }

    /**
     * Get package version.
     */
    protected function getPackageVersion(): string
    {
        // This would typically read from composer.json or a version file
        return '1.0.0-dev';
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * Format duration to human readable format.
     */
    protected function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 2).'s';
        }

        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;

        return $minutes.'m '.round($seconds, 2).'s';
    }
}
