<?php

/**
 * Service File: SpamDetectionService.php
 *
 * EPIC: EPIC-002-core-spam-detection-engine
 * SPEC: SPEC-004-pattern-based-spam-detection
 * SPRINT: Sprint-007-epic-002-foundation-setup
 * TICKET: 2012-core-spam-detection-service
 *
 * Description: Enhanced spam detection service implementing hybrid detection algorithm
 * with weighted scoring, Epic-001 integration, and comprehensive analysis capabilities.
 *
 * @see docs/Planning/Epics/EPIC-002-core-spam-detection-engine.md
 * @see docs/Planning/Specs/Core-Detection-Engine/SPEC-004-pattern-based-spam-detection.md
 * @see docs/Planning/Tickets/Core-Spam-Detection-Engine/Implementation/2012-core-spam-detection-service.md
 */

declare(strict_types=1);

namespace JTD\FormSecurity\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\FormSecurity\Contracts\ConfigurationContract;
use JTD\FormSecurity\Contracts\SpamDetectionContract;
use JTD\FormSecurity\Enums\DetectionMethod;
use JTD\FormSecurity\Models\SpamPattern;
use JTD\FormSecurity\ValueObjects\SpamDetectionResult;
use JTD\FormSecurity\ValueObjects\SubmissionContext;

/**
 * Enhanced spam detection service with hybrid detection algorithm.
 *
 * Implements comprehensive spam detection using weighted scoring across multiple
 * detection methods with performance optimization and Epic-001 integration.
 */
class SpamDetectionService implements SpamDetectionContract
{
    /**
     * Default spam patterns for fallback detection.
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
     */
    public function __construct(
        protected ConfigurationContract $config,
        protected ScoreCalculatorService $scoreCalculator,
        protected SubmissionAnalyzerService $submissionAnalyzer
    ) {}

    /**
     * Analyze form data for spam indicators using hybrid detection algorithm.
     *
     * @param  array<string, mixed>  $data  The form data to analyze
     * @param  array<string, mixed>  $context  Additional context information
     * @return array<string, mixed> Analysis results with spam score and details
     */
    public function analyzeSpam(array $data, array $context = []): array
    {
        $startTime = microtime(true);

        try {
            // Quick cache check for identical submissions (performance optimization)
            $dataHash = md5(serialize($data).serialize($context));
            $cacheKey = "spam_analysis:{$dataHash}";

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                // Update timestamp and return cached result
                $cached['timestamp'] = now()->toISOString();
                $cached['from_cache'] = true;

                return $cached;
            }

            // Create submission context
            $submissionContext = $this->createSubmissionContext($data, $context);

            // Run hybrid detection analysis with performance optimizations
            $result = $this->performHybridAnalysis($data, $submissionContext, $context);

            // Update statistics (async to avoid blocking)
            $this->updateDetectionStatistics($result);

            // Log analysis for monitoring (conditional based on config)
            if ($this->config->get('spam_detection.enable_analysis_logging', true)) {
                $this->logAnalysis($result, $data, $submissionContext);
            }

            $resultArray = $result->toArray();

            // Cache results for 30 seconds to improve performance for duplicate submissions
            if ($result->processingTimeMs < 100) { // Only cache if processing was fast
                Cache::put($cacheKey, $resultArray, 30);
            }

            return $resultArray;

        } catch (\Exception $e) {
            Log::error('Spam detection analysis failed', [
                'error' => $e->getMessage(),
                'data_summary' => $this->createDataSummary($data),
                'context' => $context,
            ]);

            $processingTime = (microtime(true) - $startTime) * 1000;

            return SpamDetectionResult::error($e->getMessage(), [], $processingTime)->toArray();
        }
    }

    /**
     * Calculate spam score for given content using weighted scoring with performance optimizations.
     *
     * @param  string  $content  The content to analyze
     * @param  array<string, mixed>  $metadata  Additional metadata for analysis
     * @return float Spam score between 0.0 (clean) and 1.0 (spam)
     */
    public function calculateSpamScore(string $content, array $metadata = []): float
    {
        if (empty(trim($content))) {
            return 0.0;
        }

        // Cache score calculation for repeated content
        $contentHash = md5($content);
        $cacheKey = "spam_score:{$contentHash}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $score = 0.0;
        $contentLength = strlen($content);

        // Content length analysis
        if ($contentLength < 10) {
            $score += 0.2; // Penalize very short content
        } elseif ($contentLength > 5000) {
            $score += 0.3; // Penalize very long content
        }

        // Optimized link analysis - single pass regex
        $linkCount = preg_match_all('/https?:\/\/[^\s]+/', $content);
        if ($linkCount > 3) {
            $score += 0.3; // Penalize excessive links
        }

        // Optimized capitalization analysis
        if ($contentLength > 0) {
            $upperCount = preg_match_all('/[A-Z]/', $content);
            $upperRatio = $upperCount / $contentLength;

            if ($upperRatio > 0.5) {
                $score += 0.2; // Penalize excessive caps
            }
        }

        // Check against spam patterns (now cached)
        $patternMatches = $this->checkSpamPatterns($content);
        if (! empty($patternMatches)) {
            // Add highest pattern confidence to score
            $maxConfidence = max(array_column($patternMatches, 'confidence'));
            $score += $maxConfidence * 0.5;
        }

        $finalScore = min(1.0, $score);

        // Cache the result for 2 minutes to improve performance
        Cache::put($cacheKey, $finalScore, 120);

        return $finalScore;
    }

    /**
     * Check if content matches known spam patterns.
     *
     * @param  string  $content  The content to check
     * @return array<string, mixed> Matched patterns and their confidence scores
     */
    public function checkSpamPatterns(string $content): array
    {
        if (empty(trim($content))) {
            return [];
        }

        // Cache the content hash for pattern matching results
        $contentHash = md5($content);
        $cacheKey = "pattern_matches:{$contentHash}";

        // Check cache first for performance
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $matches = [];

        try {
            // First check custom patterns from configuration (cached)
            $customPatterns = Cache::remember('config_spam_patterns', 300, function () {
                return config('form-security.patterns.spam', []);
            });

            foreach ($customPatterns as $pattern => $confidence) {
                if (preg_match($pattern, $content, $match)) {
                    $matches[] = [
                        'pattern' => $pattern,
                        'match' => $match[0] ?? '',
                        'confidence' => is_numeric($confidence) ? (float) $confidence : 0.8,
                        'source' => 'config',
                    ];

                    // Early exit for high confidence config patterns
                    if (is_numeric($confidence) && (float) $confidence >= 0.9) {
                        Cache::put($cacheKey, $matches, 60); // Cache for 1 minute

                        return $matches;
                    }
                }
            }

            // If high-confidence custom patterns found, return them
            if (! empty($matches) && max(array_column($matches, 'confidence')) >= 0.8) {
                Cache::put($cacheKey, $matches, 60);

                return $matches;
            }

            // Get active patterns from database (cached with optimized query)
            $patterns = Cache::remember('active_patterns_optimized', 600, function () {
                return SpamPattern::active()
                    ->select(['id', 'name', 'pattern', 'pattern_type', 'risk_score', 'case_sensitive', 'whole_word_only'])
                    ->orderByPriority()
                    ->limit(20) // Limit for performance
                    ->get();
            });

            foreach ($patterns as $pattern) {
                $startTime = microtime(true);
                $result = $pattern->testPattern($content);
                $processingTime = (microtime(true) - $startTime) * 1000;

                if ($result['matches']) {
                    $matches[] = [
                        'pattern_id' => $pattern->id,
                        'pattern' => $pattern->pattern,
                        'pattern_name' => $pattern->name,
                        'match' => $result['match_details']['match'] ?? '',
                        'confidence' => $result['risk_score'] / 100.0,
                        'risk_score' => $result['risk_score'],
                        'action' => $result['action'],
                        'processing_time_ms' => $processingTime,
                    ];

                    // Record match for analytics (async to avoid blocking)
                    $pattern->recordMatch(false, (int) $processingTime);

                    // Early exit for high confidence patterns
                    if ($result['risk_score'] >= 90) {
                        break;
                    }
                }

                // Performance safeguard - exit if taking too long
                if ($processingTime > 10) { // 10ms per pattern max
                    break;
                }
            }

            // Fallback to default patterns if no database patterns found
            if (empty($matches) && $patterns->isEmpty()) {
                $matches = $this->checkDefaultPatterns($content);
            }

        } catch (\Exception $e) {
            Log::error('Pattern matching failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content),
            ]);

            // Fallback to default patterns
            $matches = $this->checkDefaultPatterns($content);
        }

        // Cache results for 1 minute to improve performance
        Cache::put($cacheKey, $matches, 60);

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
        if (empty($identifier)) {
            return true; // Allow if no identifier
        }

        // Handle custom limits from test (max_attempts, window_minutes)
        if (isset($limits['max_attempts']) && isset($limits['window_minutes'])) {
            $cacheKey = "form_security:rate_limit:{$identifier}:custom";
            $attempts = Cache::get($cacheKey, 0);

            if ($attempts >= $limits['max_attempts']) {
                Log::warning('Rate limit exceeded', [
                    'identifier' => $identifier,
                    'attempts' => $attempts,
                    'limit' => $limits['max_attempts'],
                ]);

                return false;
            }

            // Increment counter
            $ttl = $limits['window_minutes'] * 60; // Convert minutes to seconds
            Cache::put($cacheKey, $attempts + 1, now()->addSeconds($ttl));

            return true;
        }

        $defaultLimits = $this->config->get('spam_detection.rate_limits', [
            'per_minute' => 5,
            'per_hour' => 50,
            'per_day' => 200,
            'burst_limit' => 10,
        ]);

        $limits = array_merge($defaultLimits, $limits);

        // Check multiple time windows
        $timeWindows = [
            'minute' => ['limit' => $limits['per_minute'], 'ttl' => 60],
            'hour' => ['limit' => $limits['per_hour'], 'ttl' => 3600],
            'day' => ['limit' => $limits['per_day'], 'ttl' => 86400],
        ];

        foreach ($timeWindows as $window => $config) {
            $cacheKey = "form_security:rate_limit:{$identifier}:{$window}";
            $attempts = Cache::get($cacheKey, 0);

            if ($attempts >= $config['limit']) {
                // Log rate limit violation
                Log::warning('Rate limit exceeded', [
                    'identifier' => $identifier,
                    'window' => $window,
                    'attempts' => $attempts,
                    'limit' => $config['limit'],
                ]);

                return false;
            }
        }

        // Increment counters for all windows
        foreach ($timeWindows as $window => $config) {
            $cacheKey = "form_security:rate_limit:{$identifier}:{$window}";
            $attempts = Cache::get($cacheKey, 0);
            Cache::put($cacheKey, $attempts + 1, now()->addSeconds($config['ttl']));
        }

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
        try {
            $updatedCount = 0;

            foreach ($patterns as $patternData) {
                if (! isset($patternData['pattern']) || ! isset($patternData['pattern_type'])) {
                    continue;
                }

                // Create or update pattern in database
                $pattern = SpamPattern::updateOrCreate(
                    [
                        'pattern' => $patternData['pattern'],
                        'pattern_type' => $patternData['pattern_type'],
                    ],
                    array_merge($patternData, [
                        'is_active' => true,
                        'updated_by' => 'system',
                        'last_updated_at' => now(),
                    ])
                );

                if ($pattern->wasRecentlyCreated || $pattern->wasChanged()) {
                    $updatedCount++;
                }
            }

            // Clear pattern cache
            Cache::forget('active_patterns');
            Cache::forget('patterns_by_type');

            Log::info('Spam patterns updated', [
                'patterns_processed' => count($patterns),
                'patterns_updated' => $updatedCount,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update spam patterns', [
                'error' => $e->getMessage(),
                'patterns_count' => count($patterns),
            ]);

            return false;
        }
    }

    /**
     * Get current spam detection statistics.
     *
     * @return array<string, mixed> Statistics including detection rates and performance metrics
     */
    public function getDetectionStats(): array
    {
        try {
            $stats = [
                'total_analyzed' => Cache::get('form_security:stats:total_analyzed', 0),
                'spam_detected' => Cache::get('form_security:stats:spam_detected', 0),
                'clean_submissions' => Cache::get('form_security:stats:clean_submissions', 0),
                'false_positives' => Cache::get('form_security:stats:false_positives', 0),
                'average_processing_time' => Cache::get('form_security:stats:avg_processing_time', 0.0),
                'accuracy_rate' => 0.0,
                'patterns_count' => SpamPattern::active()->count(),
                'method_statistics' => $this->getMethodStatistics(),
                'performance_metrics' => $this->getPerformanceMetrics(),
                'recent_trends' => $this->getRecentTrends(),
                'timestamp' => now()->toISOString(),
            ];

            // Calculate accuracy rate
            $totalProcessed = $stats['spam_detected'] + $stats['clean_submissions'];
            if ($totalProcessed > 0) {
                $truePositives = $stats['spam_detected'] - $stats['false_positives'];
                $stats['accuracy_rate'] = max(0.0, $truePositives / $totalProcessed);
            }

            return $stats;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve detection statistics', [
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => 'Statistics temporarily unavailable',
                'timestamp' => now()->toISOString(),
            ];
        }
    }

    /**
     * Create submission context from form data and metadata.
     */
    protected function createSubmissionContext(array $formData, array $context): SubmissionContext
    {
        // Create a new SubmissionContext with the provided data
        return new SubmissionContext(
            ipAddress: $context['ip'] ?? $context['ip_address'] ?? '127.0.0.1',
            userAgent: $context['user_agent'] ?? null,
            referer: $context['referer'] ?? null,
            headers: $context['headers'] ?? [],
            sessionId: $context['session_id'] ?? null,
            userId: $context['user_id'] ?? null,
            formName: $context['form_name'] ?? null,
            formData: $formData,
            timestamp: now(),
            submissionFrequency: isset($context['submission_frequency']) ? (float) $context['submission_frequency'] : null,
            geolocation: $context['geolocation'] ?? [],
            deviceInfo: $context['device_info'] ?? [],
            behaviorData: $context['behavior_data'] ?? [],
            metadata: $context['metadata'] ?? $context,
            fingerprint: $context['fingerprint'] ?? null,
            isAuthenticated: $context['is_authenticated'] ?? false,
            flags: $context['flags'] ?? []
        );
    }

    /**
     * Perform hybrid detection analysis with performance optimizations.
     */
    protected function performHybridAnalysis(array $formData, SubmissionContext $context, array $rawContext = []): SpamDetectionResult
    {
        $startTime = microtime(true);
        $methodScores = [];
        $threats = [];
        $details = [];

        try {
            // Early exit check - if rate limited, return immediately
            if (! $context->hasIpAddress() || ! $this->checkRateLimit($context->ipAddress)) {
                $processingTime = (microtime(true) - $startTime) * 1000;

                return new SpamDetectionResult(
                    overallScore: 1.0,
                    threats: ['rate_limited'],
                    methodScores: ['rate_limit' => 1.0],
                    details: [],
                    isSpam: true,
                    confidence: 1.0,
                    metadata: ['early_exit' => 'rate_limit'],
                    processingTimeMs: $processingTime,
                    timestamp: now(),
                    recommendation: 'block',
                    analyzedContent: $this->createContentSummary($formData),
                    context: $context->toArray()
                );
            }

            // Run comprehensive analysis using submission analyzer
            $methodScores = $this->submissionAnalyzer->analyzeSubmission($formData, $context);

            // Early exit optimization - if any method scores very high, stop processing
            $maxScore = max($methodScores);
            if ($maxScore >= 0.9 && count($methodScores) >= 2) {
                $processingTime = (microtime(true) - $startTime) * 1000;

                return new SpamDetectionResult(
                    overallScore: $maxScore,
                    threats: $this->getHighConfidenceThreats($methodScores),
                    methodScores: $methodScores,
                    details: [], // Skip detailed analysis for performance
                    isSpam: true,
                    confidence: 0.95,
                    metadata: ['early_exit' => 'high_confidence', 'max_score' => $maxScore],
                    processingTimeMs: $processingTime,
                    timestamp: now(),
                    recommendation: 'block',
                    analyzedContent: $this->createContentSummary($formData),
                    context: $context->toArray()
                );
            }

            // Calculate weighted overall score
            $overallScore = $this->scoreCalculator->calculateWeightedScore($methodScores);

            // Calculate confidence based on method agreement
            $confidence = $this->scoreCalculator->calculateConfidence($methodScores);

            // Apply context-based adjustments
            $contextAdjustment = $this->scoreCalculator->calculateContextAdjustments(
                $context->getRiskIndicators()
            );
            $overallScore = max(0.0, min(1.0, $overallScore + $contextAdjustment));

            // Determine if content is spam
            $isSpam = $this->scoreCalculator->isSpam($overallScore);

            // Get field-level details (only if needed for logging or debugging)
            if ($this->config->get('spam_detection.detailed_analysis', false) || $overallScore > 0.5) {
                $details = $this->submissionAnalyzer->analyzeFields($formData, $context);
            }

            // Identify threats including raw context analysis
            $threats = $this->identifyThreats($methodScores, $context, $details, $rawContext);

            $processingTime = (microtime(true) - $startTime) * 1000;

            // Create result
            $result = new SpamDetectionResult(
                overallScore: $overallScore,
                threats: $threats,
                methodScores: $methodScores,
                details: $details,
                isSpam: $isSpam,
                confidence: $confidence,
                metadata: [
                    'context_adjustment' => $contextAdjustment,
                    'risk_level' => $this->scoreCalculator->getRiskLevel($overallScore),
                    'methods_used' => array_keys($methodScores),
                ],
                processingTimeMs: $processingTime,
                timestamp: now(),
                recommendation: $this->getRecommendation($overallScore, $confidence, $isSpam),
                analyzedContent: $this->createContentSummary($formData),
                context: $context->toArray()
            );

            return $result;

        } catch (\Exception $e) {
            $processingTime = (microtime(true) - $startTime) * 1000;

            return SpamDetectionResult::error($e->getMessage(), [], $processingTime);
        }
    }

    /**
     * Identify threats from analysis results.
     */
    protected function identifyThreats(array $methodScores, SubmissionContext $context, array $details, array $rawContext = []): array
    {
        $threats = [];

        // Add context-based threats
        $threats = array_merge($threats, $context->getRiskIndicators());

        // Add specific context analysis threats from SubmissionContext
        $contextAnalysis = $this->analyzeContext($context->toArray());
        if (! empty($contextAnalysis['threats'])) {
            $threats = array_merge($threats, $contextAnalysis['threats']);
        }

        // Add specific context analysis threats from raw context (for test compatibility)
        if (! empty($rawContext)) {
            $rawContextAnalysis = $this->analyzeContext($rawContext);
            if (! empty($rawContextAnalysis['threats'])) {
                $threats = array_merge($threats, $rawContextAnalysis['threats']);
            }
        }

        // Add method-specific threats
        foreach ($methodScores as $method => $score) {
            if ($score > 0.7) {
                $threats[] = "high_{$method}_score";
            }
        }

        // Add field-specific threats
        foreach ($details as $fieldName => $fieldDetails) {
            if (! empty($fieldDetails['threats'])) {
                $threats = array_merge($threats, $fieldDetails['threats']);
            }
        }

        return array_unique($threats);
    }

    /**
     * Get recommendation based on analysis results.
     */
    protected function getRecommendation(float $score, float $confidence, bool $isSpam): string
    {
        if ($isSpam && $confidence > 0.8) {
            return 'block';
        }

        if ($score > 0.4 && $confidence < 0.6) {
            return 'review';
        }

        if ($score > 0.7) {
            return 'captcha';
        }

        return 'allow';
    }

    /**
     * Create content summary for logging.
     */
    protected function createContentSummary(array $formData): array
    {
        return [
            'field_count' => count($formData),
            'total_length' => array_sum(array_map('strlen', array_filter($formData, 'is_string'))),
            'fields' => array_keys($formData),
        ];
    }

    /**
     * Create data summary for error logging.
     */
    protected function createDataSummary(array $data): array
    {
        return [
            'field_count' => count($data),
            'total_length' => array_sum(array_map(fn ($v) => is_string($v) ? strlen($v) : 0, $data)),
            'field_types' => array_map('gettype', $data),
        ];
    }

    /**
     * Check content against default patterns (fallback).
     */
    protected function checkDefaultPatterns(string $content): array
    {
        $matches = [];

        foreach ($this->defaultPatterns as $pattern) {
            if (preg_match($pattern, $content, $match)) {
                $matches[] = [
                    'pattern' => $pattern,
                    'match' => $match[0] ?? '',
                    'confidence' => 0.8,
                    'risk_score' => 80,
                    'source' => 'default',
                ];
            }
        }

        return $matches;
    }

    /**
     * Update detection statistics.
     */
    protected function updateDetectionStatistics(SpamDetectionResult $result): void
    {
        try {
            // Update counters
            Cache::increment('form_security:stats:total_analyzed');

            if ($result->isSpam) {
                Cache::increment('form_security:stats:spam_detected');
            } else {
                Cache::increment('form_security:stats:clean_submissions');
            }

            // Update average processing time (rolling average)
            $currentAvg = Cache::get('form_security:stats:avg_processing_time', 0.0);
            $newAvg = ($currentAvg + $result->processingTimeMs) / 2;
            Cache::put('form_security:stats:avg_processing_time', $newAvg, now()->addHours(24));

            // Update method-specific statistics
            foreach ($result->methodScores as $method => $score) {
                $key = "form_security:stats:method:{$method}";
                $stats = Cache::get($key, ['count' => 0, 'total_score' => 0.0]);
                $stats['count']++;
                $stats['total_score'] += $score;
                Cache::put($key, $stats, now()->addHours(24));
            }

        } catch (\Exception $e) {
            Log::error('Failed to update detection statistics', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log analysis for monitoring and debugging.
     */
    protected function logAnalysis(SpamDetectionResult $result, array $data, SubmissionContext $context): void
    {
        if (! $this->config->get('spam_detection.enable_analysis_logging', true)) {
            return;
        }

        $logLevel = $result->isSpam ? 'warning' : 'info';

        Log::log($logLevel, 'Spam detection analysis completed', [
            'overall_score' => $result->overallScore,
            'is_spam' => $result->isSpam,
            'confidence' => $result->confidence,
            'threats' => $result->threats,
            'processing_time_ms' => $result->processingTimeMs,
            'methods_used' => array_keys($result->methodScores),
            'recommendation' => $result->recommendation,
            'context_summary' => [
                'ip' => $context->ipAddress,
                'user_agent_present' => $context->hasUserAgent(),
                'authenticated' => $context->isAuthenticated,
                'form_name' => $context->formName,
            ],
            'data_summary' => $this->createDataSummary($data),
        ]);
    }

    /**
     * Get method-specific statistics.
     */
    protected function getMethodStatistics(): array
    {
        $stats = [];

        foreach (DetectionMethod::getAllMethods() as $method) {
            $key = "form_security:stats:method:{$method->value}";
            $methodStats = Cache::get($key, ['count' => 0, 'total_score' => 0.0]);

            $stats[$method->value] = [
                'usage_count' => $methodStats['count'],
                'average_score' => $methodStats['count'] > 0
                    ? $methodStats['total_score'] / $methodStats['count']
                    : 0.0,
                'default_weight' => $method->getDefaultWeight(),
                'description' => $method->getDescription(),
            ];
        }

        return $stats;
    }

    /**
     * Get performance metrics.
     */
    protected function getPerformanceMetrics(): array
    {
        return [
            'average_processing_time' => Cache::get('form_security:stats:avg_processing_time', 0.0),
            'target_processing_time' => 50.0, // <50ms target
            'performance_status' => $this->getPerformanceStatus(),
            'cache_hit_ratio' => $this->getCacheHitRatio(),
        ];
    }

    /**
     * Get recent detection trends.
     */
    protected function getRecentTrends(): array
    {
        $hourlyStats = [];

        for ($i = 0; $i < 24; $i++) {
            $hour = now()->subHours($i)->format('Y-m-d-H');
            $key = "form_security:stats:hourly:{$hour}";
            $stats = Cache::get($key, ['analyzed' => 0, 'spam' => 0]);

            $hourlyStats[] = [
                'hour' => $hour,
                'analyzed' => $stats['analyzed'],
                'spam_detected' => $stats['spam'],
                'spam_rate' => $stats['analyzed'] > 0 ? $stats['spam'] / $stats['analyzed'] : 0,
            ];
        }

        return array_reverse($hourlyStats);
    }

    /**
     * Get performance status.
     */
    protected function getPerformanceStatus(): string
    {
        $avgTime = Cache::get('form_security:stats:avg_processing_time', 0.0);

        return match (true) {
            $avgTime < 10 => 'excellent',
            $avgTime < 25 => 'good',
            $avgTime < 50 => 'acceptable',
            default => 'needs_improvement',
        };
    }

    /**
     * Get cache hit ratio (simplified).
     */
    protected function getCacheHitRatio(): float
    {
        $hits = Cache::get('form_security:cache:hits', 0);
        $total = Cache::get('form_security:cache:total', 0);

        return $total > 0 ? $hits / $total : 0.0;
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
     * @param  array<string, mixed>  $context  Context information (can be raw context or SubmissionContext array)
     * @return array<string, mixed> Context analysis results
     */
    protected function analyzeContext(array $context): array
    {
        $scoreAdjustment = 0.0;
        $threats = [];

        // Handle both direct context arrays and SubmissionContext->toArray() format
        $submissionFrequency = $context['submission_frequency'] ?? $context['submissionFrequency'] ?? null;
        $userAgent = $context['user_agent'] ?? $context['userAgent'] ?? null;

        // Check submission frequency
        if ($submissionFrequency !== null && $submissionFrequency > 5) {
            $scoreAdjustment += 0.2;
            $threats[] = 'high_frequency_submission';
        }

        // Check user agent
        if (isset($userAgent) && empty($userAgent)) {
            $scoreAdjustment += 0.1;
            $threats[] = 'missing_user_agent';
        }

        return [
            'score_adjustment' => $scoreAdjustment,
            'threats' => $threats,
        ];
    }
}
