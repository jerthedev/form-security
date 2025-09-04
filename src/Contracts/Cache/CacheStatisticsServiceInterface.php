<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Contracts\Cache;

/**
 * CacheStatisticsServiceInterface
 */
interface CacheStatisticsServiceInterface
{
    public function getStats(?array $levels = null): array;

    public function getHitRatio(?array $levels = null): float;

    public function getCacheSize(?array $levels = null): array;

    public function getSize(?array $levels = null): array;

    public function getCacheSizes(?array $levels = null): array;

    public function getMemoryCacheStats(): array;

    public function getDatabaseCacheStats(): array;

    public function getDatabaseCacheSize(): array;

    public function getAverageResponseTime(?array $levels = null): float;

    public function resetStats(): void;

    public function getEnhancedStats(?array $levels = null): array;

    public function calculateCacheEfficiency(?array $levels = null): array;
}
