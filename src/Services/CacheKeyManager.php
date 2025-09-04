<?php

declare(strict_types=1);

/**
 * Service File: CacheKeyManager.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Advanced cache key management service with hierarchical
 * organization, naming strategies, and key lifecycle management.
 */

namespace JTD\FormSecurity\Services;

use JTD\FormSecurity\ValueObjects\CacheKey;

/**
 * CacheKeyManager Service
 *
 * Provides advanced cache key management functionality including
 * hierarchical organization, naming strategies, key validation,
 * and lifecycle management for the multi-level caching system.
 */
class CacheKeyManager
{
    /**
     * Registered key generators
     *
     * @var array<string, callable>
     */
    private array $keyGenerators = [];

    /**
     * Key naming strategies
     *
     * @var array<string, callable>
     */
    private array $namingStrategies = [];

    /**
     * Key validation rules
     *
     * @var array<string, callable>
     */
    private array $validationRules = [];

    public function __construct()
    {
        $this->initializeDefaultGenerators();
        $this->initializeDefaultStrategies();
        $this->initializeDefaultValidationRules();
    }

    /**
     * Generate a cache key using a registered generator
     */
    public function generate(string $generator, array $parameters = []): CacheKey
    {
        if (! isset($this->keyGenerators[$generator])) {
            throw new \InvalidArgumentException("Unknown key generator: {$generator}");
        }

        return $this->keyGenerators[$generator]($parameters);
    }

    /**
     * Create a hierarchical cache key with parent-child relationship
     */
    public function createHierarchical(string $parent, string $child, string|array $childOrContext = [], array $context = []): CacheKey
    {
        // Handle different parameter patterns
        if (is_string($childOrContext)) {
            // Pattern: createHierarchical(parent, child, grandchild, context)
            $hierarchicalKey = "{$parent}:{$child}:{$childOrContext}";
            $components = [$parent, $child, $childOrContext];
        } else {
            // Pattern: createHierarchical(parent, child, context)
            $hierarchicalKey = "{$parent}:{$child}";
            $components = [$parent, $child];
            $context = $childOrContext;
        }

        return new CacheKey(
            key: $hierarchicalKey,
            namespace: $context['namespace'] ?? 'hierarchical',
            tags: array_merge(['hierarchical'], $components, $context['tags'] ?? []),
            context: array_merge(['parent' => $parent, 'child' => $child, 'components' => $components], $context),
            prefix: $context['prefix'] ?? null,
            ttl: $context['ttl'] ?? null,
            levels: $context['levels'] ?? null
        );
    }

    /**
     * Create a versioned cache key
     */
    public function createVersioned(string $baseKey, string $version, array $context = []): CacheKey
    {
        $versionedKey = "{$baseKey}:v{$version}";

        return new CacheKey(
            key: $versionedKey,
            namespace: $context['namespace'] ?? 'versioned',
            tags: array_merge(['versioned', $baseKey], $context['tags'] ?? []),
            context: array_merge(['base_key' => $baseKey, 'version' => $version], $context),
            prefix: $context['prefix'] ?? null,
            ttl: $context['ttl'] ?? null,
            levels: $context['levels'] ?? null
        );
    }

    /**
     * Create a time-based cache key
     */
    public function createTimeBased(string $baseKey, string $timeUnit = 'hour', array $context = []): CacheKey
    {
        $timestamp = match ($timeUnit) {
            'minute' => now()->format('Y-m-d-H-i'),
            'hour' => now()->format('Y-m-d-H'),
            'day' => now()->format('Y-m-d'),
            'week' => now()->format('Y-W'),
            'month' => now()->format('Y-m'),
            default => now()->format('Y-m-d-H'),
        };

        $timeBasedKey = "{$baseKey}:{$timestamp}";

        return new CacheKey(
            key: $timeBasedKey,
            namespace: $context['namespace'] ?? 'time_based',
            tags: array_merge(['time_based', $timeUnit, $baseKey], $context['tags'] ?? []),
            context: array_merge(['base_key' => $baseKey, 'time_unit' => $timeUnit, 'timestamp' => $timestamp], $context),
            prefix: $context['prefix'] ?? null,
            ttl: $context['ttl'] ?? $this->getTimeBasedTtl($timeUnit),
            levels: $context['levels'] ?? null
        );
    }

    /**
     * Validate a cache key according to registered rules
     */
    public function validate(CacheKey $key): array
    {
        $errors = [];

        foreach ($this->validationRules as $ruleName => $rule) {
            $result = $rule($key);
            if ($result !== true) {
                $errors[$ruleName] = $result;
            }
        }

        return $errors;
    }

    /**
     * Check if a cache key is valid
     */
    public function isValid(CacheKey $key): bool
    {
        return empty($this->validate($key));
    }

    /**
     * Register a custom key generator
     */
    public function registerGenerator(string $name, callable $generator): void
    {
        $this->keyGenerators[$name] = $generator;
    }

    /**
     * Register a custom naming strategy
     */
    public function registerNamingStrategy(string $name, callable $strategy): void
    {
        $this->namingStrategies[$name] = $strategy;
    }

    /**
     * Register a custom validation rule
     */
    public function registerValidationRule(string $name, callable $rule): void
    {
        $this->validationRules[$name] = $rule;
    }

    /**
     * Get all registered generators
     */
    public function getGenerators(): array
    {
        return array_keys($this->keyGenerators);
    }

    /**
     * Get all registered naming strategies
     */
    public function getNamingStrategies(): array
    {
        return array_keys($this->namingStrategies);
    }

    /**
     * Get all registered validation rules
     */
    public function getValidationRules(): array
    {
        return array_keys($this->validationRules);
    }

    /**
     * Create a namespaced cache key
     */
    public function createNamespaced(string $key, string $namespace, array $context = []): CacheKey
    {
        return new CacheKey(
            key: $key,
            namespace: $namespace,
            tags: array_merge([$namespace], $context['tags'] ?? []),
            context: $context,
            prefix: $context['prefix'] ?? null,
            ttl: $context['ttl'] ?? null,
            levels: $context['levels'] ?? null
        );
    }

    /**
     * Create a contextual cache key with additional metadata
     */
    public function createContextual(string $key, string $namespace = 'contextual', array $context = []): CacheKey
    {
        return new CacheKey(
            key: $key,
            namespace: $namespace,
            tags: array_merge(['contextual'], $context['tags'] ?? []),
            context: $context,
            prefix: $context['prefix'] ?? null,
            ttl: $context['ttl'] ?? null,
            levels: $context['levels'] ?? null
        );
    }

    /**
     * Create a tagged cache key
     */
    public function createTagged(string $key, string|array $namespaceOrTags, array $tagsOrContext = [], array $context = []): CacheKey
    {
        // Handle different parameter patterns
        if (is_string($namespaceOrTags)) {
            // Pattern: createTagged(key, namespace, tags, context)
            $namespace = $namespaceOrTags;
            $tags = $tagsOrContext;
        } else {
            // Pattern: createTagged(key, tags, context)
            $namespace = 'tagged';
            $tags = $namespaceOrTags;
            $context = $tagsOrContext;
        }

        return new CacheKey(
            key: $key,
            namespace: $namespace,
            tags: array_merge(['tagged'], $tags),
            context: $context,
            prefix: $context['prefix'] ?? null,
            ttl: $context['ttl'] ?? null,
            levels: $context['levels'] ?? null
        );
    }

    /**
     * Create an expiring cache key with specific TTL
     */
    public function createExpiring(string $key, string|int $namespaceOrTtl, int|array $ttlOrContext = [], array $context = []): CacheKey
    {
        // Handle different parameter patterns
        if (is_string($namespaceOrTtl)) {
            // Pattern: createExpiring(key, namespace, ttl, context)
            $namespace = $namespaceOrTtl;
            $ttl = (int) $ttlOrContext;
        } else {
            // Pattern: createExpiring(key, ttl, context)
            $namespace = 'expiring';
            $ttl = $namespaceOrTtl;
            $context = is_array($ttlOrContext) ? $ttlOrContext : [];
        }

        return new CacheKey(
            key: $key,
            namespace: $namespace,
            tags: array_merge(['expiring'], $context['tags'] ?? []),
            context: $context,
            prefix: $context['prefix'] ?? null,
            ttl: $ttl,
            levels: $context['levels'] ?? null
        );
    }

    /**
     * Create a cache key from a pattern
     */
    public function createFromPattern(string $pattern, array $replacements = [], array $context = []): CacheKey
    {
        $key = $pattern;
        foreach ($replacements as $placeholder => $value) {
            $key = str_replace("{{$placeholder}}", (string) $value, $key);
        }

        return new CacheKey(
            key: $key,
            namespace: $context['namespace'] ?? 'pattern',
            tags: array_merge(['pattern'], $context['tags'] ?? []),
            context: array_merge(['pattern' => $pattern, 'replacements' => $replacements], $context),
            prefix: $context['prefix'] ?? null,
            ttl: $context['ttl'] ?? null,
            levels: $context['levels'] ?? null
        );
    }

    /**
     * Extract components from a hierarchical cache key
     */
    public function extractComponents(CacheKey $key): array
    {
        return explode(':', $key->key);
    }

    /**
     * Build a cache key from components
     */
    public function buildFromComponents(array $components, string $namespace = 'hierarchical', array $context = []): CacheKey
    {
        $key = implode(':', $components);

        return new CacheKey(
            key: $key,
            namespace: $namespace,
            tags: array_merge(['hierarchical'], $context['tags'] ?? []),
            context: array_merge(['components' => $components], $context),
            prefix: $context['prefix'] ?? null,
            ttl: $context['ttl'] ?? null,
            levels: $context['levels'] ?? null
        );
    }

    /**
     * Normalize a cache key according to naming strategies
     */
    public function normalize(string|CacheKey $key, string $strategy = 'snake_case'): string|CacheKey
    {
        if (! isset($this->namingStrategies[$strategy])) {
            throw new \InvalidArgumentException("Unknown naming strategy: {$strategy}");
        }

        if (is_string($key)) {
            // Simple string normalization
            return $this->namingStrategies[$strategy]($key);
        }

        // CacheKey normalization
        $normalizedKey = $this->namingStrategies[$strategy]($key->key);

        return new CacheKey(
            key: $normalizedKey,
            namespace: $key->namespace,
            tags: $key->tags,
            context: $key->context,
            prefix: $key->prefix,
            ttl: $key->ttl,
            levels: $key->levels
        );
    }

    /**
     * Initialize default key generators
     */
    private function initializeDefaultGenerators(): void
    {
        $this->keyGenerators['ip_reputation'] = fn (array $params) => CacheKey::forIpReputation($params['ip'] ?? '');

        $this->keyGenerators['spam_pattern'] = fn (array $params) => CacheKey::forSpamPattern($params['type'] ?? '', $params['identifier'] ?? null);

        $this->keyGenerators['geolocation'] = fn (array $params) => CacheKey::forGeolocation($params['ip'] ?? '');

        $this->keyGenerators['configuration'] = fn (array $params) => CacheKey::forConfiguration($params['key'] ?? '');

        $this->keyGenerators['analytics'] = fn (array $params) => CacheKey::forAnalytics($params['metric'] ?? '', $params['dimensions'] ?? []);
    }

    /**
     * Initialize default naming strategies
     */
    private function initializeDefaultStrategies(): void
    {
        $this->namingStrategies['snake_case'] = fn (string $input) => strtolower(preg_replace('/\s+/', '_', $input));

        $this->namingStrategies['kebab_case'] = fn (string $input) => strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $input));

        $this->namingStrategies['camel_case'] = fn (string $input) => lcfirst(str_replace(' ', '', ucwords(preg_replace('/[^A-Za-z0-9]+/', ' ', $input))));
    }

    /**
     * Initialize default validation rules
     */
    private function initializeDefaultValidationRules(): void
    {
        $this->validationRules['key_length'] = function (CacheKey $key): bool|string {
            $maxLength = 250; // Redis key limit

            return strlen($key->toString()) <= $maxLength ?: "Key too long (max {$maxLength} characters)";
        };

        $this->validationRules['key_characters'] = function (CacheKey $key): bool|string {
            $pattern = '/^[a-zA-Z0-9:_\-\.]+$/';

            return preg_match($pattern, $key->toString()) ? true : 'Key contains invalid characters';
        };

        $this->validationRules['namespace_required'] = function (CacheKey $key): bool|string {
            return ! empty($key->namespace) ?: 'Namespace is required';
        };

        $this->validationRules['ttl_reasonable'] = function (CacheKey $key): bool|string {
            if ($key->ttl === null) {
                return true;
            }
            $maxTtl = 604800; // 7 days

            if ($key->ttl < 0) {
                return 'TTL cannot be negative';
            }

            return $key->ttl <= $maxTtl ?: "TTL too high (max {$maxTtl} seconds)";
        };
    }

    /**
     * Get appropriate TTL for time-based keys
     */
    private function getTimeBasedTtl(string $timeUnit): int
    {
        return match ($timeUnit) {
            'minute' => 120, // 2 minutes
            'hour' => 3900, // 65 minutes
            'day' => 86400 + 3600, // 25 hours
            'week' => 604800 + 86400, // 8 days
            'month' => 2678400 + 86400, // 31 + 1 days
            default => 3900,
        };
    }
}
