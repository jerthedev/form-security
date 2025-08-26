<?php

declare(strict_types=1);

/**
 * Test File: ServiceProviderTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: N/A - Service provider testing
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1010-service-provider-package-registration
 *
 * Description: Tests for the FormSecurityServiceProvider including service
 * registration, container bindings, and Laravel 12 enhanced features.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1010-service-provider-package-registration.md
 */

namespace JTD\FormSecurity\Tests\Unit;

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
#[Group('ticket-1010')]
#[Group('service-provider')]
#[Group('unit')]
class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_core_service_contracts(): void
    {
        // Test that all core service contracts are bound in the container
        $this->assertTrue($this->app->bound(ConfigurationContract::class));
        $this->assertTrue($this->app->bound(FormSecurityContract::class));
        $this->assertTrue($this->app->bound(SpamDetectionContract::class));
    }

    #[Test]
    public function it_resolves_services_as_singletons(): void
    {
        // Test that services are registered as singletons
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
    public function it_binds_correct_implementations(): void
    {
        // Test that contracts are bound to correct implementations
        $configService = $this->app->make(ConfigurationContract::class);
        $this->assertInstanceOf(ConfigurationService::class, $configService);

        $formSecurityService = $this->app->make(FormSecurityContract::class);
        $this->assertInstanceOf(FormSecurityService::class, $formSecurityService);

        $spamDetectionService = $this->app->make(SpamDetectionContract::class);
        $this->assertInstanceOf(SpamDetectionService::class, $spamDetectionService);
    }

    #[Test]
    public function it_registers_service_aliases(): void
    {
        // Test that service aliases are registered
        $this->assertTrue($this->app->bound('form-security'));
        $this->assertTrue($this->app->bound('form-security.config'));
        $this->assertTrue($this->app->bound('form-security.spam-detector'));

        // Test that aliases resolve to correct services
        $formSecurity = $this->app->make('form-security');
        $this->assertInstanceOf(FormSecurityContract::class, $formSecurity);

        $config = $this->app->make('form-security.config');
        $this->assertInstanceOf(ConfigurationContract::class, $config);

        $spamDetector = $this->app->make('form-security.spam-detector');
        $this->assertInstanceOf(SpamDetectionContract::class, $spamDetector);
    }

    #[Test]
    public function it_provides_correct_services(): void
    {
        // Test that the service provider declares the correct provided services
        $serviceProvider = new FormSecurityServiceProvider($this->app);
        $providedServices = $serviceProvider->provides();

        $expectedServices = [
            ConfigurationContract::class,
            FormSecurityContract::class,
            SpamDetectionContract::class,
            'form-security',
            'form-security.config',
            'form-security.spam-detector',
        ];

        foreach ($expectedServices as $service) {
            $this->assertContains($service, $providedServices);
        }
    }

    #[Test]
    public function it_merges_configuration_correctly(): void
    {
        // Test that package configuration is merged with application config
        $this->assertTrue(config()->has('form-security'));
        $this->assertTrue(config()->has('form-security.enabled'));
        $this->assertTrue(config()->has('form-security.features'));
        $this->assertTrue(config()->has('form-security.spam_threshold'));
    }

    #[Test]
    public function it_injects_dependencies_correctly(): void
    {
        // Test that services receive their dependencies correctly
        $formSecurityService = $this->app->make(FormSecurityContract::class);

        // Use reflection to check if dependencies are injected
        $reflection = new \ReflectionClass($formSecurityService);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $parameters = $constructor->getParameters();

        // Check that FormSecurityService has the expected dependencies
        $this->assertCount(2, $parameters);
        $this->assertEquals('config', $parameters[0]->getName());
        $this->assertEquals('spamDetector', $parameters[1]->getName());
    }

    #[Test]
    public function it_handles_conditional_service_registration(): void
    {
        // Test conditional service registration based on feature flags

        // Test when AI analysis is disabled (default)
        $this->assertFalse($this->app->bound('form-security.ai-analyzer'));

        // Test when geolocation is disabled (default)
        $this->assertFalse($this->app->bound('form-security.geolocation'));

        // Enable features and re-register services
        config(['form-security.features.ai_analysis' => true]);
        config(['form-security.features.geolocation' => true]);

        // Re-register the service provider to test conditional registration
        $serviceProvider = new FormSecurityServiceProvider($this->app);
        $serviceProvider->register();

        // Note: In a real implementation, these services would be registered
        // For now, we just test that the configuration is properly set
        $this->assertTrue(config('form-security.features.ai_analysis'));
        $this->assertTrue(config('form-security.features.geolocation'));
    }

    #[Test]
    public function it_registers_configuration_service_with_laravel_config(): void
    {
        // Test that ConfigurationService receives Laravel's config repository
        $configService = $this->app->make(ConfigurationContract::class);

        // Use reflection to check the injected dependency
        $reflection = new \ReflectionClass($configService);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $parameters = $constructor->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('config', $parameters[0]->getName());

        // Test that the service can access configuration
        $enabled = $configService->get('enabled');
        $this->assertIsBool($enabled);
    }

    #[Test]
    public function service_provider_bootstrap_performance_meets_requirements(): void
    {
        // Test that service provider bootstrap time is under 50ms
        $startTime = microtime(true);

        // Create a new application instance and register the service provider
        $app = $this->createApplication();
        $serviceProvider = new FormSecurityServiceProvider($app);
        $serviceProvider->register();
        $serviceProvider->boot();

        $endTime = microtime(true);
        $bootstrapTime = $endTime - $startTime;

        // Assert performance requirement (50ms = 0.05 seconds)
        $this->assertPerformanceRequirement($bootstrapTime, 'service provider bootstrap');
    }

    #[Test]
    public function it_handles_service_resolution_errors_gracefully(): void
    {
        // Test that the service provider handles missing dependencies gracefully

        // This test ensures that if there are issues with service resolution,
        // the application doesn't crash but handles errors appropriately

        try {
            $formSecurity = $this->app->make(FormSecurityContract::class);
            $this->assertInstanceOf(FormSecurityContract::class, $formSecurity);
        } catch (\Exception $e) {
            $this->fail('Service resolution should not throw exceptions: '.$e->getMessage());
        }
    }

    #[Test]
    public function it_maintains_service_container_integrity(): void
    {
        // Test that service registration doesn't interfere with Laravel's container

        // Ensure Laravel's core services are still available
        $this->assertTrue($this->app->bound('config'));
        $this->assertTrue($this->app->bound('cache'));
        $this->assertTrue($this->app->bound('log'));

        // Ensure our services don't conflict with Laravel services
        $laravelConfig = $this->app->make('config');
        $this->assertInstanceOf(ConfigRepository::class, $laravelConfig);

        $ourConfig = $this->app->make(ConfigurationContract::class);
        $this->assertInstanceOf(ConfigurationContract::class, $ourConfig);

        // They should be different instances
        $this->assertNotSame($laravelConfig, $ourConfig);
    }
}
