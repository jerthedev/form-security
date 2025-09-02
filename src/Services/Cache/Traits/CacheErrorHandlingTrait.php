<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services\Cache\Traits;

/**
 * CacheErrorHandlingTrait
 */
trait CacheErrorHandlingTrait
{
    private array $errorHandlers = [];

    private array $fallbackStrategies = [];

    private array $circuitBreakers = [];

    public function registerErrorHandler(string $errorType, callable $handler): void
    {
        if (! isset($this->errorHandlers[$errorType])) {
            $this->errorHandlers[$errorType] = [];
        }
        $this->errorHandlers[$errorType][] = $handler;
    }

    private function handleCacheError(\Exception $error, string $operation, array $context = []): mixed
    {
        error_log("Cache error in {$operation}: ".$error->getMessage());

        return null;
    }
}
