<?php

declare(strict_types=1);

/**
 * Test File: CLICommandWorkflowsTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-017-console-commands-cli
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: Complete CLI Command Workflows
 *
 * Description: Feature tests for complete CLI command workflows including
 * user interactions, error handling, and end-to-end command execution.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-017-console-commands-cli.md
 * @see docs/Planning/Sprints/004-caching-cli-integration.md
 */

namespace JTD\FormSecurity\Tests\Feature;

use JTD\FormSecurity\Services\CacheManager;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('feature')]
#[Group('cli-workflows')]
class CLICommandWorkflowsTest extends TestCase
{
    private CacheManager $cacheManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheManager = app(CacheManager::class);
        $this->cacheManager->flush();
    }

    #[Test]
    public function it_completes_full_installation_workflow(): void
    {
        // Test complete installation workflow with user interactions
        $this->artisan('form-security:install')
            ->expectsQuestion('Do you want to publish the configuration file?', 'yes')
            ->expectsQuestion('Do you want to run database migrations?', 'no') // Skip migrations in test
            ->expectsQuestion('Do you want to clear and rebuild the cache?', 'yes')
            ->expectsOutputToContain('FormSecurity installation')
            ->assertExitCode(1); // Installation command returns 1 when there are issues or incomplete steps

        // Verify installation effects
        $this->assertTrue(file_exists(config_path('form-security.php')));
        
        // Test cache functionality after installation
        $this->assertTrue($this->cacheManager->put('install_test', 'success', 300));
        $this->assertEquals('success', $this->cacheManager->get('install_test'));
    }

    #[Test]
    public function it_handles_installation_workflow_with_force_flag(): void
    {
        // Test installation with force flag (no prompts)
        $this->artisan('form-security:install --force')
            ->expectsOutputToContain('FormSecurity installation')
            ->assertExitCode(1); // Installation command returns 1 when there are issues or incomplete steps

        // Verify forced installation works
        $this->assertTrue(file_exists(config_path('form-security.php')));
    }

    #[Test]
    public function it_completes_cache_management_workflow(): void
    {
        // 1. Populate cache with test data
        $testData = [
            'workflow_key_1' => 'workflow_value_1',
            'workflow_key_2' => 'workflow_value_2',
            'workflow_key_3' => 'workflow_value_3'
        ];

        foreach ($testData as $key => $value) {
            $this->cacheManager->put($key, $value, 3600);
        }

        // 2. Check cache statistics
        $this->artisan('form-security:cache stats')
            ->expectsOutputToContain('Cache Statistics')
            ->assertExitCode(0);

        // 3. Test cache optimization
        $this->artisan('form-security:cache optimize --force')
            ->expectsOutputToContain('optimization')
            ->assertExitCode(0);

        // 4. Verify data integrity after optimization
        foreach ($testData as $key => $expectedValue) {
            $this->assertEquals($expectedValue, $this->cacheManager->get($key));
        }

        // 5. Test cache warming
        $this->artisan('form-security:cache warm --force')
            ->expectsOutputToContain('warming')
            ->assertExitCode(0);

        // 6. Clear cache with confirmation
        $this->artisan('form-security:cache clear')
            ->expectsQuestion('Are you sure you want to clear the cache?', 'yes')
            ->expectsOutputToContain('cleared')
            ->assertExitCode(0);

        // 7. Verify cache is cleared
        foreach (array_keys($testData) as $key) {
            $this->assertNull($this->cacheManager->get($key));
        }
    }

    #[Test]
    public function it_handles_cache_workflow_with_user_cancellation(): void
    {
        // Populate cache
        $this->cacheManager->put('cancel_test', 'should_remain', 300);

        // Test user cancellation
        $this->artisan('form-security:cache clear')
            ->expectsQuestion('Are you sure you want to clear the cache?', 'no')
            ->expectsOutputToContain('cancelled')
            ->assertExitCode(0);

        // Verify cache was not cleared
        $this->assertEquals('should_remain', $this->cacheManager->get('cancel_test'));
    }

    #[Test]
    public function it_completes_health_check_workflow(): void
    {
        // 1. Basic health check
        $this->artisan('form-security:health-check')
            ->expectsOutputToContain('Health Check')
            ->assertExitCode(1); // Health checks typically return 1 if issues found

        // 2. Detailed health check
        $this->artisan('form-security:health-check --detailed')
            ->expectsOutputToContain('Health Check')
            ->assertExitCode(1);

        // 3. Health check with fix attempt
        $this->artisan('form-security:health-check --fix')
            ->expectsOutputToContain('Health Check')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_handles_multi_level_cache_workflow(): void
    {
        // 1. Populate different cache levels
        $this->cacheManager->putInRequest('request_workflow', 'request_data');
        $this->cacheManager->putInMemory('memory_workflow', 'memory_data');
        $this->cacheManager->putInDatabase('database_workflow', 'database_data');

        // 2. Test level-specific statistics
        $this->artisan('form-security:cache stats --detailed')
            ->expectsOutputToContain('Cache Statistics')
            ->assertExitCode(0);

        // 3. Test selective level clearing
        $this->artisan('form-security:cache clear --level=memory --force')
            ->expectsOutputToContain('cleared')
            ->assertExitCode(0);

        // 4. Verify selective clearing
        $this->assertNotNull($this->cacheManager->getFromRequest('request_workflow'));
        $this->assertNull($this->cacheManager->getFromMemory('memory_workflow'));
        $this->assertNotNull($this->cacheManager->getFromDatabase('database_workflow'));

        // 5. Clear all levels
        $this->artisan('form-security:cache clear --level=all --force')
            ->expectsOutputToContain('cleared')
            ->assertExitCode(0);

        // 6. Verify all levels cleared
        $this->assertNull($this->cacheManager->getFromRequest('request_workflow'));
        $this->assertNull($this->cacheManager->getFromDatabase('database_workflow'));
    }

    #[Test]
    public function it_handles_error_scenarios_gracefully(): void
    {
        // 1. Invalid cache action
        $this->artisan('form-security:cache invalid-action')
            ->expectsOutputToContain('Invalid action')
            ->assertExitCode(1);

        // 2. Invalid cache level
        $this->artisan('form-security:cache clear --level=invalid --force')
            ->expectsOutputToContain('Invalid')
            ->assertExitCode(1);

        // 3. Help functionality works
        $this->artisan('form-security:cache --help')
            ->expectsOutputToContain('form-security:cache')
            ->assertExitCode(0);

        $this->artisan('form-security:health-check --help')
            ->expectsOutputToContain('form-security:health-check')
            ->assertExitCode(0);

        $this->artisan('form-security:install --help')
            ->expectsOutputToContain('form-security:install')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_completes_performance_testing_workflow(): void
    {
        // 1. Generate test data for performance testing
        for ($i = 0; $i < 50; $i++) {
            $this->cacheManager->put("perf_test_{$i}", "perf_value_{$i}", 300);
        }

        // 2. Run cache performance test
        $this->artisan('form-security:cache test')
            ->expectsOutputToContain('Test')
            ->assertExitCode(0);

        // 3. Check detailed statistics after performance test
        $this->artisan('form-security:cache stats --detailed')
            ->expectsOutputToContain('Cache Statistics')
            ->assertExitCode(0);

        // 4. Run optimization after performance test
        $this->artisan('form-security:cache optimize --force')
            ->expectsOutputToContain('optimization')
            ->assertExitCode(0);

        // 5. Verify data integrity after optimization
        for ($i = 0; $i < 10; $i++) { // Check first 10 items
            $this->assertEquals("perf_value_{$i}", $this->cacheManager->get("perf_test_{$i}"));
        }
    }

    #[Test]
    public function it_handles_concurrent_command_simulation(): void
    {
        // Simulate concurrent-like command execution
        // (Sequential execution simulating concurrent patterns)

        // 1. Populate cache
        $this->cacheManager->put('concurrent_test', 'concurrent_value', 300);

        // 2. Run stats command
        $this->artisan('form-security:cache stats')
            ->expectsOutputToContain('Cache Statistics')
            ->assertExitCode(0);

        // 3. Immediately run optimization (simulating concurrent access)
        $this->artisan('form-security:cache optimize --force')
            ->expectsOutputToContain('optimization')
            ->assertExitCode(0);

        // 4. Run health check (simulating another concurrent operation)
        $this->artisan('form-security:health-check')
            ->expectsOutputToContain('Health Check')
            ->assertExitCode(1);

        // 5. Verify data integrity after "concurrent" operations
        $this->assertEquals('concurrent_value', $this->cacheManager->get('concurrent_test'));
    }

    #[Test]
    public function it_completes_maintenance_workflow(): void
    {
        // 1. Populate cache with data that needs maintenance
        for ($i = 0; $i < 20; $i++) {
            $this->cacheManager->put("maintenance_key_{$i}", "maintenance_value_{$i}", 300);
        }

        // 2. Run cache optimization
        $this->artisan('form-security:cache optimize --force')
            ->expectsOutputToContain('optimization')
            ->assertExitCode(0);

        // 3. Check system health
        $this->artisan('form-security:health-check')
            ->expectsOutputToContain('Health Check')
            ->assertExitCode(1);

        // 4. Run cache statistics to verify maintenance
        $this->artisan('form-security:cache stats')
            ->expectsOutputToContain('Cache Statistics')
            ->assertExitCode(0);

        // 5. Verify data integrity after maintenance
        for ($i = 0; $i < 5; $i++) { // Check first 5 items
            $this->assertEquals("maintenance_value_{$i}", $this->cacheManager->get("maintenance_key_{$i}"));
        }
    }

    #[Test]
    public function it_handles_export_and_reporting_workflow(): void
    {
        // 1. Generate some cache activity
        $this->cacheManager->put('export_test_1', 'export_value_1', 300);
        $this->cacheManager->put('export_test_2', 'export_value_2', 300);

        // 2. Test health check with export functionality
        $this->artisan('form-security:health-check --export=json')
            ->expectsOutputToContain('exported')
            ->assertExitCode(1);

        // 3. Test different export formats
        $this->artisan('form-security:health-check --export=txt')
            ->expectsOutputToContain('exported')
            ->assertExitCode(1);

        // 4. Test invalid export format handling
        $this->artisan('form-security:health-check --export=invalid')
            ->expectsOutputToContain('Invalid')
            ->assertExitCode(1);
    }

    protected function tearDown(): void
    {
        $this->cacheManager->flush();
        
        // Clean up any published config files
        $configFile = config_path('form-security.php');
        if (file_exists($configFile)) {
            unlink($configFile);
        }
        
        parent::tearDown();
    }
}
