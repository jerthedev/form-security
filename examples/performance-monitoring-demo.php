<?php

declare(strict_types=1);

/**
 * Performance Monitoring Demonstration Script
 *
 * This script demonstrates the comprehensive performance monitoring
 * and profiling capabilities of the JTD-FormSecurity package.
 */

require_once __DIR__.'/../vendor/autoload.php';

use JTD\FormSecurity\Services\PerformanceMonitoringService;
use JTD\FormSecurity\Services\PerformanceProfiler;

echo "🚀 JTD-FormSecurity Performance Monitoring Demo\n";
echo "================================================\n\n";

// Initialize services
$monitor = new PerformanceMonitoringService;
$profiler = new PerformanceProfiler;

// Start monitoring
echo "📊 Starting Performance Monitoring...\n";
$monitor->startMonitoring();

// Start profiling session
echo "🔍 Starting Profiling Session...\n";
$sessionId = $profiler->startProfiling('demo-session');

// Simulate various operations
echo "⚙️ Performing Sample Operations...\n\n";

// Operation 1: Database simulation
$timerId1 = $monitor->startTimer('database_simulation');
$opId1 = $profiler->startOperation('simulate_database_query', ['table' => 'users']);

echo "  → Simulating database query...\n";
usleep(150000); // 150ms
$monitor->recordMetric('query_execution_time', 150.5, ['query_type' => 'select']);

$profiler->stopOperation($opId1);
$duration1 = $monitor->stopTimer($timerId1);
echo "    ✓ Database query completed in {$duration1}ms\n";

// Operation 2: Cache simulation
$timerId2 = $monitor->startTimer('cache_simulation');
$opId2 = $profiler->startOperation('simulate_cache_operation', ['operation' => 'get']);

echo "  → Simulating cache operations...\n";
usleep(25000); // 25ms
$monitor->recordMetric('cache_operation_time', 25.3, ['cache_type' => 'redis']);

$profiler->stopOperation($opId2);
$duration2 = $monitor->stopTimer($timerId2);
echo "    ✓ Cache operation completed in {$duration2}ms\n";

// Operation 3: Memory intensive task
$timerId3 = $monitor->startTimer('memory_intensive_task');
$opId3 = $profiler->startOperation('memory_allocation_test');

echo "  → Performing memory intensive task...\n";
$monitor->recordMemoryUsage('before_allocation');
$largeArray = array_fill(0, 100000, 'sample_data');
$monitor->recordMemoryUsage('after_allocation');
unset($largeArray);
$monitor->recordMemoryUsage('after_cleanup');

$profiler->stopOperation($opId3);
$duration3 = $monitor->stopTimer($timerId3);
echo "    ✓ Memory task completed in {$duration3}ms\n";

// Add some checkpoints
$profiler->checkpoint('halfway_point', ['operations_completed' => 3]);

// Operation 4: Slow operation simulation
$timerId4 = $monitor->startTimer('slow_operation');
$opId4 = $profiler->startOperation('simulate_slow_operation');

echo "  → Simulating slow operation (should trigger alerts)...\n";
usleep(600000); // 600ms - should trigger threshold alert
$monitor->recordMetric('slow_operation_time', 600.8, ['operation_type' => 'file_processing']);

$profiler->stopOperation($opId4);
$duration4 = $monitor->stopTimer($timerId4);
echo "    ⚠️  Slow operation completed in {$duration4}ms (threshold exceeded)\n";

// Set custom thresholds
echo "\n🎯 Setting Custom Performance Thresholds...\n";
$monitor->setThreshold('custom_metric', 50.0, 'gt');
$monitor->recordMetric('custom_metric', 75.0); // Should trigger alert
$monitor->recordMetric('custom_metric', 25.0); // Should not trigger alert

// Stop profiling and monitoring
echo "\n📈 Generating Performance Results...\n";
$profilingResults = $profiler->stopProfiling($sessionId);
$monitor->stopMonitoring();

// Display results
echo "\n".str_repeat('=', 60)."\n";
echo "📊 PERFORMANCE MONITORING RESULTS\n";
echo str_repeat('=', 60)."\n\n";

// Show monitoring statistics
$statistics = $monitor->getStatistics('1h');
if (! empty($statistics)) {
    echo "📈 Performance Statistics:\n";
    foreach ($statistics as $metric => $stats) {
        echo "  • {$metric}:\n";
        echo "    - Count: {$stats['count']}\n";
        echo '    - Average: '.number_format($stats['avg'], 2)."ms\n";
        echo '    - Max: '.number_format($stats['max'], 2)."ms\n";
        echo '    - P95: '.number_format($stats['p95'], 2)."ms\n";
    }
    echo "\n";
}

// Show profiling results
echo "🔍 Profiling Results:\n";
echo '  • Session Duration: '.number_format($profilingResults['duration'], 2)."ms\n";
echo '  • Memory Allocated: '.formatBytes($profilingResults['memory_usage']['allocated'])."\n";
echo '  • Operations Count: '.count($profilingResults['operations'])."\n";
echo '  • Checkpoints: '.count($profilingResults['checkpoints'])."\n\n";

// Show slowest operations
echo "🐌 Slowest Operations:\n";
$slowestOps = $profiler->getSlowestOperations(5);
foreach ($slowestOps as $index => $operation) {
    $num = $index + 1;
    echo "  {$num}. {$operation['name']}: ".number_format($operation['duration'], 2)."ms\n";
}

// Show threshold violations
echo "\n⚠️  Performance Alerts:\n";
$violations = $monitor->checkThresholds();
if (! empty($violations)) {
    foreach ($violations as $violation) {
        echo "  • {$violation['metric']}: {$violation['value']} {$violation['comparison']} {$violation['threshold']}\n";
    }
} else {
    echo "  ✓ No threshold violations detected\n";
}

// Show recommendations
echo "\n💡 Performance Recommendations:\n";
if (! empty($profilingResults['recommendations'])) {
    foreach ($profilingResults['recommendations'] as $recommendation) {
        $priority = match ($recommendation['priority']) {
            'high' => '🔴',
            'medium' => '🟡',
            'low' => '🟢',
            default => '⚪',
        };
        echo "  {$priority} {$recommendation['message']}\n";
        if (isset($recommendation['details'])) {
            echo "     Details: {$recommendation['details']}\n";
        }
    }
} else {
    echo "  ✓ No specific recommendations at this time\n";
}

// Memory analysis
echo "\n🧠 Memory Analysis:\n";
$memoryAnalysis = $profiler->getMemoryAnalysis();
echo "  • Operations Analyzed: {$memoryAnalysis['operations_count']}\n";
echo '  • Total Memory Allocated: '.formatBytes($memoryAnalysis['total_allocated'])."\n";
echo '  • Peak Memory Allocated: '.formatBytes($memoryAnalysis['peak_allocated'])."\n";
echo '  • Average per Operation: '.formatBytes($memoryAnalysis['average_allocated'])."\n";

if (! empty($memoryAnalysis['memory_intensive_operations'])) {
    echo "  • Memory Intensive Operations:\n";
    foreach ($memoryAnalysis['memory_intensive_operations'] as $op) {
        echo "    - {$op['name']}: {$op['allocated_formatted']}\n";
    }
}

// Export capabilities demonstration
echo "\n📤 Export Capabilities:\n";
echo "  → Exporting profiling data to JSON...\n";
$exportedData = $profiler->exportProfilingData('json');
$exportFile = '/tmp/performance-demo-export.json';
file_put_contents($exportFile, $exportedData);
echo "    ✓ Data exported to: {$exportFile}\n";

echo "\n🎉 Performance Monitoring Demo Completed!\n";
echo "   Check the exported JSON file for detailed results.\n\n";

/**
 * Helper function to format bytes
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision).' '.$units[$i];
}
