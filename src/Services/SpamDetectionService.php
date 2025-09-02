<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services;

use Illuminate\Support\Facades\Cache;
use JTD\FormSecurity\Contracts\ConfigurationContract;
use JTD\FormSecurity\Contracts\SpamDetectionContract;

/**
 * Spam detection service for analyzing form submissions.
 *
 * This service implements various spam detection algorithms including
 * pattern matching, rate limiting, and content analysis.
 */
class SpamDetectionService implements SpamDetectionContract
{
    /**
     * Default spam patterns for detection.
     *
     * @var array<string>
     */
    protected array $defaultPatterns = [
        '/\b(viagra|cialis|pharmacy)\b/i',
        '/\b(casino|gambling|poker)\b/i',
        '/\b(loan|credit|mortgage)\b/i',
        '/\b(seo|backlink|link building)\b/i',
        '/\b(replica|fake|counterfeit)\b/i',
    ];

    /**
     * Create a new spam detection service instance.
     *
     * @param  ConfigurationContract  $config  Configuration service
     */
    public function __construct(
        protected ConfigurationContract $config
    ) {}

    /**
     * Analyze form data for spam indicators.
     *
     * @param  array<string, mixed>  $data  The form data to analyze
     * @param  array<string, mixed>  $context  Additional context information
     * @return array<string, mixed> Analysis results with spam score and details
     */
    public function analyzeSpam(array $data, array $context = []): array
    {
        $score = 0.0;
        $threats = [];
        $details = [];

        // Analyze each field in the form data
        foreach ($data as $field => $value) {
            if (is_string($value)) {
                $fieldAnalysis = $this->analyzeField($field, $value);
                $score = max($score, $fieldAnalysis['score']);
                $threats = array_merge($threats, $fieldAnalysis['threats']);
                $details[$field] = $fieldAnalysis;
            }
        }

        // Apply context-based adjustments
        if (! empty($context)) {
            $contextAnalysis = $this->analyzeContext($context);
            $score = min(1.0, $score + $contextAnalysis['score_adjustment']);
            $threats = array_merge($threats, $contextAnalysis['threats']);
        }

        return [
            'score' => round($score, 3),
            'threats' => array_unique($threats),
            'details' => $details,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Calculate spam score for given content.
     *
     * @param  string  $content  The content to analyze
     * @param  array<string, mixed>  $metadata  Additional metadata for analysis
     * @return float Spam score between 0.0 (clean) and 1.0 (spam)
     */
    public function calculateSpamScore(string $content, array $metadata = []): float
    {
        $score = 0.0;

        // Check content length
        $length = strlen($content);
        if ($length < 10) {
            $score += 0.2; // Very short content is suspicious
        } elseif ($length > 5000) {
            $score += 0.3; // Very long content might be spam
        }

        // Check for spam patterns
        $patternMatches = $this->checkSpamPatterns($content);
        if (! empty($patternMatches)) {
            // Calculate weighted score based on pattern confidence and count
            $patternScore = 0.0;
            foreach ($patternMatches as $match) {
                $patternScore += $match['confidence'];
            }
            // Cap the pattern score contribution but allow multiple matches to accumulate
            $score += min(0.8, $patternScore);
        }

        // Check for excessive links
        $linkCount = preg_match_all('/https?:\/\/[^\s]+/', $content);
        if ($linkCount > 3) {
            $score += 0.3;
        }

        // Check for excessive capitalization
        $upperCount = preg_match_all('/[A-Z]/', $content);
        $upperRatio = $length > 0 ? $upperCount / $length : 0;
        if ($upperRatio > 0.5) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }

    /**
     * Check if content matches known spam patterns.
     *
     * @param  string  $content  The content to check
     * @return array<string, mixed> Matched patterns and their confidence scores
     */
    public function checkSpamPatterns(string $content): array
    {
        $matches = [];
        $configPatterns = $this->config->get('patterns.spam', []);

        // Process default patterns (simple array format)
        foreach ($this->defaultPatterns as $pattern) {
            if (preg_match($pattern, $content, $match)) {
                $matches[] = [
                    'pattern' => $pattern,
                    'match' => $match[0] ?? '',
                    'confidence' => 0.8, // Default confidence for built-in patterns
                ];
            }
        }

        // Process config patterns (pattern => weight format)
        foreach ($configPatterns as $pattern => $weight) {
            $confidence = is_numeric($weight) ? $weight : 0.8;

            if (is_string($pattern) && preg_match($pattern, $content, $match)) {
                $matches[] = [
                    'pattern' => $pattern,
                    'match' => $match[0] ?? '',
                    'confidence' => $confidence,
                ];
            }
        }

        return $matches;
    }

    /**
     * Validate submission rate limits for IP/user.
     *
     * @param  string  $identifier  IP address or user identifier
     * @param  array<string, mixed>  $limits  Rate limit configuration
     * @return bool True if within limits, false if rate limited
     */
    public function checkRateLimit(string $identifier, array $limits = []): bool
    {
        $defaultLimits = $this->config->get('rate_limit', [
            'max_attempts' => 10,
            'window_minutes' => 60,
        ]);

        $limits = array_merge($defaultLimits, $limits);
        $cacheKey = "form_security:rate_limit:{$identifier}";

        $attempts = Cache::get($cacheKey, 0);

        if ($attempts >= $limits['max_attempts']) {
            return false; // Rate limit exceeded
        }

        // Increment attempt counter
        Cache::put($cacheKey, $attempts + 1, now()->addMinutes($limits['window_minutes']));

        return true;
    }

    /**
     * Update spam detection patterns and rules.
     *
     * @param  array<string, mixed>  $patterns  New patterns to add or update
     * @return bool True if patterns were successfully updated
     */
    public function updateSpamPatterns(array $patterns): bool
    {
        // In a real implementation, this would update the configuration
        // or database with new patterns
        return $this->config->set('patterns.spam', $patterns);
    }

    /**
     * Get current spam detection statistics.
     *
     * @return array<string, mixed> Statistics including detection rates and performance metrics
     */
    public function getDetectionStats(): array
    {
        return [
            'total_analyzed' => Cache::get('form_security:stats:total_analyzed', 0),
            'spam_detected' => Cache::get('form_security:stats:spam_detected', 0),
            'false_positives' => Cache::get('form_security:stats:false_positives', 0),
            'average_processing_time' => Cache::get('form_security:stats:avg_processing_time', 0.0),
            'patterns_count' => count($this->defaultPatterns) + count($this->config->get('patterns.spam', [])),
        ];
    }

    /**
     * Analyze a specific form field.
     *
     * @param  string  $field  Field name
     * @param  string  $value  Field value
     * @return array<string, mixed> Field analysis results
     */
    protected function analyzeField(string $field, string $value): array
    {
        $score = $this->calculateSpamScore($value);
        $threats = [];

        // Field-specific analysis
        if ($field === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $threats[] = 'invalid_email';
            $score += 0.3;
        }

        if ($field === 'url' && ! filter_var($value, FILTER_VALIDATE_URL)) {
            $threats[] = 'invalid_url';
            $score += 0.2;
        }

        return [
            'score' => min(1.0, $score),
            'threats' => $threats,
        ];
    }

    /**
     * Analyze submission context for additional spam indicators.
     *
     * @param  array<string, mixed>  $context  Context information
     * @return array<string, mixed> Context analysis results
     */
    protected function analyzeContext(array $context): array
    {
        $scoreAdjustment = 0.0;
        $threats = [];

        // Check submission frequency
        if (isset($context['submission_frequency']) && $context['submission_frequency'] > 5) {
            $scoreAdjustment += 0.2;
            $threats[] = 'high_frequency_submission';
        }

        // Check user agent
        if (isset($context['user_agent']) && empty($context['user_agent'])) {
            $scoreAdjustment += 0.1;
            $threats[] = 'missing_user_agent';
        }

        return [
            'score_adjustment' => $scoreAdjustment,
            'threats' => $threats,
        ];
    }
}
