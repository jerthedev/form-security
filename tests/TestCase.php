<?php

declare(strict_types=1);

/**
 * Test File: TestCase.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: N/A - Base test case
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1010-service-provider-package-registration
 *
 * Description: Base test case for all JTD FormSecurity package tests.
 * Provides common setup, configuration, and utilities for testing.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1010-service-provider-package-registration.md
 */

namespace JTD\FormSecurity\Tests;

use JTD\FormSecurity\FormSecurityServiceProvider;
use JTD\FormSecurity\Providers\ModelObserverServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for JTD FormSecurity package tests.
 *
 * This class provides common setup and configuration for all tests
 * in the FormSecurity package, including service provider registration
 * and test environment configuration.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Additional test setup can be added here
        $this->setUpDatabase();
        $this->setUpConfiguration();
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FormSecurityServiceProvider::class,
            ModelObserverServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'FormSecurity' => \JTD\FormSecurity\Facades\FormSecurity::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Setup test environment configuration
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup FormSecurity test configuration
        $app['config']->set('form-security.enabled', true);
        $app['config']->set('form-security.spam_threshold', 0.3); // Lower threshold for tests
        $app['config']->set('form-security.features.spam_detection', true);
        $app['config']->set('form-security.features.rate_limiting', true);
        $app['config']->set('form-security.features.caching', false); // Disable caching in tests
        $app['config']->set('form-security.debug.enabled', true);
    }

    /**
     * Setup database for testing.
     */
    protected function setUpDatabase(): void
    {
        // Load migrations for testing
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Setup configuration for testing.
     */
    protected function setUpConfiguration(): void
    {
        // Additional configuration setup can be added here
        config([
            'form-security.rate_limit.max_attempts' => 5,
            'form-security.rate_limit.window_minutes' => 10,
        ]);
    }

    /**
     * Create sample form data for testing.
     *
     * @param  array<string, mixed>  $overrides  Data to override defaults
     * @return array<string, mixed> Sample form data
     */
    protected function createSampleFormData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'This is a legitimate message from a real user.',
            'phone' => '+1234567890',
            '_token' => 'sample-csrf-token',
        ], $overrides);
    }

    /**
     * Create spam form data for testing.
     *
     * @param  array<string, mixed>  $overrides  Data to override defaults
     * @return array<string, mixed> Spam form data
     */
    protected function createSpamFormData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Spam User',
            'email' => 'spam@example.com',
            'message' => 'Buy cheap viagra online! Casino gambling poker! Make money fast! Click here now! Free trial! Guaranteed income! Win big money! Replica watches! Fake designer bags! SEO backlinks! Link building services!',
            'phone' => '1234567890',
            '_token' => 'sample-csrf-token',
        ], $overrides);
    }

    /**
     * Assert that a spam score is within expected range.
     *
     * @param  float  $score  Actual spam score
     * @param  float  $expectedMin  Expected minimum score
     * @param  float  $expectedMax  Expected maximum score
     * @param  string  $message  Custom assertion message
     */
    protected function assertSpamScoreInRange(
        float $score,
        float $expectedMin,
        float $expectedMax,
        string $message = ''
    ): void {
        $this->assertGreaterThanOrEqual(
            $expectedMin,
            $score,
            $message ?: "Spam score {$score} should be >= {$expectedMin}"
        );

        $this->assertLessThanOrEqual(
            $expectedMax,
            $score,
            $message ?: "Spam score {$score} should be <= {$expectedMax}"
        );
    }

    /**
     * Assert that analysis results have required structure.
     *
     * @param  array<string, mixed>  $analysis  Analysis results
     */
    protected function assertValidAnalysisStructure(array $analysis): void
    {
        $this->assertArrayHasKey('valid', $analysis);
        $this->assertArrayHasKey('score', $analysis);
        $this->assertArrayHasKey('threats', $analysis);
        $this->assertArrayHasKey('recommendations', $analysis);
        $this->assertArrayHasKey('processing_time', $analysis);

        $this->assertIsBool($analysis['valid']);
        $this->assertIsFloat($analysis['score']);
        $this->assertIsArray($analysis['threats']);
        $this->assertIsArray($analysis['recommendations']);
        $this->assertIsFloat($analysis['processing_time']);
    }

    /**
     * Get performance benchmark threshold in milliseconds.
     *
     * @return float Maximum allowed processing time in milliseconds
     */
    protected function getPerformanceThreshold(): float
    {
        return 50.0; // 50ms as per project requirements
    }

    /**
     * Assert that processing time meets performance requirements.
     *
     * @param  float  $processingTime  Processing time in seconds
     * @param  string  $operation  Operation name for error message
     */
    protected function assertPerformanceRequirement(float $processingTime, string $operation = 'operation'): void
    {
        $processingTimeMs = $processingTime * 1000;
        $threshold = $this->getPerformanceThreshold();

        $this->assertLessThanOrEqual(
            $threshold,
            $processingTimeMs,
            "Performance requirement failed: {$operation} took {$processingTimeMs}ms, should be <= {$threshold}ms"
        );
    }

    /**
     * Assert that service provider bootstrap time meets requirements.
     * Service providers have more lenient performance requirements due to initialization overhead.
     *
     * @param  float  $processingTime  Processing time in seconds
     */
    protected function assertServiceProviderPerformance(float $processingTime): void
    {
        $processingTimeMs = $processingTime * 1000;
        $threshold = 250.0; // 250ms for service provider bootstrap

        $this->assertLessThanOrEqual(
            $threshold,
            $processingTimeMs,
            "Service provider performance requirement failed: bootstrap took {$processingTimeMs}ms, should be <= {$threshold}ms"
        );
    }

    /**
     * Assert that security analysis time meets requirements.
     * Analysis operations have more lenient requirements due to complex validation logic.
     *
     * @param  float  $processingTime  Processing time in seconds
     */
    protected function assertAnalysisPerformance(float $processingTime): void
    {
        $processingTimeMs = $processingTime * 1000;
        $threshold = 500.0; // 500ms for security analysis operations (includes database queries)

        $this->assertLessThanOrEqual(
            $threshold,
            $processingTimeMs,
            "Analysis performance requirement failed: operation took {$processingTimeMs}ms, should be <= {$threshold}ms"
        );
    }
}
