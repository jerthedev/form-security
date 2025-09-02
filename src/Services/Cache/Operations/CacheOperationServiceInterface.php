<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Contracts\Cache;

use JTD\FormSecurity\ValueObjects\CacheKey;

/**
 * CacheOperationServiceInterface
 */
interface CacheOperationServiceInterface
{
    public function get(CacheKey|string $key, mixed $default = null, ?array $levels = null): mixed;

    public function put(CacheKey|string $key, mixed $value, ?int $ttl = null, ?array $levels = null): bool;

    public function forget(CacheKey|string $key, ?array $levels = null): bool;

    public function flush(?array $levels = null): bool;

    public function clear(): bool;

    public function getFromLevel(): mixed;

    public function putToLevel(): mixed;

    public function invalidateLevel(): mixed;
}
