<?php

declare(strict_types=1);

/**
 * Model File: IpReputation.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Eloquent model for IP reputation caching and tracking with comprehensive
 * threat intelligence, scoring, and analytics for the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\FormSecurity\Casts\ThreatCategoriesCast;
use JTD\FormSecurity\Casts\ValidatedMetadataCast;
use JTD\FormSecurity\Contracts\AnalyticsModelInterface;
use JTD\FormSecurity\Contracts\CacheableModelInterface;
use JTD\FormSecurity\Enums\ReputationStatus;

/**
 * IpReputation Model
 *
 * Caches IP reputation data from various threat intelligence sources with scoring,
 * activity tracking, and automated cache management for high-performance lookups.
 *
 * @property int $id
 * @property string $ip_address
 * @property int $reputation_score
 * @property ReputationStatus $reputation_status
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
class IpReputation extends BaseModel implements AnalyticsModelInterface, CacheableModelInterface
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return new \Database\Factories\IpReputationFactory();
    }

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
        'reputation_status' => ReputationStatus::class,
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
        'threat_categories' => ThreatCategoriesCast::class,
        'metadata' => ValidatedMetadataCast::class,
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
    public function scopeByStatus(Builder $query, ReputationStatus|string $status): Builder
    {
        $statusValue = $status instanceof ReputationStatus ? $status->value : $status;

        return $query->where('reputation_status', $statusValue);
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
     * Query scope: Filter by threat type
     */
    public function scopeByThreatType(Builder $query, string $threatType): Builder
    {
        return $query->whereJsonContains('threat_categories', $threatType);
    }

    /**
     * Query scope: Filter by multiple threat types
     */
    public function scopeByThreatTypes(Builder $query, array $threatTypes): Builder
    {
        return $query->where(function ($q) use ($threatTypes) {
            foreach ($threatTypes as $threatType) {
                $q->orWhereJsonContains('threat_categories', $threatType);
            }
        });
    }

    /**
     * Query scope: Filter by country code
     */
    public function scopeByCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Query scope: Filter by ISP
     */
    public function scopeByIsp(Builder $query, string $isp): Builder
    {
        return $query->where('isp', 'LIKE', "%{$isp}%");
    }

    /**
     * Query scope: Filter by organization
     */
    public function scopeByOrganization(Builder $query, string $organization): Builder
    {
        return $query->where('organization', 'LIKE', "%{$organization}%");
    }

    /**
     * Query scope: Filter by minimum submission count
     */
    public function scopeMinSubmissions(Builder $query, int $minCount): Builder
    {
        return $query->where('submission_count', '>=', $minCount);
    }

    /**
     * Query scope: Filter by recently seen IPs
     */
    public function scopeRecentlySeen(Builder $query, int $hours = 168): Builder // 7 days default
    {
        return $query->where('last_seen', '>=', now()->subHours($hours));
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

    /**
     * Get reputation status from score
     */
    public function getReputationStatusFromScore(): ReputationStatus
    {
        return ReputationStatus::fromScore($this->reputation_score);
    }

    /**
     * Check if reputation allows access
     */
    public function allowsAccess(): bool
    {
        return $this->reputation_status->allowsAccess();
    }

    // Model Relationships

    /**
     * Relationship: All blocked submissions from this IP address
     */
    public function blockedSubmissions(): HasMany
    {
        return $this->hasMany(BlockedSubmission::class, 'ip_address', 'ip_address');
    }

    /**
     * Get recent blocked submissions from this IP
     */
    public function recentBlockedSubmissions(int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return $this->blockedSubmissions()
            ->where('blocked_at', '>=', now()->subHours($hours))
            ->orderBy('blocked_at', 'desc')
            ->get();
    }

    /**
     * Get blocked submissions count by time period
     */
    public function getBlockedSubmissionsCount(int $hours = 24): int
    {
        return $this->blockedSubmissions()
            ->where('blocked_at', '>=', now()->subHours($hours))
            ->count();
    }

    // CacheableModelInterface Implementation

    /**
     * Generate a unique cache key for this model instance
     */
    public function getCacheKey(): string
    {
        return "ip_reputation:{$this->ip_address}";
    }

    /**
     * Generate a cache key for a specific lookup value
     */
    public static function getCacheKeyFor(string $identifier): string
    {
        return "ip_reputation:{$identifier}";
    }

    /**
     * Get the cache expiration time for this model
     */
    public function getCacheExpiration(): ?Carbon
    {
        return $this->cache_expires_at;
    }

    /**
     * Check if the cached data has expired
     */
    public function isCacheExpired(): bool
    {
        return $this->cache_expires_at && $this->cache_expires_at->isPast();
    }

    /**
     * Refresh the cache expiration time
     */
    public function refreshCacheExpiration(): bool
    {
        $this->cache_expires_at = now()->addHours(static::getDefaultCacheTtl() / 3600);

        return $this->save();
    }

    /**
     * Invalidate the cache for this model instance
     */
    public function invalidateCache(): bool
    {
        Cache::forget($this->getCacheKey());

        return true;
    }

    /**
     * Get cached data or retrieve from database
     */
    public static function getCached(string $identifier): ?static
    {
        $cacheKey = static::getCacheKeyFor($identifier);

        return Cache::remember($cacheKey, static::getDefaultCacheTtl(), function () use ($identifier) {
            return static::where('ip_address', $identifier)->first();
        });
    }

    /**
     * Store model data in cache
     */
    public function storeInCache(): bool
    {
        Cache::put($this->getCacheKey(), $this, static::getDefaultCacheTtl());

        return true;
    }

    /**
     * Remove model data from cache
     */
    public function removeFromCache(): bool
    {
        return Cache::forget($this->getCacheKey());
    }

    /**
     * Get the default cache TTL in seconds
     */
    public static function getDefaultCacheTtl(): int
    {
        return 3600; // 1 hour
    }

    // AnalyticsModelInterface Implementation

    /**
     * Scope query to filter by date range
     */
    public function scopeByDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('updated_at', [$startDate, $endDate]);
    }

    /**
     * Scope query to filter recent records within specified hours
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('last_seen', '>=', now()->subHours($hours));
    }

    /**
     * Get count of records for the specified date range
     */
    public static function getCountByDateRange(Carbon $startDate, Carbon $endDate): int
    {
        return static::query()
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Get aggregated data grouped by date
     *
     * @return array<string, mixed>
     */
    public static function getAggregatedByDate(Carbon $startDate, Carbon $endDate, string $groupBy = 'day'): array
    {
        $dateFormat = match ($groupBy) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        return static::query()
            ->selectRaw("DATE_FORMAT(updated_at, '{$dateFormat}') as period")
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('AVG(reputation_score) as avg_reputation_score')
            ->selectRaw('COUNT(CASE WHEN reputation_status = "malicious" THEN 1 END) as malicious_count')
            ->selectRaw('COUNT(CASE WHEN reputation_status = "blocked" THEN 1 END) as blocked_count')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    /**
     * Get top records by specified field within date range
     *
     * @return array<string, mixed>
     */
    public static function getTopByField(string $field, Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        return static::query()
            ->select($field)
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('AVG(reputation_score) as avg_reputation_score')
            ->selectRaw('AVG(block_rate) as avg_block_rate')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->whereNotNull($field)
            ->groupBy($field)
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get analytics summary for the specified period
     *
     * @return array<string, mixed>
     */
    public static function getAnalyticsSummary(Carbon $startDate, Carbon $endDate): array
    {
        $query = static::query()->whereBetween('updated_at', [$startDate, $endDate]);

        return [
            'total_ips' => $query->count(),
            'unique_countries' => $query->distinct('country_code')->count('country_code'),
            'avg_reputation_score' => round($query->avg('reputation_score'), 2),
            'malicious_ips' => $query->where('reputation_status', 'malicious')->count(),
            'blocked_ips' => $query->where('reputation_status', 'blocked')->count(),
            'trusted_ips' => $query->where('reputation_status', 'trusted')->count(),
            'threat_network_ips' => $query->where(function ($q) {
                $q->where('is_tor', true)
                    ->orWhere('is_proxy', true)
                    ->orWhere('is_vpn', true);
            })->count(),
            'avg_block_rate' => round($query->avg('block_rate'), 4),
            'top_countries' => static::getTopByField('country_code', $startDate, $endDate, 5),
            'top_isps' => static::getTopByField('isp', $startDate, $endDate, 5),
        ];
    }

    // Advanced Business Logic Methods

    /**
     * Update reputation score based on recent activity
     */
    public function updateReputationScore(): bool
    {
        $recentSubmissions = $this->recentBlockedSubmissions(168); // 7 days
        $totalSubmissions = $this->submission_count;
        $blockedCount = $this->blocked_count;

        // Calculate new reputation score
        $baseScore = 50; // Neutral starting point

        // Adjust based on block rate
        if ($this->block_rate > 0.8) {
            $baseScore -= 40;
        } elseif ($this->block_rate > 0.5) {
            $baseScore -= 20;
        } elseif ($this->block_rate < 0.1) {
            $baseScore += 20;
        }

        // Adjust based on submission volume
        if ($totalSubmissions > 1000) {
            $baseScore -= 10; // High volume is suspicious
        } elseif ($totalSubmissions > 100) {
            $baseScore -= 5;
        }

        // Adjust based on network type
        if ($this->is_tor) {
            $baseScore -= 25;
        }
        if ($this->is_proxy) {
            $baseScore -= 15;
        }
        if ($this->is_vpn) {
            $baseScore -= 10;
        }
        if ($this->is_malware) {
            $baseScore -= 30;
        }
        if ($this->is_botnet) {
            $baseScore -= 35;
        }

        // Adjust based on threat categories
        if (is_array($this->threat_categories)) {
            $threatCount = count($this->threat_categories);
            $baseScore -= ($threatCount * 5);
        }

        // Manual adjustments
        if ($this->is_whitelisted) {
            $baseScore = max($baseScore, 80);
        }
        if ($this->is_blacklisted) {
            $baseScore = min($baseScore, 20);
        }

        // Time decay - improve score over time if no recent blocks
        $daysSinceLastBlock = $this->last_blocked ?
            $this->last_blocked->diffInDays(now()) : 365;

        if ($daysSinceLastBlock > 30) {
            $baseScore += min(10, $daysSinceLastBlock / 10);
        }

        $newScore = max(0, min(100, $baseScore));
        $newStatus = ReputationStatus::fromScore($newScore);

        $this->update([
            'reputation_score' => $newScore,
            'reputation_status' => $newStatus->value,
        ]);

        return true;
    }

    /**
     * Calculate threat intelligence score
     */
    public function calculateThreatIntelligenceScore(): array
    {
        $score = [
            'overall_threat_level' => 0,
            'network_threat' => 0,
            'behavioral_threat' => 0,
            'geographic_threat' => 0,
            'temporal_threat' => 0,
        ];

        // Network-based threats
        if ($this->is_tor) {
            $score['network_threat'] += 25;
        }
        if ($this->is_proxy) {
            $score['network_threat'] += 20;
        }
        if ($this->is_vpn) {
            $score['network_threat'] += 15;
        }
        if ($this->is_malware) {
            $score['network_threat'] += 35;
        }
        if ($this->is_botnet) {
            $score['network_threat'] += 40;
        }

        // Behavioral threats
        if ($this->block_rate > 0.8) {
            $score['behavioral_threat'] += 30;
        }
        if ($this->submission_count > 1000) {
            $score['behavioral_threat'] += 20;
        }
        if ($this->blocked_count > 500) {
            $score['behavioral_threat'] += 25;
        }

        // Geographic threats
        $highRiskCountries = ['CN', 'RU', 'KP', 'IR', 'SY'];
        if (in_array($this->country_code, $highRiskCountries)) {
            $score['geographic_threat'] += 15;
        }

        // Temporal threats (recent activity patterns)
        $recentBlocks = $this->getBlockedSubmissionsCount(24);
        if ($recentBlocks > 50) {
            $score['temporal_threat'] += 25;
        } elseif ($recentBlocks > 20) {
            $score['temporal_threat'] += 15;
        } elseif ($recentBlocks > 10) {
            $score['temporal_threat'] += 10;
        }

        // Calculate overall threat level
        $score['overall_threat_level'] = min(100, array_sum([
            $score['network_threat'],
            $score['behavioral_threat'],
            $score['geographic_threat'],
            $score['temporal_threat'],
        ]));

        return $score;
    }

    /**
     * Generate comprehensive reputation summary
     */
    public function getReputationSummary(): array
    {
        $threatScore = $this->calculateThreatIntelligenceScore();

        return [
            'ip_address' => $this->ip_address,
            'reputation_score' => $this->reputation_score,
            'reputation_status' => $this->reputation_status->value,
            'allows_access' => $this->allowsAccess(),
            'threat_intelligence' => $threatScore,
            'network_classification' => [
                'is_tor' => $this->is_tor,
                'is_proxy' => $this->is_proxy,
                'is_vpn' => $this->is_vpn,
                'is_hosting' => $this->is_hosting,
                'is_malware' => $this->is_malware,
                'is_botnet' => $this->is_botnet,
            ],
            'activity_metrics' => [
                'total_submissions' => $this->submission_count,
                'blocked_submissions' => $this->blocked_count,
                'block_rate' => $this->block_rate,
                'first_seen' => $this->first_seen?->toDateTimeString(),
                'last_seen' => $this->last_seen?->toDateTimeString(),
                'last_blocked' => $this->last_blocked?->toDateTimeString(),
            ],
            'geographic_info' => [
                'country' => $this->country_code,
                'region' => $this->region,
                'city' => $this->city,
                'isp' => $this->isp,
                'organization' => $this->organization,
            ],
            'cache_info' => [
                'expires_at' => $this->cache_expires_at?->toDateTimeString(),
                'is_expired' => $this->isCacheExpired(),
            ],
            'manual_overrides' => [
                'is_whitelisted' => $this->is_whitelisted,
                'is_blacklisted' => $this->is_blacklisted,
            ],
        ];
    }

    // Performance Optimizations

    /**
     * Get optimized select columns for reputation queries
     */
    protected function getOptimizedSelectColumns(): array
    {
        return [
            'id',
            'ip_address',
            'reputation_score',
            'reputation_status',
            'block_rate',
            'country_code',
            'is_blacklisted',
            'is_whitelisted',
            'last_seen',
        ];
    }

    /**
     * Get optimized eager loading relationships
     */
    protected function getOptimizedEagerLoads(): array
    {
        return ['blockedSubmissions:ip_address,blocked_at,risk_score'];
    }

    /**
     * Scope for high-performance threat intelligence queries
     */
    public function scopeForThreatIntelligence(Builder $query): Builder
    {
        return $query->select([
            'ip_address',
            'reputation_score',
            'reputation_status',
            'block_rate',
            'threat_categories',
            'country_code',
            'is_malware',
            'is_botnet',
            'last_seen',
        ])
            ->where('reputation_score', '<=', 50)
            ->orderBy('reputation_score');
    }

    /**
     * Get cached threat intelligence summary
     */
    public static function getCachedThreatIntelligence(int $hours = 24): array
    {
        return static::getCachedResults('getThreatIntelligence', [$hours], 900); // 15 min cache
    }

    /**
     * Get threat intelligence data (cacheable method)
     */
    public static function getThreatIntelligence(int $hours = 24): array
    {
        return [
            'high_risk_ips' => static::where('reputation_score', '<=', 30)->count(),
            'malware_ips' => static::where('is_malware', true)->count(),
            'botnet_ips' => static::where('is_botnet', true)->count(),
            'blacklisted_ips' => static::where('is_blacklisted', true)->count(),
            'recent_activity' => static::where('last_seen', '>=', now()->subHours($hours))->count(),
            'top_threat_countries' => static::select([
                'country_code',
                DB::raw('COUNT(*) as threat_count'),
                DB::raw('AVG(reputation_score) as avg_score'),
            ])
                ->where('reputation_score', '<=', 50)
                ->groupBy('country_code')
                ->orderByDesc('threat_count')
                ->limit(10)
                ->get()
                ->toArray(),
        ];
    }

    /**
     * Bulk update reputation scores
     */
    public static function bulkUpdateReputations(array $updates): int
    {
        return static::bulkUpdate($updates, 'ip_address');
    }
}
