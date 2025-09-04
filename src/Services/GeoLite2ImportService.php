<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeoLite2ImportService
{
    protected int $chunkSize = 1000;

    protected int $memoryLimit = 128; // MB

    protected string $tempPath = 'temp/geolite2';

    /**
     * Import GeoLite2 locations data from CSV file
     */
    public function importLocations(string $csvPath, ?callable $progressCallback = null): array
    {
        $stats = [
            'processed' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
            'start_time' => microtime(true),
        ];

        try {
            if (! file_exists($csvPath)) {
                throw new Exception("CSV file not found: {$csvPath}");
            }

            $handle = fopen($csvPath, 'r');
            if (! $handle) {
                throw new Exception("Cannot open CSV file: {$csvPath}");
            }

            // Skip header row
            $header = fgetcsv($handle);
            $this->validateLocationsHeader($header);

            $chunk = [];
            $chunkCount = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $stats['processed']++;

                try {
                    $locationData = $this->parseLocationRow($row, $header);
                    if ($locationData) {
                        $chunk[] = $locationData;

                        if (count($chunk) >= $this->chunkSize) {
                            $imported = $this->insertLocationChunk($chunk);
                            $stats['imported'] += $imported;
                            $chunkCount++;
                            $chunk = [];

                            // Memory management
                            if ($chunkCount % 10 === 0) {
                                $this->checkMemoryUsage();
                            }

                            // Progress callback
                            if ($progressCallback) {
                                $progressCallback($stats);
                            }
                        }
                    } else {
                        $stats['skipped']++;
                    }
                } catch (Exception $e) {
                    $stats['errors']++;
                    Log::warning('Error processing location row: '.$e->getMessage(), [
                        'row' => $row,
                        'line' => $stats['processed'],
                    ]);
                }
            }

            // Process remaining chunk
            if (! empty($chunk)) {
                $imported = $this->insertLocationChunk($chunk);
                $stats['imported'] += $imported;
            }

            fclose($handle);

        } catch (Exception $e) {
            Log::error('GeoLite2 locations import failed: '.$e->getMessage());
            throw $e;
        }

        $stats['end_time'] = microtime(true);
        $stats['duration'] = $stats['end_time'] - $stats['start_time'];

        return $stats;
    }

    /**
     * Import GeoLite2 IPv4 blocks data from CSV file
     */
    public function importIPv4Blocks(string $csvPath, ?callable $progressCallback = null): array
    {
        $stats = [
            'processed' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
            'start_time' => microtime(true),
        ];

        try {
            if (! file_exists($csvPath)) {
                throw new Exception("CSV file not found: {$csvPath}");
            }

            $handle = fopen($csvPath, 'r');
            if (! $handle) {
                throw new Exception("Cannot open CSV file: {$csvPath}");
            }

            // Skip header row
            $header = fgetcsv($handle);
            $this->validateIPv4BlocksHeader($header);

            $chunk = [];
            $chunkCount = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $stats['processed']++;

                try {
                    $blockData = $this->parseIPv4BlockRow($row, $header);
                    if ($blockData) {
                        $chunk[] = $blockData;

                        if (count($chunk) >= $this->chunkSize) {
                            $imported = $this->insertIPv4BlockChunk($chunk);
                            $stats['imported'] += $imported;
                            $chunkCount++;
                            $chunk = [];

                            // Memory management
                            if ($chunkCount % 10 === 0) {
                                $this->checkMemoryUsage();
                            }

                            // Progress callback
                            if ($progressCallback) {
                                $progressCallback($stats);
                            }
                        }
                    } else {
                        $stats['skipped']++;
                    }
                } catch (Exception $e) {
                    $stats['errors']++;
                    Log::warning('Error processing IPv4 block row: '.$e->getMessage(), [
                        'row' => $row,
                        'line' => $stats['processed'],
                    ]);
                }
            }

            // Process remaining chunk
            if (! empty($chunk)) {
                $imported = $this->insertIPv4BlockChunk($chunk);
                $stats['imported'] += $imported;
            }

            fclose($handle);

        } catch (Exception $e) {
            Log::error('GeoLite2 IPv4 blocks import failed: '.$e->getMessage());
            throw $e;
        }

        $stats['end_time'] = microtime(true);
        $stats['duration'] = $stats['end_time'] - $stats['start_time'];

        return $stats;
    }

    /**
     * Validate locations CSV header
     */
    protected function validateLocationsHeader(array $header): void
    {
        $required = ['geoname_id', 'locale_code', 'continent_code', 'continent_name',
            'country_iso_code', 'country_name', 'subdivision_1_iso_code',
            'subdivision_1_name', 'city_name', 'time_zone'];

        foreach ($required as $field) {
            if (! in_array($field, $header)) {
                throw new Exception("Missing required header field: {$field}");
            }
        }
    }

    /**
     * Validate IPv4 blocks CSV header
     */
    protected function validateIPv4BlocksHeader(array $header): void
    {
        $required = ['network', 'geoname_id', 'registered_country_geoname_id',
            'represented_country_geoname_id', 'is_anonymous_proxy',
            'is_satellite_provider', 'postal_code', 'latitude', 'longitude'];

        foreach ($required as $field) {
            if (! in_array($field, $header)) {
                throw new Exception("Missing required header field: {$field}");
            }
        }
    }

    /**
     * Parse location CSV row into database format
     */
    protected function parseLocationRow(array $row, array $header): ?array
    {
        $data = array_combine($header, $row);

        if (empty($data['geoname_id'])) {
            return null;
        }

        return [
            'geoname_id' => (int) $data['geoname_id'],
            'locale_code' => $data['locale_code'] ?: 'en',
            'continent_code' => $data['continent_code'] ?: null,
            'continent_name' => $data['continent_name'] ?: null,
            'country_iso_code' => $data['country_iso_code'] ?: null,
            'country_name' => $data['country_name'] ?: null,
            'subdivision_1_iso_code' => $data['subdivision_1_iso_code'] ?: null,
            'subdivision_1_name' => $data['subdivision_1_name'] ?: null,
            'subdivision_2_iso_code' => $data['subdivision_2_iso_code'] ?? null,
            'subdivision_2_name' => $data['subdivision_2_name'] ?? null,
            'city_name' => $data['city_name'] ?: null,
            'metro_code' => $data['metro_code'] ?? null,
            'time_zone' => $data['time_zone'] ?: null,
            'latitude' => ! empty($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => ! empty($data['longitude']) ? (float) $data['longitude'] : null,
            'accuracy_radius' => ! empty($data['accuracy_radius']) ? (int) $data['accuracy_radius'] : null,
            'is_in_european_union' => ($data['is_in_european_union'] ?? '0') === '1',
            'data_updated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Parse IPv4 block CSV row into database format
     */
    protected function parseIPv4BlockRow(array $row, array $header): ?array
    {
        $data = array_combine($header, $row);

        if (empty($data['network'])) {
            return null;
        }

        // Calculate integer representations for IP range
        [$networkStart, $networkLast] = $this->calculateIPRange($data['network']);

        return [
            'network' => $data['network'],
            'network_start_integer' => $networkStart,
            'network_last_integer' => $networkLast,
            'geoname_id' => ! empty($data['geoname_id']) ? (int) $data['geoname_id'] : null,
            'registered_country_geoname_id' => ! empty($data['registered_country_geoname_id']) ? (int) $data['registered_country_geoname_id'] : null,
            'represented_country_geoname_id' => ! empty($data['represented_country_geoname_id']) ? (int) $data['represented_country_geoname_id'] : null,
            'is_anonymous_proxy' => ($data['is_anonymous_proxy'] ?? '0') === '1',
            'is_satellite_provider' => ($data['is_satellite_provider'] ?? '0') === '1',
            'is_anycast' => ($data['is_anycast'] ?? '0') === '1',
            'postal_code' => $data['postal_code'] ?: null,
            'latitude' => ! empty($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => ! empty($data['longitude']) ? (float) $data['longitude'] : null,
            'accuracy_radius' => ! empty($data['accuracy_radius']) ? (int) $data['accuracy_radius'] : null,
            'data_updated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Calculate IP range integers from CIDR notation
     */
    protected function calculateIPRange(string $cidr): array
    {
        [$ip, $prefix] = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $mask = -1 << (32 - (int) $prefix);
        $networkStart = $ipLong & $mask;
        $networkLast = $networkStart | (~$mask & 0xFFFFFFFF);

        return [$networkStart, $networkLast];
    }

    /**
     * Insert location data chunk into database
     */
    protected function insertLocationChunk(array $chunk): int
    {
        try {
            DB::table('geolite2_locations')->insertOrIgnore($chunk);

            return count($chunk);
        } catch (Exception $e) {
            Log::error('Failed to insert location chunk: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Insert IPv4 block data chunk into database
     */
    protected function insertIPv4BlockChunk(array $chunk): int
    {
        try {
            DB::table('geolite2_ipv4_blocks')->insertOrIgnore($chunk);

            return count($chunk);
        } catch (Exception $e) {
            Log::error('Failed to insert IPv4 block chunk: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Check memory usage and garbage collect if needed
     */
    protected function checkMemoryUsage(): void
    {
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB

        if ($memoryUsage > $this->memoryLimit) {
            gc_collect_cycles();
            Log::info('Memory cleanup performed', [
                'memory_before' => $memoryUsage,
                'memory_after' => memory_get_usage(true) / 1024 / 1024,
            ]);
        }
    }

    /**
     * Set chunk size for processing
     */
    public function setChunkSize(int $size): self
    {
        $this->chunkSize = max(100, min(10000, $size));

        return $this;
    }

    /**
     * Set memory limit for processing
     */
    public function setMemoryLimit(int $limitMB): self
    {
        $this->memoryLimit = max(64, $limitMB);

        return $this;
    }

    /**
     * Clear all GeoLite2 data from database
     */
    public function clearData(): void
    {
        DB::table('geolite2_ipv4_blocks')->truncate();
        DB::table('geolite2_locations')->truncate();
        Log::info('GeoLite2 data cleared from database');
    }

    /**
     * Get import statistics
     */
    public function getImportStats(): array
    {
        return [
            'locations_count' => DB::table('geolite2_locations')->count(),
            'ipv4_blocks_count' => DB::table('geolite2_ipv4_blocks')->count(),
            'last_updated' => DB::table('geolite2_locations')->max('data_updated_at'),
        ];
    }

    /**
     * Alias for importLocations with chunked processing
     */
    public function importLocationsChunked(string $filePath, int $batchSize = 1000): bool
    {
        $this->setChunkSize($batchSize);
        $result = $this->importLocations($filePath);

        return $result['imported'] > 0;
    }

    /**
     * Alias for importIPv4Blocks
     */
    public function importIpBlocks(string $filePath): bool
    {
        $result = $this->importIPv4Blocks($filePath);

        return $result['imported'] > 0;
    }

    /**
     * Alias for importIPv4Blocks with chunked processing
     */
    public function importIpBlocksChunked(string $filePath, int $batchSize = 1000): bool
    {
        $this->setChunkSize($batchSize);
        $result = $this->importIPv4Blocks($filePath);

        return $result['imported'] > 0;
    }
}
