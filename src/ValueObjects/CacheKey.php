<?php

declare(strict_types=1);

/**
 * Value Object File: CacheKey.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Value object for hierarchical cache key management
 * with namespace support, tagging, and intelligent key generation.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-003-multi-level-caching-system.md
 * @see docs/Planning/Sprints/004-caching-cli-integration.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1014-multi-level-caching-system.md
 */

namespace JTD\FormSecurity\ValueObjects;

use JTD\FormSecurity\Enums\CacheLevel;

/**
 * CacheKey Value Object
 *
 * Immutable value object that represents a cache key with hierarchical
 * namespace support, tagging capabilities, and intelligent key generation
 * for the multi-level caching system.
 */
readonly class CacheKey
{
    /**
     * Create a new cache key instance.
     *
     * @param  string  $key  Base cache key
     * @param  string  $namespace  Key namespace for organization
     * @param  array<string>  $tags  Associated tags for invalidation
     * @param  array<string, mixed>  $context  Additional context for key generation
     * @param  string|null  $prefix  Optional prefix for the key
     * @param  int|null  $ttl  Time to live in seconds
     * @param  array<CacheLevel>|null  $levels  Preferred cache levels
     */
    public function __construct(
        public string $key,
        public string $namespace = 'default',
        public array $tags = [],
        public array $context = [],
        public ?string $prefix = null,
        public ?int $ttl = null,
        public ?array $levels = null
    ) {}

    /**
     * Create a cache key from a simple string
     */
    public static function make(string $key, string $namespace = 'default', array $tags = []): self
    {
        return new self($key, $namespace, $tags);
    }

    /**
     * Create a cache key for IP reputation lookups
     */
    public static function forIpReputation(string $ipAddress): self
    {
        return new self(
            key: "ip:{$ipAddress}",
            namespace: 'ip_reputation',
            tags: ['ip_reputation', 'security'],
            context: ['ip' => $ipAddress],
            ttl: 3600 // 1 hour
        );
    }

    /**
     * Create a cache key for geolocation data
     */
    public static function forGeolocation(string $ipAddress): self
    {
        return new self(
            key: "geo:{$ipAddress}",
            namespace: 'geolocation',
            tags: ['geolocation', 'ip_data'],
            context: ['ip' => $ipAddress],
            ttl: 86400 // 24 hours
        );
    }

    /**
     * Create a cache key for spam patterns
     */
    public static function forSpamPattern(string $type, ?string $identifier = null): self
    {
        $key = $identifier ? "pattern:{$type}:{$identifier}" : "patterns:{$type}";

        return new self(
            key: $key,
            namespace: 'spam_pattern',
            tags: ['spam_pattern', 'detection', $type],
            context: ['type' => $type, 'identifier' => $identifier],
            ttl: 1800 // 30 minutes
        );
    }

    /**
     * Create a cache key for configuration values
     */
    public static function forConfiguration(string $configKey): self
    {
        return new self(
            key: "config:{$configKey}",
            namespace: 'configuration',
            tags: ['configuration', 'settings'],
            context: ['config_key' => $configKey],
            ttl: 3600 // 1 hour
        );
    }

    /**
     * Create a cache key for analytics data
     */
    public static function forAnalytics(string $metric, array $dimensions = []): self
    {
        $dimensionString = empty($dimensions) ? '' : ':'.implode(':', $dimensions);

        return new self(
            key: "analytics:{$metric}{$dimensionString}",
            namespace: 'analytics',
            tags: ['analytics', 'metrics', $metric],
            context: ['metric' => $metric, 'dimensions' => $dimensions],
            ttl: 1800 // 30 minutes
        );
    }

    /**
     * Get the full cache key string with namespace and prefix
     */
    public function toString(): string
    {
        $parts = [];

        if ($this->prefix) {
            $parts[] = $this->prefix;
        }

        $parts[] = 'form_security';
        $parts[] = $this->namespace;
        $parts[] = $this->key;

        return implode(':', $parts);
    }

    /**
     * Get the cache key as a string (magic method)
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Create a new cache key with additional tags
     *
     * @param  array<string>  $tags  Additional tags to add
     */
    public function withTags(array $tags): self
    {
        return new self(
            key: $this->key,
            namespace: $this->namespace,
            tags: array_unique([...$this->tags, ...$tags]),
            context: $this->context,
            prefix: $this->prefix,
            ttl: $this->ttl,
            levels: $this->levels
        );
    }

    /**
     * Create a new cache key with a different namespace
     */
    public function withNamespace(string $namespace): self
    {
        return new self(
            key: $this->key,
            namespace: $namespace,
            tags: $this->tags,
            context: $this->context,
            prefix: $this->prefix,
            ttl: $this->ttl,
            levels: $this->levels
        );
    }

    /**
     * Create a new cache key with a prefix
     */
    public function withPrefix(string $prefix): self
    {
        return new self(
            key: $this->key,
            namespace: $this->namespace,
            tags: $this->tags,
            context: $this->context,
            prefix: $prefix,
            ttl: $this->ttl,
            levels: $this->levels
        );
    }

    /**
     * Create a new cache key with a specific TTL
     */
    public function withTtl(int $ttl): self
    {
        return new self(
            key: $this->key,
            namespace: $this->namespace,
            tags: $this->tags,
            context: $this->context,
            prefix: $this->prefix,
            ttl: $ttl,
            levels: $this->levels
        );
    }

    /**
     * Create a new cache key with specific cache levels
     *
     * @param  array<CacheLevel>  $levels  Preferred cache levels
     */
    public function withLevels(array $levels): self
    {
        return new self(
            key: $this->key,
            namespace: $this->namespace,
            tags: $this->tags,
            context: $this->context,
            prefix: $this->prefix,
            ttl: $this->ttl,
            levels: $levels
        );
    }

    /**
     * Check if this cache key has a specific tag
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    /**
     * Check if this cache key has any of the given tags
     *
     * @param  array<string>  $tags  Tags to check for
     */
    public function hasAnyTag(array $tags): bool
    {
        return ! empty(array_intersect($this->tags, $tags));
    }

    /**
     * Check if this cache key has all of the given tags
     *
     * @param  array<string>  $tags  Tags to check for
     */
    public function hasAllTags(array $tags): bool
    {
        return empty(array_diff($tags, $this->tags));
    }

    /**
     * Get the hash of this cache key for consistent storage
     */
    public function getHash(): string
    {
        return hash('sha256', $this->toString());
    }

    /**
     * Get a short hash for display purposes
     */
    public function getShortHash(): string
    {
        return substr($this->getHash(), 0, 8);
    }

    /**
     * Convert to array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'namespace' => $this->namespace,
            'tags' => $this->tags,
            'context' => $this->context,
            'prefix' => $this->prefix,
            'ttl' => $this->ttl,
            'levels' => $this->levels ? array_map(fn ($level) => $level->value, $this->levels) : null,
            'full_key' => $this->toString(),
            'hash' => $this->getHash(),
        ];
    }

    /**
     * Create instance from array data
     *
     * @param  array<string, mixed>  $data  Array data
     */
    public static function fromArray(array $data): self
    {
        $levels = null;
        if (isset($data['levels']) && is_array($data['levels'])) {
            $levels = array_map(fn ($level) => CacheLevel::from($level), $data['levels']);
        }

        return new self(
            key: $data['key'],
            namespace: $data['namespace'] ?? 'default',
            tags: $data['tags'] ?? [],
            context: $data['context'] ?? [],
            prefix: $data['prefix'] ?? null,
            ttl: $data['ttl'] ?? null,
            levels: $levels
        );
    }

    /**
     * Check if this cache key is valid
     */
    public function isValid(): bool
    {
        return ! empty($this->key) && ! empty($this->namespace);
    }

    /**
     * Get the estimated size of this cache key in bytes
     */
    public function getEstimatedSize(): int
    {
        return strlen($this->toString()) +
               array_sum(array_map('strlen', $this->tags)) +
               strlen(serialize($this->context));
    }

    /**
     * Check if this cache key is hierarchical (contains parent-child relationship)
     */
    public function isHierarchical(): bool
    {
        return isset($this->context['parent']) && isset($this->context['child']);
    }

    /**
     * Check if this cache key is versioned
     */
    public function isVersioned(): bool
    {
        return isset($this->context['version']) || str_contains($this->key, ':v');
    }

    /**
     * Check if this cache key is time-based
     */
    public function isTimeBased(): bool
    {
        return isset($this->context['time_unit']) || $this->hasTag('time_based');
    }

    /**
     * Get the parent key if this is a hierarchical key
     */
    public function getParent(): ?string
    {
        return $this->context['parent'] ?? null;
    }

    /**
     * Get the child key if this is a hierarchical key
     */
    public function getChild(): ?string
    {
        return $this->context['child'] ?? null;
    }

    /**
     * Get the version if this is a versioned key
     */
    public function getVersion(): ?string
    {
        return $this->context['version'] ?? null;
    }

    /**
     * Create a child key from this parent key
     */
    public function createChild(string $childKey, array $additionalContext = []): self
    {
        return new self(
            key: $this->key.':'.$childKey,
            namespace: $this->namespace,
            tags: array_unique([...$this->tags, 'hierarchical']),
            context: array_merge($this->context, [
                'parent' => $this->key,
                'child' => $childKey,
            ], $additionalContext),
            prefix: $this->prefix,
            ttl: $this->ttl,
            levels: $this->levels
        );
    }

    /**
     * Create a sibling key (same parent, different child)
     */
    public function createSibling(string $siblingKey): self
    {
        if (! $this->isHierarchical()) {
            throw new \InvalidArgumentException('Cannot create sibling from non-hierarchical key');
        }

        $parent = $this->getParent();

        return new self(
            key: $parent.':'.$siblingKey,
            namespace: $this->namespace,
            tags: $this->tags,
            context: array_merge($this->context, [
                'child' => $siblingKey,
            ]),
            prefix: $this->prefix,
            ttl: $this->ttl,
            levels: $this->levels
        );
    }
}
