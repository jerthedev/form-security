<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JTD\FormSecurity\ValueObjects\ConfigurationValue;

/**
 * Configuration changed event.
 *
 * This event is fired when configuration values are changed,
 * enabling cache invalidation and notification systems.
 */
class ConfigurationChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new configuration changed event instance.
     *
     * @param  string  $key  Configuration key that changed
     * @param  ConfigurationValue|null  $oldValue  Previous value
     * @param  ConfigurationValue  $newValue  New value
     * @param  string  $changeType  Type of change (created, updated, deleted)
     * @param  array<string, mixed>  $context  Additional context
     * @param  string|null  $userId  User who made the change
     * @param  \DateTimeImmutable  $timestamp  When the change occurred
     */
    public function __construct(
        public readonly string $key,
        public readonly ?ConfigurationValue $oldValue,
        public readonly ConfigurationValue $newValue,
        public readonly string $changeType = 'updated',
        public readonly array $context = [],
        public readonly ?string $userId = null,
        public readonly \DateTimeImmutable $timestamp = new \DateTimeImmutable
    ) {}

    /**
     * Create a configuration created event.
     *
     * @param  string  $key  Configuration key
     * @param  ConfigurationValue  $value  New value
     * @param  array<string, mixed>  $context  Additional context
     * @param  string|null  $userId  User who created the configuration
     * @return self New event instance
     */
    public static function created(
        string $key,
        ConfigurationValue $value,
        array $context = [],
        ?string $userId = null
    ): self {
        return new self(
            key: $key,
            oldValue: null,
            newValue: $value,
            changeType: 'created',
            context: $context,
            userId: $userId
        );
    }

    /**
     * Create a configuration updated event.
     *
     * @param  string  $key  Configuration key
     * @param  ConfigurationValue  $oldValue  Previous value
     * @param  ConfigurationValue  $newValue  New value
     * @param  array<string, mixed>  $context  Additional context
     * @param  string|null  $userId  User who updated the configuration
     * @return self New event instance
     */
    public static function updated(
        string $key,
        ConfigurationValue $oldValue,
        ConfigurationValue $newValue,
        array $context = [],
        ?string $userId = null
    ): self {
        return new self(
            key: $key,
            oldValue: $oldValue,
            newValue: $newValue,
            changeType: 'updated',
            context: $context,
            userId: $userId
        );
    }

    /**
     * Create a configuration deleted event.
     *
     * @param  string  $key  Configuration key
     * @param  ConfigurationValue  $oldValue  Previous value
     * @param  array<string, mixed>  $context  Additional context
     * @param  string|null  $userId  User who deleted the configuration
     * @return self New event instance
     */
    public static function deleted(
        string $key,
        ConfigurationValue $oldValue,
        array $context = [],
        ?string $userId = null
    ): self {
        return new self(
            key: $key,
            oldValue: $oldValue,
            newValue: ConfigurationValue::create(null),
            changeType: 'deleted',
            context: $context,
            userId: $userId
        );
    }

    /**
     * Check if this is a creation event.
     *
     * @return bool True if this is a creation event
     */
    public function isCreation(): bool
    {
        return $this->changeType === 'created';
    }

    /**
     * Check if this is an update event.
     *
     * @return bool True if this is an update event
     */
    public function isUpdate(): bool
    {
        return $this->changeType === 'updated';
    }

    /**
     * Check if this is a deletion event.
     *
     * @return bool True if this is a deletion event
     */
    public function isDeletion(): bool
    {
        return $this->changeType === 'deleted';
    }

    /**
     * Check if the value type changed.
     *
     * @return bool True if value type changed
     */
    public function hasTypeChanged(): bool
    {
        return $this->oldValue && $this->oldValue->type !== $this->newValue->type;
    }

    /**
     * Check if the value source changed.
     *
     * @return bool True if value source changed
     */
    public function hasSourceChanged(): bool
    {
        return $this->oldValue && $this->oldValue->source !== $this->newValue->source;
    }

    /**
     * Check if the value scope changed.
     *
     * @return bool True if value scope changed
     */
    public function hasScopeChanged(): bool
    {
        return $this->oldValue && $this->oldValue->scope !== $this->newValue->scope;
    }

    /**
     * Check if the encryption status changed.
     *
     * @return bool True if encryption status changed
     */
    public function hasEncryptionChanged(): bool
    {
        return $this->oldValue && $this->oldValue->isEncrypted !== $this->newValue->isEncrypted;
    }

    /**
     * Get the affected cache keys.
     *
     * @return array<string> Cache keys that should be invalidated
     */
    public function getAffectedCacheKeys(): array
    {
        $keys = [
            "form_security_config_{$this->key}",
            'form_security_config_all',
        ];

        // Add scope-specific cache keys
        $keys[] = $this->newValue->scope->getCachePrefix()."_{$this->key}";

        // Add wildcard patterns for hierarchical keys
        $keyParts = explode('.', $this->key);
        $currentKey = '';

        foreach ($keyParts as $part) {
            $currentKey = $currentKey ? "{$currentKey}.{$part}" : $part;
            $keys[] = "form_security_config_{$currentKey}.*";
        }

        return array_unique($keys);
    }

    /**
     * Get event summary for logging.
     *
     * @return array<string, mixed> Event summary
     */
    public function getSummary(): array
    {
        return [
            'key' => $this->key,
            'change_type' => $this->changeType,
            'old_value' => $this->oldValue?->getSafeValue(),
            'new_value' => $this->newValue->getSafeValue(),
            'type_changed' => $this->hasTypeChanged(),
            'source_changed' => $this->hasSourceChanged(),
            'scope_changed' => $this->hasScopeChanged(),
            'encryption_changed' => $this->hasEncryptionChanged(),
            'user_id' => $this->userId,
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
            'key' => $this->key,
            'old_value' => $this->oldValue?->toArray(),
            'new_value' => $this->newValue->toArray(),
            'change_type' => $this->changeType,
            'context' => $this->context,
            'user_id' => $this->userId,
            'timestamp' => $this->timestamp->format('c'),
        ];
    }
}
