<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Contracts;

/**
 * Configuration management contract for the FormSecurity package.
 *
 * This contract defines methods for managing package configuration,
 * feature flags, and runtime settings with validation and caching.
 */
interface ConfigurationContract
{
    /**
     * Get configuration value by key.
     *
     * @param  string  $key  Configuration key using dot notation
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set configuration value at runtime.
     *
     * @param  string  $key  Configuration key using dot notation
     * @param  mixed  $value  Value to set
     * @return bool True if value was successfully set
     */
    public function set(string $key, mixed $value): bool;

    /**
     * Check if a feature is enabled.
     *
     * @param  string  $feature  Feature name to check
     * @return bool True if feature is enabled
     */
    public function isFeatureEnabled(string $feature): bool;

    /**
     * Enable or disable a feature.
     *
     * @param  string  $feature  Feature name to toggle
     * @param  bool  $enabled  Whether to enable or disable
     * @return bool True if feature was successfully toggled
     */
    public function toggleFeature(string $feature, bool $enabled): bool;

    /**
     * Validate configuration values.
     *
     * @param  array<string, mixed>  $config  Configuration array to validate
     * @return array<string, mixed> Validation results with errors if any
     */
    public function validateConfig(array $config): array;

    /**
     * Get all enabled features.
     *
     * @return array<string> List of enabled feature names
     */
    public function getEnabledFeatures(): array;

    /**
     * Refresh configuration from source.
     *
     * @return bool True if configuration was successfully refreshed
     */
    public function refresh(): bool;

    /**
     * Get configuration schema for validation.
     *
     * @return array<string, mixed> Configuration schema definition
     */
    public function getSchema(): array;
}
