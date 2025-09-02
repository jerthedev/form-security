<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services\Cache\Support;

/**
 * CacheConfiguration value object
 */
class CacheConfiguration
{
    public function __construct(
        public readonly array $levels = [],
        public readonly array $security = [],
        public readonly array $integration = [],
        public readonly array $errorHandling = []
    ) {}
}
