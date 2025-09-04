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
        Schema::create('geolite2_ipv4_blocks', function (Blueprint $table) {
            $table->id();

            // IP network block definition
            $table->string('network', 18)->unique()->index(); // CIDR notation (e.g., "192.168.1.0/24")
            $table->unsignedBigInteger('network_start_integer')->index(); // IP as integer for range queries
            $table->unsignedBigInteger('network_last_integer')->index(); // Last IP in range as integer

            // GeoLite2 location references
            $table->unsignedInteger('geoname_id')->nullable()->index();
            $table->unsignedInteger('registered_country_geoname_id')->nullable()->index();
            $table->unsignedInteger('represented_country_geoname_id')->nullable()->index();

            // Network classification
            $table->boolean('is_anonymous_proxy')->default(false)->index();
            $table->boolean('is_satellite_provider')->default(false)->index();
            $table->boolean('is_anycast')->default(false)->index();

            // Postal and location data
            $table->string('postal_code', 20)->nullable()->index();
            $table->decimal('latitude', 10, 8)->nullable()->index();
            $table->decimal('longitude', 11, 8)->nullable()->index();
            $table->unsignedInteger('accuracy_radius')->nullable();

            // Data management
            $table->timestamp('data_updated_at')->nullable();
            $table->string('data_version', 50)->nullable();

            // Metadata
            $table->json('metadata')->nullable(); // Additional flexible data
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('geoname_id')
                ->references('geoname_id')
                ->on('geolite2_locations')
                ->onDelete('set null');

            $table->foreign('registered_country_geoname_id')
                ->references('geoname_id')
                ->on('geolite2_locations')
                ->onDelete('set null');

            $table->foreign('represented_country_geoname_id')
                ->references('geoname_id')
                ->on('geolite2_locations')
                ->onDelete('set null');

            // Critical indexes for IP range lookups
            $table->index(['network_start_integer', 'network_last_integer']);
            $table->index(['network_last_integer', 'network_start_integer']);

            // Geographic lookup indexes
            $table->index(['geoname_id', 'network_start_integer']);
            $table->index(['registered_country_geoname_id', 'network_start_integer']);
            $table->index(['postal_code', 'geoname_id']);

            // Network type indexes
            $table->index(['is_anonymous_proxy', 'network_start_integer']);
            $table->index(['is_satellite_provider', 'network_start_integer']);

            // Coordinate-based indexes
            $table->index(['latitude', 'longitude']);
            $table->index(['geoname_id', 'latitude', 'longitude']);

            // Composite indexes for common queries
            $table->index(['network_start_integer', 'geoname_id', 'postal_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geolite2_ipv4_blocks');
    }
};
