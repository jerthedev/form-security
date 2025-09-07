<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services;

use Illuminate\Support\Facades\Cache;
use JTD\FormSecurity\Contracts\ConfigurationContract;
use JTD\FormSecurity\Enums\DetectionMethod;
use JTD\FormSecurity\Models\SpamPattern;
use JTD\FormSecurity\ValueObjects\SubmissionContext;

/**
 * Submission analyzer service for comprehensive form submission analysis.
 *
 * Provides detailed analysis of form submissions including content extraction,
 * pattern matching, behavioral analysis, and context evaluation for spam detection.
 */
class SubmissionAnalyzerService
{
    /**
     * Create a new submission analyzer service instance.
     */
    public function __construct(
        protected ConfigurationContract $config
    ) {}

    /**
     * Analyze form submission using multiple detection methods.
     *
     * @param  array<string, mixed>  $formData  Form submission data
     * @param  SubmissionContext  $context  Submission context
     * @return array<string, float> Method scores by detection method
     */
    public function analyzeSubmission(array $formData, SubmissionContext $context): array
    {
        $methodScores = [];

        // Run each detection method
        $methodScores[DetectionMethod::REGEX->value] = $this->analyzeWithRegex($formData, $context);
        $methodScores[DetectionMethod::KEYWORD->value] = $this->analyzeWithKeywords($formData, $context);
        $methodScores[DetectionMethod::PATTERN->value] = $this->analyzeWithPatterns($formData, $context);
        $methodScores[DetectionMethod::CONTENT_ANALYSIS->value] = $this->analyzeContent($formData, $context);
        $methodScores[DetectionMethod::BEHAVIORAL->value] = $this->analyzeBehavior($formData, $context);
        $methodScores[DetectionMethod::RATE_LIMIT->value] = $this->analyzeRateLimit($context);

        // Optional methods (if enabled in configuration)
        if ($this->config->get('spam_detection.enable_bayesian', false)) {
            $methodScores[DetectionMethod::BAYESIAN->value] = $this->analyzeBayesian($formData, $context);
        }

        if ($this->config->get('spam_detection.enable_ai', false)) {
            $methodScores[DetectionMethod::AI->value] = $this->analyzeWithAI($formData, $context);
        }

        if ($this->config->get('spam_detection.enable_geolocation', false)) {
            $methodScores[DetectionMethod::GEOLOCATION->value] = $this->analyzeGeolocation($context);
        }

        if ($this->config->get('spam_detection.enable_ip_reputation', false)) {
            $methodScores[DetectionMethod::IP_REPUTATION->value] = $this->analyzeIPReputation($context);
        }

        return array_filter($methodScores, fn ($score) => $score !== null);
    }

    /**
     * Analyze submission using regex patterns.
     */
    protected function analyzeWithRegex(array $formData, SubmissionContext $context): float
    {
        $score = 0.0;
        $content = $this->extractTextContent($formData);

        if (empty($content)) {
            return 0.0;
        }

        // Get regex patterns from database
        $patterns = SpamPattern::active()
            ->byType('regex')
            ->orderByPriority()
            ->get();

        foreach ($patterns as $pattern) {
            $startTime = microtime(true);

            try {
                $flags = $pattern->case_sensitive ? '' : 'i';
                $matches = preg_match("/{$pattern->pattern}/{$flags}", $content);

                if ($matches) {
                    $patternScore = $pattern->risk_score / 100.0;
                    $score = max($score, $patternScore);

                    // Update pattern statistics
                    $processingTime = (microtime(true) - $startTime) * 1000;
                    $pattern->recordMatch(false, (int) $processingTime);

                    // Early exit for high-confidence patterns
                    if ($patternScore >= 0.9) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                // Log regex error and continue
                continue;
            }
        }

        return min(1.0, $score);
    }

    /**
     * Analyze submission using keyword detection.
     */
    protected function analyzeWithKeywords(array $formData, SubmissionContext $context): float
    {
        $score = 0.0;
        $content = $this->extractTextContent($formData);

        if (empty($content)) {
            return 0.0;
        }

        // Get keyword patterns
        $patterns = SpamPattern::active()
            ->byType('keyword')
            ->orderByPriority()
            ->get();

        $contentLower = strtolower($content);

        foreach ($patterns as $pattern) {
            $keyword = $pattern->case_sensitive ? $pattern->pattern : strtolower($pattern->pattern);

            $found = $pattern->whole_word_only
                ? preg_match('/\b'.preg_quote($keyword, '/').'\b/', $contentLower)
                : strpos($contentLower, $keyword) !== false;

            if ($found) {
                $patternScore = $pattern->risk_score / 100.0;
                $score += $patternScore * 0.5; // Keywords are less definitive than regex

                $pattern->recordMatch();
            }
        }

        return min(1.0, $score);
    }

    /**
     * Analyze submission using advanced spam patterns.
     */
    protected function analyzeWithPatterns(array $formData, SubmissionContext $context): float
    {
        $score = 0.0;
        $content = $this->extractTextContent($formData);

        if (empty($content)) {
            return 0.0;
        }

        // Advanced pattern analysis
        $patterns = SpamPattern::active()
            ->whereIn('pattern_type', ['phrase', 'email_pattern', 'url_pattern'])
            ->orderByPriority()
            ->get();

        foreach ($patterns as $pattern) {
            $result = $pattern->testPattern($content, $context->toArray());

            if ($result['matches']) {
                $patternScore = $result['risk_score'] / 100.0;
                $score = max($score, $patternScore);

                $pattern->recordMatch(false, (int) $result['processing_time_ms']);
            }
        }

        return min(1.0, $score);
    }

    /**
     * Analyze content structure and characteristics.
     */
    protected function analyzeContent(array $formData, SubmissionContext $context): float
    {
        $score = 0.0;
        $factors = [];

        $content = $this->extractTextContent($formData);
        $contentLength = strlen($content);

        // Content length analysis
        if ($contentLength < 10) {
            $factors['very_short'] = 0.3;
        } elseif ($contentLength > 5000) {
            $factors['very_long'] = 0.2;
        }

        // Link analysis
        $linkCount = preg_match_all('/https?:\/\/[^\s]+/', $content);
        if ($linkCount > 3) {
            $factors['excessive_links'] = min(0.4, $linkCount * 0.1);
        }

        // Capitalization analysis
        if ($contentLength > 0) {
            $upperCount = preg_match_all('/[A-Z]/', $content);
            $upperRatio = $upperCount / $contentLength;

            if ($upperRatio > 0.5) {
                $factors['excessive_caps'] = 0.3;
            }
        }

        // Repetition analysis
        $words = array_count_values(str_word_count($content, 1));
        $maxRepetition = ! empty($words) ? max($words) : 0;
        if ($maxRepetition > 5) {
            $factors['excessive_repetition'] = min(0.3, $maxRepetition * 0.05);
        }

        // Email/phone pattern analysis
        $emailCount = preg_match_all('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $content);
        $phoneCount = preg_match_all('/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/', $content);

        if ($emailCount > 2 || $phoneCount > 1) {
            $factors['contact_spam'] = 0.4;
        }

        // Calculate overall content score
        foreach ($factors as $factor => $value) {
            $score += $value;
        }

        return min(1.0, $score);
    }

    /**
     * Analyze behavioral patterns in the submission.
     */
    protected function analyzeBehavior(array $formData, SubmissionContext $context): float
    {
        $score = 0.0;
        $behaviorFactors = [];

        // Submission frequency analysis
        if ($context->submissionFrequency !== null) {
            if ($context->submissionFrequency > 10) {
                $behaviorFactors['high_frequency'] = 0.5;
            } elseif ($context->submissionFrequency > 5) {
                $behaviorFactors['medium_frequency'] = 0.3;
            }
        }

        // User agent analysis
        if (! $context->hasUserAgent()) {
            $behaviorFactors['missing_user_agent'] = 0.2;
        } elseif ($context->isBot()) {
            $behaviorFactors['bot_detected'] = 0.6;
        }

        // Timing analysis
        if (! $context->isRecentSubmission()) {
            $behaviorFactors['delayed_submission'] = 0.1;
        }

        // Form completion analysis
        $fieldCount = $context->getFormFieldCount();
        $totalLength = $context->getTotalContentLength();

        if ($fieldCount > 0 && $totalLength / $fieldCount < 5) {
            $behaviorFactors['minimal_content'] = 0.2;
        }

        // Authentication status
        if (! $context->isAuthenticated && $totalLength > 1000) {
            $behaviorFactors['anonymous_long_content'] = 0.2;
        }

        // Calculate behavior score
        foreach ($behaviorFactors as $factor => $value) {
            $score += $value;
        }

        return min(1.0, $score);
    }

    /**
     * Analyze rate limiting violations.
     */
    protected function analyzeRateLimit(SubmissionContext $context): float
    {
        if (! $context->hasIpAddress()) {
            return 0.0;
        }

        $cacheKey = "rate_limit_analysis:{$context->getClientIdentifier()}";
        $recentSubmissions = Cache::get($cacheKey, 0);

        // Rate limit configuration
        $limits = $this->config->get('spam_detection.rate_limits', [
            'per_minute' => 5,
            'per_hour' => 50,
            'per_day' => 200,
        ]);

        $score = 0.0;

        // Check different time windows
        foreach ($limits as $window => $limit) {
            $windowKey = "{$cacheKey}:{$window}";
            $count = Cache::get($windowKey, 0);

            if ($count > $limit) {
                $excessRatio = ($count - $limit) / $limit;
                $score = max($score, min(1.0, 0.5 + $excessRatio * 0.5));
            }
        }

        return $score;
    }

    /**
     * Analyze using Bayesian spam filtering (simplified implementation).
     */
    protected function analyzeBayesian(array $formData, SubmissionContext $context): float
    {
        // This is a simplified Bayesian analysis
        // In a full implementation, this would use trained models

        $content = $this->extractTextContent($formData);
        if (empty($content)) {
            return 0.0;
        }

        $words = str_word_count(strtolower($content), 1);
        $spamProbability = 0.0;
        $wordCount = 0;

        // Get spam word probabilities from cache or config
        $spamWords = $this->config->get('spam_detection.bayesian_words', [
            'free' => 0.8,
            'money' => 0.7,
            'offer' => 0.6,
            'click' => 0.6,
            'buy' => 0.5,
            'sale' => 0.5,
        ]);

        foreach ($words as $word) {
            if (isset($spamWords[$word])) {
                $spamProbability += $spamWords[$word];
                $wordCount++;
            }
        }

        return $wordCount > 0 ? min(1.0, $spamProbability / $wordCount) : 0.0;
    }

    /**
     * Analyze using AI-powered content analysis (placeholder).
     */
    protected function analyzeWithAI(array $formData, SubmissionContext $context): ?float
    {
        // This would integrate with external AI services
        // For now, return null to indicate the service is not available
        return null;
    }

    /**
     * Analyze geolocation information.
     */
    protected function analyzeGeolocation(SubmissionContext $context): ?float
    {
        if (! $context->hasGeolocation()) {
            return null;
        }

        $score = 0.0;
        $geo = $context->geolocation;

        // High-risk countries or regions
        $highRiskCountries = $this->config->get('spam_detection.high_risk_countries', []);
        if (isset($geo['country']) && in_array($geo['country'], $highRiskCountries, true)) {
            $score += 0.3;
        }

        // Tor network detection
        if (isset($geo['is_tor']) && $geo['is_tor']) {
            $score += 0.5;
        }

        // VPN detection
        if (isset($geo['is_vpn']) && $geo['is_vpn']) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }

    /**
     * Analyze IP reputation.
     */
    protected function analyzeIPReputation(SubmissionContext $context): ?float
    {
        if (! $context->hasIpAddress()) {
            return null;
        }

        // This would typically query external reputation databases
        // For now, implement basic internal reputation checking

        $cacheKey = "ip_reputation:{$context->ipAddress}";
        $reputation = Cache::get($cacheKey);

        if ($reputation === null) {
            return null;
        }

        // Convert reputation score to spam probability
        return max(0.0, min(1.0, 1.0 - $reputation));
    }

    /**
     * Extract text content from form data.
     */
    protected function extractTextContent(array $formData): string
    {
        $content = '';

        foreach ($formData as $key => $value) {
            if (is_string($value) && ! empty(trim($value))) {
                $content .= trim($value).' ';
            } elseif (is_array($value)) {
                // Handle nested arrays (e.g., checkboxes, multi-select)
                $content .= $this->extractTextContent($value);
            }
        }

        return trim($content);
    }

    /**
     * Get field-specific analysis for detailed reporting.
     *
     * @param  array<string, mixed>  $formData  Form submission data
     * @param  SubmissionContext  $context  Submission context
     * @return array<string, array> Field analysis results
     */
    public function analyzeFields(array $formData, SubmissionContext $context): array
    {
        $fieldAnalysis = [];

        foreach ($formData as $fieldName => $fieldValue) {
            if (! is_string($fieldValue)) {
                continue;
            }

            $analysis = [
                'field_name' => $fieldName,
                'field_value_length' => strlen($fieldValue),
                'spam_score' => 0.0,
                'threats' => [],
                'patterns_matched' => [],
            ];

            // Analyze field content
            if (! empty(trim($fieldValue))) {
                $analysis['spam_score'] = $this->analyzeFieldContent($fieldName, $fieldValue);
                $analysis['threats'] = $this->identifyFieldThreats($fieldName, $fieldValue);
                $analysis['patterns_matched'] = $this->getMatchedPatterns($fieldValue);
            }

            $fieldAnalysis[$fieldName] = $analysis;
        }

        return $fieldAnalysis;
    }

    /**
     * Analyze individual field content.
     */
    protected function analyzeFieldContent(string $fieldName, string $fieldValue): float
    {
        $score = 0.0;

        // Field-specific validation
        switch ($fieldName) {
            case 'email':
                if (! filter_var($fieldValue, FILTER_VALIDATE_EMAIL)) {
                    $score += 0.5;
                }
                break;

            case 'url':
            case 'website':
                if (! empty($fieldValue) && ! filter_var($fieldValue, FILTER_VALIDATE_URL)) {
                    $score += 0.3;
                }
                break;

            case 'phone':
                if (! preg_match('/^[\+]?[1-9]?[\d\s\-\(\)\.]{7,15}$/', $fieldValue)) {
                    $score += 0.2;
                }
                break;
        }

        // General content analysis
        $contentScore = $this->analyzeContent(['field' => $fieldValue], new SubmissionContext);
        $score += $contentScore * 0.5; // Reduce impact for individual fields

        return min(1.0, $score);
    }

    /**
     * Identify threats in field content.
     */
    protected function identifyFieldThreats(string $fieldName, string $fieldValue): array
    {
        $threats = [];

        // Field validation threats
        if ($fieldName === 'email' && ! filter_var($fieldValue, FILTER_VALIDATE_EMAIL)) {
            $threats[] = 'invalid_email';
        }

        if (in_array($fieldName, ['url', 'website']) && ! empty($fieldValue) && ! filter_var($fieldValue, FILTER_VALIDATE_URL)) {
            $threats[] = 'invalid_url';
        }

        // Content-based threats
        if (preg_match('/https?:\/\/[^\s]+/', $fieldValue)) {
            $threats[] = 'contains_links';
        }

        if (preg_match_all('/[A-Z]/', $fieldValue) > strlen($fieldValue) * 0.5) {
            $threats[] = 'excessive_capitalization';
        }

        return $threats;
    }

    /**
     * Get patterns matched in content.
     */
    protected function getMatchedPatterns(string $content): array
    {
        $matched = [];

        $patterns = SpamPattern::active()
            ->fastProcessing()
            ->orderByPriority()
            ->limit(10) // Limit for performance
            ->get();

        foreach ($patterns as $pattern) {
            $result = $pattern->testPattern($content);

            if ($result['matches']) {
                $matched[] = [
                    'pattern_id' => $pattern->id,
                    'pattern_name' => $pattern->name,
                    'risk_score' => $result['risk_score'],
                    'confidence' => $result['match_details']['confidence'] ?? 0.8,
                ];
            }
        }

        return $matched;
    }
}
