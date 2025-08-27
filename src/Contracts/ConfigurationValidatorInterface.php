<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Contracts;

/**
 * Configuration validation interface for comprehensive business rule validation.
 *
 * This interface defines methods for validating configuration values against
 * business rules, type constraints, and security requirements.
 */
interface ConfigurationValidatorInterface
{
    /**
     * Validate a single configuration value.
     *
     * @param  string  $key  Configuration key
     * @param  mixed  $value  Value to validate
     * @param  array<string, mixed>  $context  Additional validation context
     * @return array<string, mixed> Validation result with errors if any
     */
    public function validateValue(string $key, mixed $value, array $context = []): array;

    /**
     * Validate entire configuration array.
     *
     * @param  array<string, mixed>  $config  Configuration to validate
     * @param  array<string, mixed>  $options  Validation options
     * @return array<string, mixed> Comprehensive validation results
     */
    public function validateConfiguration(array $config, array $options = []): array;

    /**
     * Validate configuration against schema.
     *
     * @param  array<string, mixed>  $config  Configuration to validate
     * @param  array<string, mixed>  $schema  Schema definition
     * @return array<string, mixed> Schema validation results
     */
    public function validateAgainstSchema(array $config, array $schema): array;

    /**
     * Add custom validation rule.
     *
     * @param  string  $key  Configuration key pattern
     * @param  callable  $validator  Validation function
     * @param  string|null  $message  Custom error message
     * @return bool True if rule was added successfully
     */
    public function addValidationRule(string $key, callable $validator, ?string $message = null): bool;

    /**
     * Remove validation rule.
     *
     * @param  string  $key  Configuration key pattern
     * @return bool True if rule was removed successfully
     */
    public function removeValidationRule(string $key): bool;

    /**
     * Get all validation rules.
     *
     * @return array<string, array<string, mixed>> All validation rules
     */
    public function getValidationRules(): array;

    /**
     * Validate business rules for configuration.
     *
     * @param  array<string, mixed>  $config  Configuration to validate
     * @return array<string, mixed> Business rule validation results
     */
    public function validateBusinessRules(array $config): array;

    /**
     * Validate security constraints.
     *
     * @param  string  $key  Configuration key
     * @param  mixed  $value  Value to validate
     * @return array<string, mixed> Security validation results
     */
    public function validateSecurityConstraints(string $key, mixed $value): array;

    /**
     * Validate performance constraints.
     *
     * @param  array<string, mixed>  $config  Configuration to validate
     * @return array<string, mixed> Performance validation results
     */
    public function validatePerformanceConstraints(array $config): array;

    /**
     * Get validation schema for configuration key.
     *
     * @param  string  $key  Configuration key
     * @return array<string, mixed> Validation schema
     */
    public function getValidationSchema(string $key): array;

    /**
     * Check if configuration key requires validation.
     *
     * @param  string  $key  Configuration key
     * @return bool True if validation is required
     */
    public function requiresValidation(string $key): bool;

    /**
     * Get validation error messages.
     *
     * @return array<string, string> Error message templates
     */
    public function getErrorMessages(): array;

    /**
     * Set custom error message for validation rule.
     *
     * @param  string  $rule  Validation rule name
     * @param  string  $message  Error message template
     * @return bool True if message was set successfully
     */
    public function setErrorMessage(string $rule, string $message): bool;
}
