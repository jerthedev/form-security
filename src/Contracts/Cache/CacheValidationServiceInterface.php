<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Contracts\Cache;

/**
 * CacheValidationServiceInterface
 */
interface CacheValidationServiceInterface
{
    public function validatePerformance(): array;
    public function validateCacheCapacity(): array;
    public function validateConcurrentOperations(int $targetRpm = 10000, int $testDurationSeconds = 60): array;
    public function manageCapacity(array $options = []): array;
}
