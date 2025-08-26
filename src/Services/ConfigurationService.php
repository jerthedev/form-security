<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use JTD\FormSecurity\Contracts\ConfigurationContract;

/**
 * Configuration service for managing package settings and feature flags.
 *
 * This service provides a centralized way to manage configuration values,
 * feature toggles, and runtime settings with validation and caching.
 */
class ConfigurationService implements ConfigurationContract
{
    /**
     * Create a new configuration service instance.
     *
     * @param  ConfigRepository  $config  Laravel configuration repository
     */
    public function __construct(
        protected ConfigRepository $config
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
        // Handle empty key (get all config)
        if (empty($key)) {
            return $this->config->get('form-security', []);
        }

        return $this->config->get("form-security.{$key}", $default);
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
        $this->config->set("form-security.{$key}", $value);

        return true;
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
        $errors = [];
        $schema = $this->getSchema();

        foreach ($schema as $key => $rules) {
            if (! isset($config[$key]) && ($rules['required'] ?? false)) {
                $errors[$key] = "Required configuration key '{$key}' is missing";

                continue;
            }

            if (isset($config[$key])) {
                $value = $config[$key];
                $type = $rules['type'] ?? 'mixed';

                if (! $this->validateType($value, $type)) {
                    $errors[$key] = "Configuration key '{$key}' must be of type {$type}";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
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
        // In a real implementation, this would reload configuration from files
        // For now, we'll just return true as Laravel handles config caching
        return true;
    }

    /**
     * Get configuration schema for validation.
     *
     * @return array<string, mixed> Configuration schema definition
     */
    public function getSchema(): array
    {
        return [
            'enabled' => ['type' => 'boolean', 'required' => true],
            'features' => ['type' => 'array', 'required' => true],
            'spam_threshold' => ['type' => 'float', 'required' => true],
            'rate_limit' => ['type' => 'array', 'required' => true],
            'cache_ttl' => ['type' => 'integer', 'required' => false],
            'debug' => ['type' => 'boolean', 'required' => false],
        ];
    }

    /**
     * Validate a value against a type.
     *
     * @param  mixed  $value  Value to validate
     * @param  string  $type  Expected type
     * @return bool True if value matches type
     */
    protected function validateType(mixed $value, string $type): bool
    {
        return match ($type) {
            'boolean' => is_bool($value),
            'integer' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'string' => is_string($value),
            'array' => is_array($value),
            'mixed' => true,
            default => false,
        };
    }
}
