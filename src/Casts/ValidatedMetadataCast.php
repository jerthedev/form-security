<?php

declare(strict_types=1);

/**
 * Cast File: ValidatedMetadataCast.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Custom cast for handling metadata with validation, sanitization,
 * and size limits for the JTD-FormSecurity package.
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
 * ValidatedMetadataCast Class
 *
 * Custom cast for handling metadata with validation, sanitization, and size limits.
 * Ensures metadata is properly structured and doesn't exceed storage limits.
 *
 * @implements CastsAttributes<array<string, mixed>, array<string, mixed>|null>
 */
class ValidatedMetadataCast implements CastsAttributes
{
    /**
     * Maximum metadata size in bytes (64KB)
     */
    private const MAX_SIZE = 65536;

    /**
     * Maximum nesting depth
     */
    private const MAX_DEPTH = 5;

    /**
     * Maximum number of keys at any level
     */
    private const MAX_KEYS = 100;

    /**
     * Cast the given value to validated metadata array
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (is_null($value)) {
            return [];
        }

        // If already an array, validate and return
        if (is_array($value)) {
            return $this->validateAndSanitizeMetadata($value);
        }

        // If JSON string, decode it
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->validateAndSanitizeMetadata($decoded);
            }
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

        if (! is_array($value)) {
            return null;
        }

        $validated = $this->validateAndSanitizeMetadata($value);

        if (empty($validated)) {
            return null;
        }

        $json = json_encode($validated, JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return null;
        }

        // Check size limit
        if (strlen($json) > self::MAX_SIZE) {
            // Try to reduce size by removing less important data
            $reduced = $this->reduceMetadataSize($validated);
            $json = json_encode($reduced, JSON_UNESCAPED_UNICODE);

            if ($json === false || strlen($json) > self::MAX_SIZE) {
                return null;
            }
        }

        return $json;
    }

    /**
     * Validate and sanitize metadata
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function validateAndSanitizeMetadata(array $metadata, int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $sanitized = [];
        $keyCount = 0;

        foreach ($metadata as $key => $value) {
            if ($keyCount >= self::MAX_KEYS) {
                break;
            }

            // Sanitize key
            $sanitizedKey = $this->sanitizeKey($key);
            if (empty($sanitizedKey)) {
                continue;
            }

            // Sanitize value
            $sanitizedValue = $this->sanitizeValue($value, $depth + 1);
            if ($sanitizedValue !== null) {
                $sanitized[$sanitizedKey] = $sanitizedValue;
                $keyCount++;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize metadata key
     */
    private function sanitizeKey(mixed $key): string
    {
        if (! is_string($key) && ! is_numeric($key)) {
            return '';
        }

        $key = (string) $key;

        // Remove or replace invalid characters
        $sanitized = preg_replace('/[^\w\-_.]/', '_', $key);
        $key = $sanitized !== null ? $sanitized : $key;

        // Limit length
        $key = substr($key, 0, 100);

        // Don't trim underscores to preserve sanitization markers
        return $key ?: '';
    }

    /**
     * Sanitize metadata value
     */
    private function sanitizeValue(mixed $value, int $depth): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            // Ensure reasonable numeric limits
            if (is_float($value)) {
                return round($value, 8); // Limit decimal precision
            }

            return is_int($value) ? $value : (int) $value;
        }

        if (is_string($value)) {
            // Limit string length and sanitize
            $value = substr($value, 0, 1000);

            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        if (is_array($value)) {
            if ($depth > self::MAX_DEPTH) {
                return null;
            }

            return $this->validateAndSanitizeMetadata($value, $depth);
        }

        // For objects or other types, try to convert to array
        if (is_object($value)) {
            try {
                $encoded = json_encode($value);
                if ($encoded !== false) {
                    $array = json_decode($encoded, true);
                    if (is_array($array)) {
                        return $this->validateAndSanitizeMetadata($array, $depth);
                    }
                }
            } catch (\Exception $e) {
                // Ignore conversion errors
            }
        }

        return null;
    }

    /**
     * Reduce metadata size by removing less important data
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function reduceMetadataSize(array $metadata): array
    {
        $reduced = [];
        $priority = $this->getKeyPriority();

        // Sort keys by priority (higher priority first)
        uksort($metadata, function ($a, $b) use ($priority) {
            $priorityA = $priority[$a] ?? 0;
            $priorityB = $priority[$b] ?? 0;

            return $priorityB <=> $priorityA;
        });

        $currentSize = 0;
        $maxSize = self::MAX_SIZE * 0.8; // Use 80% of max size as target

        foreach ($metadata as $key => $value) {
            $itemJson = json_encode([$key => $value]);
            if ($itemJson === false) {
                continue;
            }

            $itemSize = strlen($itemJson);

            if ($currentSize + $itemSize <= $maxSize) {
                $reduced[$key] = $value;
                $currentSize += $itemSize;
            }
        }

        return $reduced;
    }

    /**
     * Get key priority for size reduction
     *
     * @return array<string, int>
     */
    private function getKeyPriority(): array
    {
        return [
            // High priority - security related
            'threat_score' => 100,
            'risk_level' => 95,
            'block_reason' => 90,
            'ip_reputation' => 85,
            'geolocation' => 80,

            // Medium priority - analysis data
            'user_agent' => 70,
            'referrer' => 65,
            'form_data' => 60,
            'headers' => 55,
            'session_data' => 50,

            // Low priority - debug/trace data
            'debug_info' => 30,
            'trace_data' => 25,
            'raw_data' => 20,
            'temp_data' => 10,
        ];
    }
}
