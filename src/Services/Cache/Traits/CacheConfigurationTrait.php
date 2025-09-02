<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services\Cache\Traits;

/**
 * CacheConfigurationTrait
 */
trait CacheConfigurationTrait
{
    private array $configuration = [];

    public function configure(array $config): void
    {
        $this->configuration = array_merge($this->configuration, $config);
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }
}
