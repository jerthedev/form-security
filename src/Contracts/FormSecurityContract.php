<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Contracts;

/**
 * Main FormSecurity contract defining the core package interface.
 *
 * This contract defines the primary methods for form security operations
 * including spam detection, validation, and protection mechanisms.
 */
interface FormSecurityContract
{
    /**
     * Analyze a form submission for spam and security threats.
     *
     * @param  array<string, mixed>  $data  The form data to analyze
     * @param  array<string, mixed>  $options  Additional analysis options
     * @return array<string, mixed> Analysis results with score and recommendations
     */
    public function analyzeSubmission(array $data, array $options = []): array;

    /**
     * Validate form data against security rules.
     *
     * @param  array<string, mixed>  $data  The form data to validate
     * @param  array<string, mixed>  $rules  Validation rules to apply
     * @return bool True if validation passes, false otherwise
     */
    public function validateSubmission(array $data, array $rules = []): bool;

    /**
     * Check if an IP address is blocked or suspicious.
     *
     * @param  string  $ipAddress  The IP address to check
     * @return bool True if IP is blocked, false otherwise
     */
    public function isIpBlocked(string $ipAddress): bool;

    /**
     * Get the current configuration for the package.
     *
     * @param  string|null  $key  Optional configuration key to retrieve
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed Configuration value or full config array
     */
    public function getConfig(?string $key = null, mixed $default = null): mixed;

    /**
     * Enable or disable specific security features.
     *
     * @param  string  $feature  The feature name to toggle
     * @param  bool  $enabled  Whether to enable or disable the feature
     * @return bool True if feature was successfully toggled
     */
    public function toggleFeature(string $feature, bool $enabled): bool;

    /**
     * Get package version and status information.
     *
     * @return array<string, mixed> Package information including version, features, and status
     */
    public function getPackageInfo(): array;
}
