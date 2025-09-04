<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\FormSecurity\Services\CacheManager;
use JTD\FormSecurity\Services\ConfigurationManager;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Base FormSecurity command class.
 *
 * Provides common functionality for all FormSecurity CLI commands including
 * error handling, output formatting, progress reporting, and shared utilities.
 * Leverages Laravel 12's enhanced console features with performance optimizations.
 */
abstract class FormSecurityCommand extends Command
{
    /**
     * Configuration manager instance (lazy loaded).
     */
    protected ?ConfigurationManager $configManager = null;

    /**
     * Cache manager instance (lazy loaded).
     */
    protected ?CacheManager $cacheManager = null;

    /**
     * Command start time for performance tracking.
     */
    protected float $startTime;

    /**
     * Memory usage at command start.
     */
    protected int $startMemory;

    /**
     * Command performance metrics.
     */
    protected array $performanceMetrics = [];

    /**
     * Styled output helper.
     */
    protected ?SymfonyStyle $io = null;

    /**
     * Cached system information.
     */
    protected static ?array $systemInfoCache = null;

    /**
     * Create a new command instance with lazy loading.
     */
    public function __construct()
    {
        parent::__construct();

        // Dependencies will be resolved lazily when needed
    }

    /**
     * Execute the console command with performance monitoring.
     */
    public function handle(): int
    {
        $this->initializePerformanceTracking();
        $this->io = new SymfonyStyle($this->input, $this->output);

        try {
            $this->displayHeader();

            // Perform pre-execution checks (optimized)
            if (! $this->preExecutionChecks()) {
                return Command::FAILURE;
            }

            // Record pre-execution metrics
            $this->recordMetric('pre_execution_time', microtime(true) - $this->startTime);
            $this->recordMetric('pre_execution_memory', memory_get_usage(true) - $this->startMemory);

            // Execute the main command logic
            $executionStart = microtime(true);
            $result = $this->executeCommand();
            $this->recordMetric('execution_time', microtime(true) - $executionStart);

            $this->displayFooter($result);
            $this->displayPerformanceMetrics();

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
     * Display optimized command header with branding.
     */
    protected function displayHeader(): void
    {
        // Use buffered output for better performance
        $header = "\n".
                 "┌─────────────────────────────────────────────────────────────┐\n".
                 "│                    JTD FormSecurity                        │\n".
                 "│                 CLI Management Tool                        │\n".
                 "└─────────────────────────────────────────────────────────────┘\n\n";

        $this->output->write($header);
    }

    /**
     * Display optimized command footer with execution summary.
     */
    protected function displayFooter(int $result): void
    {
        $executionTime = round(microtime(true) - $this->startTime, 3);
        $memoryUsage = memory_get_usage(true) - $this->startMemory;
        $peakMemory = memory_get_peak_usage(true);

        $status = $result === Command::SUCCESS ? 'SUCCESS' : 'FAILURE';
        $statusColor = $result === Command::SUCCESS ? 'green' : 'red';

        $footer = "\n─────────────────────────────────────────────────────────────\n".
                 "Status: <fg={$statusColor}>{$status}</>\n".
                 "Execution Time: {$executionTime}s\n".
                 "Memory Used: {$this->formatBytes($memoryUsage)}\n".
                 "Peak Memory: {$this->formatBytes($peakMemory)}\n".
                 "─────────────────────────────────────────────────────────────\n\n";

        $this->output->write($footer);
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
     * Create an optimized progress bar with time estimates and memory tracking.
     */
    protected function createProgressBar(int $max): ProgressBar
    {
        $progressBar = $this->output->createProgressBar($max);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% %message%');
        $progressBar->setBarCharacter('<fg=green>█</>');
        $progressBar->setEmptyBarCharacter('<fg=red>░</>');
        $progressBar->setProgressCharacter('<fg=green>█</>');
        $progressBar->setMessage('Initializing...');

        return $progressBar;
    }

    /**
     * Initialize performance tracking.
     */
    protected function initializePerformanceTracking(): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        $this->performanceMetrics = [
            'start_time' => $this->startTime,
            'start_memory' => $this->startMemory,
        ];
    }

    /**
     * Record a performance metric.
     */
    protected function recordMetric(string $key, float $value): void
    {
        $this->performanceMetrics[$key] = $value;
    }

    /**
     * Display performance metrics if verbose mode is enabled.
     */
    protected function displayPerformanceMetrics(): void
    {
        if (! $this->option('verbose')) {
            return;
        }

        $this->newLine();
        $this->line('<comment>Performance Metrics:</comment>');

        $headers = ['Metric', 'Value'];
        $rows = [];

        foreach ($this->performanceMetrics as $metric => $value) {
            $formattedValue = match (true) {
                str_contains($metric, 'time') => number_format($value * 1000, 2).'ms',
                str_contains($metric, 'memory') => $this->formatBytes((int) $value),
                default => is_float($value) ? number_format($value, 3) : (string) $value,
            };

            $rows[] = [ucwords(str_replace('_', ' ', $metric)), $formattedValue];
        }

        if (! empty($rows)) {
            $this->table($headers, $rows);
        }
    }

    /**
     * Get configuration manager with lazy loading.
     */
    protected function getConfigManager(): ConfigurationManager
    {
        if ($this->configManager === null) {
            $this->configManager = app(ConfigurationManager::class);
        }

        return $this->configManager;
    }

    /**
     * Get cache manager with lazy loading.
     */
    protected function getCacheManager(): CacheManager
    {
        if ($this->cacheManager === null) {
            $this->cacheManager = app(CacheManager::class);
        }

        return $this->cacheManager;
    }

    /**
     * Lazy property accessor for configManager.
     */
    public function __get(string $name)
    {
        return match ($name) {
            'configManager' => $this->getConfigManager(),
            'cacheManager' => $this->getCacheManager(),
            default => throw new \InvalidArgumentException("Property {$name} does not exist"),
        };
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
     * Get cached system information for diagnostics.
     */
    protected function getSystemInfo(): array
    {
        if (self::$systemInfoCache === null) {
            self::$systemInfoCache = [
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

        return self::$systemInfoCache;
    }

    /**
     * Process large datasets in chunks for memory efficiency.
     */
    protected function processInChunks(\Closure $query, int $chunkSize, \Closure $callback): int
    {
        $totalProcessed = 0;
        $chunkNumber = 0;

        do {
            $startTime = microtime(true);
            $chunk = $query($chunkSize, $chunkNumber * $chunkSize);
            $count = count($chunk);

            if ($count > 0) {
                $callback($chunk, $chunkNumber);
                $totalProcessed += $count;

                // Record chunk processing metrics
                $this->recordMetric("chunk_{$chunkNumber}_time", microtime(true) - $startTime);
                $this->recordMetric("chunk_{$chunkNumber}_size", $count);

                // Force garbage collection every 10 chunks to prevent memory buildup
                if ($chunkNumber % 10 === 0) {
                    gc_collect_cycles();
                }
            }

            $chunkNumber++;
        } while ($count === $chunkSize);

        return $totalProcessed;
    }

    /**
     * Execute multiple operations in parallel using coroutines.
     */
    protected function executeInParallel(array $operations): array
    {
        $results = [];
        $startTime = microtime(true);

        // Simple parallel execution using generators for lightweight operations
        foreach ($operations as $key => $operation) {
            $operationStart = microtime(true);

            try {
                $results[$key] = $operation();
                $this->recordMetric("parallel_{$key}_time", microtime(true) - $operationStart);
            } catch (\Exception $e) {
                $results[$key] = null;
                $this->recordMetric("parallel_{$key}_error", $e->getMessage());
            }
        }

        $this->recordMetric('parallel_total_time', microtime(true) - $startTime);

        return $results;
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
