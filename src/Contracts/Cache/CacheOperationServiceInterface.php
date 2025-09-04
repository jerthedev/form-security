<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Contracts\Cache;

use JTD\FormSecurity\Enums\CacheLevel;
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

    public function remember(CacheKey|string $key, callable $callback, ?int $ttl = null, ?array $levels = null): mixed;

    public function rememberForever(CacheKey|string $key, callable $callback, ?array $levels = null): mixed;

    public function add(CacheKey|string $key, mixed $value, ?int $ttl = null, ?array $levels = null): bool;

    public function has(CacheKey|string $key, ?array $levels = null): bool;

    // Level-specific operations
    public function getFromRequest(CacheKey|string $key, mixed $default = null): mixed;

    public function getFromMemory(CacheKey|string $key, mixed $default = null): mixed;

    public function getFromDatabase(CacheKey|string $key, mixed $default = null): mixed;

    public function putInRequest(CacheKey|string $key, mixed $value): bool;

    public function putInMemory(CacheKey|string $key, mixed $value, ?int $ttl = null): bool;

    public function putInDatabase(CacheKey|string $key, mixed $value, ?int $ttl = null): bool;

    public function forgetFromRequest(CacheKey|string $key): bool;

    public function forgetFromMemory(CacheKey|string $key): bool;

    public function forgetFromDatabase(CacheKey|string $key): bool;

    public function flushRequest(): bool;

    public function flushMemory(): bool;

    public function flushDatabase(): bool;

    // Advanced operations
    public function getFromLevel(CacheKey|string $key, int $level, mixed $default = null): mixed;

    public function putToLevel(CacheKey|string $key, mixed $value, int $level, ?int $ttl = null): bool;

    public function invalidateLevel(int $level): bool;

    public function invalidateByPattern(string $pattern, ?array $levels = null): bool;

    public function invalidateByTags(array $tags, ?array $levels = null): bool;

    public function invalidateByNamespace(string $namespace, ?array $levels = null): bool;

    // Level management
    public function toggleLevel(CacheLevel $level, bool $enabled): bool;

    public function isLevelEnabled(CacheLevel $level): bool;

    public function getEnabledLevels(): array;

    public function getDisabledLevels(): array;

    public function enableAllLevels(): array;

    public function disableAllLevels(): array;

    public function getLevelStatusSummary(): array;

    // Configuration
    public function getConfiguration(): array;

    public function updateConfiguration(array $config): bool;

    // Fluent interface
    public function tags(array $tags): self;

    public function prefix(string $prefix): self;

    public function levels(array $levels): self;

    public function ttl(int $seconds): self;

    public function fluent(): self;

    public function fluentGet(CacheKey|string $key, mixed $default = null): mixed;

    public function fluentPut(CacheKey|string $key, mixed $value): bool;

    public function fluentRemember(CacheKey|string $key, callable $callback): mixed;

    public function fluentForget(CacheKey|string $key): bool;

    public function fluentFlush(): bool;

    public function getFluentContext(): array;

    public function hasFluentContext(): bool;

    public function clearFluentContext(): self;

    // Events and error handling
    public function addEventListener(string $event, callable $listener): void;

    public function processQueuedEvents(): array;

    public function configureIntegration(array $config): void;

    public function getIntegrationStatus(): array;

    public function registerErrorHandler(string $errorType, callable $handler): void;

    public function registerFallbackStrategy(string $operation, callable $fallback): void;

    public function configureErrorHandling(array $config): void;

    public function getErrorHandlingStatus(): array;

    public function testErrorHandling(): array;

    public function getOperationStats(): array;
}
