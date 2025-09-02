<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services\Cache\Traits;

use JTD\FormSecurity\Enums\CacheLevel;
use JTD\FormSecurity\Services\Cache\Support\RequestLevelCacheRepository;

/**
 * CacheUtilitiesTrait
 */
trait CacheUtilitiesTrait
{
    private array $repositories = [];
    private array $requestCache = []; // In-memory request-level cache
    private array $keyTracker = []; // Track keys stored in each level for pattern matching
    private array $tagTracker = []; // Track which keys have which tags

    private function initializeRepositories(): void
    {
        foreach (CacheLevel::cases() as $level) {
            if ($level === CacheLevel::REQUEST) {
                // Request level uses in-memory array, not Laravel cache
                $this->repositories[$level->value] = new RequestLevelCacheRepository($this->requestCache);
            } else {
                try {
                    // Create isolated cache stores for testing to ensure proper isolation
                    if (app()->environment('testing')) {
                        $this->repositories[$level->value] = $this->createIsolatedCacheStore($level);
                    } else {
                        $this->repositories[$level->value] = $this->cacheManager->store($level->getDriverName());
                    }
                } catch (\Exception $e) {
                    $this->repositories[$level->value] = null;
                }
            }
        }
    }

    /**
     * Create an isolated cache store for testing
     */
    private function createIsolatedCacheStore(CacheLevel $level): \Illuminate\Contracts\Cache\Repository
    {
        // Create a unique array store for each level to ensure isolation
        $config = [
            'driver' => 'array',
            'serialize' => false,
        ];

        $store = new \Illuminate\Cache\ArrayStore($config['serialize']);
        return new \Illuminate\Cache\Repository($store);
    }

    public function isLevelEnabled(CacheLevel $level): bool
    {
        return isset($this->repositories[$level->value]) && $this->repositories[$level->value] !== null;
    }
}
