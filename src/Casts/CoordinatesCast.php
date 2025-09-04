<?php

declare(strict_types=1);

/**
 * Cast File: CoordinatesCast.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Custom cast for handling geographic coordinates with validation
 * and precision control for the JTD-FormSecurity package.
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
 * CoordinatesCast Class
 *
 * Custom cast for handling geographic coordinates with validation and precision.
 * Ensures coordinates are within valid ranges and maintains proper precision.
 *
 * @implements CastsAttributes<array<string, mixed>|null, array<string, mixed>|null>
 */
class CoordinatesCast implements CastsAttributes
{
    /**
     * Cast the given value to coordinates array
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if (is_null($value)) {
            return null;
        }

        // If already an array, return as-is
        if (is_array($value)) {
            return $this->validateAndFormatCoordinates($value);
        }

        // If JSON string, decode it
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->validateAndFormatCoordinates($decoded);
            }
        }

        return null;
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

        $coordinates = $this->validateAndFormatCoordinates($value);

        if ($coordinates === null) {
            return null;
        }

        $encoded = json_encode($coordinates);

        return $encoded !== false ? $encoded : null;
    }

    /**
     * Validate and format coordinates
     *
     * @param  array<string, mixed>  $coordinates
     * @return array<string, float>|null
     */
    private function validateAndFormatCoordinates(array $coordinates): ?array
    {
        // Check for required keys
        if (! isset($coordinates['latitude']) || ! isset($coordinates['longitude'])) {
            return null;
        }

        $lat = (float) $coordinates['latitude'];
        $lng = (float) $coordinates['longitude'];

        // Validate latitude range (-90 to 90)
        if ($lat < -90 || $lat > 90) {
            return null;
        }

        // Validate longitude range (-180 to 180)
        if ($lng < -180 || $lng > 180) {
            return null;
        }

        $result = [
            'latitude' => round($lat, 8), // 8 decimal places for high precision
            'longitude' => round($lng, 8),
        ];

        // Include optional accuracy radius if present
        if (isset($coordinates['accuracy_radius'])) {
            $accuracy = (int) $coordinates['accuracy_radius'];
            if ($accuracy >= 0) {
                $result['accuracy_radius'] = $accuracy;
            }
        }

        return $result;
    }
}
