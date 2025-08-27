<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Enums;

/**
 * Configuration type enumeration for type safety and validation.
 *
 * This enum defines the different types of configuration values
 * supported by the FormSecurity package with their validation rules.
 */
enum ConfigurationType: string
{
    case BOOLEAN = 'boolean';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case STRING = 'string';
    case ARRAY = 'array';
    case OBJECT = 'object';
    case MIXED = 'mixed';
    case EMAIL = 'email';
    case URL = 'url';
    case IP_ADDRESS = 'ip_address';
    case REGEX = 'regex';
    case JSON = 'json';
    case ENCRYPTED = 'encrypted';
    case ENUM = 'enum';
    case DATETIME = 'datetime';
    case FILE_PATH = 'file_path';

    /**
     * Get the PHP type for validation.
     *
     * @return string PHP type name
     */
    public function getPhpType(): string
    {
        return match ($this) {
            self::BOOLEAN => 'bool',
            self::INTEGER => 'int',
            self::FLOAT => 'float',
            self::STRING, self::EMAIL, self::URL, self::IP_ADDRESS,
            self::REGEX, self::JSON, self::ENCRYPTED, self::DATETIME,
            self::FILE_PATH => 'string',
            self::ARRAY => 'array',
            self::OBJECT => 'object',
            self::MIXED, self::ENUM => 'mixed',
        };
    }

    /**
     * Check if the type requires special validation.
     *
     * @return bool True if special validation is required
     */
    public function requiresSpecialValidation(): bool
    {
        return match ($this) {
            self::EMAIL, self::URL, self::IP_ADDRESS, self::REGEX,
            self::JSON, self::ENCRYPTED, self::ENUM, self::DATETIME,
            self::FILE_PATH => true,
            default => false,
        };
    }

    /**
     * Get validation pattern for the type.
     *
     * @return string|null Validation pattern or null if not applicable
     */
    public function getValidationPattern(): ?string
    {
        return match ($this) {
            self::EMAIL => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
            self::URL => '/^https?:\/\/[^\s\/$.?#].[^\s]*$/i',
            self::IP_ADDRESS => '/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$|^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/',
            self::DATETIME => '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/',
            self::FILE_PATH => '/^[\/\w\-. ]+$/',
            default => null,
        };
    }

    /**
     * Get default value for the type.
     *
     * @return mixed Default value
     */
    public function getDefaultValue(): mixed
    {
        return match ($this) {
            self::BOOLEAN => false,
            self::INTEGER => 0,
            self::FLOAT => 0.0,
            self::STRING, self::EMAIL, self::URL, self::IP_ADDRESS,
            self::REGEX, self::JSON, self::ENCRYPTED, self::DATETIME,
            self::FILE_PATH => '',
            self::ARRAY => [],
            self::OBJECT => new \stdClass,
            self::MIXED, self::ENUM => null,
        };
    }

    /**
     * Check if the type is sensitive and should be encrypted.
     *
     * @return bool True if type contains sensitive data
     */
    public function isSensitive(): bool
    {
        return $this === self::ENCRYPTED;
    }

    /**
     * Get all configuration types.
     *
     * @return array<string> Array of type values
     */
    public static function getTypes(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }

    /**
     * Get types that support caching.
     *
     * @return array<self> Array of cacheable types
     */
    public static function getCacheableTypes(): array
    {
        return array_filter(
            self::cases(),
            fn (self $type) => ! $type->isSensitive()
        );
    }

    /**
     * Get types that require validation.
     *
     * @return array<self> Array of types requiring validation
     */
    public static function getValidatableTypes(): array
    {
        return array_filter(
            self::cases(),
            fn (self $type) => $type->requiresSpecialValidation()
        );
    }
}
