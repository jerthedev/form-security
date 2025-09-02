<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Contracts\Cache;

/**
 * CacheMaintenanceServiceInterface
 */
interface CacheMaintenanceServiceInterface
{
    public function maintainDatabaseCache(array $operations = []): array;
    public function maintenance(array $operations = ['cleanup', 'optimize']): array;
}
