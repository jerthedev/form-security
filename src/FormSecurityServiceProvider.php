<?php

declare(strict_types=1);

namespace JTD\FormSecurity;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use JTD\FormSecurity\Contracts\ConfigurationContract;
use JTD\FormSecurity\Contracts\ConfigurationManagerInterface;
use JTD\FormSecurity\Contracts\ConfigurationValidatorInterface;
use JTD\FormSecurity\Contracts\FormSecurityContract;
use JTD\FormSecurity\Contracts\SpamDetectionContract;

/**
 * FormSecurity Service Provider with Laravel 12 enhanced features.
 *
 * This service provider implements conditional service registration,
 * deferred loading for performance optimization, and comprehensive
 * dependency injection patterns for the FormSecurity package.
 */
class FormSecurityServiceProvider extends ServiceProvider
{
    /**
     * All of the container singletons that should be registered.
     *
     * @var array<string, string>
     */
    public array $singletons = [
        ConfigurationContract::class => Services\ConfigurationService::class,
        ConfigurationManagerInterface::class => Services\ConfigurationManager::class,
        ConfigurationValidatorInterface::class => Services\ConfigurationValidator::class,
        FormSecurityContract::class => Services\FormSecurityService::class,
        SpamDetectionContract::class => Services\SpamDetectionService::class,
        Services\FeatureToggleService::class => Services\FeatureToggleService::class,
    ];

    /**
     * All of the container bindings that should be registered.
     *
     * @var array<string, \Closure|string|null>
     */
    public array $bindings = [];

    /**
     * Bootstrap any application services.
     *
     * This method is called after all other service providers have been registered,
     * meaning you have access to all other services that have been registered.
     */
    public function boot(): void
    {
        $this->bootConfiguration();
        $this->bootMigrations();
        $this->bootCommands();
        $this->bootMiddleware();
        $this->bootEventListeners();
    }

    /**
     * Register any application services.
     *
     * This method is called during the registration phase and should be used
     * to bind services into the service container.
     */
    public function register(): void
    {
        $this->registerConfiguration();
        $this->registerCoreServices();
        $this->registerCacheServices();
        $this->registerConditionalServices();
        $this->registerAliases();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            ConfigurationContract::class,
            ConfigurationManagerInterface::class,
            ConfigurationValidatorInterface::class,
            FormSecurityContract::class,
            SpamDetectionContract::class,
            Services\FeatureToggleService::class,
            'form-security',
            'form-security.config',
            'form-security.spam-detector',
        ];
    }

    /**
     * Bootstrap configuration publishing and merging.
     */
    protected function bootConfiguration(): void
    {
        // Publish configuration files
        $this->publishes([
            __DIR__.'/../config/form-security.php' => config_path('form-security.php'),
        ], 'form-security-config');

        // Publish additional configuration files
        $this->publishes([
            __DIR__.'/../config/form-security-cache.php' => config_path('form-security-cache.php'),
            __DIR__.'/../config/form-security-patterns.php' => config_path('form-security-patterns.php'),
        ], 'form-security-config-extended');
    }

    /**
     * Bootstrap database migrations.
     */
    protected function bootMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /**
     * Bootstrap console commands.
     */
    protected function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Existing commands
                Console\Commands\ImportGeoLite2Command::class,
                Console\Commands\ConfigurationPublishCommand::class,
                Console\Commands\ConfigurationValidateCommand::class,
                Console\Commands\FeatureToggleCommand::class,

                // New CLI commands suite
                Console\Commands\InstallCommand::class,
                Console\Commands\CacheCommand::class,
                Console\Commands\CleanupCommand::class,
                Console\Commands\HealthCheckCommand::class,
                Console\Commands\OptimizeCommand::class,
                Console\Commands\ReportCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap HTTP middleware.
     */
    protected function bootMiddleware(): void
    {
        // Register middleware will be handled in the boot method
        // when we have the router available
    }

    /**
     * Bootstrap event listeners.
     */
    protected function bootEventListeners(): void
    {
        // Register configuration event listeners
        $this->app['events']->listen(
            Events\ConfigurationChanged::class,
            Listeners\InvalidateConfigurationCache::class
        );
    }

    /**
     * Register package configuration.
     */
    protected function registerConfiguration(): void
    {
        // Merge package configuration with application configuration
        $this->mergeConfigFrom(__DIR__.'/../config/form-security.php', 'form-security');
        $this->mergeConfigFrom(__DIR__.'/../config/form-security-cache.php', 'form-security.cache');
        $this->mergeConfigFrom(__DIR__.'/../config/form-security-patterns.php', 'form-security.patterns');
    }

    /**
     * Register core services in the container.
     */
    protected function registerCoreServices(): void
    {
        // Core services are registered via the $singletons property
        // Additional custom bindings can be added here if needed
    }

    /**
     * Register cache services in the container.
     */
    protected function registerCacheServices(): void
    {
        // Register the cache service provider
        $this->app->register(\JTD\FormSecurity\Providers\CacheServiceProvider::class);
    }

    /**
     * Register conditional services based on configuration.
     */
    protected function registerConditionalServices(): void
    {
        // Register services conditionally based on feature flags
        $this->app->when(FormSecurityContract::class)
            ->needs('$config')
            ->give(function (Application $app) {
                return $app->make(ConfigurationContract::class);
            });

        // Register additional services based on enabled features
        if (config('form-security.features.ai_analysis', false)) {
            $this->app->singleton('form-security.ai-analyzer', function (Application $app) {
                return new Services\AiAnalysisService($app->make(ConfigurationContract::class));
            });
        }

        if (config('form-security.features.geolocation', false)) {
            $this->app->singleton('form-security.geolocation', function (Application $app) {
                return new Services\GeolocationService($app->make(ConfigurationContract::class));
            });
        }
    }

    /**
     * Register service aliases for easier access.
     */
    protected function registerAliases(): void
    {
        $this->app->alias(FormSecurityContract::class, 'form-security');
        $this->app->alias(ConfigurationContract::class, 'form-security.config');
        $this->app->alias(SpamDetectionContract::class, 'form-security.spam-detector');
    }
}
