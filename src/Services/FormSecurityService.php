<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services;

use JTD\FormSecurity\Contracts\ConfigurationContract;
use JTD\FormSecurity\Contracts\FormSecurityContract;
use JTD\FormSecurity\Contracts\SpamDetectionContract;

/**
 * Main FormSecurity service providing core package functionality.
 *
 * This service coordinates between different security components and provides
 * a unified interface for form security operations.
 */
class FormSecurityService implements FormSecurityContract
{
    /**
     * Package version.
     */
    protected const VERSION = '1.0.0';

    /**
     * Create a new FormSecurity service instance.
     *
     * @param  ConfigurationContract  $config  Configuration service
     * @param  SpamDetectionContract  $spamDetector  Spam detection service
     */
    public function __construct(
        protected ConfigurationContract $config,
        protected SpamDetectionContract $spamDetector
    ) {}

    /**
     * Analyze a form submission for spam and security threats.
     *
     * @param  array<string, mixed>  $data  The form data to analyze
     * @param  array<string, mixed>  $options  Additional analysis options
     * @return array<string, mixed> Analysis results with score and recommendations
     */
    public function analyzeSubmission(array $data, array $options = []): array
    {
        $startTime = microtime(true);

        // Basic validation
        if (empty($data)) {
            return [
                'valid' => false,
                'score' => 0.0,
                'threats' => ['empty_submission'],
                'recommendations' => ['Provide form data for analysis'],
                'processing_time' => microtime(true) - $startTime,
            ];
        }

        // Perform spam analysis
        $spamAnalysis = $this->spamDetector->analyzeSpam($data, $options);

        // Determine if submission is valid based on spam threshold
        $threshold = $this->config->get('spam_threshold', 0.7);
        $isValid = $spamAnalysis['score'] < $threshold;

        return [
            'valid' => $isValid,
            'score' => $spamAnalysis['score'],
            'threats' => $spamAnalysis['threats'] ?? [],
            'recommendations' => $this->generateRecommendations($spamAnalysis),
            'processing_time' => microtime(true) - $startTime,
            'analysis_details' => $spamAnalysis,
        ];
    }

    /**
     * Validate form data against security rules.
     *
     * @param  array<string, mixed>  $data  The form data to validate
     * @param  array<string, mixed>  $rules  Validation rules to apply
     * @return bool True if validation passes, false otherwise
     */
    public function validateSubmission(array $data, array $rules = []): bool
    {
        // Basic validation
        if (empty($data)) {
            return false;
        }

        // Check rate limits if enabled
        if ($this->config->isFeatureEnabled('rate_limiting')) {
            $identifier = $this->getSubmissionIdentifier($data);
            if (! $this->spamDetector->checkRateLimit($identifier)) {
                return false;
            }
        }

        // Perform spam analysis
        $analysis = $this->analyzeSubmission($data);

        return $analysis['valid'];
    }

    /**
     * Check if an IP address is blocked or suspicious.
     *
     * @param  string  $ipAddress  The IP address to check
     * @return bool True if IP is blocked, false otherwise
     */
    public function isIpBlocked(string $ipAddress): bool
    {
        // Basic IP validation
        if (! filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            return true; // Invalid IP is considered blocked
        }

        // Check against blocked IP list
        $blockedIps = $this->config->get('blocked_ips', []);
        if (in_array($ipAddress, $blockedIps, true)) {
            return true;
        }

        // Check IP reputation if feature is enabled
        if ($this->config->isFeatureEnabled('ip_reputation')) {
            return $this->checkIpReputation($ipAddress);
        }

        return false;
    }

    /**
     * Get the current configuration for the package.
     *
     * @param  string|null  $key  Optional configuration key to retrieve
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed Configuration value or full config array
     */
    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        return $key ? $this->config->get($key, $default) : $this->config->get('');
    }

    /**
     * Enable or disable specific security features.
     *
     * @param  string  $feature  The feature name to toggle
     * @param  bool  $enabled  Whether to enable or disable the feature
     * @return bool True if feature was successfully toggled
     */
    public function toggleFeature(string $feature, bool $enabled): bool
    {
        return $this->config->toggleFeature($feature, $enabled);
    }

    /**
     * Get package version and status information.
     *
     * @return array<string, mixed> Package information including version, features, and status
     */
    public function getPackageInfo(): array
    {
        return [
            'name' => 'JTD FormSecurity',
            'version' => self::VERSION,
            'enabled' => $this->config->get('enabled', true),
            'features' => $this->config->getEnabledFeatures(),
            'performance' => [
                'cache_enabled' => $this->config->isFeatureEnabled('caching'),
                'rate_limiting' => $this->config->isFeatureEnabled('rate_limiting'),
            ],
            'statistics' => $this->spamDetector->getDetectionStats(),
        ];
    }

    /**
     * Generate recommendations based on analysis results.
     *
     * @param  array<string, mixed>  $analysis  Spam analysis results
     * @return array<string> List of recommendations
     */
    protected function generateRecommendations(array $analysis): array
    {
        $recommendations = [];
        $score = $analysis['score'] ?? 0.0;

        if ($score > 0.8) {
            $recommendations[] = 'Block this submission - high spam probability';
        } elseif ($score > 0.5) {
            $recommendations[] = 'Review this submission manually';
            $recommendations[] = 'Consider additional verification steps';
        } elseif ($score > 0.3) {
            $recommendations[] = 'Monitor this user for future submissions';
        }

        if (isset($analysis['threats']) && ! empty($analysis['threats'])) {
            $recommendations[] = 'Address detected threats: '.implode(', ', $analysis['threats']);
        }

        return $recommendations;
    }

    /**
     * Get submission identifier for rate limiting.
     *
     * @param  array<string, mixed>  $data  Form data
     * @return string Identifier for rate limiting
     */
    protected function getSubmissionIdentifier(array $data): string
    {
        // Use IP address if available, otherwise use a hash of the data
        return $data['_ip'] ?? md5(serialize($data));
    }

    /**
     * Check IP reputation against stored intelligence data.
     *
     * @param  string  $ipAddress  IP address to check
     * @return bool True if IP has bad reputation (blacklisted, high threat score, etc.)
     */
    protected function checkIpReputation(string $ipAddress): bool
    {
        try {
            // Get cached IP reputation data
            $reputation = \JTD\FormSecurity\Models\IpReputation::getCached($ipAddress);

            if (! $reputation) {
                // No reputation data available - consider safe by default
                return false;
            }

            // Check if IP is explicitly blacklisted
            if ($reputation->is_blacklisted) {
                return true;
            }

            // Check if IP is whitelisted (override bad reputation)
            if ($reputation->is_whitelisted) {
                return false;
            }

            // Check reputation score threshold (assuming 0-100 scale, >70 is bad)
            if ($reputation->reputation_score > 70) {
                return true;
            }

            // Check block rate threshold (>80% blocked submissions)
            if ($reputation->block_rate > 0.8 && $reputation->submission_count >= 5) {
                return true;
            }

            // Check for known threat indicators
            $threatFlags = [
                $reputation->is_malware,
                $reputation->is_botnet,
            ];

            return in_array(true, $threatFlags, true);
        } catch (\Exception $e) {
            // Log error but don't block submission due to reputation check failure
            \Illuminate\Support\Facades\Log::warning('IP reputation check failed', [
                'ip_address' => $ipAddress,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
