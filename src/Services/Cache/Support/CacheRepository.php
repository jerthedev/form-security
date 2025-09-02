<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services\Cache\Support;

use Illuminate\Contracts\Cache\Repository;

/**
 * CacheRepository wrapper
 */
class CacheRepository
{
    public function __construct(
        private Repository $repository
    ) {}

    // TODO: Add wrapper methods
}
