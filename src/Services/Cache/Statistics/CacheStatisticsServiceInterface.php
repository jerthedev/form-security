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

    public function getCacheSizes(): mixed;

    public function getEnhancedStats(): mixed;
}
