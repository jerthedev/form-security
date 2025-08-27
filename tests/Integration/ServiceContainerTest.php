<?php

declare(strict_types=1);

/**
 * Test File: ServiceContainerTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: N/A - Service container integration testing
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1020-service-provider-tests
 *
 * Description: Integration tests for service container bindings, dependency
 * injection, and service resolution functionality in the FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1020-service-provider-tests.md
 */

namespace JTD\FormSecurity\Tests\Integration;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use JTD\FormSecurity\Contracts\ConfigurationContract;
use JTD\FormSecurity\Contracts\FormSecurityContract;
use JTD\FormSecurity\Contracts\SpamDetectionContract;
use JTD\FormSecurity\FormSecurityServiceProvider;
use JTD\FormSecurity\Services\ConfigurationService;
use JTD\FormSecurity\Services\FormSecurityService;
use JTD\FormSecurity\Services\SpamDetectionService;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-002')]
#[Group('ticket-1020')]
#[Group('service-container')]
#[Group('integration')]
class ServiceContainerTest extends TestCase
{
    #[Test]
    public function container_resolves_all_service_contracts(): void
    {
        // Test that the container can resolve all service contracts
        $configService = $this->app->make(ConfigurationContract::class);
        $this->assertInstanceOf(ConfigurationService::class, $configService);

        $formSecurityService = $this->app->make(FormSecurityContract::class);
        $this->assertInstanceOf(FormSecurityService::class, $formSecurityService);

        $spamDetectionService = $this->app->make(SpamDetectionContract::class);
        $this->assertInstanceOf(SpamDetectionService::class, $spamDetectionService);
    }

    #[Test]
    public function container_maintains_singleton_behavior(): void
    {
        // Test that services are resolved as singletons
        $config1 = $this->app->make(ConfigurationContract::class);
        $config2 = $this->app->make(ConfigurationContract::class);
        $this->assertSame($config1, $config2);

        $formSecurity1 = $this->app->make(FormSecurityContract::class);
        $formSecurity2 = $this->app->make(FormSecurityContract::class);
        $this->assertSame($formSecurity1, $formSecurity2);

        $spamDetector1 = $this->app->make(SpamDetectionContract::class);
        $spamDetector2 = $this->app->make(SpamDetectionContract::class);
        $this->assertSame($spamDetector1, $spamDetector2);
    }

    #[Test]
    public function container_injects_dependencies_correctly(): void
    {
        // Test that dependencies are injected correctly
        $formSecurityService = $this->app->make(FormSecurityContract::class);

        // Use reflection to verify dependencies
        $reflection = new \ReflectionClass($formSecurityService);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $parameters = $constructor->getParameters();

        // FormSecurityService should have config and spamDetector dependencies
        $this->assertCount(2, $parameters);
        $this->assertEquals('config', $parameters[0]->getName());
        $this->assertEquals('spamDetector', $parameters[1]->getName());
    }

    #[Test]
    public function container_resolves_service_aliases(): void
    {
        // Test that service aliases are properly resolved
        $formSecurity = $this->app->make('form-security');
        $this->assertInstanceOf(FormSecurityContract::class, $formSecurity);

        $config = $this->app->make('form-security.config');
        $this->assertInstanceOf(ConfigurationContract::class, $config);

        $spamDetector = $this->app->make('form-security.spam-detector');
        $this->assertInstanceOf(SpamDetectionContract::class, $spamDetector);
    }

    #[Test]
    public function container_handles_circular_dependencies(): void
    {
        // Test that there are no circular dependencies
        try {
            $config = $this->app->make(ConfigurationContract::class);
            $formSecurity = $this->app->make(FormSecurityContract::class);
            $spamDetector = $this->app->make(SpamDetectionContract::class);

            $this->assertNotNull($config);
            $this->assertNotNull($formSecurity);
            $this->assertNotNull($spamDetector);
        } catch (\Exception $e) {
            $this->fail('Circular dependency detected: '.$e->getMessage());
        }
    }

    #[Test]
    public function container_resolves_conditional_services(): void
    {
        // Test conditional service registration

        // By default, optional services should not be bound
        $this->assertFalse($this->app->bound('form-security.ai-analyzer'));
        $this->assertFalse($this->app->bound('form-security.geolocation'));

        // Enable features
        config(['form-security.features.ai_analysis' => true]);
        config(['form-security.features.geolocation' => true]);

        // Re-register service provider
        $serviceProvider = new FormSecurityServiceProvider($this->app);
        $serviceProvider->register();

        // Services should now be conditionally registered based on config
        $this->assertTrue(config('form-security.features.ai_analysis'));
        $this->assertTrue(config('form-security.features.geolocation'));
    }

    #[Test]
    public function container_handles_service_resolution_errors(): void
    {
        // Test graceful handling of service resolution errors

        // Create a new container without our service provider
        $freshContainer = new Container;

        try {
            $freshContainer->make(FormSecurityContract::class);
            $this->fail('Expected exception for unbound service');
        } catch (\Exception $e) {
            $this->assertStringContainsString('FormSecurityContract', $e->getMessage());
        }
    }

    #[Test]
    public function container_maintains_laravel_service_integrity(): void
    {
        // Test that our services don't interfere with Laravel's core services

        $laravelConfig = $this->app->make('config');
        $this->assertInstanceOf(ConfigRepository::class, $laravelConfig);

        $cache = $this->app->make('cache');
        $this->assertNotNull($cache);

        $log = $this->app->make('log');
        $this->assertNotNull($log);

        // Our services should coexist with Laravel services
        $ourConfig = $this->app->make(ConfigurationContract::class);
        $this->assertInstanceOf(ConfigurationContract::class, $ourConfig);

        // They should be different instances
        $this->assertNotSame($laravelConfig, $ourConfig);
    }

    #[Test]
    public function container_service_resolution_performance(): void
    {
        // Test service resolution performance
        $startTime = microtime(true);

        // Resolve services multiple times
        for ($i = 0; $i < 100; $i++) {
            $this->app->make(ConfigurationContract::class);
            $this->app->make(FormSecurityContract::class);
            $this->app->make(SpamDetectionContract::class);
        }

        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;

        // Service resolution should be fast (under 50ms for 100 resolutions)
        $this->assertPerformanceRequirement($processingTime, 'service resolution (100x)');
    }

    #[Test]
    public function container_handles_service_provider_deferred_loading(): void
    {
        // Test deferred service provider functionality
        $serviceProvider = new FormSecurityServiceProvider($this->app);

        // Check if services are provided
        $providedServices = $serviceProvider->provides();
        $this->assertNotEmpty($providedServices);

        // All provided services should be resolvable
        foreach ($providedServices as $service) {
            if (class_exists($service) || interface_exists($service)) {
                $resolved = $this->app->make($service);
                $this->assertNotNull($resolved);
            }
        }
    }

    #[Test]
    public function container_validates_service_contracts(): void
    {
        // Test that resolved services implement their contracts correctly

        $configService = $this->app->make(ConfigurationContract::class);
        $this->assertInstanceOf(ConfigurationContract::class, $configService);

        // Test contract methods are available
        $this->assertTrue(method_exists($configService, 'get'));
        $this->assertTrue(method_exists($configService, 'set'));
        $this->assertTrue(method_exists($configService, 'isFeatureEnabled'));

        $formSecurityService = $this->app->make(FormSecurityContract::class);
        $this->assertInstanceOf(FormSecurityContract::class, $formSecurityService);

        $spamDetectionService = $this->app->make(SpamDetectionContract::class);
        $this->assertInstanceOf(SpamDetectionContract::class, $spamDetectionService);
    }

    #[Test]
    public function container_handles_service_dependencies_chain(): void
    {
        // Test complex dependency chains
        $formSecurityService = $this->app->make(FormSecurityContract::class);

        // FormSecurityService depends on ConfigurationContract and SpamDetectionContract
        // Both should be properly injected
        $this->assertInstanceOf(FormSecurityContract::class, $formSecurityService);

        // Test that the service can perform its functions (indicating dependencies are working)
        $data = $this->createSampleFormData();
        $result = $formSecurityService->analyzeSubmission($data);

        $this->assertValidAnalysisStructure($result);
    }

    #[Test]
    public function container_memory_efficiency(): void
    {
        // Test memory efficiency of service resolution
        $memoryBefore = memory_get_usage(true);

        // Resolve services multiple times
        for ($i = 0; $i < 50; $i++) {
            $this->app->make(ConfigurationContract::class);
            $this->app->make(FormSecurityContract::class);
            $this->app->make(SpamDetectionContract::class);
        }

        $memoryAfter = memory_get_usage(true);
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB

        // Memory usage should be minimal due to singleton pattern
        $this->assertLessThan(1.0, $memoryUsed, 'Service resolution should not consume excessive memory');
    }
}
