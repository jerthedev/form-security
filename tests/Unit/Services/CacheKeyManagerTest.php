<?php

declare(strict_types=1);

/**
 * Test File: CacheKeyManagerTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Tests for the CacheKeyManager service
 * including hierarchical key management and validation.
 */

namespace JTD\FormSecurity\Tests\Unit\Services;

use JTD\FormSecurity\Services\CacheKeyManager;
use JTD\FormSecurity\Tests\TestCase;
use JTD\FormSecurity\ValueObjects\CacheKey;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('ticket-1014')]
#[Group('caching')]
#[Group('key-management')]
#[Group('unit')]
class CacheKeyManagerTest extends TestCase
{
    private CacheKeyManager $keyManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyManager = new CacheKeyManager;
    }

    #[Test]
    public function it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(CacheKeyManager::class, $this->keyManager);
    }

    #[Test]
    public function it_can_generate_keys_using_registered_generators(): void
    {
        $key = $this->keyManager->generate('ip_reputation', ['ip' => '192.168.1.1']);

        $this->assertInstanceOf(CacheKey::class, $key);
        $this->assertEquals('ip:192.168.1.1', $key->key);
        $this->assertEquals('ip_reputation', $key->namespace);
    }

    #[Test]
    public function it_throws_exception_for_unknown_generator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown key generator: unknown');

        $this->keyManager->generate('unknown');
    }

    #[Test]
    public function it_can_create_hierarchical_keys(): void
    {
        $key = $this->keyManager->createHierarchical('parent', 'child');

        $this->assertEquals('parent:child', $key->key);
        $this->assertEquals('hierarchical', $key->namespace);
        $this->assertContains('hierarchical', $key->tags);
        $this->assertContains('parent', $key->tags);
        $this->assertEquals('parent', $key->context['parent']);
        $this->assertEquals('child', $key->context['child']);
    }

    #[Test]
    public function it_can_create_versioned_keys(): void
    {
        $key = $this->keyManager->createVersioned('base_key', '1.0');

        $this->assertEquals('base_key:v1.0', $key->key);
        $this->assertEquals('versioned', $key->namespace);
        $this->assertContains('versioned', $key->tags);
        $this->assertContains('base_key', $key->tags);
        $this->assertEquals('base_key', $key->context['base_key']);
        $this->assertEquals('1.0', $key->context['version']);
    }

    #[Test]
    public function it_can_create_time_based_keys(): void
    {
        $key = $this->keyManager->createTimeBased('analytics', 'hour');

        $this->assertStringStartsWith('analytics:', $key->key);
        $this->assertEquals('time_based', $key->namespace);
        $this->assertContains('time_based', $key->tags);
        $this->assertContains('hour', $key->tags);
        $this->assertContains('analytics', $key->tags);
        $this->assertEquals('analytics', $key->context['base_key']);
        $this->assertEquals('hour', $key->context['time_unit']);
        $this->assertNotNull($key->ttl);
    }

    #[Test]
    public function it_can_validate_cache_keys(): void
    {
        $validKey = CacheKey::make('valid_key', 'test');
        $errors = $this->keyManager->validate($validKey);

        $this->assertEmpty($errors);
        $this->assertTrue($this->keyManager->isValid($validKey));
    }

    #[Test]
    public function it_detects_invalid_cache_keys(): void
    {
        // Create a key with invalid characters
        $invalidKey = new CacheKey(
            key: 'invalid key with spaces!@#',
            namespace: 'test'
        );

        $errors = $this->keyManager->validate($invalidKey);

        $this->assertNotEmpty($errors);
        $this->assertFalse($this->keyManager->isValid($invalidKey));
        $this->assertArrayHasKey('key_characters', $errors);
    }

    #[Test]
    public function it_can_register_custom_generators(): void
    {
        $this->keyManager->registerGenerator('custom', function (array $params) {
            return CacheKey::make($params['key'] ?? 'default', 'custom');
        });

        $generators = $this->keyManager->getGenerators();
        $this->assertContains('custom', $generators);

        $key = $this->keyManager->generate('custom', ['key' => 'test']);
        $this->assertEquals('test', $key->key);
        $this->assertEquals('custom', $key->namespace);
    }

    #[Test]
    public function it_can_register_custom_validation_rules(): void
    {
        $this->keyManager->registerValidationRule('custom_rule', function (CacheKey $key) {
            return $key->key !== 'forbidden' ?: 'Key is forbidden';
        });

        $rules = $this->keyManager->getValidationRules();
        $this->assertContains('custom_rule', $rules);

        $forbiddenKey = CacheKey::make('forbidden', 'test');
        $errors = $this->keyManager->validate($forbiddenKey);

        $this->assertArrayHasKey('custom_rule', $errors);
        $this->assertEquals('Key is forbidden', $errors['custom_rule']);
    }

    #[Test]
    public function it_has_default_generators(): void
    {
        $generators = $this->keyManager->getGenerators();

        $this->assertContains('ip_reputation', $generators);
        $this->assertContains('spam_pattern', $generators);
        $this->assertContains('geolocation', $generators);
        $this->assertContains('configuration', $generators);
        $this->assertContains('analytics', $generators);
    }

    #[Test]
    public function it_has_default_validation_rules(): void
    {
        $rules = $this->keyManager->getValidationRules();

        $this->assertContains('key_length', $rules);
        $this->assertContains('key_characters', $rules);
        $this->assertContains('namespace_required', $rules);
        $this->assertContains('ttl_reasonable', $rules);
    }

    #[Test]
    public function it_validates_key_length(): void
    {
        // Create a very long key
        $longKey = new CacheKey(
            key: str_repeat('a', 300),
            namespace: 'test'
        );

        $errors = $this->keyManager->validate($longKey);
        $this->assertArrayHasKey('key_length', $errors);
    }

    #[Test]
    public function it_validates_ttl_reasonableness(): void
    {
        $highTtlKey = new CacheKey(
            key: 'test',
            namespace: 'test',
            ttl: 1000000 // Very high TTL
        );

        $errors = $this->keyManager->validate($highTtlKey);
        $this->assertArrayHasKey('ttl_reasonable', $errors);
    }

    #[Test]
    public function time_based_keys_have_appropriate_ttl(): void
    {
        $minuteKey = $this->keyManager->createTimeBased('test', 'minute');
        $hourKey = $this->keyManager->createTimeBased('test', 'hour');
        $dayKey = $this->keyManager->createTimeBased('test', 'day');

        $this->assertEquals(120, $minuteKey->ttl); // 2 minutes
        $this->assertEquals(3900, $hourKey->ttl); // 65 minutes
        $this->assertEquals(90000, $dayKey->ttl); // 25 hours
    }

    // Advanced Cache Key Management Tests
    #[Test]
    public function it_can_generate_complex_hierarchical_keys(): void
    {
        $key = $this->keyManager->createHierarchical('users', 'profile', 'settings');

        $this->assertEquals('users:profile:settings', $key->key);
        $this->assertEquals('hierarchical', $key->namespace);
        $this->assertContains('hierarchical', $key->tags);
        $this->assertContains('users', $key->tags);
        $this->assertContains('profile', $key->tags);
        $this->assertContains('settings', $key->tags);
    }

    #[Test]
    public function it_can_create_namespace_specific_keys(): void
    {
        $namespaces = ['ip_reputation', 'spam_patterns', 'geolocation', 'configuration'];

        foreach ($namespaces as $namespace) {
            $key = $this->keyManager->createNamespaced('test_key', $namespace);

            $this->assertEquals('test_key', $key->key);
            $this->assertEquals($namespace, $key->namespace);
            $this->assertContains($namespace, $key->tags);
        }
    }

    #[Test]
    public function it_validates_namespace_requirements(): void
    {
        $keyWithoutNamespace = new CacheKey(
            key: 'test_key',
            namespace: '' // Empty namespace
        );

        $errors = $this->keyManager->validate($keyWithoutNamespace);
        $this->assertArrayHasKey('namespace_required', $errors);
        $this->assertFalse($this->keyManager->isValid($keyWithoutNamespace));
    }

    #[Test]
    public function it_validates_key_character_restrictions(): void
    {
        $invalidCharacters = [
            'spaces in key',
            'key@with#symbols',
            'key\with\backslashes',
            'key/with/slashes',
            'key with unicode 🚀',
        ];

        foreach ($invalidCharacters as $invalidKey) {
            $key = new CacheKey(key: $invalidKey, namespace: 'test');
            $errors = $this->keyManager->validate($key);

            $this->assertArrayHasKey('key_characters', $errors, "Failed for key: {$invalidKey}");
            $this->assertFalse($this->keyManager->isValid($key));
        }
    }

    #[Test]
    public function it_allows_valid_key_characters(): void
    {
        $validKeys = [
            'simple_key',
            'key-with-dashes',
            'key.with.dots',
            'key123with456numbers',
            'UPPERCASE_KEY',
            'mixedCaseKey',
            'key:with:colons',
        ];

        foreach ($validKeys as $validKey) {
            $key = new CacheKey(key: $validKey, namespace: 'test');
            $errors = $this->keyManager->validate($key);

            $this->assertArrayNotHasKey('key_characters', $errors, "Failed for key: {$validKey}");
            $this->assertTrue($this->keyManager->isValid($key));
        }
    }

    #[Test]
    public function it_can_create_tagged_keys_with_multiple_tags(): void
    {
        $tags = ['user', 'profile', 'cache', 'v1.0', 'production'];
        $key = $this->keyManager->createTagged('tagged_key', 'test', $tags);

        $this->assertEquals('tagged_key', $key->key);
        $this->assertEquals('test', $key->namespace);

        foreach ($tags as $tag) {
            $this->assertContains($tag, $key->tags);
        }
    }

    #[Test]
    public function it_can_create_contextual_keys(): void
    {
        $context = [
            'user_id' => 123,
            'action' => 'profile_view',
            'timestamp' => time(),
            'ip_address' => '192.168.1.1',
        ];

        $key = $this->keyManager->createContextual('contextual_key', 'test', $context);

        $this->assertEquals('contextual_key', $key->key);
        $this->assertEquals('test', $key->namespace);
        $this->assertEquals($context, $key->context);
        $this->assertContains('contextual', $key->tags);
    }

    #[Test]
    public function it_can_generate_keys_for_different_data_types(): void
    {
        $generators = [
            'ip_reputation' => ['ip' => '192.168.1.1'],
            'spam_pattern' => ['type' => 'email', 'pattern' => 'test@example.com'],
            'geolocation' => ['ip' => '8.8.8.8'],
            'configuration' => ['section' => 'cache', 'key' => 'ttl'],
            'analytics' => ['metric' => 'page_views', 'period' => 'daily'],
        ];

        foreach ($generators as $type => $params) {
            $key = $this->keyManager->generate($type, $params);

            $this->assertInstanceOf(CacheKey::class, $key);
            $this->assertEquals($type, $key->namespace);
            $this->assertNotEmpty($key->key);
        }
    }

    #[Test]
    public function it_can_create_expiring_keys_with_custom_ttl(): void
    {
        $ttlValues = [60, 300, 1800, 3600, 86400]; // Various TTL values

        foreach ($ttlValues as $ttl) {
            $key = $this->keyManager->createExpiring('expiring_key', 'test', $ttl);

            $this->assertEquals('expiring_key', $key->key);
            $this->assertEquals('test', $key->namespace);
            $this->assertEquals($ttl, $key->ttl);
            $this->assertContains('expiring', $key->tags);
        }
    }

    #[Test]
    public function it_validates_ttl_boundaries(): void
    {
        $invalidTtls = [
            -1,      // Negative TTL
            0,       // Zero TTL (might be invalid depending on rules)
            1000000, // Very high TTL
        ];

        foreach ($invalidTtls as $ttl) {
            $key = new CacheKey(key: 'test', namespace: 'test', ttl: $ttl);
            $errors = $this->keyManager->validate($key);

            if ($ttl < 0 || $ttl > 604800) { // 7 days max
                $this->assertArrayHasKey('ttl_reasonable', $errors, "Failed for TTL: {$ttl}");
            }
        }
    }

    #[Test]
    public function it_can_create_pattern_based_keys(): void
    {
        $patterns = [
            'user:{id}:profile' => ['id' => 123],
            'cache:{namespace}:{key}' => ['namespace' => 'test', 'key' => 'value'],
            'analytics:{metric}:{period}:{date}' => ['metric' => 'views', 'period' => 'daily', 'date' => '2024-01-01'],
        ];

        foreach ($patterns as $pattern => $params) {
            $key = $this->keyManager->createFromPattern($pattern, $params);

            $this->assertInstanceOf(CacheKey::class, $key);
            $this->assertNotEmpty($key->key);
            $this->assertContains('pattern', $key->tags);
        }
    }

    #[Test]
    public function it_can_normalize_keys_consistently(): void
    {
        $testCases = [
            'Simple Key' => 'simple_key',
            'KEY WITH SPACES' => 'key_with_spaces',
            'key-with-dashes' => 'key-with-dashes',
            'key.with.dots' => 'key.with.dots',
            'MixedCaseKey' => 'mixedcasekey',
        ];

        foreach ($testCases as $input => $expected) {
            $normalized = $this->keyManager->normalize($input);
            $this->assertEquals($expected, $normalized, "Failed for input: {$input}");
        }
    }

    #[Test]
    public function it_can_extract_key_components(): void
    {
        $hierarchicalKey = CacheKey::make('parent:child:grandchild', 'test');
        $components = $this->keyManager->extractComponents($hierarchicalKey);

        $this->assertIsArray($components);
        $this->assertCount(3, $components);
        $this->assertEquals(['parent', 'child', 'grandchild'], $components);
    }

    #[Test]
    public function it_can_build_keys_from_components(): void
    {
        $components = ['users', 'profile', 'settings', 'theme'];
        $key = $this->keyManager->buildFromComponents($components, 'test');

        $this->assertEquals('users:profile:settings:theme', $key->key);
        $this->assertEquals('test', $key->namespace);
        $this->assertContains('hierarchical', $key->tags);
    }
}
