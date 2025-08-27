<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Enums;

/**
 * Configuration source enumeration for hierarchical loading.
 *
 * This enum defines the different sources from which configuration
 * can be loaded, with priority ordering for hierarchical resolution.
 */
enum ConfigurationSource: string
{
    case RUNTIME = 'runtime';
    case ENVIRONMENT = 'environment';
    case DATABASE = 'database';
    case FILE = 'file';
    case CACHE = 'cache';
    case DEFAULT = 'default';
    case API = 'api';
    case REMOTE = 'remote';

    /**
     * Get the priority order for configuration sources.
     * Lower numbers have higher priority.
     *
     * @return int Priority order
     */
    public function getPriority(): int
    {
        return match ($this) {
            self::RUNTIME => 1,
            self::ENVIRONMENT => 2,
            self::DATABASE => 3,
            self::CACHE => 4,
            self::FILE => 5,
            self::API => 6,
            self::REMOTE => 7,
            self::DEFAULT => 8,
        };
    }

    /**
     * Check if the source supports caching.
     *
     * @return bool True if source supports caching
     */
    public function supportsCaching(): bool
    {
        return match ($this) {
            self::DATABASE, self::FILE, self::API, self::REMOTE => true,
            default => false,
        };
    }

    /**
     * Check if the source is persistent.
     *
     * @return bool True if source persists data
     */
    public function isPersistent(): bool
    {
        return match ($this) {
            self::DATABASE, self::FILE => true,
            default => false,
        };
    }

    /**
     * Check if the source is remote.
     *
     * @return bool True if source is remote
     */
    public function isRemote(): bool
    {
        return match ($this) {
            self::API, self::REMOTE => true,
            default => false,
        };
    }

    /**
     * Get the default timeout for the source in seconds.
     *
     * @return int Timeout in seconds
     */
    public function getDefaultTimeout(): int
    {
        return match ($this) {
            self::API, self::REMOTE => 30,
            self::DATABASE => 10,
            self::FILE => 5,
            default => 1,
        };
    }

    /**
     * Get sources ordered by priority.
     *
     * @return array<self> Sources ordered by priority (highest first)
     */
    public static function getByPriority(): array
    {
        $sources = self::cases();
        usort($sources, fn (self $a, self $b) => $a->getPriority() <=> $b->getPriority());

        return $sources;
    }

    /**
     * Get cacheable sources.
     *
     * @return array<self> Sources that support caching
     */
    public static function getCacheableSources(): array
    {
        return array_filter(
            self::cases(),
            fn (self $source) => $source->supportsCaching()
        );
    }

    /**
     * Get persistent sources.
     *
     * @return array<self> Sources that persist data
     */
    public static function getPersistentSources(): array
    {
        return array_filter(
            self::cases(),
            fn (self $source) => $source->isPersistent()
        );
    }

    /**
     * Get remote sources.
     *
     * @return array<self> Remote sources
     */
    public static function getRemoteSources(): array
    {
        return array_filter(
            self::cases(),
            fn (self $source) => $source->isRemote()
        );
    }
}
