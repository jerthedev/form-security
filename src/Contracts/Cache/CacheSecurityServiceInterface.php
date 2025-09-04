<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Contracts\Cache;

/**
 * CacheSecurityServiceInterface
 */
interface CacheSecurityServiceInterface
{
    public function enableSecurity(array $config = []): void;

    public function disableSecurity(): void;

    public function getSecurityStatus(): array;
}
