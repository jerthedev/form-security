<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Log;
use JTD\FormSecurity\Contracts\ConfigurationManagerInterface;
use JTD\FormSecurity\Contracts\ConfigurationValidatorInterface;
use JTD\FormSecurity\Enums\ConfigurationScope;
use JTD\FormSecurity\Enums\ConfigurationSource;
use JTD\FormSecurity\Events\ConfigurationChanged;
use JTD\FormSecurity\ValueObjects\ConfigurationValue;

/**
 * Enhanced configuration manager with hierarchical loading and caching.
 *
 * This service provides comprehensive configuration management including
 * hierarchical loading, validation, caching, and runtime updates.
 */
class ConfigurationManager implements ConfigurationManagerInterface
{
    /**
     * Runtime configuration storage.
     *
     * @var array<string, ConfigurationValue>
     */
    protected array $runtimeConfig = [];

    /**
     * Configuration change history.
     *
     * @var array<array<string, mixed>>
     */
    protected array $changeHistory = [];

    /**
     * Performance metrics.
     *
     * @var array<string, mixed>
     */
    protected array $performanceMetrics = [
        'cache_hits' => 0,
        'cache_misses' => 0,
        'validation_calls' => 0,
        'load_times' => [],
    ];

    /**
     * Create a new configuration manager instance.
     *
     * @param  ConfigRepository  $config  Laravel configuration repository
     * @param  CacheRepository  $cache  Cache repository
     * @param  ConfigurationValidatorInterface  $validator  Configuration validator
     * @param  EventDispatcher  $events  Event dispatcher
     */
    public function __construct(
        protected ConfigRepository $config,
        protected CacheRepository $cache,
        protected ConfigurationValidatorInterface $validator,
        protected EventDispatcher $events
    ) {}

    /**
     * Get configuration value by key.
     *
     * @param  string  $key  Configuration key using dot notation
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $configValue = $this->getValue($key, $default);

        return $configValue->getDecryptedValue();
    }

    /**
     * Get configuration value with hierarchical resolution.
     *
     * @param  string  $key  Configuration key using dot notation
     * @param  mixed  $default  Default value if key doesn't exist
     * @param  bool  $useCache  Whether to use cached values
     * @return ConfigurationValue Configuration value object
     */
    public function getValue(string $key, mixed $default = null, bool $useCache = true): ConfigurationValue
    {
        $startTime = microtime(true);

        try {
            // Check runtime configuration first
            if (isset($this->runtimeConfig[$key])) {
                $this->recordPerformanceMetric('cache_hits');

                return $this->runtimeConfig[$key];
            }

            // Try cache if enabled
            if ($useCache) {
                $cacheKey = $this->getCacheKey($key);
                $cached = $this->cache->get($cacheKey);

                if ($cached !== null) {
                    $this->recordPerformanceMetric('cache_hits');

                    return ConfigurationValue::fromArray($cached);
                }
            }

            $this->recordPerformanceMetric('cache_misses');

            // Load from sources with hierarchy
            $sources = ConfigurationSource::getByPriority();
            $configValue = $this->getFromSources($key, array_map(fn ($s) => $s->value, $sources));

            // Use default if no value found
            if ($configValue->value === null && $default !== null) {
                $configValue = ConfigurationValue::create($default, [
                    'source' => ConfigurationSource::DEFAULT->value,
                ]);
            }

            // Cache the result if cacheable
            if ($useCache && $configValue->scope->supportsCaching()) {
                $this->cacheConfiguration($key, $configValue->scope->getDefaultTtl());
            }

            return $configValue;
        } finally {
            $this->recordLoadTime(microtime(true) - $startTime);
        }
    }

    /**
     * Set configuration value at runtime.
     *
     * @param  string  $key  Configuration key using dot notation
     * @param  mixed  $value  Value to set
     * @return bool True if value was successfully set
     */
    public function set(string $key, mixed $value): bool
    {
        return $this->setValue($key, $value, false, true);
    }

    /**
     * Set configuration value with validation and caching.
     *
     * @param  string  $key  Configuration key using dot notation
     * @param  mixed  $value  Value to set
     * @param  bool  $persist  Whether to persist the change
     * @param  bool  $validate  Whether to validate the value
     * @return bool True if value was successfully set
     */
    public function setValue(string $key, mixed $value, bool $persist = false, bool $validate = true): bool
    {
        try {
            // Get old value for change tracking
            $oldValue = $this->runtimeConfig[$key] ?? null;

            // Validate if requested
            if ($validate) {
                $this->recordPerformanceMetric('validation_calls');
                $validationResult = $this->validator->validateValue($key, $value);

                if (! $validationResult['valid']) {
                    Log::warning('Configuration validation failed', [
                        'key' => $key,
                        'errors' => $validationResult['errors'],
                    ]);

                    return false;
                }
            }

            // Create configuration value object
            $configValue = ConfigurationValue::create($value, [
                'source' => ConfigurationSource::RUNTIME->value,
                'scope' => ConfigurationScope::APPLICATION->value,
            ]);

            // Store in runtime configuration
            $this->runtimeConfig[$key] = $configValue;

            // Invalidate cache
            $this->invalidateCache($key);

            // Record change in history
            $this->recordChange($key, $oldValue, $configValue);

            // Fire configuration changed event
            if ($oldValue) {
                $this->events->dispatch(ConfigurationChanged::updated($key, $oldValue, $configValue));
            } else {
                $this->events->dispatch(ConfigurationChanged::created($key, $configValue));
            }

            // Persist if requested (implementation depends on storage backend)
            if ($persist) {
                $this->persistConfiguration($key, $configValue);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to set configuration value', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if a feature is enabled.
     *
     * @param  string  $feature  Feature name to check
     * @return bool True if feature is enabled
     */
    public function isFeatureEnabled(string $feature): bool
    {
        return (bool) $this->get("features.{$feature}", false);
    }

    /**
     * Enable or disable a feature.
     *
     * @param  string  $feature  Feature name to toggle
     * @param  bool  $enabled  Whether to enable or disable
     * @return bool True if feature was successfully toggled
     */
    public function toggleFeature(string $feature, bool $enabled): bool
    {
        return $this->set("features.{$feature}", $enabled);
    }

    /**
     * Validate configuration values.
     *
     * @param  array<string, mixed>  $config  Configuration array to validate
     * @return array<string, mixed> Validation results with errors if any
     */
    public function validateConfig(array $config): array
    {
        return $this->validator->validateConfiguration($config);
    }

    /**
     * Get all enabled features.
     *
     * @return array<string> List of enabled feature names
     */
    public function getEnabledFeatures(): array
    {
        $features = $this->get('features', []);

        return array_keys(array_filter($features, fn ($enabled) => (bool) $enabled));
    }

    /**
     * Refresh configuration from source.
     *
     * @return bool True if configuration was successfully refreshed
     */
    public function refresh(): bool
    {
        try {
            // Clear runtime configuration
            $this->runtimeConfig = [];

            // Clear cache
            $this->invalidateCache();

            // Reload from Laravel config
            $this->config->set('form-security', $this->config->get('form-security', []));

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to refresh configuration', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get configuration schema for validation.
     *
     * @return array<string, mixed> Configuration schema definition
     */
    public function getSchema(): array
    {
        return [
            'enabled' => ['type' => 'boolean', 'required' => true, 'default' => true],
            'features' => ['type' => 'array', 'required' => true, 'default' => []],
            'spam_threshold' => ['type' => 'float', 'required' => true, 'default' => 0.7],
            'rate_limit' => ['type' => 'array', 'required' => true, 'default' => []],
            'performance' => ['type' => 'array', 'required' => false, 'default' => []],
            'logging' => ['type' => 'array', 'required' => false, 'default' => []],
            'debug' => ['type' => 'array', 'required' => false, 'default' => []],
        ];
    }

    /**
     * Get configuration from multiple sources with hierarchy.
     *
     * @param  string  $key  Configuration key
     * @param  array<string>  $sources  Sources to check in order
     * @return ConfigurationValue Configuration value from first available source
     */
    public function getFromSources(string $key, array $sources = []): ConfigurationValue
    {
        if (empty($sources)) {
            $sources = array_map(fn ($s) => $s->value, ConfigurationSource::getByPriority());
        }

        foreach ($sources as $sourceName) {
            $source = ConfigurationSource::tryFrom($sourceName);
            if (! $source) {
                continue;
            }

            $value = $this->loadFromSpecificSource($key, $source);
            if ($value !== null) {
                return ConfigurationValue::create($value, [
                    'source' => $source->value,
                    'scope' => ConfigurationScope::APPLICATION->value,
                ]);
            }
        }

        // Return null value if not found in any source
        return ConfigurationValue::create(null, [
            'source' => ConfigurationSource::DEFAULT->value,
        ]);
    }

    /**
     * Load configuration from external source.
     *
     * @param  string  $source  Source identifier (file, database, api, etc.)
     * @param  array<string, mixed>  $options  Source-specific options
     * @return bool True if configuration was successfully loaded
     */
    public function loadFromSource(string $source, array $options = []): bool
    {
        try {
            $sourceEnum = ConfigurationSource::tryFrom($source);
            if (! $sourceEnum) {
                return false;
            }

            // Implementation would depend on the specific source
            // For now, we'll just log the attempt
            Log::info('Loading configuration from source', [
                'source' => $source,
                'options' => $options,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to load configuration from source', [
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Cache configuration values for performance.
     *
     * @param  string|null  $key  Specific key to cache, or null for all
     * @param  int|null  $ttl  Time to live in seconds
     * @return bool True if caching was successful
     */
    public function cacheConfiguration(?string $key = null, ?int $ttl = null): bool
    {
        try {
            if ($key) {
                // Cache specific key
                $configValue = $this->runtimeConfig[$key] ?? $this->getValue($key, null, false);
                $cacheKey = $this->getCacheKey($key);

                return $this->cache->put($cacheKey, $configValue->toArray(), $ttl ?? 3600);
            } else {
                // Cache all runtime configuration
                foreach ($this->runtimeConfig as $configKey => $configValue) {
                    $cacheKey = $this->getCacheKey($configKey);
                    $this->cache->put($cacheKey, $configValue->toArray(), $ttl ?? 3600);
                }

                return true;
            }
        } catch (\Exception $e) {
            Log::error('Failed to cache configuration', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Invalidate configuration cache.
     *
     * @param  string|null  $key  Specific key to invalidate, or null for all
     * @return bool True if cache was successfully invalidated
     */
    public function invalidateCache(?string $key = null): bool
    {
        try {
            if ($key) {
                $cacheKey = $this->getCacheKey($key);

                return $this->cache->forget($cacheKey);
            } else {
                // Clear all configuration cache
                $this->cache->flush();

                return true;
            }
        } catch (\Exception $e) {
            Log::error('Failed to invalidate configuration cache', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get cache key for configuration.
     *
     * @param  string  $key  Configuration key
     * @return string Cache key
     */
    protected function getCacheKey(string $key): string
    {
        return "form_security_config_{$key}";
    }

    /**
     * Load value from specific source.
     *
     * @param  string  $key  Configuration key
     * @param  ConfigurationSource  $source  Configuration source
     * @return mixed Configuration value or null if not found
     */
    protected function loadFromSpecificSource(string $key, ConfigurationSource $source): mixed
    {
        return match ($source) {
            ConfigurationSource::RUNTIME => $this->runtimeConfig[$key]->value ?? null,
            ConfigurationSource::ENVIRONMENT => env(strtoupper(str_replace('.', '_', "FORM_SECURITY_{$key}"))),
            ConfigurationSource::FILE => $this->config->get("form-security.{$key}"),
            ConfigurationSource::CACHE => $this->cache->get($this->getCacheKey($key)),
            default => null,
        };
    }

    /**
     * Record configuration change in history.
     *
     * @param  string  $key  Configuration key
     * @param  ConfigurationValue|null  $oldValue  Previous value
     * @param  ConfigurationValue  $newValue  New value
     */
    protected function recordChange(string $key, ?ConfigurationValue $oldValue, ConfigurationValue $newValue): void
    {
        $this->changeHistory[] = [
            'key' => $key,
            'old_value' => $oldValue?->getSafeValue(),
            'new_value' => $newValue->getSafeValue(),
            'timestamp' => new \DateTimeImmutable,
            'user_id' => auth()->id(),
        ];

        // Keep only last 1000 changes
        if (count($this->changeHistory) > 1000) {
            $this->changeHistory = array_slice($this->changeHistory, -1000);
        }
    }

    /**
     * Record performance metric.
     *
     * @param  string  $metric  Metric name
     */
    protected function recordPerformanceMetric(string $metric): void
    {
        if (isset($this->performanceMetrics[$metric])) {
            $this->performanceMetrics[$metric]++;
        }
    }

    /**
     * Record load time.
     *
     * @param  float  $time  Load time in seconds
     */
    protected function recordLoadTime(float $time): void
    {
        $this->performanceMetrics['load_times'][] = $time;

        // Keep only last 100 load times
        if (count($this->performanceMetrics['load_times']) > 100) {
            $this->performanceMetrics['load_times'] = array_slice($this->performanceMetrics['load_times'], -100);
        }
    }

    /**
     * Get configuration with environment variable fallback.
     *
     * @param  string  $key  Configuration key
     * @param  string|null  $envKey  Environment variable key
     * @param  mixed  $default  Default value
     * @return ConfigurationValue Configuration value
     */
    public function getWithEnvFallback(string $key, ?string $envKey = null, mixed $default = null): ConfigurationValue
    {
        // Try configuration first
        $configValue = $this->getValue($key, null, true);

        if ($configValue->value !== null) {
            return $configValue;
        }

        // Try environment variable
        $envKey = $envKey ?? strtoupper(str_replace('.', '_', "FORM_SECURITY_{$key}"));
        $envValue = env($envKey);

        if ($envValue !== null) {
            return ConfigurationValue::create($envValue, [
                'source' => ConfigurationSource::ENVIRONMENT->value,
                'scope' => ConfigurationScope::APPLICATION->value,
            ]);
        }

        // Use default
        return ConfigurationValue::create($default, [
            'source' => ConfigurationSource::DEFAULT->value,
        ]);
    }

    /**
     * Validate configuration against schema with detailed results.
     *
     * @param  array<string, mixed>  $config  Configuration to validate
     * @param  string|null  $schemaPath  Path to specific schema section
     * @return array<string, mixed> Detailed validation results
     */
    public function validateWithSchema(array $config, ?string $schemaPath = null): array
    {
        $schema = $this->getSchema();

        if ($schemaPath) {
            $schema = data_get($schema, $schemaPath, []);
        }

        return $this->validator->validateAgainstSchema($config, $schema);
    }

    /**
     * Get configuration change history.
     *
     * @param  string|null  $key  Specific key to get history for
     * @param  int  $limit  Maximum number of changes to return
     * @return array<array<string, mixed>> Configuration change history
     */
    public function getChangeHistory(?string $key = null, int $limit = 100): array
    {
        $history = $this->changeHistory;

        if ($key) {
            $history = array_filter($history, fn ($change) => $change['key'] === $key);
        }

        // Sort by timestamp descending (newest first)
        usort($history, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_slice($history, 0, $limit);
    }

    /**
     * Export configuration to array with optional filtering.
     *
     * @param  array<string>  $keys  Specific keys to export
     * @param  bool  $includeSensitive  Whether to include sensitive values
     * @return array<string, mixed> Exported configuration
     */
    public function exportConfiguration(array $keys = [], bool $includeSensitive = false): array
    {
        $config = [];

        if (empty($keys)) {
            // Export all configuration
            $allConfig = $this->config->get('form-security', []);

            foreach ($allConfig as $key => $value) {
                $configValue = $this->getValue($key, $value);
                $config[$key] = $includeSensitive ? $configValue->getDecryptedValue() : $configValue->getSafeValue();
            }

            // Add runtime configuration
            foreach ($this->runtimeConfig as $key => $configValue) {
                $config[$key] = $includeSensitive ? $configValue->getDecryptedValue() : $configValue->getSafeValue();
            }
        } else {
            // Export specific keys
            foreach ($keys as $key) {
                $configValue = $this->getValue($key);
                $config[$key] = $includeSensitive ? $configValue->getDecryptedValue() : $configValue->getSafeValue();
            }
        }

        return $config;
    }

    /**
     * Import configuration from array with validation.
     *
     * @param  array<string, mixed>  $config  Configuration to import
     * @param  bool  $merge  Whether to merge with existing config
     * @param  bool  $validate  Whether to validate before import
     * @return bool True if import was successful
     */
    public function importConfiguration(array $config, bool $merge = true, bool $validate = true): bool
    {
        try {
            // Validate if requested
            if ($validate) {
                $validationResult = $this->validateConfig($config);
                if (! $validationResult['valid']) {
                    Log::error('Configuration import validation failed', [
                        'errors' => $validationResult['errors'],
                    ]);

                    return false;
                }
            }

            // Import configuration
            foreach ($config as $key => $value) {
                if (! $this->setValue($key, $value, false, false)) {
                    Log::warning('Failed to import configuration key', ['key' => $key]);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to import configuration', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get configuration performance metrics.
     *
     * @return array<string, mixed> Performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $loadTimes = $this->performanceMetrics['load_times'];

        return [
            'cache_hits' => $this->performanceMetrics['cache_hits'],
            'cache_misses' => $this->performanceMetrics['cache_misses'],
            'cache_hit_ratio' => $this->calculateCacheHitRatio(),
            'validation_calls' => $this->performanceMetrics['validation_calls'],
            'average_load_time' => empty($loadTimes) ? 0 : array_sum($loadTimes) / count($loadTimes),
            'max_load_time' => empty($loadTimes) ? 0 : max($loadTimes),
            'min_load_time' => empty($loadTimes) ? 0 : min($loadTimes),
            'total_requests' => $this->performanceMetrics['cache_hits'] + $this->performanceMetrics['cache_misses'],
        ];
    }

    /**
     * Warm up configuration cache.
     *
     * @param  array<string>  $keys  Specific keys to warm up
     * @return bool True if cache warming was successful
     */
    public function warmCache(array $keys = []): bool
    {
        try {
            if (empty($keys)) {
                // Warm up common configuration keys
                $keys = [
                    'enabled',
                    'features',
                    'spam_threshold',
                    'rate_limit',
                    'performance.cache_ttl',
                ];
            }

            foreach ($keys as $key) {
                $this->getValue($key, null, false); // Load without cache, then cache it
                $this->cacheConfiguration($key);
            }

            Log::info('Configuration cache warmed up', ['keys' => $keys]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to warm up configuration cache', [
                'keys' => $keys,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if configuration is encrypted.
     *
     * @param  string  $key  Configuration key to check
     * @return bool True if configuration value is encrypted
     */
    public function isEncrypted(string $key): bool
    {
        $configValue = $this->getValue($key);

        return $configValue->isEncrypted;
    }

    /**
     * Encrypt sensitive configuration value.
     *
     * @param  string  $key  Configuration key
     * @param  mixed  $value  Value to encrypt
     * @return bool True if encryption was successful
     */
    public function encryptValue(string $key, mixed $value): bool
    {
        try {
            $encryptedValue = ConfigurationValue::createEncrypted($value, [
                'source' => ConfigurationSource::RUNTIME->value,
            ]);

            $this->runtimeConfig[$key] = $encryptedValue;
            $this->invalidateCache($key);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to encrypt configuration value', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Decrypt sensitive configuration value.
     *
     * @param  string  $key  Configuration key
     * @return ConfigurationValue Decrypted configuration value
     */
    public function decryptValue(string $key): ConfigurationValue
    {
        $configValue = $this->getValue($key);

        if (! $configValue->isEncrypted) {
            return $configValue;
        }

        try {
            $decryptedValue = $configValue->getDecryptedValue();

            return $configValue->withValue($decryptedValue)->withMetadata([
                'decrypted_at' => new \DateTimeImmutable,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt configuration value', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return $configValue;
        }
    }

    /**
     * Calculate cache hit ratio.
     *
     * @return float Cache hit ratio (0.0 to 1.0)
     */
    protected function calculateCacheHitRatio(): float
    {
        $hits = $this->performanceMetrics['cache_hits'];
        $misses = $this->performanceMetrics['cache_misses'];
        $total = $hits + $misses;

        return $total > 0 ? $hits / $total : 0.0;
    }

    /**
     * Persist configuration to storage.
     *
     * @param  string  $key  Configuration key
     * @param  ConfigurationValue  $value  Configuration value
     * @return bool True if persistence was successful
     */
    protected function persistConfiguration(string $key, ConfigurationValue $value): bool
    {
        // Implementation would depend on the persistence backend
        // For now, we'll just log the attempt
        Log::info('Persisting configuration', [
            'key' => $key,
            'value' => $value->getSafeValue(),
        ]);

        return true;
    }
}
