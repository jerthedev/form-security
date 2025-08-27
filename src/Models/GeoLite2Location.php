<?php

declare(strict_types=1);

/**
 * Model File: GeoLite2Location.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-019-geolite2-database-management
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Eloquent model for GeoLite2 location data with hierarchical geographic
 * relationships and efficient querying for the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Specialized-Features/SPEC-019-geolite2-database-management.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace JTD\FormSecurity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * GeoLite2Location Model
 *
 * Stores MaxMind GeoLite2 location data with hierarchical geographic information
 * for IP geolocation lookups and geographic analysis.
 *
 * @property int $id
 * @property int $geoname_id
 * @property string $locale_code
 * @property string|null $continent_code
 * @property string|null $continent_name
 * @property string|null $country_iso_code
 * @property string|null $country_name
 * @property string|null $subdivision_1_iso_code
 * @property string|null $subdivision_1_name
 * @property string|null $subdivision_2_iso_code
 * @property string|null $subdivision_2_name
 * @property string|null $city_name
 * @property int|null $metro_code
 * @property string|null $time_zone
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $accuracy_radius
 * @property bool $is_in_european_union
 * @property array|null $postal_codes
 * @property Carbon|null $data_updated_at
 * @property string|null $data_version
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class GeoLite2Location extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'geolite2_locations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'geoname_id',
        'locale_code',
        'continent_code',
        'continent_name',
        'country_iso_code',
        'country_name',
        'subdivision_1_iso_code',
        'subdivision_1_name',
        'subdivision_2_iso_code',
        'subdivision_2_name',
        'city_name',
        'metro_code',
        'time_zone',
        'latitude',
        'longitude',
        'accuracy_radius',
        'is_in_european_union',
        'postal_codes',
        'data_updated_at',
        'data_version',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'geoname_id' => 'integer',
        'metro_code' => 'integer',
        'accuracy_radius' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_in_european_union' => 'boolean',
        'postal_codes' => 'array',
        'metadata' => 'array',
        'data_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: IP blocks that reference this location
     */
    public function ipBlocks(): HasMany
    {
        return $this->hasMany(GeoLite2IpBlock::class, 'geoname_id', 'geoname_id');
    }

    /**
     * Query scope: Filter by geoname ID
     */
    public function scopeByGeonameId(Builder $query, int $geonameId): Builder
    {
        return $query->where('geoname_id', $geonameId);
    }

    /**
     * Query scope: Filter by country
     */
    public function scopeByCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_iso_code', $countryCode);
    }

    /**
     * Query scope: Filter by continent
     */
    public function scopeByContinent(Builder $query, string $continentCode): Builder
    {
        return $query->where('continent_code', $continentCode);
    }

    /**
     * Query scope: Filter by city
     */
    public function scopeByCity(Builder $query, string $cityName): Builder
    {
        return $query->where('city_name', 'LIKE', "%{$cityName}%");
    }

    /**
     * Query scope: Filter by timezone
     */
    public function scopeByTimezone(Builder $query, string $timezone): Builder
    {
        return $query->where('time_zone', $timezone);
    }

    /**
     * Query scope: European Union countries only
     */
    public function scopeEuropeanUnion(Builder $query): Builder
    {
        return $query->where('is_in_european_union', true);
    }

    /**
     * Query scope: Locations with coordinates
     */
    public function scopeWithCoordinates(Builder $query): Builder
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    /**
     * Query scope: Filter by coordinate bounds
     */
    public function scopeWithinBounds(
        Builder $query,
        float $minLat,
        float $maxLat,
        float $minLng,
        float $maxLng
    ): Builder {
        return $query->whereBetween('latitude', [$minLat, $maxLat])
                    ->whereBetween('longitude', [$minLng, $maxLng]);
    }

    /**
     * Query scope: Filter by subdivision (state/province)
     */
    public function scopeBySubdivision(Builder $query, string $subdivisionCode, int $level = 1): Builder
    {
        $column = $level === 1 ? 'subdivision_1_iso_code' : 'subdivision_2_iso_code';
        return $query->where($column, $subdivisionCode);
    }

    /**
     * Query scope: Recent data updates
     */
    public function scopeRecentlyUpdated(Builder $query, int $days = 30): Builder
    {
        return $query->where('data_updated_at', '>=', now()->subDays($days));
    }

    /**
     * Get the full location name
     */
    public function getFullLocationName(): string
    {
        $parts = array_filter([
            $this->city_name,
            $this->subdivision_1_name,
            $this->country_name,
        ]);

        return implode(', ', $parts) ?: 'Unknown Location';
    }

    /**
     * Get the short location name
     */
    public function getShortLocationName(): string
    {
        return $this->city_name ?: $this->country_name ?: 'Unknown';
    }

    /**
     * Check if location has coordinates
     */
    public function hasCoordinates(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * Calculate distance to another location in kilometers
     */
    public function distanceTo(GeoLite2Location $other): ?float
    {
        if (!$this->hasCoordinates() || !$other->hasCoordinates()) {
            return null;
        }

        $earthRadius = 6371; // Earth's radius in kilometers

        $lat1Rad = deg2rad((float)$this->latitude);
        $lat2Rad = deg2rad((float)$other->latitude);
        $deltaLatRad = deg2rad((float)$other->latitude - (float)$this->latitude);
        $deltaLngRad = deg2rad((float)$other->longitude - (float)$this->longitude);

        $a = sin($deltaLatRad / 2) * sin($deltaLatRad / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLngRad / 2) * sin($deltaLngRad / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get location hierarchy as array
     */
    public function getHierarchy(): array
    {
        return [
            'continent' => [
                'code' => $this->continent_code,
                'name' => $this->continent_name,
            ],
            'country' => [
                'code' => $this->country_iso_code,
                'name' => $this->country_name,
            ],
            'subdivision_1' => [
                'code' => $this->subdivision_1_iso_code,
                'name' => $this->subdivision_1_name,
            ],
            'subdivision_2' => [
                'code' => $this->subdivision_2_iso_code,
                'name' => $this->subdivision_2_name,
            ],
            'city' => $this->city_name,
        ];
    }

    /**
     * Get coordinates as array
     */
    public function getCoordinates(): ?array
    {
        if (!$this->hasCoordinates()) {
            return null;
        }

        return [
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'accuracy_radius' => $this->accuracy_radius,
        ];
    }

    /**
     * Check if location is in a specific region
     */
    public function isInRegion(string $region): bool
    {
        return match (strtolower($region)) {
            'eu', 'european_union' => $this->is_in_european_union,
            'north_america' => in_array($this->continent_code, ['NA']),
            'europe' => in_array($this->continent_code, ['EU']),
            'asia' => in_array($this->continent_code, ['AS']),
            'africa' => in_array($this->continent_code, ['AF']),
            'south_america' => in_array($this->continent_code, ['SA']),
            'oceania' => in_array($this->continent_code, ['OC']),
            'antarctica' => in_array($this->continent_code, ['AN']),
            default => false,
        };
    }
}
