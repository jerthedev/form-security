<?php

declare(strict_types=1);

/**
 * Model File: IpReputation.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Eloquent model for IP reputation caching and tracking with comprehensive
 * threat intelligence, scoring, and analytics for the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace JTD\FormSecurity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

/**
 * IpReputation Model
 *
 * Caches IP reputation data from various threat intelligence sources with scoring,
 * activity tracking, and automated cache management for high-performance lookups.
 *
 * @property int $id
 * @property string $ip_address
 * @property int $reputation_score
 * @property string $reputation_status
 * @property bool $is_tor
 * @property bool $is_proxy
 * @property bool $is_vpn
 * @property bool $is_hosting
 * @property bool $is_malware
 * @property bool $is_botnet
 * @property string|null $country_code
 * @property string|null $region
 * @property string|null $city
 * @property string|null $isp
 * @property string|null $organization
 * @property int $submission_count
 * @property int $blocked_count
 * @property float $block_rate
 * @property Carbon|null $first_seen
 * @property Carbon|null $last_seen
 * @property Carbon|null $last_blocked
 * @property array|null $threat_sources
 * @property array|null $threat_categories
 * @property string|null $notes
 * @property Carbon|null $cache_expires_at
 * @property bool $is_whitelisted
 * @property bool $is_blacklisted
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class IpReputation extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ip_reputation';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'ip_address',
        'reputation_score',
        'reputation_status',
        'is_tor',
        'is_proxy',
        'is_vpn',
        'is_hosting',
        'is_malware',
        'is_botnet',
        'country_code',
        'region',
        'city',
        'isp',
        'organization',
        'submission_count',
        'blocked_count',
        'block_rate',
        'first_seen',
        'last_seen',
        'last_blocked',
        'threat_sources',
        'threat_categories',
        'notes',
        'cache_expires_at',
        'is_whitelisted',
        'is_blacklisted',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'reputation_score' => 'integer',
        'submission_count' => 'integer',
        'blocked_count' => 'integer',
        'block_rate' => 'decimal:4',
        'is_tor' => 'boolean',
        'is_proxy' => 'boolean',
        'is_vpn' => 'boolean',
        'is_hosting' => 'boolean',
        'is_malware' => 'boolean',
        'is_botnet' => 'boolean',
        'is_whitelisted' => 'boolean',
        'is_blacklisted' => 'boolean',
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
        'last_blocked' => 'datetime',
        'cache_expires_at' => 'datetime',
        'threat_sources' => 'array',
        'threat_categories' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Query scope: Filter by IP address
     */
    public function scopeByIpAddress(Builder $query, string $ipAddress): Builder
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Query scope: Filter by reputation status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('reputation_status', $status);
    }

    /**
     * Query scope: Filter by reputation score range
     */
    public function scopeByScoreRange(Builder $query, int $minScore, int $maxScore = 100): Builder
    {
        return $query->whereBetween('reputation_score', [$minScore, $maxScore]);
    }

    /**
     * Query scope: Malicious IPs (score <= 30)
     */
    public function scopeMalicious(Builder $query): Builder
    {
        return $query->where('reputation_score', '<=', 30);
    }

    /**
     * Query scope: Suspicious IPs (score 31-60)
     */
    public function scopeSuspicious(Builder $query): Builder
    {
        return $query->whereBetween('reputation_score', [31, 60]);
    }

    /**
     * Query scope: Trusted IPs (score >= 80)
     */
    public function scopeTrusted(Builder $query): Builder
    {
        return $query->where('reputation_score', '>=', 80);
    }

    /**
     * Query scope: Whitelisted IPs
     */
    public function scopeWhitelisted(Builder $query): Builder
    {
        return $query->where('is_whitelisted', true);
    }

    /**
     * Query scope: Blacklisted IPs
     */
    public function scopeBlacklisted(Builder $query): Builder
    {
        return $query->where('is_blacklisted', true);
    }

    /**
     * Query scope: Expired cache entries
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('cache_expires_at', '<', now());
    }

    /**
     * Query scope: Active cache entries
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('cache_expires_at', '>', now());
    }

    /**
     * Query scope: Filter by proxy/VPN/Tor usage
     */
    public function scopeByNetworkType(Builder $query, string $type): Builder
    {
        return match ($type) {
            'tor' => $query->where('is_tor', true),
            'proxy' => $query->where('is_proxy', true),
            'vpn' => $query->where('is_vpn', true),
            'hosting' => $query->where('is_hosting', true),
            'malware' => $query->where('is_malware', true),
            'botnet' => $query->where('is_botnet', true),
            default => $query,
        };
    }

    /**
     * Query scope: High activity IPs
     */
    public function scopeHighActivity(Builder $query, int $minSubmissions = 100): Builder
    {
        return $query->where('submission_count', '>=', $minSubmissions);
    }

    /**
     * Query scope: High block rate IPs
     */
    public function scopeHighBlockRate(Builder $query, float $minRate = 0.8): Builder
    {
        return $query->where('block_rate', '>=', $minRate);
    }

    /**
     * Check if the IP reputation cache is expired
     */
    public function isExpired(): bool
    {
        return $this->cache_expires_at === null || $this->cache_expires_at->isPast();
    }

    /**
     * Check if the IP is considered malicious
     */
    public function isMalicious(): bool
    {
        return $this->reputation_score <= 30 || $this->is_blacklisted;
    }

    /**
     * Check if the IP is considered suspicious
     */
    public function isSuspicious(): bool
    {
        return $this->reputation_score >= 31 && $this->reputation_score <= 60;
    }

    /**
     * Check if the IP is considered trusted
     */
    public function isTrusted(): bool
    {
        return $this->reputation_score >= 80 || $this->is_whitelisted;
    }

    /**
     * Check if the IP is from a suspicious network type
     */
    public function isSuspiciousNetwork(): bool
    {
        return $this->is_tor || $this->is_proxy || $this->is_vpn || $this->is_malware || $this->is_botnet;
    }

    /**
     * Get the reputation level as a string
     */
    public function getReputationLevel(): string
    {
        // Prioritize explicit whitelist/blacklist status over score-based classification
        return match (true) {
            $this->is_blacklisted => 'malicious',
            $this->is_whitelisted => 'trusted',
            $this->isMalicious() => 'malicious',
            $this->isTrusted() => 'trusted',
            $this->isSuspicious() => 'suspicious',
            default => 'neutral',
        };
    }

    /**
     * Update activity statistics
     */
    public function updateActivity(bool $wasBlocked = false): void
    {
        $this->increment('submission_count');
        
        if ($wasBlocked) {
            $this->increment('blocked_count');
            $this->last_blocked = now();
        }

        $this->last_seen = now();
        $this->block_rate = $this->submission_count > 0 
            ? $this->blocked_count / $this->submission_count 
            : 0;

        $this->save();
    }

    /**
     * Extend cache expiration
     */
    public function extendCache(int $hours = 24): void
    {
        $this->cache_expires_at = now()->addHours($hours);
        $this->save();
    }
}
