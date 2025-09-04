<?php

declare(strict_types=1);

/**
 * Command File: PerformanceReportCommand.php
 *
 * EPIC: EPIC-006-analytics-monitoring
 * SPEC: SPEC-013-performance-monitoring-profiling
 * SPRINT: Sprint-005-performance-monitoring
 * TICKET: 2052-performance-reporting-cli
 *
 * Description: CLI command for generating comprehensive performance reports
 * with detailed metrics, analysis, and optimization recommendations.
 *
 * @see docs/Planning/Epics/EPIC-006-analytics-monitoring.md
 * @see docs/Planning/Specs/Analytics-Monitoring/SPEC-013-performance-monitoring-profiling.md
 */

namespace JTD\FormSecurity\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JTD\FormSecurity\Contracts\PerformanceMonitorInterface;
use JTD\FormSecurity\Contracts\PerformanceProfilerInterface;

/**
 * Performance Report Command
 *
 * Generates comprehensive performance reports including system metrics,
 * profiling data, slow queries analysis, and optimization recommendations.
 */
class PerformanceReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'form-security:performance:report 
                            {--period=1h : Time period for report (1m, 5m, 15m, 30m, 1h, 6h, 12h, 24h)}
                            {--format=table : Output format (table, json, html, export)}
                            {--output= : Output file path for export formats}
                            {--details : Include detailed metrics in report}
                            {--profile : Include profiling analysis}
                            {--slow-queries=10 : Number of slow queries to include}
                            {--memory-analysis : Include memory usage analysis}
                            {--recommendations : Include optimization recommendations}
                            {--alerts : Include recent performance alerts}';

    /**
     * The console command description.
     */
    protected $description = 'Generate comprehensive performance reports with metrics analysis';

    /**
     * Performance monitor service
     */
    private PerformanceMonitorInterface $monitor;

    /**
     * Performance profiler service
     */
    private PerformanceProfilerInterface $profiler;

    /**
     * Create a new command instance.
     */
    public function __construct(
        PerformanceMonitorInterface $monitor,
        PerformanceProfilerInterface $profiler
    ) {
        parent::__construct();
        $this->monitor = $monitor;
        $this->profiler = $profiler;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Generating Performance Report...');

        try {
            $reportData = $this->generateReportData();
            $this->displayReport($reportData);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error generating performance report: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Generate comprehensive report data
     */
    private function generateReportData(): array
    {
        $period = $this->option('period');
        $includeDetails = $this->option('details');
        $includeProfile = $this->option('profile');
        $slowQueriesLimit = (int) $this->option('slow-queries');
        $includeMemoryAnalysis = $this->option('memory-analysis');
        $includeRecommendations = $this->option('recommendations');
        $includeAlerts = $this->option('alerts');

        $this->info("📊 Collecting performance data for period: {$period}");

        $report = [
            'meta' => [
                'generated_at' => now()->toDateTimeString(),
                'period' => $period,
                'options' => [
                    'details' => $includeDetails,
                    'profile' => $includeProfile,
                    'slow_queries_limit' => $slowQueriesLimit,
                    'memory_analysis' => $includeMemoryAnalysis,
                    'recommendations' => $includeRecommendations,
                    'alerts' => $includeAlerts,
                ],
            ],
        ];

        // System information
        $this->line('  → Collecting system information...');
        $report['system'] = $this->getSystemInfo();

        // Performance statistics
        $this->line('  → Analyzing performance statistics...');
        $report['statistics'] = $this->monitor->getStatistics($period);

        // Slow queries analysis
        if ($slowQueriesLimit > 0) {
            $this->line('  → Analyzing slow queries...');
            $report['slow_queries'] = $this->getSlowQueriesAnalysis($slowQueriesLimit);
        }

        // Memory analysis
        if ($includeMemoryAnalysis) {
            $this->line('  → Analyzing memory usage...');
            $report['memory_analysis'] = $this->getMemoryAnalysis();
        }

        // Profiling data
        if ($includeProfile) {
            $this->line('  → Collecting profiling data...');
            $report['profiling'] = $this->getProfilingAnalysis();
        }

        // Performance alerts
        if ($includeAlerts) {
            $this->line('  → Checking performance alerts...');
            $report['alerts'] = $this->getAlertsAnalysis();
        }

        // Optimization recommendations
        if ($includeRecommendations) {
            $this->line('  → Generating recommendations...');
            $report['recommendations'] = $this->getOptimizationRecommendations();
        }

        // Detailed metrics
        if ($includeDetails) {
            $this->line('  → Including detailed metrics...');
            $report['detailed_metrics'] = $this->monitor->getMetrics();
        }

        return $report;
    }

    /**
     * Display the report based on format option
     */
    private function displayReport(array $reportData): void
    {
        $format = $this->option('format');
        $outputFile = $this->option('output');

        switch ($format) {
            case 'json':
                $this->displayJsonReport($reportData, $outputFile);
                break;
            case 'html':
                $this->displayHtmlReport($reportData, $outputFile);
                break;
            case 'export':
                $this->exportReport($reportData, $outputFile);
                break;
            case 'table':
            default:
                $this->displayTableReport($reportData);
                break;
        }
    }

    /**
     * Display report in table format
     */
    private function displayTableReport(array $data): void
    {
        $this->info("\n📈 Performance Report - {$data['meta']['generated_at']}");
        $this->info("Period: {$data['meta']['period']}");

        // System Information
        if (isset($data['system'])) {
            $this->displaySystemTable($data['system']);
        }

        // Performance Statistics
        if (isset($data['statistics']) && ! empty($data['statistics'])) {
            $this->displayStatisticsTable($data['statistics']);
        }

        // Slow Queries
        if (isset($data['slow_queries']) && ! empty($data['slow_queries'])) {
            $this->displaySlowQueriesTable($data['slow_queries']);
        }

        // Memory Analysis
        if (isset($data['memory_analysis'])) {
            $this->displayMemoryAnalysisTable($data['memory_analysis']);
        }

        // Profiling Analysis
        if (isset($data['profiling'])) {
            $this->displayProfilingTable($data['profiling']);
        }

        // Alerts
        if (isset($data['alerts']) && ! empty($data['alerts'])) {
            $this->displayAlertsTable($data['alerts']);
        }

        // Recommendations
        if (isset($data['recommendations']) && ! empty($data['recommendations'])) {
            $this->displayRecommendationsTable($data['recommendations']);
        }
    }

    /**
     * Display system information table
     */
    private function displaySystemTable(array $system): void
    {
        $this->line("\n🖥️  System Information");
        $this->table(
            ['Metric', 'Value'],
            [
                ['PHP Version', $system['php_version'] ?? 'N/A'],
                ['Laravel Version', $system['laravel_version'] ?? 'N/A'],
                ['Memory Limit', $system['memory_limit'] ?? 'N/A'],
                ['Max Execution Time', $system['max_execution_time'] ?? 'N/A'],
                ['Server Time', $system['server_time'] ?? 'N/A'],
                ['Timezone', $system['timezone'] ?? 'N/A'],
            ]
        );
    }

    /**
     * Display performance statistics table
     */
    private function displayStatisticsTable(array $stats): void
    {
        $this->line("\n📊 Performance Statistics");

        $tableData = [];
        foreach ($stats as $metric => $data) {
            $tableData[] = [
                'Metric' => $metric,
                'Count' => $data['count'] ?? 0,
                'Avg' => number_format($data['avg'] ?? 0, 2),
                'Min' => number_format($data['min'] ?? 0, 2),
                'Max' => number_format($data['max'] ?? 0, 2),
                'P95' => number_format($data['p95'] ?? 0, 2),
            ];
        }

        if (! empty($tableData)) {
            $this->table(
                ['Metric', 'Count', 'Avg', 'Min', 'Max', 'P95'],
                $tableData
            );
        } else {
            $this->warn('No performance statistics available for the specified period.');
        }
    }

    /**
     * Display slow queries table
     */
    private function displaySlowQueriesTable(array $queries): void
    {
        $this->line("\n🐌 Slow Queries Analysis");

        $tableData = [];
        foreach ($queries as $hash => $query) {
            $tableData[] = [
                'Query (truncated)' => Str::limit($query['sql'] ?? 'N/A', 50),
                'Count' => $query['count'] ?? 0,
                'Avg Time (ms)' => number_format($query['avg_time'] ?? 0, 2),
                'Max Time (ms)' => number_format($query['max_time'] ?? 0, 2),
                'Last Seen' => $query['last_seen'] ?? 'N/A',
            ];
        }

        if (! empty($tableData)) {
            $this->table(
                ['Query (truncated)', 'Count', 'Avg Time (ms)', 'Max Time (ms)', 'Last Seen'],
                $tableData
            );
        } else {
            $this->info('No slow queries found for the specified period.');
        }
    }

    /**
     * Display memory analysis table
     */
    private function displayMemoryAnalysisTable(array $analysis): void
    {
        $this->line("\n🧠 Memory Usage Analysis");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Current Usage', $analysis['current_formatted'] ?? 'N/A'],
                ['Peak Usage', $analysis['peak_formatted'] ?? 'N/A'],
                ['Memory Limit', $analysis['limit'] ?? 'N/A'],
                ['Usage %', $this->calculateMemoryUsagePercentage($analysis)],
            ]
        );
    }

    /**
     * Display profiling table
     */
    private function displayProfilingTable(array $profiling): void
    {
        $this->line("\n🔍 Profiling Analysis");

        if (isset($profiling['slowest_operations']) && ! empty($profiling['slowest_operations'])) {
            $this->line('Top Slowest Operations:');
            $tableData = [];
            foreach ($profiling['slowest_operations'] as $operation) {
                $tableData[] = [
                    'Operation' => $operation['name'] ?? 'Unknown',
                    'Duration (ms)' => number_format($operation['duration'] ?? 0, 2),
                    'Memory Allocated' => $this->formatBytes($operation['memory_usage']['allocated'] ?? 0),
                    'Session ID' => Str::limit($operation['session_id'] ?? 'N/A', 8),
                ];
            }

            $this->table(
                ['Operation', 'Duration (ms)', 'Memory Allocated', 'Session ID'],
                $tableData
            );
        }
    }

    /**
     * Display alerts table
     */
    private function displayAlertsTable(array $alerts): void
    {
        $this->line("\n⚠️  Performance Alerts");

        $tableData = [];
        foreach ($alerts as $alert) {
            $tableData[] = [
                'Metric' => $alert['metric'] ?? 'Unknown',
                'Value' => number_format($alert['value'] ?? 0, 2),
                'Threshold' => number_format($alert['threshold'] ?? 0, 2),
                'Comparison' => $alert['comparison'] ?? 'N/A',
                'Time' => $alert['datetime'] ?? 'N/A',
            ];
        }

        if (! empty($tableData)) {
            $this->table(
                ['Metric', 'Value', 'Threshold', 'Comparison', 'Time'],
                $tableData
            );
        } else {
            $this->info('No performance alerts found for the specified period.');
        }
    }

    /**
     * Display recommendations table
     */
    private function displayRecommendationsTable(array $recommendations): void
    {
        $this->line("\n💡 Optimization Recommendations");

        foreach ($recommendations as $index => $recommendation) {
            $priority = $recommendation['priority'] ?? 'medium';
            $symbol = match ($priority) {
                'high' => '🔴',
                'medium' => '🟡',
                'low' => '🟢',
                default => '⚪',
            };

            $this->line("{$symbol} {$recommendation['message']}");
            if (isset($recommendation['details'])) {
                $this->line("   Details: {$recommendation['details']}");
            }
        }
    }

    /**
     * Display JSON report
     */
    private function displayJsonReport(array $data, ?string $outputFile): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);

        if ($outputFile) {
            File::put($outputFile, $json);
            $this->info("JSON report exported to: {$outputFile}");
        } else {
            $this->line($json);
        }
    }

    /**
     * Display HTML report
     */
    private function displayHtmlReport(array $data, ?string $outputFile): void
    {
        $html = $this->generateHtmlReport($data);

        if ($outputFile) {
            File::put($outputFile, $html);
            $this->info("HTML report exported to: {$outputFile}");
        } else {
            $this->line($html);
        }
    }

    /**
     * Export report to file
     */
    private function exportReport(array $data, ?string $outputFile): void
    {
        $outputFile = $outputFile ?? storage_path('logs/performance-report-'.date('Y-m-d-H-i-s').'.json');

        File::put($outputFile, json_encode($data, JSON_PRETTY_PRINT));
        $this->info("Performance report exported to: {$outputFile}");
    }

    /**
     * Get system information
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'server_time' => now()->toDateTimeString(),
            'timezone' => config('app.timezone'),
        ];
    }

    /**
     * Get slow queries analysis
     */
    private function getSlowQueriesAnalysis(int $limit): array
    {
        // This would typically get data from the PerformanceMonitoringService
        // For now, we'll return a placeholder
        return []; // $this->monitor->getTopSlowQueries($limit);
    }

    /**
     * Get memory analysis
     */
    private function getMemoryAnalysis(): array
    {
        return [
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'current_formatted' => $this->formatBytes(memory_get_usage(true)),
            'peak_formatted' => $this->formatBytes(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * Get profiling analysis
     */
    private function getProfilingAnalysis(): array
    {
        return [
            'slowest_operations' => $this->profiler->getSlowestOperations(10),
            'memory_analysis' => $this->profiler->getMemoryAnalysis(),
        ];
    }

    /**
     * Get alerts analysis
     */
    private function getAlertsAnalysis(): array
    {
        return $this->monitor->checkThresholds();
    }

    /**
     * Get optimization recommendations
     */
    private function getOptimizationRecommendations(): array
    {
        // This would typically analyze the collected data and generate recommendations
        $recommendations = [];

        // Memory usage recommendations
        $memoryUsage = memory_get_usage(true);
        if ($memoryUsage > 50 * 1024 * 1024) { // 50MB
            $recommendations[] = [
                'type' => 'memory',
                'priority' => 'high',
                'message' => 'High memory usage detected. Consider memory optimization.',
                'details' => 'Current usage: '.$this->formatBytes($memoryUsage),
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate memory usage percentage
     */
    private function calculateMemoryUsagePercentage(array $analysis): string
    {
        $limit = $analysis['limit'] ?? '128M';
        $current = $analysis['current_usage'] ?? 0;

        // Convert memory limit to bytes
        $limitBytes = $this->convertToBytes($limit);

        if ($limitBytes > 0) {
            $percentage = ($current / $limitBytes) * 100;

            return number_format($percentage, 1).'%';
        }

        return 'N/A';
    }

    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes(string $limit): int
    {
        $limit = trim($limit);
        $value = (int) $limit;
        $unit = strtoupper(substr($limit, -1));

        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Format bytes into human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * Generate HTML report
     */
    private function generateHtmlReport(array $data): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>Performance Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .section { margin-bottom: 30px; }
        .metric-card { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; }
        .alert-high { color: #dc3545; }
        .alert-medium { color: #ffc107; }
        .alert-low { color: #28a745; }
    </style>
</head>
<body>';

        $html .= '<div class="header">';
        $html .= '<h1>Performance Report</h1>';
        $html .= '<p>Generated: '.($data['meta']['generated_at'] ?? 'Unknown').'</p>';
        $html .= '<p>Period: '.($data['meta']['period'] ?? 'Unknown').'</p>';
        $html .= '</div>';

        // Add sections based on available data
        foreach ($data as $section => $sectionData) {
            if ($section === 'meta' || empty($sectionData)) {
                continue;
            }

            $html .= '<div class="section">';
            $html .= '<h2>'.ucfirst(str_replace('_', ' ', $section)).'</h2>';
            $html .= '<div class="metric-card">';
            $html .= '<pre>'.json_encode($sectionData, JSON_PRETTY_PRINT).'</pre>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</body></html>';

        return $html;
    }
}
