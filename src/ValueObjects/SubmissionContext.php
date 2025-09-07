<?php

declare(strict_types=1);

namespace JTD\FormSecurity\ValueObjects;

use Carbon\Carbon;

/**
 * Submission context value object.
 *
 * Captures the context and metadata surrounding a form submission
 * for comprehensive spam analysis and threat detection.
 */
readonly class SubmissionContext
{
    /**
     * Create a new submission context.
     *
     * @param  string|null  $ipAddress  Client IP address
     * @param  string|null  $userAgent  User agent string
     * @param  string|null  $referer  HTTP referer header
     * @param  array<string, mixed>  $headers  HTTP headers
     * @param  string|null  $sessionId  Session identifier
     * @param  string|null  $userId  User identifier (if authenticated)
     * @param  string|null  $formName  Form identifier/name
     * @param  array<string, mixed>  $formData  Original form data
     * @param  Carbon  $timestamp  Submission timestamp
     * @param  float|null  $submissionFrequency  Recent submission frequency
     * @param  array<string, mixed>  $geolocation  Geographic information
     * @param  array<string, mixed>  $deviceInfo  Device/browser information
     * @param  array<string, mixed>  $behaviorData  User behavior metrics
     * @param  array<string, mixed>  $metadata  Additional context metadata
     * @param  string|null  $fingerprint  Browser/device fingerprint
     * @param  bool  $isAuthenticated  Whether user is authenticated
     * @param  array<string>  $flags  Context flags or markers
     */
    public function __construct(
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $referer = null,
        public array $headers = [],
        public ?string $sessionId = null,
        public ?string $userId = null,
        public ?string $formName = null,
        public array $formData = [],
        public Carbon $timestamp = new Carbon,
        public ?float $submissionFrequency = null,
        public array $geolocation = [],
        public array $deviceInfo = [],
        public array $behaviorData = [],
        public array $metadata = [],
        public ?string $fingerprint = null,
        public bool $isAuthenticated = false,
        public array $flags = []
    ) {
        // Ensure timestamp is set
        if (! $this->timestamp) {
            $this->timestamp = now();
        }
    }

    /**
     * Create context from HTTP request.
     */
    public static function fromRequest(
        array $requestData,
        array $formData = [],
        ?string $formName = null
    ): self {
        return new self(
            ipAddress: $requestData['ip'] ?? null,
            userAgent: $requestData['user_agent'] ?? null,
            referer: $requestData['referer'] ?? null,
            headers: $requestData['headers'] ?? [],
            sessionId: $requestData['session_id'] ?? null,
            userId: $requestData['user_id'] ?? null,
            formName: $formName,
            formData: $formData,
            timestamp: now(),
            isAuthenticated: ! empty($requestData['user_id']),
            metadata: $requestData['metadata'] ?? []
        );
    }

    /**
     * Create minimal context with just IP and form data.
     */
    public static function minimal(
        string $ipAddress,
        array $formData = [],
        ?string $formName = null
    ): self {
        return new self(
            ipAddress: $ipAddress,
            formData: $formData,
            formName: $formName,
            timestamp: now()
        );
    }

    /**
     * Create anonymous context (no identifying information).
     */
    public static function anonymous(array $formData = []): self
    {
        return new self(
            formData: $formData,
            timestamp: now(),
            flags: ['anonymous']
        );
    }

    /**
     * Get the client identifier (IP or user ID).
     */
    public function getClientIdentifier(): string
    {
        if ($this->userId && $this->isAuthenticated) {
            return "user:{$this->userId}";
        }

        return "ip:{$this->ipAddress}";
    }

    /**
     * Check if context has IP address.
     */
    public function hasIpAddress(): bool
    {
        return ! empty($this->ipAddress);
    }

    /**
     * Check if context has user agent.
     */
    public function hasUserAgent(): bool
    {
        return ! empty($this->userAgent);
    }

    /**
     * Check if context has geolocation data.
     */
    public function hasGeolocation(): bool
    {
        return ! empty($this->geolocation);
    }

    /**
     * Check if context has behavior data.
     */
    public function hasBehaviorData(): bool
    {
        return ! empty($this->behaviorData);
    }

    /**
     * Check if context indicates suspicious activity.
     */
    public function isSuspicious(): bool
    {
        return in_array('suspicious', $this->flags, true) ||
               $this->submissionFrequency > 5.0 ||
               empty($this->userAgent);
    }

    /**
     * Get risk indicators from context.
     */
    public function getRiskIndicators(): array
    {
        $indicators = [];

        // Missing user agent
        if (empty($this->userAgent)) {
            $indicators[] = 'missing_user_agent';
        }

        // High submission frequency
        if ($this->submissionFrequency > 5.0) {
            $indicators[] = 'high_frequency_submission';
        }

        // Suspicious flags
        if (in_array('suspicious', $this->flags, true)) {
            $indicators[] = 'flagged_suspicious';
        }

        // Anonymous submission
        if (in_array('anonymous', $this->flags, true)) {
            $indicators[] = 'anonymous_submission';
        }

        // No referer (possible direct access)
        if (empty($this->referer)) {
            $indicators[] = 'missing_referer';
        }

        // Tor or VPN indicators
        if (isset($this->geolocation['is_tor']) && $this->geolocation['is_tor']) {
            $indicators[] = 'tor_network';
        }

        if (isset($this->geolocation['is_vpn']) && $this->geolocation['is_vpn']) {
            $indicators[] = 'vpn_detected';
        }

        return $indicators;
    }

    /**
     * Get form field count.
     */
    public function getFormFieldCount(): int
    {
        return count($this->formData);
    }

    /**
     * Get total form content length.
     */
    public function getTotalContentLength(): int
    {
        $length = 0;

        foreach ($this->formData as $value) {
            if (is_string($value)) {
                $length += strlen($value);
            }
        }

        return $length;
    }

    /**
     * Get submission age in seconds.
     */
    public function getSubmissionAge(): int
    {
        return (int) now()->diffInSeconds($this->timestamp);
    }

    /**
     * Check if submission is recent (within last 5 minutes).
     */
    public function isRecentSubmission(): bool
    {
        return $this->getSubmissionAge() <= 300;
    }

    /**
     * Get browser information from user agent.
     */
    public function getBrowserInfo(): array
    {
        if (empty($this->userAgent)) {
            return [];
        }

        $info = [
            'user_agent' => $this->userAgent,
            'is_mobile' => $this->isMobileDevice(),
            'is_bot' => $this->isBot(),
        ];

        // Extract browser name (simplified)
        if (strpos($this->userAgent, 'Chrome') !== false) {
            $info['browser'] = 'Chrome';
        } elseif (strpos($this->userAgent, 'Firefox') !== false) {
            $info['browser'] = 'Firefox';
        } elseif (strpos($this->userAgent, 'Safari') !== false) {
            $info['browser'] = 'Safari';
        } elseif (strpos($this->userAgent, 'Edge') !== false) {
            $info['browser'] = 'Edge';
        }

        return $info;
    }

    /**
     * Check if request is from mobile device.
     */
    public function isMobileDevice(): bool
    {
        if (empty($this->userAgent)) {
            return false;
        }

        return preg_match('/Mobile|Android|iPhone|iPad/', $this->userAgent) === 1;
    }

    /**
     * Check if request is from a bot/crawler.
     */
    public function isBot(): bool
    {
        if (empty($this->userAgent)) {
            return false;
        }

        return preg_match('/bot|crawler|spider|scraper/i', $this->userAgent) === 1;
    }

    /**
     * Get security assessment score (0.0 to 1.0, higher = more suspicious).
     */
    public function getSecurityScore(): float
    {
        $score = 0.0;
        $risks = $this->getRiskIndicators();

        // Each risk indicator adds to the score
        $score += count($risks) * 0.1;

        // High submission frequency
        if ($this->submissionFrequency > 10) {
            $score += 0.3;
        } elseif ($this->submissionFrequency > 5) {
            $score += 0.2;
        }

        // Bot detection
        if ($this->isBot()) {
            $score += 0.4;
        }

        return min(1.0, $score);
    }

    /**
     * Create context with additional flag.
     */
    public function withFlag(string $flag): self
    {
        $flags = $this->flags;
        if (! in_array($flag, $flags, true)) {
            $flags[] = $flag;
        }

        return new self(
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            referer: $this->referer,
            headers: $this->headers,
            sessionId: $this->sessionId,
            userId: $this->userId,
            formName: $this->formName,
            formData: $this->formData,
            timestamp: $this->timestamp,
            submissionFrequency: $this->submissionFrequency,
            geolocation: $this->geolocation,
            deviceInfo: $this->deviceInfo,
            behaviorData: $this->behaviorData,
            metadata: $this->metadata,
            fingerprint: $this->fingerprint,
            isAuthenticated: $this->isAuthenticated,
            flags: $flags
        );
    }

    /**
     * Create context with updated submission frequency.
     */
    public function withSubmissionFrequency(float $frequency): self
    {
        return new self(
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            referer: $this->referer,
            headers: $this->headers,
            sessionId: $this->sessionId,
            userId: $this->userId,
            formName: $this->formName,
            formData: $this->formData,
            timestamp: $this->timestamp,
            submissionFrequency: $frequency,
            geolocation: $this->geolocation,
            deviceInfo: $this->deviceInfo,
            behaviorData: $this->behaviorData,
            metadata: $this->metadata,
            fingerprint: $this->fingerprint,
            isAuthenticated: $this->isAuthenticated,
            flags: $this->flags
        );
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'referer' => $this->referer,
            'headers' => $this->headers,
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'form_name' => $this->formName,
            'form_data_summary' => [
                'field_count' => $this->getFormFieldCount(),
                'total_length' => $this->getTotalContentLength(),
            ],
            'timestamp' => $this->timestamp->toISOString(),
            'submission_frequency' => $this->submissionFrequency,
            'geolocation' => $this->geolocation,
            'device_info' => $this->deviceInfo,
            'behavior_data' => $this->behaviorData,
            'metadata' => $this->metadata,
            'fingerprint' => $this->fingerprint,
            'is_authenticated' => $this->isAuthenticated,
            'flags' => $this->flags,
            'risk_indicators' => $this->getRiskIndicators(),
            'security_score' => round($this->getSecurityScore(), 3),
            'browser_info' => $this->getBrowserInfo(),
        ];
    }

    /**
     * Convert to JSON representation.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES);
    }
}
