<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Configuration cache invalidated event.
 *
 * This event is fired when configuration cache is invalidated,
 * enabling cache warming and performance monitoring.
 */
class ConfigurationCacheInvalidated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new configuration cache invalidated event instance.
     *
     * @param  array<string>  $keys  Cache keys that were invalidated
     * @param  string  $reason  Reason for cache invalidation
     * @param  array<string, mixed>  $context  Additional context
     * @param  \DateTimeImmutable  $timestamp  When the cache was invalidated
     */
    public function __construct(
        public readonly array $keys,
        public readonly string $reason = 'configuration_changed',
        public readonly array $context = [],
        public readonly \DateTimeImmutable $timestamp = new \DateTimeImmutable
    ) {}

    /**
     * Check if all configuration cache was invalidated.
     *
     * @return bool True if all cache was invalidated
     */
    public function isFullInvalidation(): bool
    {
        return in_array('form_security_config_all', $this->keys) ||
               in_array('*', $this->keys);
    }

    /**
     * Check if specific key cache was invalidated.
     *
     * @param  string  $key  Configuration key to check
     * @return bool True if key cache was invalidated
     */
    public function wasKeyInvalidated(string $key): bool
    {
        return in_array("form_security_config_{$key}", $this->keys) ||
               $this->isFullInvalidation();
    }

    /**
     * Get the number of invalidated keys.
     *
     * @return int Number of invalidated keys
     */
    public function getInvalidatedCount(): int
    {
        return count($this->keys);
    }

    /**
     * Get event summary for logging.
     *
     * @return array<string, mixed> Event summary
     */
    public function getSummary(): array
    {
        return [
            'invalidated_count' => $this->getInvalidatedCount(),
            'is_full_invalidation' => $this->isFullInvalidation(),
            'reason' => $this->reason,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'context' => $this->context,
        ];
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed> Array representation
     */
    public function toArray(): array
    {
        return [
            'keys' => $this->keys,
            'reason' => $this->reason,
            'context' => $this->context,
            'timestamp' => $this->timestamp->format('c'),
        ];
    }
}
