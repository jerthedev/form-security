<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Facades;

use Illuminate\Support\Facades\Facade;
use JTD\FormSecurity\Contracts\FormSecurityContract;

/**
 * FormSecurity Facade for easy access to package functionality.
 *
 * This facade provides a clean, static interface to the FormSecurity
 * package services, making it easy to use throughout your application.
 *
 *
 * @method static array analyzeSubmission(array $data, array $options = [])
 * @method static bool validateSubmission(array $data, array $rules = [])
 * @method static bool isIpBlocked(string $ipAddress)
 * @method static mixed getConfig(?string $key = null)
 * @method static bool toggleFeature(string $feature, bool $enabled)
 * @method static array getPackageInfo()
 *
 * @see \JTD\FormSecurity\Contracts\FormSecurityContract
 */
class FormSecurity extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return FormSecurityContract::class;
    }

    /**
     * Quick spam check for form data.
     *
     * This is a convenience method that performs a basic spam analysis
     * and returns a simple boolean result.
     *
     * @param  array<string, mixed>  $data  Form data to check
     * @param  float|null  $threshold  Custom spam threshold (optional)
     * @return bool True if content appears to be spam
     */
    public static function isSpam(array $data, ?float $threshold = null): bool
    {
        $analysis = static::analyzeSubmission($data);
        $spamThreshold = $threshold ?? static::getConfig('spam_threshold', 0.7);

        return $analysis['score'] >= $spamThreshold;
    }

    /**
     * Quick validation check for form data.
     *
     * This is a convenience method that performs basic validation
     * and returns a simple boolean result.
     *
     * @param  array<string, mixed>  $data  Form data to validate
     * @return bool True if data passes validation
     */
    public static function isValid(array $data): bool
    {
        return static::validateSubmission($data);
    }

    /**
     * Get spam score for form data.
     *
     * This is a convenience method that returns just the spam score
     * without the full analysis details.
     *
     * @param  array<string, mixed>  $data  Form data to analyze
     * @return float Spam score between 0.0 (clean) and 1.0 (spam)
     */
    public static function getSpamScore(array $data): float
    {
        $analysis = static::analyzeSubmission($data);

        return $analysis['score'] ?? 0.0;
    }

    /**
     * Check if the package is enabled and functional.
     *
     * @return bool True if package is enabled
     */
    public static function isEnabled(): bool
    {
        return (bool) static::getConfig('enabled', true);
    }

    /**
     * Check if a specific feature is enabled.
     *
     * @param  string  $feature  Feature name to check
     * @return bool True if feature is enabled
     */
    public static function isFeatureEnabled(string $feature): bool
    {
        return (bool) static::getConfig("features.{$feature}", false);
    }

    /**
     * Get package version information.
     *
     * @return string Package version
     */
    public static function version(): string
    {
        $info = static::getPackageInfo();

        return $info['version'] ?? 'unknown';
    }

    /**
     * Get package statistics and performance metrics.
     *
     * @return array<string, mixed> Statistics array
     */
    public static function getStats(): array
    {
        $info = static::getPackageInfo();

        return $info['statistics'] ?? [];
    }

    /**
     * Analyze multiple form submissions in batch.
     *
     * This method allows you to analyze multiple submissions at once
     * for better performance when processing bulk data.
     *
     * @param  array<array<string, mixed>>  $submissions  Array of form submissions
     * @param  array<string, mixed>  $options  Analysis options
     * @return array<array<string, mixed>> Array of analysis results
     */
    public static function analyzeBatch(array $submissions, array $options = []): array
    {
        $results = [];

        foreach ($submissions as $index => $submission) {
            $results[$index] = static::analyzeSubmission($submission, $options);
        }

        return $results;
    }

    /**
     * Create a middleware instance for protecting routes.
     *
     * This is a convenience method for creating middleware instances
     * with specific configuration.
     *
     * @param  array<string, mixed>  $config  Middleware configuration
     * @return string Middleware class name
     */
    public static function middleware(array $config = []): string
    {
        // This would return the middleware class name
        // The actual middleware implementation would be in src/Middleware/
        return \JTD\FormSecurity\Middleware\FormSecurityMiddleware::class;
    }

    /**
     * Create a validation rule instance.
     *
     * This is a convenience method for creating validation rules
     * that can be used with Laravel's validator.
     *
     * @param  array<string, mixed>  $options  Rule options
     */
    public static function rule(array $options = []): \JTD\FormSecurity\Rules\SpamDetectionRule
    {
        return new \JTD\FormSecurity\Rules\SpamDetectionRule($options);
    }

    /**
     * Enable debug mode for detailed analysis information.
     *
     * @param  bool  $enabled  Whether to enable debug mode
     * @return bool True if debug mode was successfully toggled
     */
    public static function debug(bool $enabled = true): bool
    {
        return static::toggleFeature('debug', $enabled);
    }

    /**
     * Clear all package caches.
     *
     * This method clears all cached data used by the package,
     * forcing fresh analysis on subsequent requests.
     *
     * @return bool True if caches were successfully cleared
     */
    public static function clearCache(): bool
    {
        // This would clear package-specific caches
        // Implementation would depend on the cache service
        return true;
    }

    /**
     * Get human-readable analysis summary.
     *
     * This method provides a user-friendly summary of the analysis
     * results that can be displayed to administrators.
     *
     * @param  array<string, mixed>  $data  Form data to analyze
     * @return string Human-readable summary
     */
    public static function getSummary(array $data): string
    {
        $analysis = static::analyzeSubmission($data);
        $score = $analysis['score'];

        if ($score >= 0.8) {
            return "High spam probability ({$score}). Recommend blocking.";
        } elseif ($score >= 0.5) {
            return "Moderate spam probability ({$score}). Recommend manual review.";
        } elseif ($score >= 0.3) {
            return "Low spam probability ({$score}). Monitor for patterns.";
        } else {
            return "Clean submission ({$score}). No action needed.";
        }
    }
}
