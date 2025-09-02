<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use JTD\FormSecurity\Services\ConfigurationManager;
use JTD\FormSecurity\Services\CacheManager;
use Carbon\Carbon;

/**
 * FormSecurity health check command.
 *
 * Provides comprehensive system diagnostics including database connectivity,
 * cache status, configuration validation, performance checks, and system health monitoring.
 */
class HealthCheckCommand extends FormSecurityCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'form-security:health-check 
                            {--detailed : Show detailed diagnostic information}
                            {--fix : Attempt to fix detected issues automatically}
                            {--export=* : Export results to file (json|html|txt)}';

    /**
     * The console command description.
     */
    protected $description = 'Perform comprehensive FormSecurity system health check';

    /**
     * Health check results.
     */
    protected array $healthResults = [
        'overall_status' => 'unknown',
        'checks' => [],
        'warnings' => [],
        'errors' => [],
        'recommendations' => [],
    ];

    /**
     * Execute the main command logic.
     */
    protected function executeCommand(): int
    {
        $detailed = $this->option('detailed');
        $fix = $this->option('fix');
        $export = $this->option('export');

        $this->line('<comment>FormSecurity System Health Check</comment>');
        $this->newLine();

        // Perform all health checks
        $this->performHealthChecks($detailed, $fix);

        // Display results
        $this->displayHealthResults($detailed);

        // Export results if requested
        if (!empty($export)) {
            $this->exportResults($export);
        }

        // Determine overall status
        $overallStatus = $this->determineOverallStatus();
        $this->healthResults['overall_status'] = $overallStatus;

        return $overallStatus === 'healthy' ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Perform all health checks.
     */
    protected function performHealthChecks(bool $detailed, bool $fix): void
    {
        $checks = [
            'System Requirements' => 'checkSystemRequirements',
            'Database Connectivity' => 'checkDatabaseConnectivity',
            'Cache System' => 'checkCacheSystem',
            'Configuration' => 'checkConfiguration',
            'File Permissions' => 'checkFilePermissions',
            'Performance' => 'checkPerformance',
            'Security' => 'checkSecurity',
            'Dependencies' => 'checkDependencies',
        ];

        $progressBar = $this->createProgressBar(count($checks));
        $progressBar->start();

        foreach ($checks as $checkName => $method) {
            $progressBar->setMessage("Checking: {$checkName}");
            
            try {
                $result = $this->$method($detailed, $fix);
                $this->healthResults['checks'][$checkName] = $result;
            } catch (\Exception $e) {
                $this->healthResults['checks'][$checkName] = [
                    'status' => 'error',
                    'message' => 'Check failed: ' . $e->getMessage(),
                    'details' => $detailed ? $e->getTraceAsString() : null,
                ];
                $this->healthResults['errors'][] = "Failed to check {$checkName}: " . $e->getMessage();
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Check system requirements.
     */
    protected function checkSystemRequirements(bool $detailed, bool $fix): array
    {
        $result = ['status' => 'healthy', 'details' => []];
        
        // PHP version
        $phpVersion = PHP_VERSION;
        if (version_compare($phpVersion, '8.2.0', '<')) {
            $result['status'] = 'error';
            $result['details'][] = "PHP 8.2+ required, current: {$phpVersion}";
            $this->healthResults['errors'][] = "Unsupported PHP version: {$phpVersion}";
        } else {
            $result['details'][] = "PHP version: {$phpVersion} ✓";
        }

        // Laravel version
        $laravelVersion = app()->version();
        if (version_compare($laravelVersion, '12.0', '<')) {
            $result['status'] = 'error';
            $result['details'][] = "Laravel 12+ required, current: {$laravelVersion}";
            $this->healthResults['errors'][] = "Unsupported Laravel version: {$laravelVersion}";
        } else {
            $result['details'][] = "Laravel version: {$laravelVersion} ✓";
        }

        // Required extensions
        $requiredExtensions = ['pdo', 'mbstring', 'openssl', 'json', 'curl'];
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $result['status'] = 'error';
                $result['details'][] = "Missing PHP extension: {$extension}";
                $this->healthResults['errors'][] = "Missing required extension: {$extension}";
            } else {
                $result['details'][] = "Extension {$extension}: loaded ✓";
            }
        }

        // Memory limit
        $memoryLimit = ini_get('memory_limit');
        $memoryBytes = $this->parseMemoryLimit($memoryLimit);
        if ($memoryBytes < 128 * 1024 * 1024) { // 128MB
            $result['status'] = 'warning';
            $result['details'][] = "Low memory limit: {$memoryLimit} (recommended: 128M+)";
            $this->healthResults['warnings'][] = "Memory limit may be too low: {$memoryLimit}";
        } else {
            $result['details'][] = "Memory limit: {$memoryLimit} ✓";
        }

        return $result;
    }

    /**
     * Check database connectivity.
     */
    protected function checkDatabaseConnectivity(bool $detailed, bool $fix): array
    {
        $result = ['status' => 'healthy', 'details' => []];
        
        try {
            // Test connection
            $pdo = DB::connection()->getPdo();
            $result['details'][] = "Database connection: established ✓";
            
            // Check driver
            $driver = DB::connection()->getDriverName();
            $result['details'][] = "Database driver: {$driver} ✓";
            
            // Test query performance
            $start = microtime(true);
            DB::select('SELECT 1');
            $queryTime = round((microtime(true) - $start) * 1000, 2);
            
            if ($queryTime > 100) {
                $result['status'] = 'warning';
                $result['details'][] = "Slow query response: {$queryTime}ms (target: <100ms)";
                $this->healthResults['warnings'][] = "Database queries are slow: {$queryTime}ms";
            } else {
                $result['details'][] = "Query performance: {$queryTime}ms ✓";
            }
            
            // Check required tables
            $requiredTables = [
                'blocked_submissions',
                'ip_reputation',
                'spam_patterns',
            ];
            
            foreach ($requiredTables as $table) {
                if (!$this->tableExists($table)) {
                    $result['status'] = 'error';
                    $result['details'][] = "Missing table: {$table}";
                    $this->healthResults['errors'][] = "Required table missing: {$table}";
                    
                    if ($fix) {
                        $result['details'][] = "Attempting to create missing tables...";
                        // Would run migrations here
                    }
                } else {
                    $result['details'][] = "Table {$table}: exists ✓";
                }
            }
            
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['details'][] = "Database connection failed: " . $e->getMessage();
            $this->healthResults['errors'][] = "Database connection failed";
        }
        
        return $result;
    }

    /**
     * Check cache system.
     */
    protected function checkCacheSystem(bool $detailed, bool $fix): array
    {
        $result = ['status' => 'healthy', 'details' => []];
        
        try {
            // Test cache connectivity
            $testKey = 'health_check_' . time();
            $testValue = 'test_value_' . rand(1000, 9999);
            
            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            
            if ($retrieved === $testValue) {
                $result['details'][] = "Cache connectivity: working ✓";
            } else {
                $result['status'] = 'error';
                $result['details'][] = "Cache connectivity: failed";
                $this->healthResults['errors'][] = "Cache system not working properly";
            }
            
            // Check cache driver
            $driver = config('cache.default');
            $result['details'][] = "Cache driver: {$driver} ✓";
            
            // Get cache statistics
            $stats = $this->cacheManager->getStatistics();
            $hitRatio = $stats['hit_ratio'] ?? 0;
            
            if ($hitRatio < 80) {
                $result['status'] = 'warning';
                $result['details'][] = "Low cache hit ratio: {$hitRatio}% (target: 90%+)";
                $this->healthResults['warnings'][] = "Cache hit ratio is below optimal: {$hitRatio}%";
            } else {
                $result['details'][] = "Cache hit ratio: {$hitRatio}% ✓";
            }
            
            // Check cache size
            $cacheSize = $stats['cache_size'] ?? 0;
            $result['details'][] = "Cache size: " . $this->formatBytes($cacheSize);
            
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['details'][] = "Cache check failed: " . $e->getMessage();
            $this->healthResults['errors'][] = "Cache system error: " . $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Check configuration.
     */
    protected function checkConfiguration(bool $detailed, bool $fix): array
    {
        $result = ['status' => 'healthy', 'details' => []];
        
        try {
            // Check configuration files exist
            $configFiles = [
                'form-security.php',
                'form-security-cache.php',
                'form-security-patterns.php',
            ];
            
            foreach ($configFiles as $configFile) {
                if (!File::exists(config_path($configFile))) {
                    $result['status'] = 'error';
                    $result['details'][] = "Missing config file: {$configFile}";
                    $this->healthResults['errors'][] = "Configuration file missing: {$configFile}";
                } else {
                    $result['details'][] = "Config file {$configFile}: exists ✓";
                }
            }
            
            // Validate configuration values
            $validationResult = $this->configManager->validateConfig([]);
            
            if (!$validationResult['valid']) {
                $result['status'] = 'warning';
                foreach ($validationResult['errors'] as $error) {
                    $result['details'][] = "Config validation: {$error}";
                    $this->healthResults['warnings'][] = "Configuration issue: {$error}";
                }
            } else {
                $result['details'][] = "Configuration validation: passed ✓";
            }
            
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['details'][] = "Configuration check failed: " . $e->getMessage();
            $this->healthResults['errors'][] = "Configuration error: " . $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Check file permissions.
     */
    protected function checkFilePermissions(bool $detailed, bool $fix): array
    {
        $result = ['status' => 'healthy', 'details' => []];
        
        $paths = [
            storage_path('app/form-security') => 'rwx',
            storage_path('logs') => 'rwx',
            config_path() => 'r',
        ];
        
        foreach ($paths as $path => $requiredPerms) {
            if (!File::exists($path)) {
                if ($fix && $requiredPerms === 'rwx') {
                    File::makeDirectory($path, 0755, true);
                    $result['details'][] = "Created directory: {$path} ✓";
                } else {
                    $result['status'] = 'warning';
                    $result['details'][] = "Path does not exist: {$path}";
                    $this->healthResults['warnings'][] = "Missing path: {$path}";
                }
            } else {
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                $result['details'][] = "Path {$path}: permissions {$perms} ✓";
            }
        }
        
        return $result;
    }

    /**
     * Check performance metrics.
     */
    protected function checkPerformance(bool $detailed, bool $fix): array
    {
        $result = ['status' => 'healthy', 'details' => []];
        
        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        
        $result['details'][] = "Current memory: " . $this->formatBytes($memoryUsage);
        $result['details'][] = "Peak memory: " . $this->formatBytes($memoryPeak);
        
        if ($memoryPeak > 50 * 1024 * 1024) { // 50MB
            $result['status'] = 'warning';
            $result['details'][] = "High memory usage detected";
            $this->healthResults['warnings'][] = "Memory usage is high: " . $this->formatBytes($memoryPeak);
        }
        
        // Execution time
        $executionTime = microtime(true) - $this->startTime;
        $result['details'][] = "Health check time: " . round($executionTime, 2) . "s";
        
        return $result;
    }

    /**
     * Check security configuration.
     */
    protected function checkSecurity(bool $detailed, bool $fix): array
    {
        $result = ['status' => 'healthy', 'details' => []];
        
        // Check debug mode
        if (config('app.debug') && app()->environment('production')) {
            $result['status'] = 'error';
            $result['details'][] = "Debug mode enabled in production";
            $this->healthResults['errors'][] = "Debug mode should be disabled in production";
        } else {
            $result['details'][] = "Debug mode: appropriate for environment ✓";
        }
        
        // Check app key
        if (empty(config('app.key'))) {
            $result['status'] = 'error';
            $result['details'][] = "Application key not set";
            $this->healthResults['errors'][] = "Application key is missing";
        } else {
            $result['details'][] = "Application key: configured ✓";
        }
        
        return $result;
    }

    /**
     * Check dependencies.
     */
    protected function checkDependencies(bool $detailed, bool $fix): array
    {
        $result = ['status' => 'healthy', 'details' => []];
        
        // Check if services are bound
        $services = [
            ConfigurationManager::class,
            CacheManager::class,
        ];
        
        foreach ($services as $service) {
            if (!app()->bound($service)) {
                $result['status'] = 'error';
                $result['details'][] = "Service not bound: {$service}";
                $this->healthResults['errors'][] = "Missing service binding: {$service}";
            } else {
                $result['details'][] = "Service {$service}: bound ✓";
            }
        }
        
        return $result;
    }

    /**
     * Display health check results.
     */
    protected function displayHealthResults(bool $detailed): void
    {
        $this->newLine();
        $this->line('<comment>Health Check Results</comment>');
        $this->line('─────────────────────────────────────────────────────────────');
        
        foreach ($this->healthResults['checks'] as $checkName => $result) {
            $status = $result['status'];
            $statusColor = match ($status) {
                'healthy' => 'green',
                'warning' => 'yellow',
                'error' => 'red',
                default => 'white',
            };
            
            $statusIcon = match ($status) {
                'healthy' => '✓',
                'warning' => '⚠',
                'error' => '✗',
                default => '?',
            };
            
            $this->line("<fg={$statusColor}>{$statusIcon} {$checkName}: " . strtoupper($status) . "</>");
            
            if ($detailed && !empty($result['details'])) {
                foreach ($result['details'] as $detail) {
                    $this->line("  • {$detail}");
                }
            }
        }
        
        // Summary
        $this->newLine();
        $overallStatus = $this->determineOverallStatus();
        $statusColor = match ($overallStatus) {
            'healthy' => 'green',
            'warning' => 'yellow',
            'error' => 'red',
            default => 'white',
        };
        
        $this->line("<fg={$statusColor}>Overall Status: " . strtoupper($overallStatus) . "</>");
        
        // Show warnings and errors
        if (!empty($this->healthResults['warnings'])) {
            $this->newLine();
            $this->line('<fg=yellow>Warnings:</fg=yellow>');
            foreach ($this->healthResults['warnings'] as $warning) {
                $this->line("  ⚠ {$warning}");
            }
        }
        
        if (!empty($this->healthResults['errors'])) {
            $this->newLine();
            $this->line('<fg=red>Errors:</fg=red>');
            foreach ($this->healthResults['errors'] as $error) {
                $this->line("  ✗ {$error}");
            }
        }
    }

    /**
     * Determine overall health status.
     */
    protected function determineOverallStatus(): string
    {
        if (!empty($this->healthResults['errors'])) {
            return 'error';
        }
        
        if (!empty($this->healthResults['warnings'])) {
            return 'warning';
        }
        
        return 'healthy';
    }

    /**
     * Export health check results.
     */
    protected function exportResults(array $formats): void
    {
        foreach ($formats as $format) {
            $filename = 'form-security-health-check-' . date('Y-m-d-H-i-s') . '.' . $format;
            $path = storage_path('app/' . $filename);
            
            match ($format) {
                'json' => File::put($path, json_encode($this->healthResults, JSON_PRETTY_PRINT)),
                'txt' => $this->exportToText($path),
                'html' => $this->exportToHtml($path),
                default => $this->displayWarning("Unknown export format: {$format}"),
            };
            
            if (File::exists($path)) {
                $this->line("Results exported to: {$path}");
            }
        }
    }

    /**
     * Export results to text format.
     */
    protected function exportToText(string $path): void
    {
        $content = "FormSecurity Health Check Report\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $content .= str_repeat('=', 50) . "\n\n";
        
        foreach ($this->healthResults['checks'] as $checkName => $result) {
            $content .= "{$checkName}: " . strtoupper($result['status']) . "\n";
            if (!empty($result['details'])) {
                foreach ($result['details'] as $detail) {
                    $content .= "  • {$detail}\n";
                }
            }
            $content .= "\n";
        }
        
        File::put($path, $content);
    }

    /**
     * Export results to HTML format.
     */
    protected function exportToHtml(string $path): void
    {
        // HTML export implementation would go here
        $content = "<html><body><h1>FormSecurity Health Check Report</h1></body></html>";
        File::put($path, $content);
    }

    /**
     * Check if database table exists.
     */
    protected function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Parse memory limit string to bytes.
     */
    protected function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        
        return match ($last) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
