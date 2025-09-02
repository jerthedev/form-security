<?php

declare(strict_types=1);

/**
 * Service File: CacheWarmingService.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Advanced cache warming service with automated procedures
 * and intelligent warming strategies for optimal performance.
 */

namespace JTD\FormSecurity\Services;

use Illuminate\Support\Facades\Log;
use JTD\FormSecurity\Contracts\CacheManagerInterface;
use JTD\FormSecurity\ValueObjects\CacheKey;

/**
 * CacheWarmingService
 *
 * Provides advanced cache warming functionality including automated
 * warming procedures, intelligent warming strategies, and scheduled
 * warming tasks for optimal cache performance.
 */
class CacheWarmingService
{
    /**
     * Registered warming strategies
     *
     * @var array<string, callable>
     */
    private array $warmingStrategies = [];

    /**
     * Warming statistics
     *
     * @var array<string, mixed>
     */
    private array $stats = [
        'total_warmed' => 0,
        'successful_warmed' => 0,
        'failed_warmed' => 0,
        'last_warming_time' => null,
        'warming_duration' => 0,
    ];

    public function __construct(
        private CacheManagerInterface $cacheManager
    ) {
        $this->initializeDefaultStrategies();
    }

    /**
     * Warm cache using predefined strategies
     */
    public function warmCache(array $strategies = [], ?array $levels = null): array
    {
        $startTime = microtime(true);
        $strategies = $strategies ?: array_keys($this->warmingStrategies);
        $results = [];

        Log::info('Starting cache warming', ['strategies' => $strategies, 'levels' => $levels]);

        foreach ($strategies as $strategyName) {
            if (! isset($this->warmingStrategies[$strategyName])) {
                $results[$strategyName] = ['success' => false, 'error' => 'Strategy not found'];

                continue;
            }

            try {
                $strategyResults = $this->executeWarmingStrategy($strategyName, $levels);
                $results[$strategyName] = $strategyResults;

                $this->updateStats($strategyResults);
            } catch (\Exception $e) {
                $results[$strategyName] = ['success' => false, 'error' => $e->getMessage()];
                $this->stats['failed_warmed']++;
            }
        }

        $this->stats['last_warming_time'] = now()->toISOString();
        $this->stats['warming_duration'] = microtime(true) - $startTime;

        Log::info('Cache warming completed', ['results' => $results, 'duration' => $this->stats['warming_duration']]);

        return $results;
    }

    /**
     * Warm frequently accessed data
     */
    public function warmFrequentData(?array $levels = null): array
    {
        $warmers = [];

        // Convert CacheKey objects to string keys for the warmers array
        $cacheKeys = [
            CacheKey::forConfiguration('spam_detection_enabled'),
            CacheKey::forConfiguration('ip_reputation_enabled'),
            CacheKey::forConfiguration('geolocation_enabled'),
            CacheKey::forSpamPattern('email', null),
            CacheKey::forSpamPattern('content', null),
        ];

        $callbacks = [
            fn () => true,
            fn () => true,
            fn () => true,
            fn () => $this->getCommonSpamPatterns('email'),
            fn () => $this->getCommonSpamPatterns('content'),
        ];

        foreach ($cacheKeys as $index => $cacheKey) {
            $warmers[$cacheKey->toString()] = $callbacks[$index];
        }

        return $this->cacheManager->warm($warmers, $levels);
    }

    /**
     * Warm critical system data
     */
    public function warmCriticalData(?array $levels = null): array
    {
        $warmers = [];

        $cacheKeys = [
            CacheKey::make('system_status', 'system'),
            CacheKey::make('feature_flags', 'configuration'),
            CacheKey::make('rate_limits', 'configuration'),
        ];

        $callbacks = [
            fn () => ['status' => 'operational', 'timestamp' => now()],
            fn () => $this->getFeatureFlags(),
            fn () => $this->getRateLimits(),
        ];

        foreach ($cacheKeys as $index => $cacheKey) {
            $warmers[$cacheKey->toString()] = $callbacks[$index];
        }

        return $this->cacheManager->warm($warmers, $levels);
    }

    /**
     * Warm analytics data
     */
    public function warmAnalyticsData(?array $levels = null): array
    {
        $warmers = [];

        $cacheKeys = [
            CacheKey::forAnalytics('daily_submissions', ['date' => now()->format('Y-m-d')]),
            CacheKey::forAnalytics('blocked_ips', ['date' => now()->format('Y-m-d')]),
            CacheKey::forAnalytics('spam_patterns_triggered', ['date' => now()->format('Y-m-d')]),
        ];

        $callbacks = [
            fn () => $this->getDailySubmissionCount(),
            fn () => $this->getBlockedIpCount(),
            fn () => $this->getSpamPatternsTriggered(),
        ];

        foreach ($cacheKeys as $index => $cacheKey) {
            $warmers[$cacheKey->toString()] = $callbacks[$index];
        }

        return $this->cacheManager->warm($warmers, $levels);
    }

    /**
     * Register a custom warming strategy
     */
    public function registerStrategy(string $name, callable $strategy): void
    {
        $this->warmingStrategies[$name] = $strategy;
    }

    /**
     * Get warming statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset warming statistics
     */
    public function resetStats(): void
    {
        $this->stats = [
            'total_warmed' => 0,
            'successful_warmed' => 0,
            'failed_warmed' => 0,
            'last_warming_time' => null,
            'warming_duration' => 0,
        ];
    }

    /**
     * Get registered warming strategies
     */
    public function getStrategies(): array
    {
        return array_keys($this->warmingStrategies);
    }

    /**
     * Schedule automatic cache warming
     */
    public function scheduleWarming(string $frequency = 'hourly'): bool
    {
        // This would integrate with Laravel's task scheduler
        // For now, return true as a placeholder
        Log::info('Cache warming scheduled', ['frequency' => $frequency]);

        return true;
    }

    /**
     * Initialize default warming strategies
     */
    private function initializeDefaultStrategies(): void
    {
        $this->warmingStrategies = [
            'frequent_data' => fn (?array $levels) => $this->warmFrequentData($levels),
            'critical_data' => fn (?array $levels) => $this->warmCriticalData($levels),
            'analytics_data' => fn (?array $levels) => $this->warmAnalyticsData($levels),
            'configuration_data' => fn (?array $levels) => $this->warmConfigurationData($levels),
            'security_data' => fn (?array $levels) => $this->warmSecurityData($levels),
        ];
    }

    /**
     * Execute a specific warming strategy
     */
    private function executeWarmingStrategy(string $strategyName, ?array $levels): array
    {
        $strategy = $this->warmingStrategies[$strategyName];
        $results = $strategy($levels);

        return [
            'success' => true,
            'strategy' => $strategyName,
            'warmed_keys' => count($results),
            'successful_keys' => count(array_filter($results)),
            'failed_keys' => count($results) - count(array_filter($results)),
            'details' => $results,
        ];
    }

    /**
     * Update warming statistics
     */
    private function updateStats(array $results): void
    {
        if ($results['success']) {
            $this->stats['total_warmed'] += $results['warmed_keys'];
            $this->stats['successful_warmed'] += $results['successful_keys'];
            $this->stats['failed_warmed'] += $results['failed_keys'];
        }
    }

    /**
     * Warm configuration data
     */
    private function warmConfigurationData(?array $levels): array
    {
        $warmers = [];

        $cacheKeys = [
            CacheKey::forConfiguration('max_submissions_per_minute'),
            CacheKey::forConfiguration('ip_reputation_threshold'),
            CacheKey::forConfiguration('spam_pattern_sensitivity'),
        ];

        $callbacks = [
            fn () => 60,
            fn () => 0.7,
            fn () => 0.8,
        ];

        foreach ($cacheKeys as $index => $cacheKey) {
            $warmers[$cacheKey->toString()] = $callbacks[$index];
        }

        return $this->cacheManager->warm($warmers, $levels);
    }

    /**
     * Warm security-related data
     */
    private function warmSecurityData(?array $levels): array
    {
        $warmers = [];

        $cacheKeys = [
            CacheKey::make('blocked_ips', 'security'),
            CacheKey::make('trusted_ips', 'security'),
            CacheKey::make('security_rules', 'security'),
        ];

        $callbacks = [
            fn () => $this->getBlockedIps(),
            fn () => $this->getTrustedIps(),
            fn () => $this->getSecurityRules(),
        ];

        foreach ($cacheKeys as $index => $cacheKey) {
            $warmers[$cacheKey->toString()] = $callbacks[$index];
        }

        return $this->cacheManager->warm($warmers, $levels);
    }

    /**
     * Get common spam patterns for warming
     */
    private function getCommonSpamPatterns(string $type): array
    {
        // This would typically query the database for common patterns
        return match ($type) {
            'email' => ['@spam.com', '@fake.com', 'noreply@'],
            'content' => ['buy now', 'click here', 'free money'],
            default => [],
        };
    }

    /**
     * Get feature flags for warming
     */
    private function getFeatureFlags(): array
    {
        return [
            'ip_reputation_check' => true,
            'geolocation_check' => true,
            'spam_pattern_detection' => true,
            'rate_limiting' => true,
        ];
    }

    /**
     * Get rate limits for warming
     */
    private function getRateLimits(): array
    {
        return [
            'submissions_per_minute' => 60,
            'submissions_per_hour' => 1000,
            'api_calls_per_minute' => 100,
        ];
    }

    /**
     * Get daily submission count for warming
     */
    private function getDailySubmissionCount(): int
    {
        // This would typically query the database
        return 0;
    }

    /**
     * Get blocked IP count for warming
     */
    private function getBlockedIpCount(): int
    {
        // This would typically query the database
        return 0;
    }

    /**
     * Get spam patterns triggered count for warming
     */
    private function getSpamPatternsTriggered(): int
    {
        // This would typically query the database
        return 0;
    }

    /**
     * Get blocked IPs for warming
     */
    private function getBlockedIps(): array
    {
        // This would typically query the database
        return [];
    }

    /**
     * Get trusted IPs for warming
     */
    private function getTrustedIps(): array
    {
        // This would typically query the database
        return [];
    }

    /**
     * Get security rules for warming
     */
    private function getSecurityRules(): array
    {
        return [
            'max_failed_attempts' => 5,
            'lockout_duration' => 300,
            'require_captcha_after' => 3,
        ];
    }
}
