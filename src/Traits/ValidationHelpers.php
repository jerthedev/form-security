<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Traits;

use InvalidArgumentException;

/**
 * ValidationHelpers trait
 *
 * Provides reusable validation methods to reduce code duplication
 * across Cast classes, Services, and other components that perform
 * repetitive type checking and validation.
 */
trait ValidationHelpers
{
    /**
     * Validate that a value is a non-empty array.
     *
     * @param  mixed  $value  Value to validate
     * @param  string  $name  Field name for error messages
     * @return array<mixed> Validated array
     *
     * @throws InvalidArgumentException When validation fails
     */
    protected function validateArray(mixed $value, string $name = 'value'): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("The {$name} must be an array, ".gettype($value).' given.');
        }

        return $value;
    }

    /**
     * Validate that a value is a non-empty string.
     *
     * @param  mixed  $value  Value to validate
     * @param  string  $name  Field name for error messages
     * @return string Validated string
     *
     * @throws InvalidArgumentException When validation fails
     */
    protected function validateString(mixed $value, string $name = 'value'): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("The {$name} must be a string, ".gettype($value).' given.');
        }

        if (empty(trim($value))) {
            throw new InvalidArgumentException("The {$name} cannot be empty.");
        }

        return $value;
    }

    /**
     * Validate that a value is a positive integer.
     *
     * @param  mixed  $value  Value to validate
     * @param  string  $name  Field name for error messages
     * @return int Validated integer
     *
     * @throws InvalidArgumentException When validation fails
     */
    protected function validatePositiveInteger(mixed $value, string $name = 'value'): int
    {
        if (! is_int($value)) {
            throw new InvalidArgumentException("The {$name} must be an integer, ".gettype($value).' given.');
        }

        if ($value <= 0) {
            throw new InvalidArgumentException("The {$name} must be a positive integer, {$value} given.");
        }

        return $value;
    }

    /**
     * Validate that a value is a non-negative integer.
     *
     * @param  mixed  $value  Value to validate
     * @param  string  $name  Field name for error messages
     * @return int Validated integer
     *
     * @throws InvalidArgumentException When validation fails
     */
    protected function validateNonNegativeInteger(mixed $value, string $name = 'value'): int
    {
        if (! is_int($value)) {
            throw new InvalidArgumentException("The {$name} must be an integer, ".gettype($value).' given.');
        }

        if ($value < 0) {
            throw new InvalidArgumentException("The {$name} must be non-negative, {$value} given.");
        }

        return $value;
    }

    /**
     * Validate that a value is a float between min and max.
     *
     * @param  mixed  $value  Value to validate
     * @param  float  $min  Minimum allowed value
     * @param  float  $max  Maximum allowed value
     * @param  string  $name  Field name for error messages
     * @return float Validated float
     *
     * @throws InvalidArgumentException When validation fails
     */
    protected function validateFloatRange(mixed $value, float $min, float $max, string $name = 'value'): float
    {
        if (! is_float($value) && ! is_int($value)) {
            throw new InvalidArgumentException("The {$name} must be a number, ".gettype($value).' given.');
        }

        $floatValue = (float) $value;

        if ($floatValue < $min || $floatValue > $max) {
            throw new InvalidArgumentException("The {$name} must be between {$min} and {$max}, {$floatValue} given.");
        }

        return $floatValue;
    }

    /**
     * Validate that a value is a valid email address.
     *
     * @param  mixed  $value  Value to validate
     * @param  string  $name  Field name for error messages
     * @return string Validated email
     *
     * @throws InvalidArgumentException When validation fails
     */
    protected function validateEmail(mixed $value, string $name = 'email'): string
    {
        $email = $this->validateString($value, $name);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("The {$name} must be a valid email address.");
        }

        return $email;
    }

    /**
     * Validate that a value is a valid IP address.
     *
     * @param  mixed  $value  Value to validate
     * @param  string  $name  Field name for error messages
     * @return string Validated IP address
     *
     * @throws InvalidArgumentException When validation fails
     */
    protected function validateIpAddress(mixed $value, string $name = 'ip_address'): string
    {
        $ip = $this->validateString($value, $name);

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("The {$name} must be a valid IP address.");
        }

        return $ip;
    }

    /**
     * Validate that a value is in a list of allowed values.
     *
     * @param  mixed  $value  Value to validate
     * @param  array<mixed>  $allowedValues  List of allowed values
     * @param  string  $name  Field name for error messages
     * @return mixed Validated value
     *
     * @throws InvalidArgumentException When validation fails
     */
    protected function validateInList(mixed $value, array $allowedValues, string $name = 'value'): mixed
    {
        if (! in_array($value, $allowedValues, true)) {
            $allowedStr = implode(', ', array_map('strval', $allowedValues));
            throw new InvalidArgumentException("The {$name} must be one of: {$allowedStr}. Given: {$value}");
        }

        return $value;
    }

    /**
     * Validate that a value is a valid JSON string.
     *
     * @param  mixed  $value  Value to validate
     * @param  string  $name  Field name for error messages
     * @return string Validated JSON string
     *
     * @throws InvalidArgumentException When validation fails
     */
    protected function validateJson(mixed $value, string $name = 'json'): string
    {
        $json = $this->validateString($value, $name);

        json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("The {$name} must be valid JSON. Error: ".json_last_error_msg());
        }

        return $json;
    }

    /**
     * Safely convert value to array, handling various input types.
     *
     * @param  mixed  $value  Value to convert
     * @return array<mixed> Converted array
     */
    protected function safeToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            // Looks like JSON
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($value)) {
            return method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        }

        return $value === null ? [] : [$value];
    }

    /**
     * Validate and sanitize a slug/identifier string.
     *
     * @param  mixed  $value  Value to validate
     * @param  string  $name  Field name for error messages
     * @return string Validated slug
     *
     * @throws InvalidArgumentException When validation fails
     */
    protected function validateSlug(mixed $value, string $name = 'slug'): string
    {
        $slug = $this->validateString($value, $name);

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new InvalidArgumentException("The {$name} must be a valid slug (lowercase letters, numbers, and hyphens only).");
        }

        return $slug;
    }
}
