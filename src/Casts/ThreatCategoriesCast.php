<?php

declare(strict_types=1);

/**
 * Cast File: ThreatCategoriesCast.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Custom cast for handling threat categories with validation
 * and normalization for the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * ThreatCategoriesCast Class
 *
 * Custom cast for handling threat categories with validation and normalization.
 * Ensures threat categories are from a predefined list and properly formatted.
 *
 * @implements CastsAttributes<array<string>, array<mixed>|string|null>
 */
class ThreatCategoriesCast implements CastsAttributes
{
    /**
     * Valid threat categories
     */
    private const VALID_CATEGORIES = [
        'malware',
        'botnet',
        'spam',
        'phishing',
        'scanning',
        'brute_force',
        'ddos',
        'proxy',
        'tor',
        'vpn',
        'hosting',
        'suspicious',
        'blacklist',
        'reputation',
        'geolocation',
        'behavioral',
    ];

    /**
     * Cast the given value to threat categories array
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (is_null($value)) {
            return [];
        }

        // If already an array, validate and return
        if (is_array($value)) {
            return $this->validateAndNormalizeCategories($value);
        }

        // If JSON string, decode it
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->validateAndNormalizeCategories($decoded);
            }

            // If comma-separated string, split it
            if (strpos($value, ',') !== false) {
                $categories = array_map('trim', explode(',', $value));

                return $this->validateAndNormalizeCategories($categories);
            }

            // Single category as string
            return $this->validateAndNormalizeCategories([$value]);
        }

        return [];
    }

    /**
     * Prepare the given value for storage
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if (is_string($value)) {
            // Handle comma-separated string
            if (strpos($value, ',') !== false) {
                $categories = array_map('trim', explode(',', $value));
                $validated = $this->validateAndNormalizeCategories($categories);
            } else {
                $validated = $this->validateAndNormalizeCategories([$value]);
            }
        } elseif (is_array($value)) {
            $validated = $this->validateAndNormalizeCategories($value);
        } else {
            return null;
        }

        if (empty($validated)) {
            return null;
        }

        $encoded = json_encode($validated);

        return $encoded !== false ? $encoded : null;
    }

    /**
     * Validate and normalize threat categories
     *
     * @param  array<mixed>  $categories
     * @return array<string>
     */
    private function validateAndNormalizeCategories(array $categories): array
    {
        $normalized = [];

        foreach ($categories as $category) {
            if (! is_string($category)) {
                continue;
            }

            // Normalize: lowercase, trim, remove spaces/hyphens
            $normalized_category = strtolower(trim($category));
            $normalized_category = preg_replace('/[\s\-]+/', '', $normalized_category);

            // Handle special cases
            $normalized_category = match ($normalized_category) {
                'botnet', 'bot_net' => 'botnet',
                'spamemail', 'spam_email' => 'spam',
                default => $normalized_category
            };

            // Validate against allowed categories
            if (in_array($normalized_category, self::VALID_CATEGORIES, true)) {
                $normalized[] = $normalized_category;
            }
        }

        // Remove duplicates and sort
        $normalized = array_unique($normalized);
        sort($normalized);

        return $normalized;
    }

    /**
     * Get all valid threat categories
     *
     * @return array<string>
     */
    public static function getValidCategories(): array
    {
        return self::VALID_CATEGORIES;
    }

    /**
     * Check if a category is valid
     */
    public static function isValidCategory(string $category): bool
    {
        $normalized = strtolower(trim($category));
        $normalized = preg_replace('/[\s\-]+/', '', $normalized);

        // Handle special cases
        $normalized = match ($normalized) {
            'botnet', 'bot_net' => 'botnet',
            'spamemail', 'spam_email' => 'spam',
            default => $normalized
        };

        return in_array($normalized, self::VALID_CATEGORIES, true);
    }

    /**
     * Get category descriptions
     *
     * @return array<string, string>
     */
    public static function getCategoryDescriptions(): array
    {
        return [
            'malware' => 'Known malware distribution or infection',
            'botnet' => 'Part of a botnet or zombie network',
            'spam' => 'Email spam or message spam source',
            'phishing' => 'Phishing or social engineering attempts',
            'scanning' => 'Port scanning or vulnerability scanning',
            'brute_force' => 'Brute force login attempts',
            'ddos' => 'Distributed denial of service attacks',
            'proxy' => 'Open proxy or proxy service',
            'tor' => 'Tor exit node or relay',
            'vpn' => 'VPN service or endpoint',
            'hosting' => 'Hosting provider or data center',
            'suspicious' => 'Suspicious or anomalous behavior',
            'blacklist' => 'Listed on security blacklists',
            'reputation' => 'Poor reputation from threat intelligence',
            'geolocation' => 'Geographic location-based risk',
            'behavioral' => 'Behavioral analysis indicators',
        ];
    }
}
