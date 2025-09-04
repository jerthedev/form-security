#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Code Quality Metrics Analysis Script
 *
 * Analyzes codebase for various quality metrics including:
 * - Code coverage
 * - Static analysis results
 * - Code complexity
 * - Documentation coverage
 * - Performance metrics
 */

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Process\Process;

class QualityMetricsAnalyzer
{
    private array $metrics = [];

    private string $projectRoot;

    public function __construct()
    {
        $this->projectRoot = dirname(__DIR__);
    }

    public function analyze(): void
    {
        echo "🔍 Analyzing Code Quality Metrics for JTD-FormSecurity\n";
        echo str_repeat('=', 60)."\n\n";

        $this->analyzeCodebase();
        $this->analyzeStaticAnalysis();
        $this->analyzeTestCoverage();
        $this->analyzeComplexity();
        $this->analyzeDocumentation();

        $this->generateReport();
    }

    private function analyzeCodebase(): void
    {
        echo "📊 Analyzing Codebase Structure...\n";

        $srcPath = $this->projectRoot.'/src';
        $testPath = $this->projectRoot.'/tests';

        $this->metrics['codebase'] = [
            'source_files' => $this->countFiles($srcPath, '*.php'),
            'test_files' => $this->countFiles($testPath, '*.php'),
            'source_lines' => $this->countLines($srcPath),
            'test_lines' => $this->countLines($testPath),
            'classes' => $this->countClasses($srcPath),
            'interfaces' => $this->countInterfaces($srcPath),
            'traits' => $this->countTraits($srcPath),
        ];

        $testToCodeRatio = $this->metrics['codebase']['test_lines'] / $this->metrics['codebase']['source_lines'];
        $this->metrics['codebase']['test_to_code_ratio'] = round($testToCodeRatio, 2);

        echo "   ✓ Source files: {$this->metrics['codebase']['source_files']}\n";
        echo "   ✓ Test files: {$this->metrics['codebase']['test_files']}\n";
        echo "   ✓ Test-to-code ratio: {$this->metrics['codebase']['test_to_code_ratio']}:1\n\n";
    }

    private function analyzeStaticAnalysis(): void
    {
        echo "🔍 Running Static Analysis...\n";

        // PHPStan Analysis
        $phpstanProcess = new Process(['vendor/bin/phpstan', 'analyse', '--no-progress', '--error-format=json']);
        $phpstanProcess->setWorkingDirectory($this->projectRoot);
        $phpstanProcess->setTimeout(120);

        try {
            $phpstanProcess->run();
            $phpstanResult = json_decode($phpstanProcess->getOutput(), true);

            $this->metrics['static_analysis']['phpstan'] = [
                'errors' => count($phpstanResult['files'] ?? []),
                'status' => $phpstanProcess->getExitCode() === 0 ? 'passed' : 'failed',
            ];
        } catch (\Exception $e) {
            $this->metrics['static_analysis']['phpstan'] = [
                'errors' => 'unknown',
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        echo "   ✓ PHPStan: {$this->metrics['static_analysis']['phpstan']['status']}\n";

        // Psalm Analysis (if available)
        if (file_exists($this->projectRoot.'/vendor/bin/psalm')) {
            $psalmProcess = new Process(['vendor/bin/psalm', '--output-format=json']);
            $psalmProcess->setWorkingDirectory($this->projectRoot);
            $psalmProcess->setTimeout(120);

            try {
                $psalmProcess->run();
                $psalmOutput = $psalmProcess->getOutput();
                $psalmResult = json_decode($psalmOutput, true);

                $this->metrics['static_analysis']['psalm'] = [
                    'errors' => count($psalmResult ?? []),
                    'status' => $psalmProcess->getExitCode() === 0 ? 'passed' : 'failed',
                ];

                echo "   ✓ Psalm: {$this->metrics['static_analysis']['psalm']['status']}\n";
            } catch (\Exception $e) {
                $this->metrics['static_analysis']['psalm'] = [
                    'status' => 'not_available',
                ];
                echo "   ⚠ Psalm: not available\n";
            }
        }

        echo "\n";
    }

    private function analyzeTestCoverage(): void
    {
        echo "📈 Analyzing Test Coverage...\n";

        $coverageProcess = new Process([
            'vendor/bin/phpunit',
            '--coverage-clover',
            'coverage.xml',
            '--log-junit',
            'junit.xml',
        ]);
        $coverageProcess->setWorkingDirectory($this->projectRoot);
        $coverageProcess->setTimeout(300);

        try {
            $coverageProcess->run();

            // Parse coverage XML if it exists
            $coverageFile = $this->projectRoot.'/coverage.xml';
            if (file_exists($coverageFile)) {
                $coverage = simplexml_load_file($coverageFile);
                $metrics = $coverage->project->metrics[0] ?? null;

                if ($metrics) {
                    $this->metrics['coverage'] = [
                        'lines_covered' => (int) $metrics['coveredstatements'],
                        'lines_total' => (int) $metrics['statements'],
                        'coverage_percentage' => round(
                            ((int) $metrics['coveredstatements'] / (int) $metrics['statements']) * 100,
                            2
                        ),
                        'methods_covered' => (int) $metrics['coveredmethods'],
                        'methods_total' => (int) $metrics['methods'],
                        'classes_covered' => (int) $metrics['coveredclasses'],
                        'classes_total' => (int) $metrics['classes'],
                    ];
                }
            }

            // Parse JUnit XML for test results
            $junitFile = $this->projectRoot.'/junit.xml';
            if (file_exists($junitFile)) {
                $junit = simplexml_load_file($junitFile);
                $testsuite = $junit->testsuite[0] ?? null;

                if ($testsuite) {
                    $this->metrics['tests'] = [
                        'total' => (int) $testsuite['tests'],
                        'failures' => (int) $testsuite['failures'],
                        'errors' => (int) $testsuite['errors'],
                        'time' => (float) $testsuite['time'],
                    ];
                }
            }

        } catch (\Exception $e) {
            $this->metrics['coverage'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        if (isset($this->metrics['coverage']['coverage_percentage'])) {
            echo "   ✓ Line coverage: {$this->metrics['coverage']['coverage_percentage']}%\n";
            echo "   ✓ Methods covered: {$this->metrics['coverage']['methods_covered']}/{$this->metrics['coverage']['methods_total']}\n";
            echo "   ✓ Classes covered: {$this->metrics['coverage']['classes_covered']}/{$this->metrics['coverage']['classes_total']}\n";
        }

        if (isset($this->metrics['tests']['total'])) {
            echo "   ✓ Tests: {$this->metrics['tests']['total']} total, {$this->metrics['tests']['failures']} failures\n";
        }

        echo "\n";
    }

    private function analyzeComplexity(): void
    {
        echo "🧮 Analyzing Code Complexity...\n";

        // Use basic complexity analysis by counting method parameters, conditionals, etc.
        $complexity = $this->calculateComplexity($this->projectRoot.'/src');

        $this->metrics['complexity'] = $complexity;

        echo "   ✓ Average cyclomatic complexity: {$complexity['average']}\n";
        echo "   ✓ Highest complexity: {$complexity['max']}\n";
        echo "   ✓ Methods analyzed: {$complexity['methods_count']}\n\n";
    }

    private function analyzeDocumentation(): void
    {
        echo "📚 Analyzing Documentation Coverage...\n";

        $docCoverage = $this->calculateDocumentationCoverage($this->projectRoot.'/src');

        $this->metrics['documentation'] = $docCoverage;

        echo "   ✓ Methods documented: {$docCoverage['documented']}/{$docCoverage['total']}\n";
        echo "   ✓ Documentation coverage: {$docCoverage['percentage']}%\n";
        echo "   ✓ Classes documented: {$docCoverage['classes_documented']}/{$docCoverage['classes_total']}\n\n";
    }

    private function generateReport(): void
    {
        echo str_repeat('=', 60)."\n";
        echo "📊 QUALITY METRICS SUMMARY\n";
        echo str_repeat('=', 60)."\n\n";

        // Overall Quality Score
        $qualityScore = $this->calculateQualityScore();
        echo "🏆 Overall Quality Score: {$qualityScore}/100\n\n";

        // Detailed Metrics
        $this->displayCodebaseMetrics();
        $this->displayQualityGates();
        $this->displayRecommendations();

        // Save metrics to file
        $this->saveMetricsToFile();
    }

    private function calculateQualityScore(): int
    {
        $score = 0;

        // Coverage (30 points)
        if (isset($this->metrics['coverage']['coverage_percentage'])) {
            $score += min(30, ($this->metrics['coverage']['coverage_percentage'] / 100) * 30);
        }

        // Static Analysis (25 points)
        if ($this->metrics['static_analysis']['phpstan']['status'] === 'passed') {
            $score += 25;
        }

        // Documentation (20 points)
        if (isset($this->metrics['documentation']['percentage'])) {
            $score += min(20, ($this->metrics['documentation']['percentage'] / 100) * 20);
        }

        // Test Quality (15 points)
        if (isset($this->metrics['codebase']['test_to_code_ratio'])) {
            $score += min(15, $this->metrics['codebase']['test_to_code_ratio'] * 10);
        }

        // Complexity (10 points)
        if (isset($this->metrics['complexity']['average']) && $this->metrics['complexity']['average'] <= 10) {
            $score += 10;
        }

        return (int) round($score);
    }

    private function displayCodebaseMetrics(): void
    {
        echo "📈 Codebase Metrics:\n";
        echo "   • Source Files: {$this->metrics['codebase']['source_files']}\n";
        echo "   • Test Files: {$this->metrics['codebase']['test_files']}\n";
        echo "   • Classes: {$this->metrics['codebase']['classes']}\n";
        echo "   • Interfaces: {$this->metrics['codebase']['interfaces']}\n";
        echo "   • Traits: {$this->metrics['codebase']['traits']}\n\n";
    }

    private function displayQualityGates(): void
    {
        echo "✅ Quality Gates:\n";

        $gates = [
            'Code Coverage ≥ 90%' => isset($this->metrics['coverage']['coverage_percentage']) ?
                $this->metrics['coverage']['coverage_percentage'] >= 90 : false,
            'PHPStan Level Max' => $this->metrics['static_analysis']['phpstan']['status'] === 'passed',
            'Documentation ≥ 80%' => isset($this->metrics['documentation']['percentage']) ?
                $this->metrics['documentation']['percentage'] >= 80 : false,
            'Complexity ≤ 10' => isset($this->metrics['complexity']['average']) ?
                $this->metrics['complexity']['average'] <= 10 : false,
        ];

        foreach ($gates as $gate => $passed) {
            $status = $passed ? '✅' : '❌';
            echo "   {$status} {$gate}\n";
        }

        echo "\n";
    }

    private function displayRecommendations(): void
    {
        echo "💡 Recommendations:\n";

        $recommendations = [];

        if (isset($this->metrics['coverage']['coverage_percentage']) && $this->metrics['coverage']['coverage_percentage'] < 90) {
            $recommendations[] = "Increase test coverage to 90%+ (currently {$this->metrics['coverage']['coverage_percentage']}%)";
        }

        if ($this->metrics['static_analysis']['phpstan']['status'] !== 'passed') {
            $recommendations[] = 'Fix PHPStan errors to achieve zero-error status';
        }

        if (isset($this->metrics['documentation']['percentage']) && $this->metrics['documentation']['percentage'] < 80) {
            $recommendations[] = "Improve documentation coverage to 80%+ (currently {$this->metrics['documentation']['percentage']}%)";
        }

        if (isset($this->metrics['complexity']['average']) && $this->metrics['complexity']['average'] > 10) {
            $recommendations[] = "Reduce code complexity (average: {$this->metrics['complexity']['average']})";
        }

        if (empty($recommendations)) {
            echo "   🎉 All quality targets met! Keep up the excellent work.\n";
        } else {
            foreach ($recommendations as $recommendation) {
                echo "   • {$recommendation}\n";
            }
        }

        echo "\n";
    }

    private function saveMetricsToFile(): void
    {
        $metricsFile = $this->projectRoot.'/quality-metrics.json';
        $this->metrics['generated_at'] = date('Y-m-d H:i:s');

        file_put_contents($metricsFile, json_encode($this->metrics, JSON_PRETTY_PRINT));
        echo "💾 Metrics saved to: quality-metrics.json\n";
    }

    // Helper methods
    private function countFiles(string $directory, string $pattern): int
    {
        if (! is_dir($directory)) {
            return 0;
        }

        $files = glob($directory.'/**/'.$pattern, GLOB_BRACE);

        return count($files ?: []);
    }

    private function countLines(string $directory): int
    {
        if (! is_dir($directory)) {
            return 0;
        }

        $files = glob($directory.'/**/*.php', GLOB_BRACE);
        $totalLines = 0;

        foreach ($files ?: [] as $file) {
            $content = file_get_contents($file);
            $lines = substr_count($content, "\n") + 1;
            $totalLines += $lines;
        }

        return $totalLines;
    }

    private function countClasses(string $directory): int
    {
        return $this->countPatternInFiles($directory, '/^class\s+\w+/m');
    }

    private function countInterfaces(string $directory): int
    {
        return $this->countPatternInFiles($directory, '/^interface\s+\w+/m');
    }

    private function countTraits(string $directory): int
    {
        return $this->countPatternInFiles($directory, '/^trait\s+\w+/m');
    }

    private function countPatternInFiles(string $directory, string $pattern): int
    {
        if (! is_dir($directory)) {
            return 0;
        }

        $files = glob($directory.'/**/*.php', GLOB_BRACE);
        $count = 0;

        foreach ($files ?: [] as $file) {
            $content = file_get_contents($file);
            preg_match_all($pattern, $content, $matches);
            $count += count($matches[0]);
        }

        return $count;
    }

    private function calculateComplexity(string $directory): array
    {
        $files = glob($directory.'/**/*.php', GLOB_BRACE);
        $complexities = [];
        $methodCount = 0;

        foreach ($files ?: [] as $file) {
            $content = file_get_contents($file);

            // Simple complexity calculation based on control structures
            preg_match_all('/function\s+\w+\s*\([^)]*\)\s*[^{]*{[^}]*}(?:[^}]*{[^}]*})*/s', $content, $methods);

            foreach ($methods[0] as $method) {
                $complexity = 1; // Base complexity

                // Count decision points
                $complexity += substr_count($method, 'if ');
                $complexity += substr_count($method, 'elseif ');
                $complexity += substr_count($method, 'else ');
                $complexity += substr_count($method, 'while ');
                $complexity += substr_count($method, 'for ');
                $complexity += substr_count($method, 'foreach ');
                $complexity += substr_count($method, 'switch ');
                $complexity += substr_count($method, 'case ');
                $complexity += substr_count($method, '?');
                $complexity += substr_count($method, '&&');
                $complexity += substr_count($method, '||');

                $complexities[] = $complexity;
                $methodCount++;
            }
        }

        return [
            'average' => empty($complexities) ? 0 : round(array_sum($complexities) / count($complexities), 1),
            'max' => empty($complexities) ? 0 : max($complexities),
            'methods_count' => $methodCount,
            'complexities' => $complexities,
        ];
    }

    private function calculateDocumentationCoverage(string $directory): array
    {
        $files = glob($directory.'/**/*.php', GLOB_BRACE);
        $totalMethods = 0;
        $documentedMethods = 0;
        $totalClasses = 0;
        $documentedClasses = 0;

        foreach ($files ?: [] as $file) {
            $content = file_get_contents($file);

            // Count methods
            preg_match_all('/public\s+function\s+\w+|protected\s+function\s+\w+/', $content, $methods);
            $methodCount = count($methods[0]);
            $totalMethods += $methodCount;

            // Count documented methods (preceded by /** comment)
            preg_match_all('/\/\*\*[\s\S]*?\*\/\s*(?:public|protected)\s+function/', $content, $docMethods);
            $documentedMethods += count($docMethods[0]);

            // Count classes
            preg_match_all('/^class\s+\w+/m', $content, $classes);
            $classCount = count($classes[0]);
            $totalClasses += $classCount;

            // Count documented classes
            preg_match_all('/\/\*\*[\s\S]*?\*\/\s*(?:abstract\s+)?class\s+\w+/', $content, $docClasses);
            $documentedClasses += count($docClasses[0]);
        }

        return [
            'total' => $totalMethods,
            'documented' => $documentedMethods,
            'percentage' => $totalMethods > 0 ? round(($documentedMethods / $totalMethods) * 100, 1) : 0,
            'classes_total' => $totalClasses,
            'classes_documented' => $documentedClasses,
            'classes_percentage' => $totalClasses > 0 ? round(($documentedClasses / $totalClasses) * 100, 1) : 0,
        ];
    }
}

// Run the analyzer
$analyzer = new QualityMetricsAnalyzer;
$analyzer->analyze();
