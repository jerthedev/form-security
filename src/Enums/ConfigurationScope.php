<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Enums;

/**
 * Configuration scope enumeration for access control and organization.
 *
 * This enum defines the different scopes for configuration values,
 * controlling access levels and organizational structure.
 */
enum ConfigurationScope: string
{
    case GLOBAL = 'global';
    case APPLICATION = 'application';
    case ENVIRONMENT = 'environment';
    case USER = 'user';
    case SESSION = 'session';
    case REQUEST = 'request';
    case FEATURE = 'feature';
    case MODULE = 'module';

    /**
     * Get the access level for the scope.
     * Lower numbers are more restrictive.
     *
     * @return int Access level
     */
    public function getAccessLevel(): int
    {
        return match ($this) {
            self::GLOBAL => 1,
            self::APPLICATION => 2,
            self::ENVIRONMENT => 3,
            self::MODULE => 4,
            self::FEATURE => 5,
            self::USER => 6,
            self::SESSION => 7,
            self::REQUEST => 8,
        };
    }

    /**
     * Check if the scope is temporary.
     *
     * @return bool True if scope is temporary
     */
    public function isTemporary(): bool
    {
        return match ($this) {
            self::SESSION, self::REQUEST => true,
            default => false,
        };
    }

    /**
     * Check if the scope requires authentication.
     *
     * @return bool True if authentication is required
     */
    public function requiresAuthentication(): bool
    {
        return match ($this) {
            self::USER, self::SESSION => true,
            default => false,
        };
    }

    /**
     * Get the default TTL for the scope in seconds.
     *
     * @return int|null TTL in seconds, null for permanent
     */
    public function getDefaultTtl(): ?int
    {
        return match ($this) {
            self::REQUEST => 300,      // 5 minutes
            self::SESSION => 3600,     // 1 hour
            self::USER => 86400,       // 24 hours
            self::FEATURE => 7200,     // 2 hours
            default => null,           // Permanent
        };
    }

    /**
     * Check if the scope supports caching.
     *
     * @return bool True if scope supports caching
     */
    public function supportsCaching(): bool
    {
        return match ($this) {
            self::REQUEST => false,
            default => true,
        };
    }

    /**
     * Get the cache key prefix for the scope.
     *
     * @return string Cache key prefix
     */
    public function getCachePrefix(): string
    {
        return "form_security_config_{$this->value}";
    }

    /**
     * Get scopes ordered by access level.
     *
     * @return array<self> Scopes ordered by access level (most restrictive first)
     */
    public static function getByAccessLevel(): array
    {
        $scopes = self::cases();
        usort($scopes, fn (self $a, self $b) => $a->getAccessLevel() <=> $b->getAccessLevel());

        return $scopes;
    }

    /**
     * Get temporary scopes.
     *
     * @return array<self> Temporary scopes
     */
    public static function getTemporaryScopes(): array
    {
        return array_filter(
            self::cases(),
            fn (self $scope) => $scope->isTemporary()
        );
    }

    /**
     * Get scopes that require authentication.
     *
     * @return array<self> Scopes requiring authentication
     */
    public static function getAuthenticatedScopes(): array
    {
        return array_filter(
            self::cases(),
            fn (self $scope) => $scope->requiresAuthentication()
        );
    }

    /**
     * Get cacheable scopes.
     *
     * @return array<self> Scopes that support caching
     */
    public static function getCacheableScopes(): array
    {
        return array_filter(
            self::cases(),
            fn (self $scope) => $scope->supportsCaching()
        );
    }
}
