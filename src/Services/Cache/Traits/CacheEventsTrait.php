<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services\Cache\Traits;

/**
 * CacheEventsTrait
 */
trait CacheEventsTrait
{
    private array $eventListeners = [];

    public function addEventListener(string $event, callable $listener): void
    {
        if (! isset($this->eventListeners[$event])) {
            $this->eventListeners[$event] = [];
        }
        $this->eventListeners[$event][] = $listener;
    }

    private function dispatchEvent(string $event, array $data = []): void
    {
        if (isset($this->eventListeners[$event])) {
            foreach ($this->eventListeners[$event] as $listener) {
                try {
                    $listener($data);
                } catch (\Exception $e) {
                    error_log('Cache event listener failed: '.$e->getMessage());
                }
            }
        }
    }
}
