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
        Schema::create('geolite2_locations', function (Blueprint $table) {
            $table->id();
            
            // GeoLite2 location identifier
            $table->unsignedInteger('geoname_id')->unique()->index();
            
            // Locale-specific names (most common locales)
            $table->string('locale_code', 10)->default('en')->index();
            $table->string('continent_code', 2)->nullable()->index();
            $table->string('continent_name', 100)->nullable();
            $table->string('country_iso_code', 2)->nullable()->index();
            $table->string('country_name', 100)->nullable()->index();
            
            // Administrative divisions
            $table->string('subdivision_1_iso_code', 10)->nullable()->index();
            $table->string('subdivision_1_name', 100)->nullable();
            $table->string('subdivision_2_iso_code', 10)->nullable();
            $table->string('subdivision_2_name', 100)->nullable();
            
            // City information
            $table->string('city_name', 100)->nullable()->index();
            $table->string('metro_code', 10)->nullable();
            $table->string('time_zone', 50)->nullable()->index();
            
            // Coordinates
            $table->decimal('latitude', 10, 8)->nullable()->index();
            $table->decimal('longitude', 11, 8)->nullable()->index();
            $table->unsignedInteger('accuracy_radius')->nullable();
            
            // Additional location data
            $table->boolean('is_in_european_union')->default(false)->index();
            $table->json('postal_codes')->nullable(); // Array of postal codes for this location
            
            // Data management
            $table->timestamp('data_updated_at')->nullable();
            $table->string('data_version', 50)->nullable();
            
            // Metadata
            $table->json('metadata')->nullable(); // Additional flexible data
            $table->timestamps();
            
            // Geographic search indexes
            $table->index(['country_iso_code', 'subdivision_1_iso_code']);
            $table->index(['country_iso_code', 'city_name']);
            $table->index(['continent_code', 'country_iso_code']);
            $table->index(['time_zone', 'country_iso_code']);
            
            // Coordinate-based indexes for proximity searches
            $table->index(['latitude', 'longitude']);
            $table->index(['country_iso_code', 'latitude', 'longitude']);
            
            // Composite indexes for common lookups
            $table->index(['locale_code', 'country_iso_code', 'city_name']);
            $table->index(['geoname_id', 'locale_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geolite2_locations');
    }
};
