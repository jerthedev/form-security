<?php

declare(strict_types=1);

/**
 * Model File: GeoLite2IpBlock.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-019-geolite2-database-management
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Eloquent model for GeoLite2 IP block ranges with efficient IP lookup
 * capabilities and geolocation mapping for the JTD-FormSecurity package.
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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * GeoLite2IpBlock Model
 *
 * Stores MaxMind GeoLite2 IP block ranges with efficient IP lookup capabilities
 * and geolocation mapping for high-performance IP geolocation services.
 *
 * @property int $id
 * @property string $network
 * @property int $network_start_integer
 * @property int $network_last_integer
 * @property int|null $geoname_id
 * @property int|null $registered_country_geoname_id
 * @property int|null $represented_country_geoname_id
 * @property bool $is_anonymous_proxy
 * @property bool $is_satellite_provider
 * @property bool $is_anycast
 * @property string|null $postal_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $accuracy_radius
 * @property Carbon|null $data_updated_at
 * @property string|null $data_version
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class GeoLite2IpBlock extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'geolite2_ipv4_blocks';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'network',
        'network_start_integer',
        'network_last_integer',
        'geoname_id',
        'registered_country_geoname_id',
        'represented_country_geoname_id',
        'is_anonymous_proxy',
        'is_satellite_provider',
        'is_anycast',
        'postal_code',
        'latitude',
        'longitude',
        'accuracy_radius',
        'data_updated_at',
        'data_version',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'network_start_integer' => 'integer',
        'network_last_integer' => 'integer',
        'geoname_id' => 'integer',
        'registered_country_geoname_id' => 'integer',
        'represented_country_geoname_id' => 'integer',
        'accuracy_radius' => 'integer',
        'is_anonymous_proxy' => 'boolean',
        'is_satellite_provider' => 'boolean',
        'is_anycast' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'metadata' => 'array',
        'data_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Location data for this IP block
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(GeoLite2Location::class, 'geoname_id', 'geoname_id');
    }

    /**
     * Relationship: Registered country location
     */
    public function registeredCountry(): BelongsTo
    {
        return $this->belongsTo(GeoLite2Location::class, 'registered_country_geoname_id', 'geoname_id');
    }

    /**
     * Relationship: Represented country location
     */
    public function representedCountry(): BelongsTo
    {
        return $this->belongsTo(GeoLite2Location::class, 'represented_country_geoname_id', 'geoname_id');
    }

    /**
     * Query scope: Find IP block containing a specific IP address
     */
    public function scopeContainingIp(Builder $query, string $ipAddress): Builder
    {
        $ipInteger = ip2long($ipAddress);
        
        return $query->where('network_start_integer', '<=', $ipInteger)
                    ->where('network_last_integer', '>=', $ipInteger);
    }

    /**
     * Query scope: Filter by geoname ID
     */
    public function scopeByGeonameId(Builder $query, int $geonameId): Builder
    {
        return $query->where('geoname_id', $geonameId);
    }

    /**
     * Query scope: Filter by network type
     */
    public function scopeByNetworkType(Builder $query, string $type): Builder
    {
        return match ($type) {
            'proxy' => $query->where('is_anonymous_proxy', true),
            'satellite' => $query->where('is_satellite_provider', true),
            'anycast' => $query->where('is_anycast', true),
            default => $query,
        };
    }

    /**
     * Query scope: Anonymous proxy blocks
     */
    public function scopeAnonymousProxy(Builder $query): Builder
    {
        return $query->where('is_anonymous_proxy', true);
    }

    /**
     * Query scope: Satellite provider blocks
     */
    public function scopeSatelliteProvider(Builder $query): Builder
    {
        return $query->where('is_satellite_provider', true);
    }

    /**
     * Query scope: Anycast blocks
     */
    public function scopeAnycast(Builder $query): Builder
    {
        return $query->where('is_anycast', true);
    }

    /**
     * Query scope: Blocks with coordinates
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
     * Query scope: Filter by postal code
     */
    public function scopeByPostalCode(Builder $query, string $postalCode): Builder
    {
        return $query->where('postal_code', $postalCode);
    }

    /**
     * Query scope: Recent data updates
     */
    public function scopeRecentlyUpdated(Builder $query, int $days = 30): Builder
    {
        return $query->where('data_updated_at', '>=', now()->subDays($days));
    }

    /**
     * Check if an IP address is within this block
     */
    public function containsIp(string $ipAddress): bool
    {
        $ipInteger = ip2long($ipAddress);
        
        return $ipInteger >= $this->network_start_integer && 
               $ipInteger <= $this->network_last_integer;
    }

    /**
     * Get the network size (number of IPs in the block)
     */
    public function getNetworkSize(): int
    {
        return $this->network_last_integer - $this->network_start_integer + 1;
    }

    /**
     * Get the CIDR prefix length
     */
    public function getCidrPrefix(): int
    {
        $networkSize = $this->getNetworkSize();
        return 32 - (int) log($networkSize, 2);
    }

    /**
     * Get the network address as a string
     */
    public function getNetworkAddress(): string
    {
        return long2ip($this->network_start_integer);
    }

    /**
     * Get the broadcast address as a string
     */
    public function getBroadcastAddress(): string
    {
        return long2ip($this->network_last_integer);
    }

    /**
     * Check if this is a special network type
     */
    public function isSpecialNetwork(): bool
    {
        return $this->is_anonymous_proxy || 
               $this->is_satellite_provider || 
               $this->is_anycast;
    }

    /**
     * Get network type description
     */
    public function getNetworkTypeDescription(): string
    {
        $types = [];
        
        if ($this->is_anonymous_proxy) {
            $types[] = 'Anonymous Proxy';
        }
        
        if ($this->is_satellite_provider) {
            $types[] = 'Satellite Provider';
        }
        
        if ($this->is_anycast) {
            $types[] = 'Anycast';
        }
        
        return empty($types) ? 'Standard' : implode(', ', $types);
    }

    /**
     * Check if block has coordinates
     */
    public function hasCoordinates(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
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
     * Get network information summary
     */
    public function getNetworkInfo(): array
    {
        return [
            'network' => $this->network,
            'start_ip' => $this->getNetworkAddress(),
            'end_ip' => $this->getBroadcastAddress(),
            'size' => $this->getNetworkSize(),
            'cidr_prefix' => $this->getCidrPrefix(),
            'type' => $this->getNetworkTypeDescription(),
            'has_location' => !is_null($this->geoname_id),
            'has_coordinates' => $this->hasCoordinates(),
        ];
    }

    /**
     * Find the IP block containing a specific IP address
     */
    public static function findByIp(string $ipAddress): ?self
    {
        return static::containingIp($ipAddress)->first();
    }
}
