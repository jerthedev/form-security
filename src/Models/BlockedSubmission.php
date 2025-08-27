<?php

declare(strict_types=1);

/**
 * Model File: BlockedSubmission.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-008-blocked-submissions-tracking
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Eloquent model for tracking blocked form submissions with comprehensive
 * analytics, relationships, and query scopes for the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Data-Management-Analytics/SPEC-008-blocked-submissions-tracking.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace JTD\FormSecurity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

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
 * @property string $block_reason
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
class BlockedSubmission extends Model
{
    use HasFactory;

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
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'risk_score' => 'integer',
        'form_field_count' => 'integer',
        'is_tor' => 'boolean',
        'is_proxy' => 'boolean',
        'is_vpn' => 'boolean',
        'blocked_at' => 'datetime',
        'metadata' => 'array',
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
    public function scopeByBlockReason(Builder $query, string $blockReason): Builder
    {
        return $query->where('block_reason', $blockReason);
    }

    /**
     * Query scope: Filter by country code
     */
    public function scopeByCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
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
    public function getRiskLevel(): string
    {
        return match (true) {
            $this->risk_score >= 90 => 'critical',
            $this->risk_score >= 70 => 'high',
            $this->risk_score >= 40 => 'medium',
            $this->risk_score >= 20 => 'low',
            default => 'minimal',
        };
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
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * Get the time elapsed since the block occurred
     */
    public function getTimeElapsed(): string
    {
        return $this->blocked_at->diffForHumans();
    }
}
