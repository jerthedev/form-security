<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Traits;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CentralizedLogging trait
 *
 * Provides centralized, consistent logging methods for all FormSecurity components.
 * This trait reduces code duplication and ensures standardized log formatting
 * across the entire package.
 */
trait CentralizedLogging
{
    /**
     * Log an informational message with standardized context.
     *
     * @param  string  $message  Log message
     * @param  array<string, mixed>  $context  Additional context data
     * @param  string|null  $operation  Operation name for categorization
     */
    protected function logInfo(string $message, array $context = [], ?string $operation = null): void
    {
        $enrichedContext = $this->enrichLogContext($context, $operation);
        Log::info($message, $enrichedContext);
    }

    /**
     * Log a warning message with standardized context.
     *
     * @param  string  $message  Log message
     * @param  array<string, mixed>  $context  Additional context data
     * @param  string|null  $operation  Operation name for categorization
     */
    protected function logWarning(string $message, array $context = [], ?string $operation = null): void
    {
        $enrichedContext = $this->enrichLogContext($context, $operation);
        Log::warning($message, $enrichedContext);
    }

    /**
     * Log an error message with standardized context.
     *
     * @param  string  $message  Log message
     * @param  array<string, mixed>  $context  Additional context data
     * @param  string|null  $operation  Operation name for categorization
     */
    protected function logError(string $message, array $context = [], ?string $operation = null): void
    {
        $enrichedContext = $this->enrichLogContext($context, $operation);
        Log::error($message, $enrichedContext);
    }

    /**
     * Log an exception with comprehensive context.
     *
     * @param  Throwable  $exception  Exception to log
     * @param  string  $message  Custom message
     * @param  array<string, mixed>  $context  Additional context data
     * @param  string|null  $operation  Operation name for categorization
     */
    protected function logException(Throwable $exception, string $message, array $context = [], ?string $operation = null): void
    {
        $enrichedContext = array_merge($context, [
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'exception_trace' => $exception->getTraceAsString(),
        ]);

        $this->logError($message, $enrichedContext, $operation);
    }

    /**
     * Log a cache operation with standardized format.
     *
     * @param  string  $action  Cache action (hit, miss, invalidate, etc.)
     * @param  string  $key  Cache key
     * @param  array<string, mixed>  $context  Additional context data
     */
    protected function logCacheOperation(string $action, string $key, array $context = []): void
    {
        $enrichedContext = array_merge($context, [
            'cache_key' => $key,
            'cache_action' => $action,
        ]);

        $this->logInfo("Cache {$action}", $enrichedContext, 'cache');
    }

    /**
     * Log a configuration operation with standardized format.
     *
     * @param  string  $action  Configuration action
     * @param  string  $key  Configuration key
     * @param  array<string, mixed>  $context  Additional context data
     */
    protected function logConfigurationOperation(string $action, string $key, array $context = []): void
    {
        $enrichedContext = array_merge($context, [
            'config_key' => $key,
            'config_action' => $action,
        ]);

        $this->logInfo("Configuration {$action}", $enrichedContext, 'configuration');
    }

    /**
     * Log a security event with high priority.
     *
     * @param  string  $event  Security event type
     * @param  array<string, mixed>  $context  Security context data
     */
    protected function logSecurityEvent(string $event, array $context = []): void
    {
        $enrichedContext = array_merge($context, [
            'security_event' => $event,
            'timestamp' => now()->toISOString(),
            'severity' => 'high',
        ]);

        Log::warning("Security Event: {$event}", $enrichedContext);
    }

    /**
     * Enrich log context with common metadata.
     *
     * @param  array<string, mixed>  $context  Base context
     * @param  string|null  $operation  Operation name
     * @return array<string, mixed> Enriched context
     */
    private function enrichLogContext(array $context, ?string $operation = null): array
    {
        $enriched = [
            'component' => $this->getComponentName(),
            'timestamp' => now()->toISOString(),
        ];

        if ($operation) {
            $enriched['operation'] = $operation;
        }

        // Add request ID if available (for tracing)
        if (request()->headers->has('X-Request-ID')) {
            $enriched['request_id'] = request()->headers->get('X-Request-ID');
        }

        return array_merge($enriched, $context);
    }

    /**
     * Get component name for logging context.
     *
     * @return string Component name
     */
    private function getComponentName(): string
    {
        $className = static::class;
        $parts = explode('\\', $className);

        return end($parts);
    }
}
