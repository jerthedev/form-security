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
        Schema::create('blocked_submissions', function (Blueprint $table) {
            $table->id();

            // Core submission tracking
            $table->string('form_identifier', 255)->index();
            $table->string('ip_address', 45)->index(); // IPv6 support
            $table->string('user_agent', 1000)->nullable();
            $table->string('referer', 1000)->nullable();

            // Blocking reason and details
            $table->enum('block_reason', [
                'spam_pattern',
                'ip_reputation',
                'rate_limit',
                'geolocation',
                'honeypot',
                'custom_rule',
            ])->index();
            $table->text('block_details')->nullable(); // JSON details about the block

            // Form data (encrypted/hashed for privacy)
            $table->text('form_data_hash')->nullable(); // Hash of form data for pattern analysis
            $table->integer('form_field_count')->default(0);

            // Geographic and network information
            $table->string('country_code', 2)->nullable()->index();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('timezone', 50)->nullable();
            $table->string('isp', 255)->nullable();
            $table->string('organization', 255)->nullable();

            // Security and risk scoring
            $table->integer('risk_score')->default(0)->index(); // 0-100 risk score
            $table->boolean('is_tor')->default(false)->index();
            $table->boolean('is_proxy')->default(false)->index();
            $table->boolean('is_vpn')->default(false)->index();

            // Analytics and reporting
            $table->timestamp('blocked_at')->index();
            $table->string('session_id', 255)->nullable()->index();
            $table->string('fingerprint', 255)->nullable()->index(); // Browser fingerprint

            // Metadata
            $table->json('metadata')->nullable(); // Additional flexible data
            $table->timestamps();

            // Composite indexes for analytics queries
            $table->index(['form_identifier', 'blocked_at']);
            $table->index(['ip_address', 'blocked_at']);
            $table->index(['block_reason', 'blocked_at']);
            $table->index(['country_code', 'blocked_at']);
            $table->index(['risk_score', 'blocked_at']);

            // Performance indexes for high-volume queries
            $table->index(['blocked_at', 'form_identifier', 'block_reason']);
            $table->index(['ip_address', 'form_identifier', 'blocked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_submissions');
    }
};
