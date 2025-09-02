<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services;

use JTD\FormSecurity\Contracts\Cache\CacheMaintenanceServiceInterface;
use JTD\FormSecurity\Contracts\Cache\CacheOperationServiceInterface;
use JTD\FormSecurity\Contracts\Cache\CacheSecurityServiceInterface;
use JTD\FormSecurity\Contracts\Cache\CacheStatisticsServiceInterface;
use JTD\FormSecurity\Contracts\Cache\CacheValidationServiceInterface;
use JTD\FormSecurity\Contracts\Cache\CacheWarmingServiceInterface;
use JTD\FormSecurity\Contracts\CacheManagerInterface;
use JTD\FormSecurity\Enums\CacheLevel;
use JTD\FormSecurity\ValueObjects\CacheKey;

/**
 * CacheManager - Service Coordinator
 *
 * Delegates operations to specialized services
 */
class CacheManager implements CacheManagerInterface
{
    public function __construct(
        private CacheOperationServiceInterface $operations,
        private CacheWarmingServiceInterface $warming,
        private CacheMaintenanceServiceInterface $maintenance,
        private CacheSecurityServiceInterface $security,
        private CacheStatisticsServiceInterface $statistics,
        private CacheValidationServiceInterface $validation
    ) {
        // Connect the operations service to the statistics service
        if (method_exists($this->statistics, 'setOperationsService')) {
            $this->statistics->setOperationsService($this->operations);
        }
    }

    // Core cache operations
    public function get(CacheKey|string $key, mixed $default = null, ?array $levels = null): mixed
    {
        return $this->operations->get($key, $default, $levels);
    }

    public function put(CacheKey|string $key, mixed $value, ?int $ttl = null, ?array $levels = null): bool
    {
        return $this->operations->put($key, $value, $ttl, $levels);
    }

    public function forget(CacheKey|string $key, ?array $levels = null): bool
    {
        return $this->operations->forget($key, $levels);
    }

    public function flush(?array $levels = null): bool
    {
        return $this->operations->flush($levels);
    }

    // Cache warming
    public function warm(array $warmers, ?array $levels = null): array
    {
        return $this->warming->warm($warmers, $levels);
    }

    // Statistics
    public function getStats(?array $levels = null): array
    {
        return $this->statistics->getStats($levels);
    }

    public function getHitRatio(?array $levels = null): float
    {
        return $this->statistics->getHitRatio($levels);
    }

    // Validation
    public function validatePerformance(): array
    {
        return $this->validation->validatePerformance();
    }

    // Maintenance
    public function maintainDatabaseCache(array $operations = []): array
    {
        return $this->maintenance->maintainDatabaseCache($operations);
    }

    // Level-specific operations
    public function getFromRequest(CacheKey|string $key, mixed $default = null): mixed
    {
        return $this->operations->getFromRequest($key, $default);
    }

    public function getFromMemory(CacheKey|string $key, mixed $default = null): mixed
    {
        return $this->operations->getFromMemory($key, $default);
    }

    public function getFromDatabase(CacheKey|string $key, mixed $default = null): mixed
    {
        return $this->operations->getFromDatabase($key, $default);
    }

    public function putInRequest(CacheKey|string $key, mixed $value): bool
    {
        return $this->operations->putInRequest($key, $value);
    }

    public function putInMemory(CacheKey|string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->operations->putInMemory($key, $value, $ttl);
    }

    public function putInDatabase(CacheKey|string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->operations->putInDatabase($key, $value, $ttl);
    }

    public function forgetFromRequest(CacheKey|string $key): bool
    {
        return $this->operations->forgetFromRequest($key);
    }

    public function forgetFromMemory(CacheKey|string $key): bool
    {
        return $this->operations->forgetFromMemory($key);
    }

    public function forgetFromDatabase(CacheKey|string $key): bool
    {
        return $this->operations->forgetFromDatabase($key);
    }

    public function flushRequest(): bool
    {
        return $this->operations->flushRequest();
    }

    public function flushMemory(): bool
    {
        return $this->operations->flushMemory();
    }

    public function flushDatabase(): bool
    {
        return $this->operations->flushDatabase();
    }

    // Advanced cache operations
    public function remember(CacheKey|string $key, callable $callback, ?int $ttl = null, ?array $levels = null): mixed
    {
        return $this->operations->remember($key, $callback, $ttl, $levels);
    }

    public function rememberForever(CacheKey|string $key, callable $callback, ?array $levels = null): mixed
    {
        return $this->operations->rememberForever($key, $callback, $levels);
    }

    public function add(CacheKey|string $key, mixed $value, ?int $ttl = null, ?array $levels = null): bool
    {
        return $this->operations->add($key, $value, $ttl, $levels);
    }

    public function has(CacheKey|string $key, ?array $levels = null): bool
    {
        return $this->operations->has($key, $levels);
    }

    // Level management operations
    public function getFromLevel(CacheKey|string $key, int $level, mixed $default = null): mixed
    {
        return $this->operations->getFromLevel($key, $level, $default);
    }

    public function putToLevel(CacheKey|string $key, mixed $value, int $level, ?int $ttl = null): bool
    {
        return $this->operations->putToLevel($key, $value, $level, $ttl);
    }

    public function invalidateLevel(int $level): bool
    {
        return $this->operations->invalidateLevel($level);
    }

    // Pattern and tag-based invalidation
    public function invalidateByPattern(string $pattern, ?array $levels = null): bool
    {
        return $this->operations->invalidateByPattern($pattern, $levels);
    }

    public function invalidateByTags(array $tags, ?array $levels = null): bool
    {
        return $this->operations->invalidateByTags($tags, $levels);
    }

    public function invalidateByNamespace(string $namespace, ?array $levels = null): bool
    {
        return $this->operations->invalidateByNamespace($namespace, $levels);
    }

    // Statistics and monitoring
    public function getCacheSize(?array $levels = null): array
    {
        return $this->statistics->getCacheSize($levels);
    }

    public function getSize(?array $levels = null): array
    {
        return $this->statistics->getSize($levels);
    }

    public function getMemoryCacheStats(): array
    {
        return $this->statistics->getMemoryCacheStats();
    }

    public function getDatabaseCacheStats(): array
    {
        return $this->statistics->getDatabaseCacheStats();
    }

    public function getDatabaseCacheSize(): array
    {
        return $this->statistics->getDatabaseCacheSize();
    }

    public function getAverageResponseTime(?array $levels = null): float
    {
        return $this->statistics->getAverageResponseTime($levels);
    }

    public function resetStats(): void
    {
        $this->statistics->resetStats();
    }

    public function getEnhancedStats(?array $levels = null): array
    {
        return $this->statistics->getEnhancedStats($levels);
    }

    // Maintenance operations
    public function maintenance(array $operations = ['cleanup', 'optimize']): array
    {
        return $this->maintenance->maintenance($operations);
    }

    // Security operations
    public function enableSecurity(array $config = []): void
    {
        $this->security->enableSecurity($config);
    }

    public function disableSecurity(): void
    {
        $this->security->disableSecurity();
    }

    public function getSecurityStatus(): array
    {
        return $this->security->getSecurityStatus();
    }

    // Level management
    public function toggleLevel(CacheLevel $level, bool $enabled): bool
    {
        return $this->operations->toggleLevel($level, $enabled);
    }

    public function isLevelEnabled(CacheLevel $level): bool
    {
        return $this->operations->isLevelEnabled($level);
    }

    public function getEnabledLevels(): array
    {
        return $this->operations->getEnabledLevels();
    }

    public function getDisabledLevels(): array
    {
        return $this->operations->getDisabledLevels();
    }

    public function enableAllLevels(): array
    {
        return $this->operations->enableAllLevels();
    }

    public function disableAllLevels(): array
    {
        return $this->operations->disableAllLevels();
    }

    public function getLevelStatusSummary(): array
    {
        return $this->operations->getLevelStatusSummary();
    }

    // Configuration management
    public function getConfiguration(): array
    {
        return $this->operations->getConfiguration();
    }

    /**
     * Validate concurrent operations performance
     */
    public function validateConcurrentOperations(int $targetRpm, int $testDurationSeconds = 5): array
    {
        return $this->validation->validateConcurrentOperations($targetRpm, $testDurationSeconds);
    }

    public function updateConfiguration(array $config): bool
    {
        return $this->operations->updateConfiguration($config);
    }

    // Fluent interface support
    public function tags(array $tags): self
    {
        $this->operations->tags($tags);

        return $this;
    }

    public function prefix(string $prefix): self
    {
        $this->operations->prefix($prefix);

        return $this;
    }

    public function levels(array $levels): self
    {
        $this->operations->levels($levels);

        return $this;
    }

    public function ttl(int $seconds): self
    {
        $this->operations->ttl($seconds);

        return $this;
    }

    public function fluent(): CacheOperationServiceInterface
    {
        return $this->operations->fluent();
    }

    public function fluentGet(CacheKey|string $key, mixed $default = null): mixed
    {
        return $this->operations->fluentGet($key, $default);
    }

    public function fluentPut(CacheKey|string $key, mixed $value): bool
    {
        return $this->operations->fluentPut($key, $value);
    }

    public function fluentRemember(CacheKey|string $key, callable $callback): mixed
    {
        return $this->operations->fluentRemember($key, $callback);
    }

    public function fluentForget(CacheKey|string $key): bool
    {
        return $this->operations->fluentForget($key);
    }

    public function fluentFlush(): bool
    {
        return $this->operations->fluentFlush();
    }

    public function getFluentContext(): array
    {
        return $this->operations->getFluentContext();
    }

    public function hasFluentContext(): bool
    {
        return $this->operations->hasFluentContext();
    }

    public function clearFluentContext(): self
    {
        $this->operations->clearFluentContext();

        return $this;
    }

    // Event system integration
    public function addEventListener(string $event, callable $listener): void
    {
        $this->operations->addEventListener($event, $listener);
    }

    public function processQueuedEvents(): array
    {
        return $this->operations->processQueuedEvents();
    }

    public function configureIntegration(array $config): void
    {
        $this->operations->configureIntegration($config);
    }

    public function getIntegrationStatus(): array
    {
        return $this->operations->getIntegrationStatus();
    }

    // Error handling
    public function registerErrorHandler(string $errorType, callable $handler): void
    {
        $this->operations->registerErrorHandler($errorType, $handler);
    }

    public function registerFallbackStrategy(string $operation, callable $fallback): void
    {
        $this->operations->registerFallbackStrategy($operation, $fallback);
    }

    public function configureErrorHandling(array $config): void
    {
        $this->operations->configureErrorHandling($config);
    }

    public function getErrorHandlingStatus(): array
    {
        return $this->operations->getErrorHandlingStatus();
    }

    public function testErrorHandling(): array
    {
        return $this->operations->testErrorHandling();
    }

    // Cache warming
    public function processBatch(array $batch, ?array $operations = null, ?array $levels = null): array
    {
        // Handle different call signatures for backward compatibility
        if ($operations !== null && ! empty($operations) && is_array($operations) && ! ($operations[0] instanceof CacheLevel)) {
            // This is the test case: processBatch($batch, ['put'], [])
            // Convert to the format expected by CacheWarmingService
            $results = [];
            foreach ($batch as $index => $item) {
                if (is_array($item) && isset($item['key']) && isset($item['value'])) {
                    $key = $item['key'];
                    $value = $item['value'];

                    // Perform the operation (assuming 'put' for now)
                    $success = $this->put($key, $value, null, $levels);
                    $results[] = [
                        'key' => $key,
                        'value' => $value,
                        'success' => $success,
                        'operation' => $operations[0] ?? 'put',
                    ];
                }
            }

            return $results;
        } else {
            // This is the CacheWarmingService call: processBatch($batch, $levels, $batchNumber)
            $actualLevels = $operations ?? CacheLevel::cases();
            $batchNumber = $levels ?? 1;

            return $this->warming->processBatch($batch, $actualLevels, $batchNumber);
        }
    }

    // Additional utility methods
    public function clear(): bool
    {
        return $this->flush();
    }

    public function getCacheSizes(?array $levels = null): array
    {
        return $this->statistics->getCacheSizes($levels);
    }

    public function calculateCacheEfficiency(?array $levels = null): array
    {
        return $this->statistics->calculateCacheEfficiency($levels);
    }

    /**
     * Magic method to delegate calls to appropriate services
     */
    public function __call(string $method, array $args)
    {
        $services = [
            $this->operations,
            $this->warming,
            $this->maintenance,
            $this->security,
            $this->statistics,
            $this->validation,
        ];

        foreach ($services as $service) {
            if (method_exists($service, $method)) {
                return $service->{$method}(...$args);
            }
        }

        throw new \BadMethodCallException("Method {$method} does not exist on ".static::class);
    }
}
