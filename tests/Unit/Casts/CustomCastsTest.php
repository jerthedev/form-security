<?php

declare(strict_types=1);

/**
 * Test File: CustomCastsTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Comprehensive PHPUnit 12 tests for custom cast classes
 * covering validation, transformation, and edge cases.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace Tests\Unit\Casts;

use Illuminate\Database\Eloquent\Model;
use JTD\FormSecurity\Casts\CoordinatesCast;
use JTD\FormSecurity\Casts\IpRangeCast;
use JTD\FormSecurity\Casts\ThreatCategoriesCast;
use JTD\FormSecurity\Casts\ValidatedMetadataCast;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * CustomCastsTest Class
 *
 * Comprehensive test suite for custom cast classes covering:
 * - Data validation and transformation
 * - Edge cases and error handling
 * - Performance and security
 */
#[Group('casts')]
#[Group('validation')]
class CustomCastsTest extends TestCase
{
    private Model $mockModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockModel = new class extends Model {};
    }

    #[Test]
    public function coordinates_cast_validates_and_formats_coordinates(): void
    {
        $cast = new CoordinatesCast;

        $validCoordinates = [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy_radius' => 50,
        ];

        $result = $cast->get($this->mockModel, 'coordinates', json_encode($validCoordinates), []);

        $this->assertIsArray($result);
        $this->assertEquals(40.7128, $result['latitude']);
        $this->assertEquals(-74.0060, $result['longitude']);
        $this->assertEquals(50, $result['accuracy_radius']);
    }

    #[Test]
    public function coordinates_cast_rejects_invalid_coordinates(): void
    {
        $cast = new CoordinatesCast;

        $invalidCoordinates = [
            'latitude' => 91.0, // Invalid latitude
            'longitude' => -74.0060,
        ];

        $result = $cast->get($this->mockModel, 'coordinates', json_encode($invalidCoordinates), []);

        $this->assertNull($result);
    }

    #[Test]
    public function ip_range_cast_parses_cidr_notation(): void
    {
        $cast = new IpRangeCast;

        $result = $cast->get($this->mockModel, 'ip_range', '192.168.1.0/24', []);

        $this->assertIsArray($result);
        $this->assertEquals('192.168.1.0/24', $result['cidr']);
        $this->assertEquals('192.168.1.0', $result['start_ip']);
        $this->assertEquals('192.168.1.255', $result['end_ip']);
        $this->assertEquals(256, $result['total_ips']);
    }

    #[Test]
    public function ip_range_cast_validates_ip_addresses(): void
    {
        $cast = new IpRangeCast;

        $result = $cast->get($this->mockModel, 'ip_range', '999.999.999.999/24', []);

        $this->assertNull($result);
    }

    #[Test]
    public function threat_categories_cast_validates_categories(): void
    {
        $cast = new ThreatCategoriesCast;

        $validCategories = ['malware', 'botnet', 'spam'];
        $result = $cast->get($this->mockModel, 'threat_categories', json_encode($validCategories), []);

        $this->assertIsArray($result);
        $this->assertContains('malware', $result);
        $this->assertContains('botnet', $result);
        $this->assertContains('spam', $result);
    }

    #[Test]
    public function threat_categories_cast_filters_invalid_categories(): void
    {
        $cast = new ThreatCategoriesCast;

        $mixedCategories = ['malware', 'invalid_category', 'spam'];
        $result = $cast->get($this->mockModel, 'threat_categories', json_encode($mixedCategories), []);

        $this->assertIsArray($result);
        $this->assertContains('malware', $result);
        $this->assertContains('spam', $result);
        $this->assertNotContains('invalid_category', $result);
    }

    #[Test]
    public function threat_categories_cast_normalizes_categories(): void
    {
        $cast = new ThreatCategoriesCast;

        $unnormalizedCategories = ['MALWARE', 'Bot-Net', 'Spam Email'];
        $result = $cast->get($this->mockModel, 'threat_categories', json_encode($unnormalizedCategories), []);

        $this->assertIsArray($result);
        $this->assertContains('malware', $result);
        $this->assertContains('botnet', $result);
        // 'Spam Email' should be normalized but might not match valid categories
    }

    #[Test]
    public function validated_metadata_cast_sanitizes_data(): void
    {
        $cast = new ValidatedMetadataCast;

        $metadata = [
            'safe_key' => 'safe_value',
            'nested' => [
                'key' => 'value',
                'number' => 123,
            ],
            'dangerous<script>' => 'should_be_sanitized',
        ];

        $result = $cast->get($this->mockModel, 'metadata', json_encode($metadata), []);

        $this->assertIsArray($result);
        $this->assertEquals('safe_value', $result['safe_key']);
        $this->assertEquals('value', $result['nested']['key']);
        $this->assertEquals(123, $result['nested']['number']);
        $this->assertArrayHasKey('dangerous_script_', $result); // Sanitized key
    }

    #[Test]
    public function validated_metadata_cast_limits_nesting_depth(): void
    {
        $cast = new ValidatedMetadataCast;

        // Create deeply nested structure
        $deepMetadata = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'level5' => [
                                'level6' => 'too_deep',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $cast->get($this->mockModel, 'metadata', json_encode($deepMetadata), []);

        $this->assertIsArray($result);
        // Should be limited by MAX_DEPTH (5 levels)
        $this->assertArrayHasKey('level1', $result);
        $this->assertArrayHasKey('level2', $result['level1']);
        $this->assertArrayHasKey('level3', $result['level1']['level2']);
        $this->assertArrayHasKey('level4', $result['level1']['level2']['level3']);
        // Level 5 might be empty due to depth limit
    }

    #[Test]
    public function validated_metadata_cast_limits_key_count(): void
    {
        $cast = new ValidatedMetadataCast;

        // Create metadata with many keys
        $manyKeys = [];
        for ($i = 0; $i < 150; $i++) {
            $manyKeys["key_{$i}"] = "value_{$i}";
        }

        $result = $cast->get($this->mockModel, 'metadata', json_encode($manyKeys), []);

        $this->assertIsArray($result);
        // Should be limited by MAX_KEYS (100)
        $this->assertLessThanOrEqual(100, count($result));
    }

    #[Test]
    public function casts_handle_null_values_gracefully(): void
    {
        $coordinatesCast = new CoordinatesCast;
        $ipRangeCast = new IpRangeCast;
        $threatCategoriesCast = new ThreatCategoriesCast;
        $metadataCast = new ValidatedMetadataCast;

        $this->assertNull($coordinatesCast->get($this->mockModel, 'test', null, []));
        $this->assertNull($ipRangeCast->get($this->mockModel, 'test', null, []));
        $this->assertEquals([], $threatCategoriesCast->get($this->mockModel, 'test', null, []));
        $this->assertEquals([], $metadataCast->get($this->mockModel, 'test', null, []));
    }

    #[Test]
    public function casts_handle_invalid_json_gracefully(): void
    {
        $coordinatesCast = new CoordinatesCast;
        $ipRangeCast = new IpRangeCast;
        $threatCategoriesCast = new ThreatCategoriesCast;
        $metadataCast = new ValidatedMetadataCast;

        $invalidJson = '{"invalid": json}';

        $this->assertNull($coordinatesCast->get($this->mockModel, 'test', $invalidJson, []));
        $this->assertNull($ipRangeCast->get($this->mockModel, 'test', $invalidJson, []));
        $this->assertEquals([], $threatCategoriesCast->get($this->mockModel, 'test', $invalidJson, []));
        $this->assertEquals([], $metadataCast->get($this->mockModel, 'test', $invalidJson, []));
    }

    #[Test]
    public function threat_categories_cast_provides_valid_categories_list(): void
    {
        $validCategories = ThreatCategoriesCast::getValidCategories();

        $this->assertIsArray($validCategories);
        $this->assertContains('malware', $validCategories);
        $this->assertContains('botnet', $validCategories);
        $this->assertContains('spam', $validCategories);
        $this->assertContains('phishing', $validCategories);
    }

    #[Test]
    public function threat_categories_cast_validates_individual_categories(): void
    {
        $this->assertTrue(ThreatCategoriesCast::isValidCategory('malware'));
        $this->assertTrue(ThreatCategoriesCast::isValidCategory('MALWARE')); // Case insensitive
        $this->assertTrue(ThreatCategoriesCast::isValidCategory('bot-net')); // Normalized
        $this->assertFalse(ThreatCategoriesCast::isValidCategory('invalid_category'));
    }
}
