<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Contracts\Cache;

/**
 * CacheValidationServiceInterface
 */
interface CacheValidationServiceInterface
{
    public function validatePerformance(): array;

    public function validateCacheCapacity(): mixed;

    public function validateConcurrentOperations(): mixed;
}
