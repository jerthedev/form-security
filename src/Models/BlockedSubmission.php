<?php

declare(strict_types=1);

/**
 * Model File: BlockedSubmission.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-008-blocked-submissions-tracking
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Eloquent model for tracking blocked form submissions with comprehensive
 * analytics, relationships, and query scopes for the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Data-Management-Analytics/SPEC-008-blocked-submissions-tracking.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use JTD\FormSecurity\Casts\CoordinatesCast;
use JTD\FormSecurity\Casts\ValidatedMetadataCast;
use JTD\FormSecurity\Contracts\AnalyticsModelInterface;
use JTD\FormSecurity\Enums\BlockReason;
use JTD\FormSecurity\Enums\RiskLevel;

/**
 * BlockedSubmission Model
 *
 * Tracks all blocked form submissions for analysis, monitoring, and security insights.
 * Provides comprehensive data about blocked attempts including geolocation, risk scoring,
 * and detailed metadata for analytics and reporting.
 *
 * @property int $id
 * @property string $form_identifier
 * @property string $ip_address
 * @property string|null $user_agent
 * @property string|null $referer
 * @property BlockReason $block_reason
 * @property string|null $block_details
 * @property string|null $form_data_hash
 * @property int $form_field_count
 * @property string|null $country_code
 * @property string|null $region
 * @property string|null $city
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $timezone
 * @property string|null $isp
 * @property string|null $organization
 * @property int $risk_score
 * @property bool $is_tor
 * @property bool $is_proxy
 * @property bool $is_vpn
 * @property Carbon $blocked_at
 * @property string|null $session_id
 * @property string|null $fingerprint
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BlockedSubmission extends BaseModel implements AnalyticsModelInterface
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return new \Database\Factories\BlockedSubmissionFactory();
    }

    /**
     * The table associated with the model.
     */
    protected $table = 'blocked_submissions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'form_identifier',
        'ip_address',
        'user_agent',
        'referer',
        'block_reason',
        'block_details',
        'form_data_hash',
        'form_field_count',
        'country_code',
        'region',
        'city',
        'latitude',
        'longitude',
        'timezone',
        'isp',
        'organization',
        'risk_score',
        'is_tor',
        'is_proxy',
        'is_vpn',
        'blocked_at',
        'session_id',
        'fingerprint',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'block_reason' => BlockReason::class,
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'risk_score' => 'integer',
        'form_field_count' => 'integer',
        'is_tor' => 'boolean',
        'is_proxy' => 'boolean',
        'is_vpn' => 'boolean',
        'blocked_at' => 'datetime',
        'metadata' => ValidatedMetadataCast::class,
        'coordinates' => CoordinatesCast::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'form_data_hash',
        'fingerprint',
    ];

    /**
     * Query scope: Filter by form identifier
     */
    public function scopeByFormIdentifier(Builder $query, string $formIdentifier): Builder
    {
        return $query->where('form_identifier', $formIdentifier);
    }

    /**
     * Query scope: Filter by block reason
     */
    public function scopeByBlockReason(Builder $query, BlockReason|string $blockReason): Builder
    {
        $reason = $blockReason instanceof BlockReason ? $blockReason->value : $blockReason;

        return $query->where('block_reason', $reason);
    }

    /**
     * Query scope: Filter by country code
     */
    public function scopeByCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Query scope: Filter by multiple countries
     */
    public function scopeByCountries(Builder $query, array $countryCodes): Builder
    {
        return $query->whereIn('country_code', $countryCodes);
    }

    /**
     * Query scope: Filter by form type/identifier
     */
    public function scopeByFormType(Builder $query, string $formIdentifier): Builder
    {
        return $query->where('form_identifier', $formIdentifier);
    }

    /**
     * Query scope: Filter by multiple form types
     */
    public function scopeByFormTypes(Builder $query, array $formIdentifiers): Builder
    {
        return $query->whereIn('form_identifier', $formIdentifiers);
    }

    /**
     * Query scope: Filter by minimum risk score
     */
    public function scopeMinRiskScore(Builder $query, int $minScore): Builder
    {
        return $query->where('risk_score', '>=', $minScore);
    }

    /**
     * Query scope: Filter by risk score range
     */
    public function scopeRiskScoreRange(Builder $query, int $minScore, int $maxScore): Builder
    {
        return $query->whereBetween('risk_score', [$minScore, $maxScore]);
    }

    /**
     * Query scope: Filter by risk level
     */
    public function scopeByRiskLevel(Builder $query, RiskLevel $riskLevel): Builder
    {
        return $query->whereBetween('risk_score', [
            $riskLevel->getMinScore(),
            $riskLevel->getMaxScore(),
        ]);
    }

    /**
     * Query scope: Filter by suspicious network types
     */
    public function scopeSuspiciousNetworks(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('is_tor', true)
                ->orWhere('is_proxy', true)
                ->orWhere('is_vpn', true);
        });
    }

    /**
     * Query scope: Filter by region
     */
    public function scopeByRegion(Builder $query, string $region): Builder
    {
        return $query->where('region', $region);
    }

    /**
     * Query scope: Filter by city
     */
    public function scopeByCity(Builder $query, string $city): Builder
    {
        return $query->where('city', $city);
    }

    /**
     * Query scope: Filter by ISP
     */
    public function scopeByIsp(Builder $query, string $isp): Builder
    {
        return $query->where('isp', $isp);
    }

    /**
     * Query scope: Filter by timezone
     */
    public function scopeByTimezone(Builder $query, string $timezone): Builder
    {
        return $query->where('timezone', $timezone);
    }

    /**
     * Query scope: Filter by coordinate bounds (geographic area)
     */
    public function scopeWithinBounds(Builder $query, float $minLat, float $maxLat, float $minLon, float $maxLon): Builder
    {
        return $query->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLon, $maxLon]);
    }

    /**
     * Query scope: Filter by IP address
     */
    public function scopeByIpAddress(Builder $query, string $ipAddress): Builder
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Query scope: Filter by risk score range
     */
    public function scopeByRiskScore(Builder $query, int $minScore, int $maxScore = 100): Builder
    {
        return $query->whereBetween('risk_score', [$minScore, $maxScore]);
    }

    /**
     * Query scope: High risk submissions (score >= 80)
     */
    public function scopeHighRisk(Builder $query): Builder
    {
        return $query->where('risk_score', '>=', 80);
    }

    /**
     * Query scope: Recent blocks within specified hours
     */
    public function scopeRecentBlocks(Builder $query, int $hours = 24): Builder
    {
        return $query->where('blocked_at', '>=', now()->subHours($hours));
    }

    /**
     * Query scope: Filter by proxy/VPN/Tor usage
     */
    public function scopeByProxyType(Builder $query, string $type): Builder
    {
        return match ($type) {
            'tor' => $query->where('is_tor', true),
            'proxy' => $query->where('is_proxy', true),
            'vpn' => $query->where('is_vpn', true),
            'any' => $query->where(function ($q) {
                $q->where('is_tor', true)
                    ->orWhere('is_proxy', true)
                    ->orWhere('is_vpn', true);
            }),
            default => $query,
        };
    }

    /**
     * Query scope: Filter by date range
     */
    public function scopeByDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('blocked_at', [$startDate, $endDate]);
    }

    /**
     * Check if the submission is from a suspicious network
     */
    public function isSuspiciousNetwork(): bool
    {
        return $this->is_tor || $this->is_proxy || $this->is_vpn;
    }

    /**
     * Get the risk level based on risk score
     */
    public function getRiskLevel(): RiskLevel
    {
        return RiskLevel::fromScore($this->risk_score);
    }

    /**
     * Get the risk level as string (for backward compatibility)
     */
    public function getRiskLevelString(): string
    {
        return $this->getRiskLevel()->value;
    }

    /**
     * Get formatted location string
     */
    public function getLocationString(): string
    {
        $parts = array_filter([
            $this->city,
            $this->region,
            $this->country_code,
        ]);

        return implode(', ', $parts) ?: 'Unknown Location';
    }

    /**
     * Check if submission has geolocation data
     */
    public function hasGeolocation(): bool
    {
        return ! is_null($this->latitude) && ! is_null($this->longitude);
    }

    /**
     * Get the time elapsed since the block occurred
     */
    public function getTimeElapsed(): string
    {
        return $this->blocked_at->diffForHumans();
    }

    /**
     * Check if this submission requires immediate attention
     */
    public function requiresImmediateAttention(): bool
    {
        return $this->getRiskLevel()->requiresImmediateAction();
    }

    /**
     * Get block reason description
     */
    public function getBlockReasonDescription(): string
    {
        return $this->block_reason->getDescription();
    }

    /**
     * Check if block reason indicates automated threat
     */
    public function isAutomatedThreat(): bool
    {
        return $this->block_reason->isAutomatedThreat();
    }

    // Model Relationships

    /**
     * Relationship: IP reputation data for this submission's IP address
     */
    public function ipReputation(): BelongsTo
    {
        return $this->belongsTo(IpReputation::class, 'ip_address', 'ip_address');
    }

    /**
     * Get or create IP reputation record for this submission
     */
    public function getOrCreateIpReputation(): IpReputation
    {
        $reputation = $this->ipReputation;

        if (! $reputation) {
            $reputation = IpReputation::firstOrCreate(
                ['ip_address' => $this->ip_address],
                [
                    'reputation_score' => 50, // Neutral starting score
                    'reputation_status' => 'neutral',
                    'country_code' => $this->country_code,
                    'region' => $this->region,
                    'city' => $this->city,
                    'isp' => $this->isp,
                    'organization' => $this->organization,
                    'is_tor' => $this->is_tor ?? false,
                    'is_proxy' => $this->is_proxy ?? false,
                    'is_vpn' => $this->is_vpn ?? false,
                    'first_seen' => $this->blocked_at,
                    'last_seen' => $this->blocked_at,
                    'submission_count' => 1,
                    'blocked_count' => 1,
                    'block_rate' => 1.0,
                ]
            );
        }

        return $reputation;
    }

    /**
     * Get geolocation data for this submission's IP address
     */
    public function getGeolocationData(): ?GeoLite2Location
    {
        if (! $this->ip_address) {
            return null;
        }

        $ipBlock = GeoLite2IpBlock::findByIp($this->ip_address);

        return $ipBlock?->location;
    }

    /**
     * Update geolocation data from IP lookup
     */
    public function updateGeolocationFromIp(): bool
    {
        $location = $this->getGeolocationData();

        if (! $location) {
            return false;
        }

        $this->update([
            'country_code' => $location->country_iso_code,
            'region' => $location->subdivision_1_name,
            'city' => $location->city_name,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'timezone' => $location->time_zone,
        ]);

        return true;
    }

    // Advanced Business Logic Methods

    /**
     * Calculate comprehensive risk score based on multiple factors
     */
    public function calculateComprehensiveRiskScore(): int
    {
        $score = 0;

        // Base score from block reason
        $score += $this->block_reason->getDefaultRiskScore();

        // Network type adjustments
        if ($this->is_tor) {
            $score += 20;
        }
        if ($this->is_proxy) {
            $score += 15;
        }
        if ($this->is_vpn) {
            $score += 10;
        }

        // Geographic risk factors
        $highRiskCountries = ['CN', 'RU', 'KP', 'IR'];
        if (in_array($this->country_code, $highRiskCountries)) {
            $score += 15;
        }

        // Form field analysis
        if ($this->form_field_count < 3) {
            $score += 10; // Suspiciously few fields
        } elseif ($this->form_field_count > 20) {
            $score += 5; // Unusually many fields
        }

        // IP reputation factor
        $ipReputation = $this->ipReputation;
        if ($ipReputation) {
            $reputationScore = $ipReputation->reputation_score;
            if ($reputationScore <= 30) {
                $score += 25;
            } elseif ($reputationScore <= 50) {
                $score += 10;
            } elseif ($reputationScore >= 80) {
                $score -= 10;
            }
        }

        // Time-based patterns (if blocked during suspicious hours)
        $hour = $this->blocked_at->hour;
        if ($hour >= 2 && $hour <= 6) {
            $score += 5; // Late night activity
        }

        return min(100, max(0, $score));
    }

    /**
     * Analyze submission patterns for this IP
     */
    public function analyzeSubmissionPatterns(): array
    {
        $recentSubmissions = static::where('ip_address', $this->ip_address)
            ->where('blocked_at', '>=', now()->subDays(7))
            ->orderBy('blocked_at')
            ->get();

        $patterns = [
            'total_attempts' => $recentSubmissions->count(),
            'unique_forms' => $recentSubmissions->pluck('form_identifier')->unique()->count(),
            'block_reasons' => $recentSubmissions->pluck('block_reason')->map(fn ($reason) => $reason->value)->countBy()->toArray(),
            'time_distribution' => [],
            'geographic_consistency' => true,
            'escalation_detected' => false,
        ];

        // Analyze time distribution
        $hourCounts = $recentSubmissions->groupBy(function ($submission) {
            return $submission->blocked_at->hour;
        })->map->count();

        $patterns['time_distribution'] = $hourCounts->toArray();

        // Check geographic consistency
        $countries = $recentSubmissions->pluck('country_code')->unique();
        if ($countries->count() > 3) {
            $patterns['geographic_consistency'] = false;
        }

        // Detect escalation (increasing frequency)
        if ($recentSubmissions->count() >= 5) {
            $recent24h = $recentSubmissions->where('blocked_at', '>=', now()->subDay())->count();
            $previous24h = $recentSubmissions->where('blocked_at', '>=', now()->subDays(2))
                ->where('blocked_at', '<', now()->subDay())->count();

            if ($recent24h > $previous24h * 2) {
                $patterns['escalation_detected'] = true;
            }
        }

        return $patterns;
    }

    /**
     * Generate threat assessment report
     */
    public function generateThreatAssessment(): array
    {
        $patterns = $this->analyzeSubmissionPatterns();
        $riskScore = $this->calculateComprehensiveRiskScore();
        $riskLevel = RiskLevel::fromScore($riskScore);

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel->value,
            'threat_indicators' => [
                'automated_behavior' => $this->isAutomatedThreat(),
                'suspicious_network' => $this->is_tor || $this->is_proxy || $this->is_vpn,
                'high_frequency' => $patterns['total_attempts'] > 10,
                'multiple_forms' => $patterns['unique_forms'] > 3,
                'geographic_anomaly' => ! $patterns['geographic_consistency'],
                'escalation_pattern' => $patterns['escalation_detected'],
            ],
            'recommendations' => $this->generateSecurityRecommendations($riskLevel, $patterns),
            'submission_patterns' => $patterns,
            'ip_reputation' => $this->ipReputation?->getReputationSummary() ?? null,
        ];
    }

    /**
     * Generate security recommendations based on threat assessment
     */
    private function generateSecurityRecommendations(RiskLevel $riskLevel, array $patterns): array
    {
        $recommendations = [];

        if ($riskLevel === RiskLevel::CRITICAL || $riskLevel === RiskLevel::HIGH) {
            $recommendations[] = 'Immediately block IP address across all forms';
            $recommendations[] = 'Review and strengthen form validation rules';
        }

        if ($patterns['escalation_detected']) {
            $recommendations[] = 'Implement progressive rate limiting';
            $recommendations[] = 'Consider temporary IP blocking';
        }

        if ($patterns['unique_forms'] > 5) {
            $recommendations[] = 'Implement cross-form tracking and blocking';
            $recommendations[] = 'Review form exposure and access controls';
        }

        if (! $patterns['geographic_consistency']) {
            $recommendations[] = 'Investigate potential botnet activity';
            $recommendations[] = 'Consider geographic access restrictions';
        }

        if ($this->is_tor || $this->is_proxy) {
            $recommendations[] = 'Consider blocking anonymous network access';
            $recommendations[] = 'Implement additional verification for anonymous users';
        }

        return $recommendations;
    }

    // AnalyticsModelInterface Implementation

    /**
     * Scope query to filter recent records within specified hours
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $this->scopeRecentBlocks($query, $hours);
    }

    /**
     * Get count of records for the specified date range
     */
    public static function getCountByDateRange(Carbon $startDate, Carbon $endDate): int
    {
        return static::query()
            ->whereBetween('blocked_at', [$startDate, $endDate])
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
            ->selectRaw("DATE_FORMAT(blocked_at, '{$dateFormat}') as period")
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('AVG(risk_score) as avg_risk_score')
            ->selectRaw('MAX(risk_score) as max_risk_score')
            ->whereBetween('blocked_at', [$startDate, $endDate])
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
            ->selectRaw('AVG(risk_score) as avg_risk_score')
            ->whereBetween('blocked_at', [$startDate, $endDate])
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
        $query = static::query()->whereBetween('blocked_at', [$startDate, $endDate]);

        return [
            'total_blocks' => $query->count(),
            'unique_ips' => $query->distinct('ip_address')->count('ip_address'),
            'unique_forms' => $query->distinct('form_identifier')->count('form_identifier'),
            'avg_risk_score' => round($query->avg('risk_score'), 2),
            'high_risk_blocks' => $query->where('risk_score', '>=', 70)->count(),
            'suspicious_network_blocks' => $query->where(function ($q) {
                $q->where('is_tor', true)
                    ->orWhere('is_proxy', true)
                    ->orWhere('is_vpn', true);
            })->count(),
            'top_countries' => static::getTopByField('country_code', $startDate, $endDate, 5),
            'top_block_reasons' => static::getTopByField('block_reason', $startDate, $endDate, 5),
        ];
    }

    // Performance Optimizations

    /**
     * Get optimized select columns for common queries
     */
    protected function getOptimizedSelectColumns(): array
    {
        return [
            'id',
            'ip_address',
            'form_identifier',
            'block_reason',
            'risk_score',
            'country_code',
            'blocked_at',
            'created_at',
        ];
    }

    /**
     * Get optimized eager loading relationships
     */
    protected function getOptimizedEagerLoads(): array
    {
        return ['ipReputation:ip_address,reputation_score,reputation_status'];
    }

    /**
     * Scope for high-performance analytics queries
     */
    public function scopeForAnalytics(Builder $query): Builder
    {
        return $query->select([
            'ip_address',
            'form_identifier',
            'block_reason',
            'risk_score',
            'country_code',
            'blocked_at',
            DB::raw('DATE(blocked_at) as block_date'),
            DB::raw('HOUR(blocked_at) as block_hour'),
        ])
            ->orderBy('blocked_at', 'desc');
    }

    /**
     * Get cached high-risk submissions
     */
    public static function getCachedHighRiskSubmissions(int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return static::getCachedResults('getHighRiskSubmissions', [$hours], 600); // 10 min cache
    }

    /**
     * Get high-risk submissions (cacheable method)
     */
    public static function getHighRiskSubmissions(int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return static::highRisk()
            ->recentBlocks($hours)
            ->optimized()
            ->limit(1000)
            ->get();
    }

    /**
     * Get cached country statistics
     */
    public static function getCachedCountryStats(int $days = 7): array
    {
        return static::getCachedResults('getCountryStats', [$days], 1800); // 30 min cache
    }

    /**
     * Get country statistics (cacheable method)
     */
    public static function getCountryStats(int $days = 7): array
    {
        return static::select([
            'country_code',
            DB::raw('COUNT(*) as total_blocks'),
            DB::raw('AVG(risk_score) as avg_risk_score'),
            DB::raw('COUNT(DISTINCT ip_address) as unique_ips'),
        ])
            ->where('blocked_at', '>=', now()->subDays($days))
            ->groupBy('country_code')
            ->orderByDesc('total_blocks')
            ->limit(50)
            ->get()
            ->toArray();
    }

    /**
     * Optimize bulk blocking operations
     */
    public static function bulkBlock(array $submissions): int
    {
        $now = now();
        $processedSubmissions = [];

        foreach ($submissions as $submission) {
            $processedSubmissions[] = array_merge($submission, [
                'blocked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return static::bulkInsert($processedSubmissions, 500) ? count($processedSubmissions) : 0;
    }
}
