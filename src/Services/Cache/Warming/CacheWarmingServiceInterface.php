<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Contracts\Cache;

/**
 * CacheWarmingServiceInterface
 */
interface CacheWarmingServiceInterface
{
    public function warm(array $warmers, ?array $levels = null): array;

    public function processBatch(): mixed;
}
