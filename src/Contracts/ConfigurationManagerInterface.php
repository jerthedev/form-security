<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Contracts;

use JTD\FormSecurity\ValueObjects\ConfigurationValue;

/**
 * Enhanced configuration management interface for hierarchical configuration
 * with validation, caching, and runtime updates.
 *
 * This interface extends the basic ConfigurationContract with advanced features
 * including hierarchical loading, secure value handling, and performance optimization.
 */
interface ConfigurationManagerInterface extends ConfigurationContract
{
    /**
     * Get configuration value with hierarchical resolution.
     *
     * @param  string  $key  Configuration key using dot notation
     * @param  mixed  $default  Default value if key doesn't exist
     * @param  bool  $useCache  Whether to use cached values
     * @return ConfigurationValue Configuration value object
     */
    public function getValue(string $key, mixed $default = null, bool $useCache = true): ConfigurationValue;

    /**
     * Set configuration value with validation and caching.
     *
     * @param  string  $key  Configuration key using dot notation
     * @param  mixed  $value  Value to set
     * @param  bool  $persist  Whether to persist the change
     * @param  bool  $validate  Whether to validate the value
     * @return bool True if value was successfully set
     */
    public function setValue(string $key, mixed $value, bool $persist = false, bool $validate = true): bool;

    /**
     * Get configuration from multiple sources with hierarchy.
     *
     * @param  string  $key  Configuration key
     * @param  array<string>  $sources  Sources to check in order
     * @return ConfigurationValue Configuration value from first available source
     */
    public function getFromSources(string $key, array $sources = []): ConfigurationValue;

    /**
     * Load configuration from external source.
     *
     * @param  string  $source  Source identifier (file, database, api, etc.)
     * @param  array<string, mixed>  $options  Source-specific options
     * @return bool True if configuration was successfully loaded
     */
    public function loadFromSource(string $source, array $options = []): bool;

    /**
     * Cache configuration values for performance.
     *
     * @param  string|null  $key  Specific key to cache, or null for all
     * @param  int|null  $ttl  Time to live in seconds
     * @return bool True if caching was successful
     */
    public function cacheConfiguration(?string $key = null, ?int $ttl = null): bool;

    /**
     * Invalidate configuration cache.
     *
     * @param  string|null  $key  Specific key to invalidate, or null for all
     * @return bool True if cache was successfully invalidated
     */
    public function invalidateCache(?string $key = null): bool;

    /**
     * Get configuration with environment variable fallback.
     *
     * @param  string  $key  Configuration key
     * @param  string|null  $envKey  Environment variable key
     * @param  mixed  $default  Default value
     * @return ConfigurationValue Configuration value
     */
    public function getWithEnvFallback(string $key, ?string $envKey = null, mixed $default = null): ConfigurationValue;

    /**
     * Validate configuration against schema with detailed results.
     *
     * @param  array<string, mixed>  $config  Configuration to validate
     * @param  string|null  $schemaPath  Path to specific schema section
     * @return array<string, mixed> Detailed validation results
     */
    public function validateWithSchema(array $config, ?string $schemaPath = null): array;

    /**
     * Get configuration change history.
     *
     * @param  string|null  $key  Specific key to get history for
     * @param  int  $limit  Maximum number of changes to return
     * @return array<array<string, mixed>> Configuration change history
     */
    public function getChangeHistory(?string $key = null, int $limit = 100): array;

    /**
     * Export configuration to array with optional filtering.
     *
     * @param  array<string>  $keys  Specific keys to export
     * @param  bool  $includeSensitive  Whether to include sensitive values
     * @return array<string, mixed> Exported configuration
     */
    public function exportConfiguration(array $keys = [], bool $includeSensitive = false): array;

    /**
     * Import configuration from array with validation.
     *
     * @param  array<string, mixed>  $config  Configuration to import
     * @param  bool  $merge  Whether to merge with existing config
     * @param  bool  $validate  Whether to validate before import
     * @return bool True if import was successful
     */
    public function importConfiguration(array $config, bool $merge = true, bool $validate = true): bool;

    /**
     * Get configuration performance metrics.
     *
     * @return array<string, mixed> Performance metrics
     */
    public function getPerformanceMetrics(): array;

    /**
     * Warm up configuration cache.
     *
     * @param  array<string>  $keys  Specific keys to warm up
     * @return bool True if cache warming was successful
     */
    public function warmCache(array $keys = []): bool;

    /**
     * Check if configuration is encrypted.
     *
     * @param  string  $key  Configuration key to check
     * @return bool True if configuration value is encrypted
     */
    public function isEncrypted(string $key): bool;

    /**
     * Encrypt sensitive configuration value.
     *
     * @param  string  $key  Configuration key
     * @param  mixed  $value  Value to encrypt
     * @return bool True if encryption was successful
     */
    public function encryptValue(string $key, mixed $value): bool;

    /**
     * Decrypt sensitive configuration value.
     *
     * @param  string  $key  Configuration key
     * @return ConfigurationValue Decrypted configuration value
     */
    public function decryptValue(string $key): ConfigurationValue;
}
