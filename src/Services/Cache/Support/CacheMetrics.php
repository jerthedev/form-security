<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services\Cache\Support;

/**
 * CacheMetrics value object
 */
class CacheMetrics
{
    public function __construct(
        public readonly int $hits = 0,
        public readonly int $misses = 0,
        public readonly int $puts = 0,
        public readonly int $deletes = 0,
        public readonly float $hitRatio = 0.0
    ) {}
}
