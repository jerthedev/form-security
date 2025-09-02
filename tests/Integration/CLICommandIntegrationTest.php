<?php

declare(strict_types=1);

/**
 * Test File: CLICommandIntegrationTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-017-console-commands-cli
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1025-integration-tests
 *
 * Description: Integration tests for CLI commands interacting with caching
 * and database systems to validate end-to-end command functionality.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-017-console-commands-cli.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1025-integration-tests.md
 */

namespace JTD\FormSecurity\Tests\Integration;

use JTD\FormSecurity\Services\CacheManager;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('ticket-1025')]
#[Group('integration')]
#[Group('cli-commands')]
class CLICommandIntegrationTest extends TestCase
{
    private CacheManager $cacheManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheManager = app(CacheManager::class);
        $this->cacheManager->flush(); // Start with clean cache
    }

    #[Test]
    public function it_integrates_cache_command_with_cache_system(): void
    {
        // 1. Populate cache with test data
        $testData = [
            'user:123' => ['name' => 'John Doe', 'email' => 'john@example.com'],
            'product:456' => ['name' => 'Test Product', 'price' => 99.99],
            'session:abc' => ['user_id' => 123, 'expires' => time() + 3600]
        ];

        foreach ($testData as $key => $value) {
            $this->assertTrue($this->cacheManager->put($key, $value, 3600));
        }

        // 2. Verify data is cached
        foreach ($testData as $key => $expectedValue) {
            $this->assertEquals($expectedValue, $this->cacheManager->get($key));
        }

        // 3. Test cache stats command
        $this->artisan('form-security:cache stats')
            ->expectsOutputToContain('Cache Statistics')
            ->expectsOutputToContain('Hit Ratio')
            ->assertExitCode(0);

        // 4. Test cache clear command
        $this->artisan('form-security:cache clear --force')
            ->expectsOutputToContain('Cache cleared successfully')
            ->assertExitCode(0);

        // 5. Verify cache is cleared
        foreach (array_keys($testData) as $key) {
            $this->assertNull($this->cacheManager->get($key));
        }

        // 6. Test cache warm command
        $this->artisan('form-security:cache warm --force')
            ->expectsOutputToContain('Cache warming completed')
            ->assertExitCode(0);

        // 7. Test cache optimization
        $this->artisan('form-security:cache optimize --force')
            ->expectsOutputToContain('Cache optimization completed')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_integrates_health_check_command_with_system_components(): void
    {
        // 1. Populate system with test data
        $this->cacheManager->put('health_test_key', 'health_test_value', 300);

        // 2. Run basic health check
        $this->artisan('form-security:health-check')
            ->expectsOutputToContain('System Health Check')
            ->expectsOutputToContain('Overall Status:')
            ->assertExitCode(1); // Health check typically returns 1 if issues found

        // 3. Run detailed health check
        $this->artisan('form-security:health-check --detailed')
            ->expectsOutputToContain('Detailed Health Check')
            ->expectsOutputToContain('Database Connectivity')
            ->expectsOutputToContain('Cache Status')
            ->expectsOutputToContain('Configuration Validation')
            ->assertExitCode(1);

        // 4. Test health check with fix attempt
        $this->artisan('form-security:health-check --fix')
            ->expectsOutputToContain('Attempting to fix detected issues')
            ->expectsOutputToContain('Fix Results:')
            ->assertExitCode(1);

        // 5. Test health check export functionality
        $this->artisan('form-security:health-check --export=json')
            ->expectsOutputToContain('Health check results exported')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_integrates_install_command_with_configuration_system(): void
    {
        // 1. Test installation command
        $this->artisan('form-security:install')
            ->expectsQuestion('Do you want to publish the configuration file?', 'yes')
            ->expectsQuestion('Do you want to run database migrations?', 'yes')
            ->expectsQuestion('Do you want to clear and rebuild the cache?', 'yes')
            ->expectsOutputToContain('FormSecurity installation completed successfully!')
            ->assertExitCode(0);

        // 2. Verify configuration was published
        $this->assertTrue(file_exists(config_path('form-security.php')));

        // 3. Verify cache system is functional after installation
        $testKey = 'post_install_test';
        $testValue = 'installation_successful';
        
        $this->assertTrue($this->cacheManager->put($testKey, $testValue, 300));
        $this->assertEquals($testValue, $this->cacheManager->get($testKey));

        // 4. Test installation with different options
        $this->artisan('form-security:install --force')
            ->expectsOutputToContain('FormSecurity installation completed successfully!')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_command_error_scenarios_gracefully(): void
    {
        // 1. Test invalid cache action
        $this->artisan('form-security:cache invalid-action')
            ->expectsOutputToContain('Invalid action: invalid-action')
            ->expectsOutputToContain('Available actions: clear, warm, stats, optimize, test')
            ->assertExitCode(1);

        // 2. Test invalid cache level
        $this->artisan('form-security:cache clear --level=invalid --force')
            ->expectsOutputToContain('Invalid cache level: invalid')
            ->assertExitCode(1);

        // 3. Test invalid health check export format
        $this->artisan('form-security:health-check --export=invalid')
            ->expectsOutputToContain('Invalid export format')
            ->assertExitCode(1);

        // 4. Test command help functionality
        $this->artisan('form-security:cache --help')
            ->expectsOutputToContain('form-security:cache')
            ->expectsOutputToContain('action')
            ->expectsOutputToContain('clear|warm|stats|optimize|test')
            ->assertExitCode(0);

        $this->artisan('form-security:health-check --help')
            ->expectsOutputToContain('form-security:health-check')
            ->expectsOutputToContain('--detailed')
            ->expectsOutputToContain('--fix')
            ->expectsOutputToContain('--export')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_integrates_commands_with_multi_level_cache_operations(): void
    {
        // 1. Populate different cache levels
        $this->cacheManager->putInRequest('request_key', 'request_value');
        $this->cacheManager->putInMemory('memory_key', 'memory_value');
        $this->cacheManager->putInDatabase('database_key', 'database_value');

        // 2. Test cache stats shows all levels
        $this->artisan('form-security:cache stats --detailed')
            ->expectsOutputToContain('Cache Statistics')
            ->expectsOutputToContain('Detailed Statistics')
            ->assertExitCode(0);

        // 3. Test selective level clearing
        $this->artisan('form-security:cache clear --level=memory --force')
            ->expectsOutputToContain('Cache cleared successfully')
            ->assertExitCode(0);

        // 4. Verify selective clearing worked
        $this->assertNotNull($this->cacheManager->getFromRequest('request_key'));
        $this->assertNull($this->cacheManager->getFromMemory('memory_key'));
        $this->assertNotNull($this->cacheManager->getFromDatabase('database_key'));

        // 5. Test clearing all levels
        $this->artisan('form-security:cache clear --level=all --force')
            ->expectsOutputToContain('Cache cleared successfully')
            ->assertExitCode(0);

        // 6. Verify all levels cleared
        $this->assertNull($this->cacheManager->getFromRequest('request_key'));
        $this->assertNull($this->cacheManager->getFromDatabase('database_key'));
    }

    #[Test]
    public function it_integrates_commands_with_cache_performance_monitoring(): void
    {
        // 1. Generate cache activity for performance monitoring
        for ($i = 0; $i < 20; $i++) {
            $key = "perf_test_key_{$i}";
            $value = "perf_test_value_{$i}";
            
            $this->cacheManager->put($key, $value, 300);
            
            // Generate some cache hits
            for ($j = 0; $j < 3; $j++) {
                $this->cacheManager->get($key);
            }
        }

        // 2. Test cache performance testing
        $this->artisan('form-security:cache test')
            ->expectsOutputToContain('Cache Test Results')
            ->expectsOutputToContain('Status')
            ->assertExitCode(0);

        // 3. Test detailed cache statistics
        $this->artisan('form-security:cache stats --detailed')
            ->expectsOutputToContain('Cache Statistics')
            ->expectsOutputToContain('Hit Ratio')
            ->expectsOutputToContain('Total Entries')
            ->assertExitCode(0);

        // 4. Test health check performance validation
        $this->artisan('form-security:health-check --detailed')
            ->expectsOutputToContain('Performance Metrics')
            ->expectsOutputToContain('Response Time:')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_handles_concurrent_command_execution(): void
    {
        // 1. Populate cache with test data
        for ($i = 0; $i < 10; $i++) {
            $this->cacheManager->put("concurrent_key_{$i}", "concurrent_value_{$i}", 300);
        }

        // 2. Test that commands handle concurrent-like access patterns
        // (Simulated since we can't run truly concurrent commands in PHPUnit)
        
        // Run stats command
        $this->artisan('form-security:cache stats')
            ->expectsOutputToContain('Cache Statistics')
            ->assertExitCode(0);

        // Immediately run optimization
        $this->artisan('form-security:cache optimize --force')
            ->expectsOutputToContain('Cache optimization completed')
            ->assertExitCode(0);

        // Run health check
        $this->artisan('form-security:health-check')
            ->expectsOutputToContain('System Health Check')
            ->assertExitCode(1);

        // Verify cache data integrity after multiple commands
        for ($i = 0; $i < 10; $i++) {
            $value = $this->cacheManager->get("concurrent_key_{$i}");
            $this->assertEquals("concurrent_value_{$i}", $value);
        }
    }

    #[Test]
    public function it_integrates_commands_with_cache_invalidation_workflows(): void
    {
        // 1. Set up hierarchical cache data
        $userData = ['id' => 123, 'name' => 'Test User'];
        $userPostsData = ['post_1', 'post_2', 'post_3'];
        $userSessionData = ['session_id' => 'abc123', 'expires' => time() + 3600];

        $this->cacheManager->put('user:123', $userData, 3600);
        $this->cacheManager->put('user_posts:123', $userPostsData, 3600);
        $this->cacheManager->put('user_session:123', $userSessionData, 1800);

        // 2. Verify all data is cached
        $this->assertEquals($userData, $this->cacheManager->get('user:123'));
        $this->assertEquals($userPostsData, $this->cacheManager->get('user_posts:123'));
        $this->assertEquals($userSessionData, $this->cacheManager->get('user_session:123'));

        // 3. Test cache optimization (should maintain data integrity)
        $this->artisan('form-security:cache optimize --force')
            ->expectsOutputToContain('Cache optimization completed')
            ->assertExitCode(0);

        // 4. Verify data integrity after optimization
        $this->assertEquals($userData, $this->cacheManager->get('user:123'));
        $this->assertEquals($userPostsData, $this->cacheManager->get('user_posts:123'));
        $this->assertEquals($userSessionData, $this->cacheManager->get('user_session:123'));

        // 5. Test selective cache clearing
        $this->artisan('form-security:cache clear --level=memory --force')
            ->expectsOutputToContain('Cache cleared successfully')
            ->assertExitCode(0);

        // 6. Test cache warming after clearing
        $this->artisan('form-security:cache warm --force')
            ->expectsOutputToContain('Cache warming completed')
            ->assertExitCode(0);

        // 7. Final health check to ensure system stability
        $this->artisan('form-security:health-check')
            ->expectsOutputToContain('System Health Check')
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
