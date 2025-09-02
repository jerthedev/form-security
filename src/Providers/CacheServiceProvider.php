<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Providers;

use Illuminate\Support\ServiceProvider;
use JTD\FormSecurity\Contracts\CacheManagerInterface;
use JTD\FormSecurity\Contracts\Cache\CacheOperationServiceInterface;
use JTD\FormSecurity\Contracts\Cache\CacheWarmingServiceInterface;
use JTD\FormSecurity\Contracts\Cache\CacheMaintenanceServiceInterface;
use JTD\FormSecurity\Contracts\Cache\CacheSecurityServiceInterface;
use JTD\FormSecurity\Contracts\Cache\CacheStatisticsServiceInterface;
use JTD\FormSecurity\Contracts\Cache\CacheValidationServiceInterface;
use JTD\FormSecurity\Services\CacheManager;
use JTD\FormSecurity\Services\Cache\Operations\CacheOperationService;
use JTD\FormSecurity\Services\Cache\Warming\CacheWarmingService;
use JTD\FormSecurity\Services\Cache\Maintenance\CacheMaintenanceService;
use JTD\FormSecurity\Services\Cache\Security\CacheSecurityService;
use JTD\FormSecurity\Services\Cache\Statistics\CacheStatisticsService;
use JTD\FormSecurity\Services\Cache\Validation\CacheValidationService;

/**
 * CacheServiceProvider
 */
class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register service interfaces
        $this->app->bind(CacheOperationServiceInterface::class, CacheOperationService::class);
        $this->app->bind(CacheWarmingServiceInterface::class, CacheWarmingService::class);
        $this->app->bind(CacheMaintenanceServiceInterface::class, CacheMaintenanceService::class);
        $this->app->bind(CacheSecurityServiceInterface::class, CacheSecurityService::class);
        $this->app->bind(CacheStatisticsServiceInterface::class, CacheStatisticsService::class);
        $this->app->bind(CacheValidationServiceInterface::class, CacheValidationService::class);

        // Register main CacheManager
        $this->app->bind(CacheManagerInterface::class, CacheManager::class);
        $this->app->alias(CacheManagerInterface::class, 'cache.manager');
    }

    public function boot(): void
    {
        // Boot logic if needed
    }
}
