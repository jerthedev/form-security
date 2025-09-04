<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * FormSecurity cleanup command.
 *
 * Provides comprehensive data cleanup operations including old records,
 * temporary files, and maintenance tasks with confirmation prompts and
 * detailed reporting of cleanup operations.
 */
class CleanupCommand extends FormSecurityCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'form-security:cleanup 
                            {--type=* : Cleanup types (old-records|temp-files|logs|cache|all)}
                            {--days=30 : Age threshold in days for cleanup}
                            {--force : Force cleanup without confirmation}
                            {--dry-run : Show what would be cleaned without actually doing it}
                            {--batch-size=1000 : Batch size for database operations}
                            {--parallel : Enable parallel processing for multiple cleanup types}
                            {--memory-limit=100 : Memory limit in MB for cleanup operations}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old FormSecurity data and temporary files';

    /**
     * Cleanup statistics.
     */
    protected array $cleanupStats = [
        'records_deleted' => 0,
        'files_deleted' => 0,
        'cache_cleared' => 0,
        'logs_cleaned' => 0,
        'space_freed' => 0,
    ];

    /**
     * Execute the main command logic with performance optimizations.
     */
    protected function executeCommand(): int
    {
        $types = $this->option('type') ?: ['all'];
        $days = (int) $this->option('days');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');
        $parallel = $this->option('parallel');
        $memoryLimit = (int) $this->option('memory-limit');

        // Set memory limit to prevent issues with large datasets
        ini_set('memory_limit', $memoryLimit.'M');

        $this->line('<comment>FormSecurity Cleanup Operation</comment>');
        $this->newLine();

        if ($dryRun) {
            $this->line('<fg=yellow>DRY RUN MODE - No actual cleanup will be performed</fg=yellow>');
            $this->newLine();
        }

        // Validate age threshold
        if ($days < 1) {
            $this->displayError('Age threshold must be at least 1 day');

            return Command::FAILURE;
        }

        // Show cleanup plan with performance estimates
        $this->displayOptimizedCleanupPlan($types, $days, $batchSize);

        if (! $force && ! $dryRun && ! $this->confirmAction('Proceed with cleanup?', false)) {
            $this->info('Cleanup cancelled');

            return Command::SUCCESS;
        }

        try {
            $cutoffDate = Carbon::now()->subDays($days);
            $cleanupStart = microtime(true);

            if ($parallel && count($types) > 1) {
                $this->executeParallelCleanup($types, $cutoffDate, $batchSize, $dryRun);
            } else {
                $this->executeSequentialCleanup($types, $cutoffDate, $batchSize, $dryRun);
            }

            $this->recordMetric('total_cleanup_time', microtime(true) - $cleanupStart);
            $this->displayCleanupSummary($dryRun);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->displayError('Cleanup failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Display cleanup plan.
     */
    protected function displayCleanupPlan(array $types, int $days): void
    {
        $this->line('<comment>Cleanup Plan</comment>');
        $this->line('─────────────────────────────────────────────────────────────');
        $this->line("Age Threshold: {$days} days");
        $this->line('Cleanup Types: '.implode(', ', $types));
        $this->line('Cutoff Date: '.Carbon::now()->subDays($days)->format('Y-m-d H:i:s'));
        $this->newLine();

        // Show estimated cleanup sizes
        $estimates = $this->getCleanupEstimates($days);

        $headers = ['Category', 'Items to Clean', 'Estimated Size'];
        $rows = [
            ['Old Records', number_format($estimates['records']), $this->formatBytes($estimates['records_size'])],
            ['Temporary Files', number_format($estimates['temp_files']), $this->formatBytes($estimates['temp_files_size'])],
            ['Log Files', number_format($estimates['log_files']), $this->formatBytes($estimates['log_files_size'])],
            ['Cache Entries', number_format($estimates['cache_entries']), $this->formatBytes($estimates['cache_size'])],
        ];

        $this->displayTable($headers, $rows, 'Cleanup Estimates');
    }

    /**
     * Get cleanup estimates.
     */
    protected function getCleanupEstimates(int $days): array
    {
        $cutoffDate = Carbon::now()->subDays($days);

        try {
            // Estimate old records
            $recordsCount = 0;
            $recordsSize = 0;

            try {
                $recordsCount = DB::table('blocked_submissions')
                    ->where('created_at', '<', $cutoffDate)
                    ->count();
                $recordsSize = $recordsCount * 1024; // Rough estimate
            } catch (\Exception $e) {
                // Table doesn't exist or database error - use defaults
                $recordsCount = 0;
                $recordsSize = 0;
            }

            // Estimate temporary files
            $tempPath = storage_path('app/form-security/temp');
            $tempFiles = 0;
            $tempSize = 0;

            if (File::exists($tempPath)) {
                $files = File::allFiles($tempPath);
                foreach ($files as $file) {
                    if ($file->getMTime() < $cutoffDate->timestamp) {
                        $tempFiles++;
                        $tempSize += $file->getSize();
                    }
                }
            }

            // Estimate log files
            $logPath = storage_path('logs');
            $logFiles = 0;
            $logSize = 0;

            if (File::exists($logPath)) {
                $files = File::glob($logPath.'/form-security-*.log');
                foreach ($files as $file) {
                    if (filemtime($file) < $cutoffDate->timestamp) {
                        $logFiles++;
                        $logSize += filesize($file);
                    }
                }
            }

            // Estimate cache entries
            $cacheStats = $this->cacheManager->getStats();
            $cacheEntries = $cacheStats['total_entries'] ?? 0;
            $cacheSize = $cacheStats['cache_size'] ?? 0;

            return [
                'records' => $recordsCount,
                'records_size' => $recordsSize,
                'temp_files' => $tempFiles,
                'temp_files_size' => $tempSize,
                'log_files' => $logFiles,
                'log_files_size' => $logSize,
                'cache_entries' => $cacheEntries,
                'cache_size' => $cacheSize,
            ];
        } catch (\Exception $e) {
            return [
                'records' => 0,
                'records_size' => 0,
                'temp_files' => 0,
                'temp_files_size' => 0,
                'log_files' => 0,
                'log_files_size' => 0,
                'cache_entries' => 0,
                'cache_size' => 0,
            ];
        }
    }

    /**
     * Cleanup old database records with optimized chunked processing.
     */
    protected function cleanupOldRecords(Carbon $cutoffDate, int $batchSize, bool $dryRun): void
    {
        $this->line('<comment>Cleaning old database records...</comment>');

        $tables = [
            'blocked_submissions',
            'ip_reputation',
            'spam_patterns',
        ];

        $progressBar = $this->createProgressBar(count($tables));
        $progressBar->start();

        foreach ($tables as $table) {
            $progressBar->setMessage("Processing table: {$table}");

            try {
                $deleted = $this->processInChunks(
                    fn ($chunkSize, $offset) => DB::table($table)
                        ->where('created_at', '<', $cutoffDate)
                        ->limit($chunkSize)
                        ->offset($offset)
                        ->get(['id']),
                    $batchSize,
                    function ($chunk, $chunkNumber) use ($table, $dryRun) {
                        if (! $dryRun) {
                            $ids = $chunk->pluck('id');
                            $deleted = DB::table($table)->whereIn('id', $ids)->delete();
                            $this->cleanupStats['records_deleted'] += $deleted;
                        } else {
                            $count = count($chunk);
                            $this->line("Chunk {$chunkNumber}: Would delete {$count} records from {$table}");
                        }
                    }
                );

                $this->recordMetric("table_{$table}_processed", $deleted);
            } catch (\Exception $e) {
                // Table doesn't exist or database error - skip gracefully
                $this->line("Skipping table {$table}: ".$e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Cleanup temporary files.
     */
    protected function cleanupTemporaryFiles(Carbon $cutoffDate, bool $dryRun): void
    {
        $this->line('<comment>Cleaning temporary files...</comment>');

        $tempPaths = [
            storage_path('app/form-security/temp'),
            storage_path('app/form-security/uploads'),
            storage_path('app/form-security/exports'),
        ];

        foreach ($tempPaths as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $files = File::allFiles($path);
            $deletedCount = 0;
            $deletedSize = 0;

            foreach ($files as $file) {
                if ($file->getMTime() < $cutoffDate->timestamp) {
                    $size = $file->getSize();

                    if (! $dryRun) {
                        File::delete($file->getPathname());
                        $deletedCount++;
                        $deletedSize += $size;
                    } else {
                        $this->line("Would delete: {$file->getPathname()} ({$this->formatBytes($size)})");
                    }
                }
            }

            if (! $dryRun) {
                $this->cleanupStats['files_deleted'] += $deletedCount;
                $this->cleanupStats['space_freed'] += $deletedSize;
            }

            $this->line("Processed {$path}: {$deletedCount} files");
        }
    }

    /**
     * Cleanup log files.
     */
    protected function cleanupLogs(Carbon $cutoffDate, bool $dryRun): void
    {
        $this->line('<comment>Cleaning log files...</comment>');

        $logPath = storage_path('logs');
        $logFiles = File::glob($logPath.'/form-security-*.log');

        $deletedCount = 0;
        $deletedSize = 0;

        foreach ($logFiles as $file) {
            if (filemtime($file) < $cutoffDate->timestamp) {
                $size = filesize($file);

                if (! $dryRun) {
                    File::delete($file);
                    $deletedCount++;
                    $deletedSize += $size;
                } else {
                    $this->line("Would delete: {$file} ({$this->formatBytes($size)})");
                }
            }
        }

        if (! $dryRun) {
            $this->cleanupStats['logs_cleaned'] += $deletedCount;
            $this->cleanupStats['space_freed'] += $deletedSize;
        }

        $this->line("Processed log files: {$deletedCount} files");
    }

    /**
     * Cleanup cache entries.
     */
    protected function cleanupCache(bool $dryRun): void
    {
        $this->line('<comment>Cleaning cache entries...</comment>');

        if (! $dryRun) {
            $result = $this->cacheManager->maintenance(['cleanup']);
            $clearedEntries = $result['cleanup']['items_processed'] ?? 0;
            $this->cleanupStats['cache_cleared'] += $clearedEntries;
            $this->line("Cleared {$clearedEntries} expired cache entries");
        } else {
            $stats = $this->cacheManager->getStats();
            $expiredCount = $stats['expired_entries'] ?? 0;
            $this->line("Would clear {$expiredCount} expired cache entries");
        }
    }

    /**
     * Display cleanup summary.
     */
    protected function displayCleanupSummary(bool $dryRun): void
    {
        $this->newLine();
        $this->line('<comment>Cleanup Summary</comment>');
        $this->line('─────────────────────────────────────────────────────────────');

        if ($dryRun) {
            $this->line('<fg=yellow>DRY RUN COMPLETED - No actual cleanup was performed</fg=yellow>');
        } else {
            $headers = ['Category', 'Items Cleaned', 'Space Freed'];
            $rows = [
                ['Database Records', number_format($this->cleanupStats['records_deleted']), 'N/A'],
                ['Temporary Files', number_format($this->cleanupStats['files_deleted']), $this->formatBytes($this->cleanupStats['space_freed'])],
                ['Log Files', number_format($this->cleanupStats['logs_cleaned']), 'Included above'],
                ['Cache Entries', number_format($this->cleanupStats['cache_cleared']), 'N/A'],
            ];

            $this->displayTable($headers, $rows);

            $totalSpaceFreed = $this->formatBytes($this->cleanupStats['space_freed']);
            $this->displaySuccess("Cleanup completed successfully! Total space freed: {$totalSpaceFreed}");
        }

        $this->newLine();
        $this->line('<comment>Recommendations:</comment>');
        $this->line('• Run cleanup regularly to maintain optimal performance');
        $this->line('• Consider adjusting retention periods based on your needs');
        $this->line('• Monitor disk space usage after cleanup operations');
    }

    /**
     * Display optimized cleanup plan with performance estimates.
     */
    protected function displayOptimizedCleanupPlan(array $types, int $days, int $batchSize): void
    {
        $this->line('<comment>Cleanup Plan (Optimized)</comment>');
        $this->line('─────────────────────────────────────────────────────────────');
        $this->line("Age Threshold: {$days} days");
        $this->line("Batch Size: {$batchSize}");
        $this->line('Cleanup Types: '.implode(', ', $types));
        $this->line('Cutoff Date: '.Carbon::now()->subDays($days)->format('Y-m-d H:i:s'));
        $this->newLine();

        // Show estimated cleanup sizes with performance predictions
        $estimates = $this->getCleanupEstimates($days);
        $estimatedTime = $this->estimateCleanupTime($estimates, $batchSize);

        $headers = ['Category', 'Items to Clean', 'Estimated Size', 'Est. Time'];
        $rows = [
            ['Old Records', number_format($estimates['records']), $this->formatBytes($estimates['records_size']), $estimatedTime['records'].'s'],
            ['Temporary Files', number_format($estimates['temp_files']), $this->formatBytes($estimates['temp_files_size']), $estimatedTime['temp_files'].'s'],
            ['Log Files', number_format($estimates['log_files']), $this->formatBytes($estimates['log_files_size']), $estimatedTime['log_files'].'s'],
            ['Cache Entries', number_format($estimates['cache_entries']), $this->formatBytes($estimates['cache_size']), $estimatedTime['cache'].'s'],
        ];

        $this->displayTable($headers, $rows, 'Optimized Cleanup Estimates');
    }

    /**
     * Execute cleanup operations in parallel.
     */
    protected function executeParallelCleanup(array $types, Carbon $cutoffDate, int $batchSize, bool $dryRun): void
    {
        $this->line('<comment>Executing parallel cleanup...</comment>');

        $operations = [];

        if (in_array('all', $types) || in_array('old-records', $types)) {
            $operations['records'] = fn () => $this->cleanupOldRecords($cutoffDate, $batchSize, $dryRun);
        }

        if (in_array('all', $types) || in_array('temp-files', $types)) {
            $operations['temp_files'] = fn () => $this->cleanupTemporaryFiles($cutoffDate, $dryRun);
        }

        if (in_array('all', $types) || in_array('logs', $types)) {
            $operations['logs'] = fn () => $this->cleanupLogs($cutoffDate, $dryRun);
        }

        if (in_array('all', $types) || in_array('cache', $types)) {
            $operations['cache'] = fn () => $this->cleanupCache($dryRun);
        }

        $results = $this->executeInParallel($operations);

        $completed = count(array_filter($results, fn ($result) => $result !== null));
        $this->line("Parallel cleanup completed: {$completed}/{count($operations)} operations successful");
    }

    /**
     * Execute cleanup operations sequentially.
     */
    protected function executeSequentialCleanup(array $types, Carbon $cutoffDate, int $batchSize, bool $dryRun): void
    {
        $this->line('<comment>Executing sequential cleanup...</comment>');

        foreach ($types as $type) {
            $operationStart = microtime(true);

            if ($type === 'all') {
                $this->cleanupOldRecords($cutoffDate, $batchSize, $dryRun);
                $this->cleanupTemporaryFiles($cutoffDate, $dryRun);
                $this->cleanupLogs($cutoffDate, $dryRun);
                $this->cleanupCache($dryRun);
            } else {
                match ($type) {
                    'old-records' => $this->cleanupOldRecords($cutoffDate, $batchSize, $dryRun),
                    'temp-files' => $this->cleanupTemporaryFiles($cutoffDate, $dryRun),
                    'logs' => $this->cleanupLogs($cutoffDate, $dryRun),
                    'cache' => $this->cleanupCache($dryRun),
                    default => $this->displayWarning("Unknown cleanup type: {$type}"),
                };
            }

            $this->recordMetric("type_{$type}_time", microtime(true) - $operationStart);
        }
    }

    /**
     * Estimate cleanup time based on data size and batch size.
     */
    protected function estimateCleanupTime(array $estimates, int $batchSize): array
    {
        return [
            'records' => max(1, ceil($estimates['records'] / $batchSize) * 0.1),
            'temp_files' => max(1, ceil($estimates['temp_files'] / 100) * 0.05),
            'log_files' => max(1, ceil($estimates['log_files'] / 10) * 0.02),
            'cache' => 2, // Fixed time for cache operations
        ];
    }
}
