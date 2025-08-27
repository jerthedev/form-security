<?php

declare(strict_types=1);

/**
 * Cast File: IpRangeCast.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Custom cast for handling IP address ranges with CIDR notation
 * and validation for the JTD-FormSecurity package.
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
 * IpRangeCast Class
 *
 * Custom cast for handling IP address ranges with CIDR notation support.
 * Provides validation and conversion between different IP range formats.
 */
class IpRangeCast implements CastsAttributes
{
    /**
     * Cast the given value to IP range array
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if (is_null($value)) {
            return null;
        }

        // If already an array, return as-is
        if (is_array($value)) {
            return $this->validateAndFormatIpRange($value);
        }

        // If JSON string, decode it
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->validateAndFormatIpRange($decoded);
            }

            // Try to parse as CIDR notation
            if (strpos($value, '/') !== false) {
                return $this->parseCidrNotation($value);
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

        if (is_string($value) && strpos($value, '/') !== false) {
            $parsed = $this->parseCidrNotation($value);

            return $parsed ? json_encode($parsed) : null;
        }

        if (! is_array($value)) {
            return null;
        }

        $ipRange = $this->validateAndFormatIpRange($value);

        return $ipRange ? json_encode($ipRange) : null;
    }

    /**
     * Parse CIDR notation into IP range array
     */
    private function parseCidrNotation(string $cidr): ?array
    {
        if (! preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})$/', $cidr, $matches)) {
            return null;
        }

        $ip = $matches[1];
        $prefix = (int) $matches[2];

        // Validate IP address
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        // Validate prefix length
        if ($prefix < 0 || $prefix > 32) {
            return null;
        }

        $ipLong = ip2long($ip);
        $mask = -1 << (32 - $prefix);
        $networkLong = $ipLong & $mask;
        $broadcastLong = $networkLong | (~$mask & 0xFFFFFFFF);

        return [
            'cidr' => $cidr,
            'network_ip' => long2ip($networkLong),
            'broadcast_ip' => long2ip($broadcastLong),
            'start_ip' => long2ip($networkLong),
            'end_ip' => long2ip($broadcastLong),
            'start_integer' => $networkLong,
            'end_integer' => $broadcastLong,
            'prefix_length' => $prefix,
            'total_ips' => $broadcastLong - $networkLong + 1,
            'usable_ips' => max(0, $broadcastLong - $networkLong - 1), // Exclude network and broadcast
        ];
    }

    /**
     * Validate and format IP range array
     *
     * @param  array<string, mixed>  $range
     * @return array<string, mixed>|null
     */
    private function validateAndFormatIpRange(array $range): ?array
    {
        // Check for required keys
        if (! isset($range['start_ip']) || ! isset($range['end_ip'])) {
            return null;
        }

        $startIp = $range['start_ip'];
        $endIp = $range['end_ip'];

        // Validate IP addresses
        if (! filter_var($startIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ||
            ! filter_var($endIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        $startLong = ip2long($startIp);
        $endLong = ip2long($endIp);

        // Ensure start is less than or equal to end
        if ($startLong > $endLong) {
            return null;
        }

        $result = [
            'start_ip' => $startIp,
            'end_ip' => $endIp,
            'start_integer' => $startLong,
            'end_integer' => $endLong,
            'total_ips' => $endLong - $startLong + 1,
        ];

        // Add CIDR notation if it can be calculated
        if (isset($range['cidr'])) {
            $result['cidr'] = $range['cidr'];
        } else {
            $cidr = $this->calculateCidr($startLong, $endLong);
            if ($cidr) {
                $result['cidr'] = $cidr;
            }
        }

        // Add prefix length if available
        if (isset($range['prefix_length'])) {
            $result['prefix_length'] = (int) $range['prefix_length'];
        }

        return $result;
    }

    /**
     * Calculate CIDR notation from IP range (if possible)
     */
    private function calculateCidr(int $startLong, int $endLong): ?string
    {
        $totalIps = $endLong - $startLong + 1;

        // Check if it's a power of 2 (valid CIDR block)
        if (($totalIps & ($totalIps - 1)) !== 0) {
            return null;
        }

        // Calculate prefix length
        $prefixLength = 32 - log($totalIps, 2);

        // Verify the range aligns with CIDR boundaries
        $mask = -1 << (32 - $prefixLength);
        if (($startLong & $mask) !== $startLong) {
            return null;
        }

        return long2ip($startLong).'/'.$prefixLength;
    }
}
