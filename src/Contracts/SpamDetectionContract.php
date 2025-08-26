<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Contracts;

/**
 * Spam Detection contract for analyzing form submissions.
 *
 * This contract defines methods for detecting spam and malicious content
 * in form submissions using various detection algorithms and patterns.
 */
interface SpamDetectionContract
{
    /**
     * Analyze form data for spam indicators.
     *
     * @param  array<string, mixed>  $data  The form data to analyze
     * @param  array<string, mixed>  $context  Additional context information
     * @return array<string, mixed> Analysis results with spam score and details
     */
    public function analyzeSpam(array $data, array $context = []): array;

    /**
     * Calculate spam score for given content.
     *
     * @param  string  $content  The content to analyze
     * @param  array<string, mixed>  $metadata  Additional metadata for analysis
     * @return float Spam score between 0.0 (clean) and 1.0 (spam)
     */
    public function calculateSpamScore(string $content, array $metadata = []): float;

    /**
     * Check if content matches known spam patterns.
     *
     * @param  string  $content  The content to check
     * @return array<string, mixed> Matched patterns and their confidence scores
     */
    public function checkSpamPatterns(string $content): array;

    /**
     * Validate submission rate limits for IP/user.
     *
     * @param  string  $identifier  IP address or user identifier
     * @param  array<string, mixed>  $limits  Rate limit configuration
     * @return bool True if within limits, false if rate limited
     */
    public function checkRateLimit(string $identifier, array $limits = []): bool;

    /**
     * Update spam detection patterns and rules.
     *
     * @param  array<string, mixed>  $patterns  New patterns to add or update
     * @return bool True if patterns were successfully updated
     */
    public function updateSpamPatterns(array $patterns): bool;

    /**
     * Get current spam detection statistics.
     *
     * @return array<string, mixed> Statistics including detection rates and performance metrics
     */
    public function getDetectionStats(): array;
}
