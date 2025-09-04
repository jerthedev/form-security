<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ip_reputation', function (Blueprint $table) {
            $table->id();

            // IP address (primary key for lookups)
            $table->string('ip_address', 45)->unique(); // IPv6 support

            // Reputation scoring
            $table->integer('reputation_score')->default(50)->index(); // 0-100 scale
            $table->enum('reputation_status', [
                'trusted',
                'neutral',
                'suspicious',
                'malicious',
                'blocked',
            ])->default('neutral')->index();

            // Threat intelligence
            $table->boolean('is_tor')->default(false)->index();
            $table->boolean('is_proxy')->default(false)->index();
            $table->boolean('is_vpn')->default(false)->index();
            $table->boolean('is_hosting')->default(false)->index();
            $table->boolean('is_malware')->default(false)->index();
            $table->boolean('is_botnet')->default(false)->index();

            // Geographic information
            $table->string('country_code', 2)->nullable()->index();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('isp', 255)->nullable();
            $table->string('organization', 255)->nullable();

            // Activity tracking
            $table->integer('submission_count')->default(0)->index();
            $table->integer('blocked_count')->default(0)->index();
            $table->decimal('block_rate', 5, 4)->default(0.0000)->index(); // Percentage as decimal
            $table->timestamp('first_seen')->nullable()->index();
            $table->timestamp('last_seen')->nullable()->index();
            $table->timestamp('last_blocked')->nullable()->index();

            // Threat source tracking
            $table->json('threat_sources')->nullable(); // Array of threat intelligence sources
            $table->json('threat_categories')->nullable(); // Array of threat categories
            $table->text('notes')->nullable(); // Admin notes

            // Cache management
            $table->timestamp('cache_expires_at')->nullable()->index();
            $table->boolean('is_whitelisted')->default(false)->index();
            $table->boolean('is_blacklisted')->default(false)->index();

            // Metadata
            $table->json('metadata')->nullable(); // Additional flexible data
            $table->timestamps();

            // Composite indexes for performance
            $table->index(['reputation_score', 'updated_at']);
            $table->index(['reputation_status', 'updated_at']);
            $table->index(['country_code', 'reputation_score']);
            $table->index(['block_rate', 'submission_count']);
            $table->index(['cache_expires_at', 'reputation_status']);

            // Analytics indexes
            $table->index(['last_seen', 'reputation_score'], 'idx_ip_reputation_seen_score');
            $table->index(['submission_count', 'blocked_count', 'updated_at'], 'idx_ip_reputation_activity_analytics');

            // Additional performance indexes for threat intelligence queries
            $table->index(['reputation_status', 'reputation_score', 'last_seen'], 'idx_ip_reputation_status_score_seen');
            $table->index(['is_blacklisted', 'is_whitelisted', 'reputation_score'], 'idx_ip_reputation_manual_overrides');
            $table->index(['country_code', 'reputation_status', 'block_rate'], 'idx_ip_reputation_geo_status_rate');
            $table->index(['cache_expires_at', 'last_seen'], 'idx_ip_reputation_cache_management');

            // Covering indexes for high-frequency lookups
            $table->index(['ip_address', 'reputation_status', 'reputation_score', 'cache_expires_at'], 'idx_ip_reputation_lookup_covering');
            $table->index(['reputation_status', 'country_code', 'block_rate', 'submission_count'], 'idx_ip_reputation_analytics_covering');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_reputation');
    }
};
