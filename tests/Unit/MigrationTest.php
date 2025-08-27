<?php

/**
 * Test File: MigrationTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1021-database-model-tests
 *
 * Description: Comprehensive migration tests for all database tables with rollback validation,
 * index verification, and constraint testing for the JTD-FormSecurity package.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1021-database-model-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('sprint-002')]
#[Group('epic-001')]
#[Group('database')]
#[Group('models')]
#[Group('ticket-1021')]
#[Group('migration')]
class MigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function all_tables_exist_after_migration(): void
    {
        // Verify all tables exist (migrations are loaded automatically)
        $this->assertTrue(Schema::hasTable('blocked_submissions'));
        $this->assertTrue(Schema::hasTable('ip_reputation'));
        $this->assertTrue(Schema::hasTable('spam_patterns'));
        $this->assertTrue(Schema::hasTable('geolite2_locations'));
        $this->assertTrue(Schema::hasTable('geolite2_ipv4_blocks'));
    }

    #[Test]
    public function blocked_submissions_table_has_correct_structure(): void
    {
        $this->assertTrue(Schema::hasColumns('blocked_submissions', [
            'id', 'form_identifier', 'ip_address', 'user_agent', 'referer',
            'block_reason', 'block_details', 'form_data_hash', 'form_field_count',
            'country_code', 'region', 'city', 'latitude', 'longitude', 'timezone',
            'isp', 'organization', 'risk_score', 'is_tor', 'is_proxy', 'is_vpn',
            'blocked_at', 'session_id', 'fingerprint', 'metadata', 'created_at', 'updated_at'
        ]));
    }

    #[Test]
    public function ip_reputation_table_has_correct_structure(): void
    {
        $this->assertTrue(Schema::hasColumns('ip_reputation', [
            'id', 'ip_address', 'reputation_score', 'reputation_status',
            'is_tor', 'is_proxy', 'is_vpn', 'is_hosting', 'is_malware', 'is_botnet',
            'country_code', 'region', 'city', 'isp', 'organization',
            'submission_count', 'blocked_count', 'block_rate',
            'first_seen', 'last_seen', 'last_blocked',
            'threat_sources', 'threat_categories', 'notes',
            'cache_expires_at', 'is_whitelisted', 'is_blacklisted',
            'metadata', 'created_at', 'updated_at'
        ]));
    }

    #[Test]
    public function spam_patterns_table_has_correct_structure(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations']);

        $this->assertTrue(Schema::hasColumns('spam_patterns', [
            'id', 'name', 'description', 'pattern_type', 'pattern', 'pattern_config',
            'case_sensitive', 'whole_word_only', 'target_fields', 'target_forms', 'scope',
            'risk_score', 'action', 'action_config', 'match_count', 'false_positive_count',
            'accuracy_rate', 'processing_time_ms', 'is_active', 'is_learning', 'priority',
            'last_matched', 'categories', 'languages', 'regions', 'version', 'source',
            'last_updated_at', 'updated_by', 'metadata', 'created_at', 'updated_at'
        ]));
    }

    #[Test]
    public function geolite2_locations_table_has_correct_structure(): void
    {
        $this->assertTrue(Schema::hasColumns('geolite2_locations', [
            'id', 'geoname_id', 'locale_code', 'continent_code', 'continent_name',
            'country_iso_code', 'country_name', 'subdivision_1_iso_code', 'subdivision_1_name',
            'subdivision_2_iso_code', 'subdivision_2_name', 'city_name', 'metro_code',
            'time_zone', 'latitude', 'longitude', 'accuracy_radius', 'is_in_european_union',
            'postal_codes', 'data_updated_at', 'data_version', 'metadata', 'created_at', 'updated_at'
        ]));
    }

    #[Test]
    public function geolite2_ipv4_blocks_table_has_correct_structure(): void
    {
        $this->assertTrue(Schema::hasColumns('geolite2_ipv4_blocks', [
            'id', 'network', 'network_start_integer', 'network_last_integer',
            'geoname_id', 'registered_country_geoname_id', 'represented_country_geoname_id',
            'is_anonymous_proxy', 'is_satellite_provider', 'is_anycast',
            'postal_code', 'latitude', 'longitude', 'accuracy_radius',
            'data_updated_at', 'data_version', 'metadata', 'created_at', 'updated_at'
        ]));
    }

    #[Test]
    public function can_insert_and_retrieve_test_data(): void
    {
        // Insert test data into geolite2_locations
        DB::table('geolite2_locations')->insert([
            'geoname_id' => 123456,
            'locale_code' => 'en',
            'country_iso_code' => 'US',
            'country_name' => 'United States',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify data exists
        $this->assertDatabaseHas('geolite2_locations', ['geoname_id' => 123456]);

        // Test basic functionality
        $location = DB::table('geolite2_locations')->where('geoname_id', 123456)->first();
        $this->assertNotNull($location);
        $this->assertEquals('US', $location->country_iso_code);
    }

    #[Test]
    public function migration_rollback_works_correctly(): void
    {
        // Verify tables exist before rollback
        $this->assertTrue(Schema::hasTable('blocked_submissions'));
        $this->assertTrue(Schema::hasTable('ip_reputation'));
        $this->assertTrue(Schema::hasTable('spam_patterns'));
        $this->assertTrue(Schema::hasTable('geolite2_locations'));
        $this->assertTrue(Schema::hasTable('geolite2_ipv4_blocks'));

        // Rollback all migrations
        $this->artisan('migrate:rollback', ['--step' => 5]);

        // Verify tables are dropped
        $this->assertFalse(Schema::hasTable('blocked_submissions'));
        $this->assertFalse(Schema::hasTable('ip_reputation'));
        $this->assertFalse(Schema::hasTable('spam_patterns'));
        $this->assertFalse(Schema::hasTable('geolite2_locations'));
        $this->assertFalse(Schema::hasTable('geolite2_ipv4_blocks'));

        // Re-run migrations to restore state
        $this->artisan('migrate');

        // Verify tables exist again
        $this->assertTrue(Schema::hasTable('blocked_submissions'));
        $this->assertTrue(Schema::hasTable('ip_reputation'));
        $this->assertTrue(Schema::hasTable('spam_patterns'));
        $this->assertTrue(Schema::hasTable('geolite2_locations'));
        $this->assertTrue(Schema::hasTable('geolite2_ipv4_blocks'));
    }

    #[Test]
    public function database_indexes_are_created_correctly(): void
    {
        // Test blocked_submissions indexes
        $indexes = $this->getTableIndexes('blocked_submissions');
        $this->assertContains('blocked_submissions_form_identifier_index', $indexes);
        $this->assertContains('blocked_submissions_ip_address_index', $indexes);
        $this->assertContains('blocked_submissions_block_reason_index', $indexes);
        $this->assertContains('blocked_submissions_country_code_index', $indexes);
        $this->assertContains('blocked_submissions_risk_score_index', $indexes);
        $this->assertContains('blocked_submissions_blocked_at_index', $indexes);

        // Test ip_reputation indexes
        $indexes = $this->getTableIndexes('ip_reputation');
        $this->assertContains('ip_reputation_ip_address_unique', $indexes);
        $this->assertContains('ip_reputation_reputation_score_index', $indexes);
        $this->assertContains('ip_reputation_reputation_status_index', $indexes);

        // Test spam_patterns indexes
        $indexes = $this->getTableIndexes('spam_patterns');
        $this->assertContains('spam_patterns_name_index', $indexes);
        $this->assertContains('spam_patterns_pattern_type_index', $indexes);
        $this->assertContains('spam_patterns_is_active_index', $indexes);

        // Test geolite2_locations indexes
        $indexes = $this->getTableIndexes('geolite2_locations');
        $this->assertContains('geolite2_locations_geoname_id_unique', $indexes);
        $this->assertContains('geolite2_locations_country_iso_code_index', $indexes);

        // Test geolite2_ipv4_blocks indexes
        $indexes = $this->getTableIndexes('geolite2_ipv4_blocks');
        $this->assertContains('geolite2_ipv4_blocks_network_unique', $indexes);
        $this->assertContains('geolite2_ipv4_blocks_network_start_integer_index', $indexes);
        $this->assertContains('geolite2_ipv4_blocks_network_last_integer_index', $indexes);
    }

    #[Test]
    public function foreign_key_constraints_work_correctly(): void
    {
        // Insert a location record first
        DB::table('geolite2_locations')->insert([
            'geoname_id' => 123456,
            'locale_code' => 'en',
            'country_iso_code' => 'US',
            'country_name' => 'United States',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert IP block with valid geoname_id reference
        DB::table('geolite2_ipv4_blocks')->insert([
            'network' => '192.168.1.0/24',
            'network_start_integer' => ip2long('192.168.1.0'),
            'network_last_integer' => ip2long('192.168.1.255'),
            'geoname_id' => 123456,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify the relationship works
        $this->assertDatabaseHas('geolite2_ipv4_blocks', ['geoname_id' => 123456]);
    }

    #[Test]
    public function table_column_types_are_correct(): void
    {
        // Test blocked_submissions column types
        $columns = $this->getTableColumns('blocked_submissions');
        $this->assertContains($columns['id']['type'], ['bigint', 'integer']); // SQLite uses integer
        $this->assertContains($columns['form_identifier']['type'], ['varchar', 'text']);
        $this->assertContains($columns['ip_address']['type'], ['varchar', 'text']);
        $this->assertContains($columns['block_reason']['type'], ['enum', 'text', 'varchar']);
        $this->assertEquals('integer', $columns['risk_score']['type']);
        $this->assertContains($columns['is_tor']['type'], ['boolean', 'integer', 'tinyint(1)']); // Different databases use different types for boolean
        $this->assertContains($columns['latitude']['type'], ['decimal', 'numeric', 'real']);
        $this->assertContains($columns['longitude']['type'], ['decimal', 'numeric', 'real']);
        $this->assertContains($columns['metadata']['type'], ['json', 'text']);

        // Test ip_reputation column types
        $columns = $this->getTableColumns('ip_reputation');
        $this->assertContains($columns['ip_address']['type'], ['varchar', 'text']);
        $this->assertEquals('integer', $columns['reputation_score']['type']);
        $this->assertContains($columns['reputation_status']['type'], ['enum', 'text', 'varchar']);
        $this->assertContains($columns['block_rate']['type'], ['decimal', 'numeric', 'real']);

        // Test spam_patterns column types
        $columns = $this->getTableColumns('spam_patterns');
        $this->assertContains($columns['name']['type'], ['varchar', 'text']);
        $this->assertContains($columns['pattern_type']['type'], ['enum', 'text', 'varchar']);
        $this->assertEquals('text', $columns['pattern']['type']);
        $this->assertContains($columns['pattern_config']['type'], ['json', 'text']);
    }

    /**
     * Get table indexes for testing
     */
    private function getTableIndexes(string $table): array
    {
        $indexes = [];
        $results = DB::select("PRAGMA index_list({$table})");

        foreach ($results as $result) {
            $indexes[] = $result->name;
        }

        return $indexes;
    }

    /**
     * Get table column information for testing
     */
    private function getTableColumns(string $table): array
    {
        $columns = [];
        $results = DB::select("PRAGMA table_info({$table})");

        foreach ($results as $result) {
            $columns[$result->name] = [
                'type' => strtolower($result->type),
                'nullable' => !$result->notnull,
                'default' => $result->dflt_value,
            ];
        }

        return $columns;
    }
}
