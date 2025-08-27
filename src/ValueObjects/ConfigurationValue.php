<?php

declare(strict_types=1);

namespace JTD\FormSecurity\ValueObjects;

use Illuminate\Support\Facades\Crypt;
use JTD\FormSecurity\Enums\ConfigurationScope;
use JTD\FormSecurity\Enums\ConfigurationSource;
use JTD\FormSecurity\Enums\ConfigurationType;

/**
 * Configuration value object with encryption support and metadata.
 *
 * This value object encapsulates configuration values with their metadata,
 * including type information, source, scope, and encryption status.
 */
readonly class ConfigurationValue
{
    /**
     * Create a new configuration value instance.
     *
     * @param  mixed  $value  The configuration value
     * @param  ConfigurationType  $type  Value type
     * @param  ConfigurationSource  $source  Value source
     * @param  ConfigurationScope  $scope  Value scope
     * @param  bool  $isEncrypted  Whether value is encrypted
     * @param  array<string, mixed>  $metadata  Additional metadata
     * @param  \DateTimeImmutable|null  $createdAt  Creation timestamp
     * @param  \DateTimeImmutable|null  $updatedAt  Last update timestamp
     * @param  int|null  $ttl  Time to live in seconds
     */
    public function __construct(
        public mixed $value,
        public ConfigurationType $type = ConfigurationType::MIXED,
        public ConfigurationSource $source = ConfigurationSource::DEFAULT,
        public ConfigurationScope $scope = ConfigurationScope::APPLICATION,
        public bool $isEncrypted = false,
        public array $metadata = [],
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
        public ?int $ttl = null
    ) {}

    /**
     * Create a configuration value from raw data.
     *
     * @param  mixed  $value  Raw value
     * @param  array<string, mixed>  $options  Configuration options
     * @return self New configuration value instance
     */
    public static function create(mixed $value, array $options = []): self
    {
        return new self(
            value: $value,
            type: ConfigurationType::tryFrom($options['type'] ?? 'mixed') ?? ConfigurationType::MIXED,
            source: ConfigurationSource::tryFrom($options['source'] ?? 'default') ?? ConfigurationSource::DEFAULT,
            scope: ConfigurationScope::tryFrom($options['scope'] ?? 'application') ?? ConfigurationScope::APPLICATION,
            isEncrypted: $options['encrypted'] ?? false,
            metadata: $options['metadata'] ?? [],
            createdAt: isset($options['created_at']) ? new \DateTimeImmutable($options['created_at']) : new \DateTimeImmutable,
            updatedAt: isset($options['updated_at']) ? new \DateTimeImmutable($options['updated_at']) : null,
            ttl: $options['ttl'] ?? null
        );
    }

    /**
     * Create an encrypted configuration value.
     *
     * @param  mixed  $value  Value to encrypt
     * @param  array<string, mixed>  $options  Configuration options
     * @return self New encrypted configuration value
     */
    public static function createEncrypted(mixed $value, array $options = []): self
    {
        $encryptedValue = Crypt::encrypt($value);

        return self::create($encryptedValue, array_merge($options, [
            'encrypted' => true,
            'type' => ConfigurationType::ENCRYPTED->value,
        ]));
    }

    /**
     * Get the decrypted value if encrypted.
     *
     * @return mixed Decrypted value or original value if not encrypted
     */
    public function getDecryptedValue(): mixed
    {
        if (! $this->isEncrypted) {
            return $this->value;
        }

        try {
            return Crypt::decrypt($this->value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Get the raw value (encrypted if applicable).
     *
     * @return mixed Raw value
     */
    public function getRawValue(): mixed
    {
        return $this->value;
    }

    /**
     * Get the safe value for display (masked if sensitive).
     *
     * @return mixed Safe value for display
     */
    public function getSafeValue(): mixed
    {
        if ($this->isEncrypted || $this->isSensitive()) {
            return '***ENCRYPTED***';
        }

        return $this->value;
    }

    /**
     * Check if the value is sensitive.
     *
     * @return bool True if value is sensitive
     */
    public function isSensitive(): bool
    {
        return $this->isEncrypted ||
               $this->type === ConfigurationType::ENCRYPTED ||
               in_array('sensitive', $this->metadata['tags'] ?? []);
    }

    /**
     * Check if the value is expired.
     *
     * @return bool True if value is expired
     */
    public function isExpired(): bool
    {
        if ($this->ttl === null) {
            return false;
        }

        $expiresAt = ($this->updatedAt ?? $this->createdAt ?? new \DateTimeImmutable)
            ->add(new \DateInterval("PT{$this->ttl}S"));

        return $expiresAt < new \DateTimeImmutable;
    }

    /**
     * Get the expiration timestamp.
     *
     * @return \DateTimeImmutable|null Expiration timestamp or null if no TTL
     */
    public function getExpiresAt(): ?\DateTimeImmutable
    {
        if ($this->ttl === null) {
            return null;
        }

        return ($this->updatedAt ?? $this->createdAt ?? new \DateTimeImmutable)
            ->add(new \DateInterval("PT{$this->ttl}S"));
    }

    /**
     * Create a new instance with updated value.
     *
     * @param  mixed  $newValue  New value
     * @return self New instance with updated value
     */
    public function withValue(mixed $newValue): self
    {
        return new self(
            value: $newValue,
            type: $this->type,
            source: $this->source,
            scope: $this->scope,
            isEncrypted: $this->isEncrypted,
            metadata: $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable,
            ttl: $this->ttl
        );
    }

    /**
     * Create a new instance with additional metadata.
     *
     * @param  array<string, mixed>  $additionalMetadata  Additional metadata
     * @return self New instance with merged metadata
     */
    public function withMetadata(array $additionalMetadata): self
    {
        return new self(
            value: $this->value,
            type: $this->type,
            source: $this->source,
            scope: $this->scope,
            isEncrypted: $this->isEncrypted,
            metadata: array_merge($this->metadata, $additionalMetadata),
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            ttl: $this->ttl
        );
    }

    /**
     * Convert to array representation.
     *
     * @param  bool  $includeSensitive  Whether to include sensitive values
     * @return array<string, mixed> Array representation
     */
    public function toArray(bool $includeSensitive = false): array
    {
        return [
            'value' => $includeSensitive ? $this->getDecryptedValue() : $this->getSafeValue(),
            'type' => $this->type->value,
            'source' => $this->source->value,
            'scope' => $this->scope->value,
            'is_encrypted' => $this->isEncrypted,
            'is_sensitive' => $this->isSensitive(),
            'is_expired' => $this->isExpired(),
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'expires_at' => $this->getExpiresAt()?->format('Y-m-d H:i:s'),
            'ttl' => $this->ttl,
        ];
    }

    /**
     * Convert to JSON representation.
     *
     * @param  bool  $includeSensitive  Whether to include sensitive values
     * @return string JSON representation
     */
    public function toJson(bool $includeSensitive = false): string
    {
        return json_encode($this->toArray($includeSensitive), JSON_THROW_ON_ERROR);
    }

    /**
     * Create instance from array data.
     *
     * @param  array<string, mixed>  $data  Array data
     * @return self New configuration value instance
     */
    public static function fromArray(array $data): self
    {
        return new self(
            value: $data['value'],
            type: ConfigurationType::tryFrom($data['type'] ?? 'mixed') ?? ConfigurationType::MIXED,
            source: ConfigurationSource::tryFrom($data['source'] ?? 'default') ?? ConfigurationSource::DEFAULT,
            scope: ConfigurationScope::tryFrom($data['scope'] ?? 'application') ?? ConfigurationScope::APPLICATION,
            isEncrypted: $data['is_encrypted'] ?? false,
            metadata: $data['metadata'] ?? [],
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null,
            ttl: $data['ttl'] ?? null
        );
    }
}
