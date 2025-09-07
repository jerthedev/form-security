<?php

declare(strict_types=1);

namespace JTD\FormSecurity;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use JTD\FormSecurity\Contracts\ConfigurationContract;
use JTD\FormSecurity\Contracts\ConfigurationManagerInterface;
use JTD\FormSecurity\Contracts\ConfigurationValidatorInterface;
use JTD\FormSecurity\Contracts\FormSecurityContract;
use JTD\FormSecurity\Contracts\PerformanceMonitorInterface;
use JTD\FormSecurity\Contracts\PerformanceProfilerInterface;
use JTD\FormSecurity\Contracts\SpamDetectionContract;

/**
 * FormSecurity Service Provider with Laravel 12 enhanced features.
 *
 * Optimized for ultra-fast bootstrap time with deferred loading,
 * lazy configuration loading, and conditional service registration.
 */
class FormSecurityServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     * Set to false for core services that are always needed.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Core services that are always needed (minimal set).
     *
     * @var array<string, string>
     */
    public array $singletons = [
        // Removed - will be registered lazily
    ];

    /**
     * All of the container bindings that should be registered.
     *
     * @var array<string, \Closure|string|null>
     */
    public array $bindings = [];

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootServices();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Load main configuration
        $this->mergeConfigFrom(__DIR__.'/../config/form-security.php', 'form-security');
        $this->loadExtendedConfigurations();

        // Register core services
        $this->registerCoreServices();

        // Register cache services
        $this->registerCacheServices();

        // Register performance services
        $this->registerPerformanceServices();

        // Register aliases for backwards compatibility
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
            PerformanceMonitorInterface::class,
            PerformanceProfilerInterface::class,
            Services\FeatureToggleService::class,
            'form-security',
            'form-security.config',
            'form-security.spam-detector',
            'form-security.performance.monitor',
            'form-security.performance.profiler',
        ];
    }

    /**
     * Lightweight boot operations that don't require heavy resources.
     */
    protected function bootServices(): void
    {
        // Configuration publishing
        $this->publishes([
            __DIR__.'/../config/form-security.php' => config_path('form-security.php'),
        ], 'form-security-config');

        // Load migrations for console commands
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /**
     * Register core services.
     */
    protected function registerCoreServices(): void
    {
        // Register configuration service
        $this->app->singleton(ConfigurationContract::class, function (Application $app) {
            return new Services\ConfigurationService($app['config']);
        });

        // Register configuration manager
        $this->app->singleton(ConfigurationManagerInterface::class, function (Application $app) {
            return new Services\ConfigurationManager(
                $app['config'],
                $app['cache.store'],
                $app->make(ConfigurationValidatorInterface::class),
                $app['events']
            );
        });

        // Register concrete ConfigurationManager class for CLI commands
        $this->app->singleton(Services\ConfigurationManager::class, function (Application $app) {
            return $app->make(ConfigurationManagerInterface::class);
        });

        $this->app->singleton(ConfigurationValidatorInterface::class, Services\ConfigurationValidator::class);

        $this->app->singleton(FormSecurityContract::class, function (Application $app) {
            return new Services\FormSecurityService(
                $app->make(ConfigurationContract::class),
                $app->make(SpamDetectionContract::class)
            );
        });

        $this->app->singleton(SpamDetectionContract::class, Services\SpamDetectionService::class);
        $this->app->singleton(Services\FeatureToggleService::class, Services\FeatureToggleService::class);

        // Cache services are registered via registerCacheServices() above
        // Performance services are registered via registerPerformanceServices() above

        // Register conditional services with lazy evaluation
        $this->registerConditionalServicesLazy();

        // Register console commands only when needed
        $this->registerConsoleCommandsLazy();
    }

    /**
     * Load extended configurations only when actually needed.
     */
    protected function loadExtendedConfigurations(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        $this->mergeConfigFrom(__DIR__.'/../config/form-security-cache.php', 'form-security.cache');
        $this->mergeConfigFrom(__DIR__.'/../config/form-security-patterns.php', 'form-security.patterns');
        $this->mergeConfigFrom(__DIR__.'/../config/form-security-performance.php', 'form-security.performance');

        $loaded = true;
    }

    /**
     * Register cache services.
     */
    protected function registerCacheServices(): void
    {
        // Register the cache service provider immediately but let it be deferred
        $this->app->register(\JTD\FormSecurity\Providers\CacheServiceProvider::class);

        // Register additional cache services that tests might need
        $this->app->singleton(\JTD\FormSecurity\Services\CacheManager::class, \JTD\FormSecurity\Services\CacheManager::class);
        $this->app->singleton(\JTD\FormSecurity\Services\CacheKeyManager::class, \JTD\FormSecurity\Services\CacheKeyManager::class);
        $this->app->singleton(\JTD\FormSecurity\Services\CacheInvalidationService::class, \JTD\FormSecurity\Services\CacheInvalidationService::class);
        $this->app->singleton(\JTD\FormSecurity\Services\CachePerformanceMonitor::class, \JTD\FormSecurity\Services\CachePerformanceMonitor::class);

        // Register cache manager alias for backwards compatibility
        $this->app->singleton('form-security.cache.manager', function (Application $app) {
            return $app->make(\JTD\FormSecurity\Contracts\CacheManagerInterface::class);
        });
    }

    /**
     * Register performance monitoring services.
     */
    protected function registerPerformanceServices(): void
    {
        // Register performance monitor service
        $this->app->singleton(PerformanceMonitorInterface::class, function (Application $app) {
            // Only create if performance monitoring is enabled
            if (! config('form-security.performance.enabled', true)) {
                throw new \RuntimeException('Performance monitoring is not enabled');
            }

            return new Services\PerformanceMonitoringService;
        });

        // Register performance profiler service
        $this->app->singleton(PerformanceProfilerInterface::class, function (Application $app) {
            // Only create if profiling is enabled
            if (! config('form-security.performance.features.profiling', true)) {
                throw new \RuntimeException('Performance profiling is not enabled');
            }

            return new Services\PerformanceProfiler;
        });

        // Register aliases for easier access
        $this->app->alias(PerformanceMonitorInterface::class, 'form-security.performance.monitor');
        $this->app->alias(PerformanceProfilerInterface::class, 'form-security.performance.profiler');
    }

    /**
     * Register conditional services with lazy evaluation.
     */
    protected function registerConditionalServicesLazy(): void
    {
        // Register dependency injection patterns without immediate evaluation
        $this->app->when(FormSecurityContract::class)
            ->needs('$config')
            ->give(function (Application $app) {
                return $app->make(ConfigurationContract::class);
            });

        // Lazy registration for optional services
        $this->app->singleton('form-security.ai-analyzer', function (Application $app) {
            // Only check feature flag when service is actually requested
            if (! config('form-security.features.ai_analysis', false)) {
                throw new \RuntimeException('AI Analysis feature is not enabled');
            }

            return new Services\AiAnalysisService($app->make(ConfigurationContract::class));
        });

        $this->app->singleton('form-security.geolocation', function (Application $app) {
            // Only check feature flag when service is actually requested
            if (! config('form-security.features.geolocation', false)) {
                throw new \RuntimeException('Geolocation feature is not enabled');
            }

            return new Services\GeolocationService($app->make(ConfigurationContract::class));
        });
    }

    /**
     * Register console commands with lazy loading.
     */
    protected function registerConsoleCommandsLazy(): void
    {
        // Only register commands when running in console
        $this->app->booting(function ($app) {
            if ($app->runningInConsole()) {
                // Extended configuration publishing
                $this->publishes([
                    __DIR__.'/../config/form-security-cache.php' => config_path('form-security-cache.php'),
                    __DIR__.'/../config/form-security-patterns.php' => config_path('form-security-patterns.php'),
                    __DIR__.'/../config/form-security-performance.php' => config_path('form-security-performance.php'),
                ], 'form-security-config-extended');

                // Register commands
                $this->commands([
                    Console\Commands\ImportGeoLite2Command::class,
                    Console\Commands\ConfigurationPublishCommand::class,
                    Console\Commands\ConfigurationValidateCommand::class,
                    Console\Commands\FeatureToggleCommand::class,
                    Console\Commands\InstallCommand::class,
                    Console\Commands\CacheCommand::class,
                    Console\Commands\CleanupCommand::class,
                    Console\Commands\HealthCheckCommand::class,
                    Console\Commands\OptimizeCommand::class,
                    Console\Commands\ReportCommand::class,
                    Console\Commands\PerformanceReportCommand::class,
                ]);

                // Register event listeners
                $app['events']->listen(
                    Events\ConfigurationChanged::class,
                    Listeners\InvalidateConfigurationCache::class
                );
            }
        });
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
