<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services;

use Illuminate\Support\Facades\Log;
use JTD\FormSecurity\Contracts\ConfigurationValidatorInterface;
use JTD\FormSecurity\Enums\ConfigurationType;
use JTD\FormSecurity\Events\ConfigurationValidationFailed;
use JTD\FormSecurity\ValueObjects\ConfigurationSchema;

/**
 * Configuration validation service with comprehensive business rules.
 *
 * This service provides comprehensive validation for configuration values
 * including type checking, business rules, and security constraints.
 */
class ConfigurationValidator implements ConfigurationValidatorInterface
{
    /**
     * Custom validation rules.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $customRules = [];

    /**
     * Error message templates.
     *
     * @var array<string, string>
     */
    protected array $errorMessages = [
        'required' => 'Configuration :key is required',
        'type' => 'Configuration :key must be of type :type',
        'min' => 'Configuration :key must be at least :min',
        'max' => 'Configuration :key must not exceed :max',
        'min_length' => 'Configuration :key must be at least :min characters long',
        'max_length' => 'Configuration :key must not exceed :max characters',
        'pattern' => 'Configuration :key does not match required pattern',
        'allowed_values' => 'Configuration :key must be one of: :values',
        'business_rule' => 'Configuration :key violates business rule: :rule',
        'security_constraint' => 'Configuration :key violates security constraint: :constraint',
        'performance_constraint' => 'Configuration :key violates performance constraint: :constraint',
    ];

    /**
     * Configuration schema definitions.
     *
     * @var array<string, ConfigurationSchema>
     */
    protected array $schemas = [];

    /**
     * Create a new configuration validator instance.
     */
    public function __construct()
    {
        $this->loadDefaultSchemas();
    }

    /**
     * Validate a single configuration value.
     *
     * @param  string  $key  Configuration key
     * @param  mixed  $value  Value to validate
     * @param  array<string, mixed>  $context  Additional validation context
     * @return array<string, mixed> Validation result with errors if any
     */
    public function validateValue(string $key, mixed $value, array $context = []): array
    {
        $errors = [];

        // Get schema for the key
        $schema = $this->getValidationSchema($key);

        if (! empty($schema)) {
            $schemaObj = ConfigurationSchema::fromArray($key, $schema);
            $result = $schemaObj->validate($value);

            if (! $result['valid']) {
                $errors = array_merge($errors, $result['errors']);
            }
        }

        // Apply custom validation rules
        foreach ($this->customRules as $pattern => $rules) {
            if ($this->matchesPattern($key, $pattern)) {
                foreach ($rules as $rule) {
                    $result = $rule['validator']($value, $context);

                    if ($result !== true) {
                        $message = is_string($result) ? $result : ($rule['message'] ?? 'Custom validation failed');
                        $errors[] = $this->formatErrorMessage($message, ['key' => $key]);
                    }
                }
            }
        }

        // Fire validation failed event if there are errors
        if (! empty($errors)) {
            ConfigurationValidationFailed::dispatch(
                $key,
                $value,
                $errors,
                $context
            );
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'key' => $key,
            'value' => is_scalar($value) ? $value : gettype($value),
        ];
    }

    /**
     * Validate entire configuration array.
     *
     * @param  array<string, mixed>  $config  Configuration to validate
     * @param  array<string, mixed>  $options  Validation options
     * @return array<string, mixed> Comprehensive validation results
     */
    public function validateConfiguration(array $config, array $options = []): array
    {
        $results = [];
        $allErrors = [];
        $validCount = 0;

        foreach ($config as $key => $value) {
            $result = $this->validateValue($key, $value, $options['context'] ?? []);
            $results[$key] = $result;

            if ($result['valid']) {
                $validCount++;
            } else {
                $allErrors[$key] = $result['errors'];
            }
        }

        // Validate business rules across the entire configuration
        $businessRuleResults = $this->validateBusinessRules($config);
        if (! $businessRuleResults['valid']) {
            $allErrors['_business_rules'] = $businessRuleResults['errors'];
        }

        // Validate performance constraints
        $performanceResults = $this->validatePerformanceConstraints($config);
        if (! $performanceResults['valid']) {
            $allErrors['_performance'] = $performanceResults['errors'];
        }

        $isValid = empty($allErrors);

        return [
            'valid' => $isValid,
            'errors' => $allErrors,
            'results' => $results,
            'summary' => [
                'total_keys' => count($config),
                'valid_keys' => $validCount,
                'invalid_keys' => count($config) - $validCount,
                'error_count' => array_sum(array_map('count', $allErrors)),
            ],
        ];
    }

    /**
     * Validate configuration against schema.
     *
     * @param  array<string, mixed>  $config  Configuration to validate
     * @param  array<string, mixed>  $schema  Schema definition
     * @return array<string, mixed> Schema validation results
     */
    public function validateAgainstSchema(array $config, array $schema): array
    {
        $errors = [];

        // Check required fields
        foreach ($schema as $key => $rules) {
            if (($rules['required'] ?? false) && ! isset($config[$key])) {
                $errors[$key] = $this->formatErrorMessage('required', ['key' => $key]);
            }
        }

        // Validate existing fields
        foreach ($config as $key => $value) {
            if (isset($schema[$key])) {
                $schemaObj = ConfigurationSchema::fromArray($key, $schema[$key]);
                $result = $schemaObj->validate($value);

                if (! $result['valid']) {
                    $errors[$key] = $result['errors'];
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Add custom validation rule.
     *
     * @param  string  $key  Configuration key pattern
     * @param  callable  $validator  Validation function
     * @param  string|null  $message  Custom error message
     * @return bool True if rule was added successfully
     */
    public function addValidationRule(string $key, callable $validator, ?string $message = null): bool
    {
        try {
            if (! isset($this->customRules[$key])) {
                $this->customRules[$key] = [];
            }

            $this->customRules[$key][] = [
                'validator' => $validator,
                'message' => $message,
            ];

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to add validation rule', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remove validation rule.
     *
     * @param  string  $key  Configuration key pattern
     * @return bool True if rule was removed successfully
     */
    public function removeValidationRule(string $key): bool
    {
        if (isset($this->customRules[$key])) {
            unset($this->customRules[$key]);

            return true;
        }

        return false;
    }

    /**
     * Get all validation rules.
     *
     * @return array<string, array<string, mixed>> All validation rules
     */
    public function getValidationRules(): array
    {
        return $this->customRules;
    }

    /**
     * Validate business rules for configuration.
     *
     * @param  array<string, mixed>  $config  Configuration to validate
     * @return array<string, mixed> Business rule validation results
     */
    public function validateBusinessRules(array $config): array
    {
        $errors = [];

        // Rule: Spam threshold must be between 0 and 1
        if (isset($config['spam_threshold'])) {
            $threshold = $config['spam_threshold'];
            if (! is_numeric($threshold) || $threshold < 0 || $threshold > 1) {
                $errors[] = 'Spam threshold must be between 0.0 and 1.0';
            }
        }

        // Rule: Rate limit attempts must be positive
        if (isset($config['rate_limit']['max_attempts'])) {
            $attempts = $config['rate_limit']['max_attempts'];
            if (! is_int($attempts) || $attempts <= 0) {
                $errors[] = 'Rate limit max attempts must be a positive integer';
            }
        }

        // Rule: Cache TTL must be reasonable (not too short or too long)
        if (isset($config['performance']['cache_ttl'])) {
            $ttl = $config['performance']['cache_ttl'];
            if (! is_int($ttl) || $ttl < 60 || $ttl > 86400) {
                $errors[] = 'Cache TTL must be between 60 seconds and 24 hours';
            }
        }

        // Rule: If IP reputation is enabled, threshold must be set
        if (($config['features']['ip_reputation'] ?? false) && ! isset($config['ip_settings']['reputation_threshold'])) {
            $errors[] = 'IP reputation threshold must be set when IP reputation feature is enabled';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate security constraints.
     *
     * @param  string  $key  Configuration key
     * @param  mixed  $value  Value to validate
     * @return array<string, mixed> Security validation results
     */
    public function validateSecurityConstraints(string $key, mixed $value): array
    {
        $errors = [];

        // Check for potentially dangerous values
        if (is_string($value)) {
            // Check for script injection attempts
            if (preg_match('/<script|javascript:|data:/i', $value)) {
                $errors[] = 'Configuration value contains potentially dangerous content';
            }

            // Check for path traversal attempts
            if (preg_match('/\.\.[\/\\\\]/', $value)) {
                $errors[] = 'Configuration value contains path traversal patterns';
            }
        }

        // Check for sensitive keys that should be encrypted
        $sensitivePatterns = ['password', 'secret', 'key', 'token', 'api_key'];
        foreach ($sensitivePatterns as $pattern) {
            if (str_contains(strtolower($key), $pattern) && is_string($value) && ! empty($value)) {
                // This should ideally be encrypted
                Log::warning('Potentially sensitive configuration value detected', [
                    'key' => $key,
                    'recommendation' => 'Consider encrypting this value',
                ]);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate performance constraints.
     *
     * @param  array<string, mixed>  $config  Configuration to validate
     * @return array<string, mixed> Performance validation results
     */
    public function validatePerformanceConstraints(array $config): array
    {
        $errors = [];

        // Check memory limits
        if (isset($config['performance']['max_memory_usage'])) {
            $memory = $config['performance']['max_memory_usage'];
            if (! is_int($memory) || $memory < 10 || $memory > 512) {
                $errors[] = 'Max memory usage must be between 10MB and 512MB';
            }
        }

        // Check timeout values
        if (isset($config['performance']['analysis_timeout'])) {
            $timeout = $config['performance']['analysis_timeout'];
            if (! is_int($timeout) || $timeout < 1 || $timeout > 30) {
                $errors[] = 'Analysis timeout must be between 1 and 30 seconds';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get validation schema for configuration key.
     *
     * @param  string  $key  Configuration key
     * @return array<string, mixed> Validation schema
     */
    public function getValidationSchema(string $key): array
    {
        // Return specific schema if exists
        if (isset($this->schemas[$key])) {
            return $this->schemas[$key]->toArray();
        }

        // Try to find matching pattern
        foreach ($this->schemas as $pattern => $schema) {
            if ($this->matchesPattern($key, $pattern)) {
                return $schema->toArray();
            }
        }

        return [];
    }

    /**
     * Check if configuration key requires validation.
     *
     * @param  string  $key  Configuration key
     * @return bool True if validation is required
     */
    public function requiresValidation(string $key): bool
    {
        return ! empty($this->getValidationSchema($key)) || ! empty($this->getMatchingCustomRules($key));
    }

    /**
     * Get validation error messages.
     *
     * @return array<string, string> Error message templates
     */
    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }

    /**
     * Set custom error message for validation rule.
     *
     * @param  string  $rule  Validation rule name
     * @param  string  $message  Error message template
     * @return bool True if message was set successfully
     */
    public function setErrorMessage(string $rule, string $message): bool
    {
        $this->errorMessages[$rule] = $message;

        return true;
    }

    /**
     * Load default validation schemas.
     */
    protected function loadDefaultSchemas(): void
    {
        $this->schemas = [
            'enabled' => ConfigurationSchema::builder('enabled')
                ->type(ConfigurationType::BOOLEAN)
                ->required()
                ->default(true)
                ->description('Enable or disable the entire package')
                ->build(),

            'spam_threshold' => ConfigurationSchema::builder('spam_threshold')
                ->type(ConfigurationType::FLOAT)
                ->required()
                ->default(0.7)
                ->constraints(['min' => 0.0, 'max' => 1.0])
                ->description('Spam detection threshold (0.0 = clean, 1.0 = spam)')
                ->build(),

            'features.*' => ConfigurationSchema::builder('features.*')
                ->type(ConfigurationType::BOOLEAN)
                ->default(false)
                ->description('Feature toggle flags')
                ->build(),
        ];
    }

    /**
     * Check if key matches pattern.
     *
     * @param  string  $key  Configuration key
     * @param  string  $pattern  Pattern to match
     * @return bool True if key matches pattern
     */
    protected function matchesPattern(string $key, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = '/^'.str_replace(['*', '.'], ['[^.]*', '\.'], preg_quote($pattern, '/')).'$/';

        return preg_match($regex, $key) === 1;
    }

    /**
     * Get custom rules matching a key.
     *
     * @param  string  $key  Configuration key
     * @return array<array<string, mixed>> Matching custom rules
     */
    protected function getMatchingCustomRules(string $key): array
    {
        $matchingRules = [];

        foreach ($this->customRules as $pattern => $rules) {
            if ($this->matchesPattern($key, $pattern)) {
                $matchingRules = array_merge($matchingRules, $rules);
            }
        }

        return $matchingRules;
    }

    /**
     * Format error message with placeholders.
     *
     * @param  string  $message  Message template
     * @param  array<string, mixed>  $replacements  Placeholder replacements
     * @return string Formatted message
     */
    protected function formatErrorMessage(string $message, array $replacements = []): string
    {
        foreach ($replacements as $key => $value) {
            $message = str_replace(":{$key}", (string) $value, $message);
        }

        return $message;
    }
}
