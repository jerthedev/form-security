<?php

/**
 * Test File: CacheOperationServiceTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1023-caching-system-tests
 *
 * Description: Comprehensive unit tests for CacheOperationService functionality
 * including multi-level cache operations, fallback mechanisms, and performance optimization.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-003-multi-level-caching-system.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1023-caching-system-tests.md
 */

declare(strict_types=1);

namespace JTD\FormSecurity\Tests\Unit\Services;

use Illuminate\Cache\CacheManager as LaravelCacheManager;
use JTD\FormSecurity\Enums\CacheLevel;
use JTD\FormSecurity\Services\Cache\Operations\CacheOperationService;
use JTD\FormSecurity\Tests\TestCase;
use JTD\FormSecurity\ValueObjects\CacheKey;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('ticket-1023')]
#[Group('caching')]
#[Group('operations')]
class CacheOperationServiceTest extends TestCase
{
    private CacheOperationService $operationService;

    private LaravelCacheManager $laravelCacheManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laravelCacheManager = $this->app->make(LaravelCacheManager::class);
        $this->operationService = new CacheOperationService($this->laravelCacheManager);
    }

    #[Test]
    public function it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(CacheOperationService::class, $this->operationService);
    }

    #[Test]
    public function it_can_store_and_retrieve_data_with_string_key(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $this->assertTrue($this->operationService->put($key, $value));
        $this->assertEquals($value, $this->operationService->get($key));
    }

    #[Test]
    public function it_can_store_and_retrieve_data_with_cache_key_object(): void
    {
        $key = new CacheKey('test', 'category', ['param' => 'value']);
        $value = ['data' => 'test_data'];

        $this->assertTrue($this->operationService->put($key, $value));
        $this->assertEquals($value, $this->operationService->get($key));
    }

    #[Test]
    public function it_returns_default_value_for_missing_key(): void
    {
        $defaultValue = 'default';
        $result = $this->operationService->get('non_existent_key', $defaultValue);

        $this->assertEquals($defaultValue, $result);
    }

    #[Test]
    public function it_can_forget_cached_data(): void
    {
        $key = 'test_forget_key';
        $value = 'test_value';

        $this->operationService->put($key, $value);
        $this->assertEquals($value, $this->operationService->get($key));

        $this->assertTrue($this->operationService->forget($key));
        $this->assertNull($this->operationService->get($key));
    }

    #[Test]
    public function it_can_flush_all_cache_data(): void
    {
        $keys = ['key1', 'key2', 'key3'];
        $value = 'test_value';

        // Store multiple values
        foreach ($keys as $key) {
            $this->operationService->put($key, $value);
        }

        // Verify they exist
        foreach ($keys as $key) {
            $this->assertEquals($value, $this->operationService->get($key));
        }

        // Flush all
        $this->assertTrue($this->operationService->flush());

        // Verify they're gone
        foreach ($keys as $key) {
            $this->assertNull($this->operationService->get($key));
        }
    }

    #[Test]
    public function it_can_use_remember_method(): void
    {
        $key = 'remember_test_key';
        $expectedValue = 'computed_value';
        $callCount = 0;

        $callback = function () use ($expectedValue, &$callCount) {
            $callCount++;

            return $expectedValue;
        };

        // First call should execute callback
        $result1 = $this->operationService->remember($key, $callback);
        $this->assertEquals($expectedValue, $result1);
        $this->assertEquals(1, $callCount);

        // Second call should use cached value
        $result2 = $this->operationService->remember($key, $callback);
        $this->assertEquals($expectedValue, $result2);
        $this->assertEquals(1, $callCount); // Callback not called again
    }

    #[Test]
    public function it_can_use_remember_forever_method(): void
    {
        $key = 'remember_forever_test_key';
        $expectedValue = 'forever_value';
        $callCount = 0;

        $callback = function () use ($expectedValue, &$callCount) {
            $callCount++;

            return $expectedValue;
        };

        // First call should execute callback
        $result1 = $this->operationService->rememberForever($key, $callback);
        $this->assertEquals($expectedValue, $result1);
        $this->assertEquals(1, $callCount);

        // Second call should use cached value
        $result2 = $this->operationService->rememberForever($key, $callback);
        $this->assertEquals($expectedValue, $result2);
        $this->assertEquals(1, $callCount); // Callback not called again
    }

    #[Test]
    public function it_can_add_data_only_if_not_exists(): void
    {
        $key = 'add_test_key';
        $value1 = 'first_value';
        $value2 = 'second_value';

        // First add should succeed
        $this->assertTrue($this->operationService->add($key, $value1));
        $this->assertEquals($value1, $this->operationService->get($key));

        // Second add should fail (key already exists)
        $this->assertFalse($this->operationService->add($key, $value2));
        $this->assertEquals($value1, $this->operationService->get($key)); // Value unchanged
    }

    #[Test]
    public function it_can_check_if_key_exists(): void
    {
        $key = 'exists_test_key';
        $value = 'test_value';

        $this->assertFalse($this->operationService->has($key));

        $this->operationService->put($key, $value);
        $this->assertTrue($this->operationService->has($key));

        $this->operationService->forget($key);
        $this->assertFalse($this->operationService->has($key));
    }

    #[Test]
    public function it_handles_ttl_parameter(): void
    {
        $key = 'ttl_test_key';
        $value = 'test_value';
        $ttl = 3600; // 1 hour

        $this->assertTrue($this->operationService->put($key, $value, $ttl));
        $this->assertEquals($value, $this->operationService->get($key));
    }

    #[Test]
    public function it_handles_specific_cache_levels(): void
    {
        $key = 'level_test_key';
        $value = 'test_value';

        // Test with specific levels
        $levels = [CacheLevel::MEMORY];
        $this->assertTrue($this->operationService->put($key, $value, null, $levels));
        $this->assertEquals($value, $this->operationService->get($key, null, $levels));
    }

    #[Test]
    public function it_can_clear_cache(): void
    {
        $key = 'clear_test_key';
        $value = 'test_value';

        $this->operationService->put($key, $value);
        $this->assertEquals($value, $this->operationService->get($key));

        $this->assertTrue($this->operationService->clear());
        $this->assertNull($this->operationService->get($key));
    }

    #[Test]
    public function it_handles_level_specific_operations(): void
    {
        $key = 'level_specific_key';
        $value = 'test_value';

        // Test request level operations
        $this->assertTrue($this->operationService->putInRequest($key, $value));
        $this->assertEquals($value, $this->operationService->getFromRequest($key));

        // Test memory level operations
        $this->assertTrue($this->operationService->putInMemory($key, $value));
        $this->assertEquals($value, $this->operationService->getFromMemory($key));

        // Test database level operations
        $this->assertTrue($this->operationService->putInDatabase($key, $value));
        $this->assertEquals($value, $this->operationService->getFromDatabase($key));
    }

    #[Test]
    public function it_handles_level_specific_forget_operations(): void
    {
        $key = 'forget_level_key';
        $value = 'test_value';

        // Store in all levels
        $this->operationService->putInRequest($key, $value);
        $this->operationService->putInMemory($key, $value);
        $this->operationService->putInDatabase($key, $value);

        // Forget from specific levels
        $this->assertTrue($this->operationService->forgetFromRequest($key));
        $this->assertTrue($this->operationService->forgetFromMemory($key));
        $this->assertTrue($this->operationService->forgetFromDatabase($key));

        // Verify they're gone
        $this->assertNull($this->operationService->getFromRequest($key));
        $this->assertNull($this->operationService->getFromMemory($key));
        $this->assertNull($this->operationService->getFromDatabase($key));
    }

    #[Test]
    public function it_handles_level_specific_flush_operations(): void
    {
        $key = 'flush_level_key';
        $value = 'test_value';

        // Store in all levels
        $this->operationService->putInRequest($key, $value);
        $this->operationService->putInMemory($key, $value);
        $this->operationService->putInDatabase($key, $value);

        // Flush specific levels
        $this->assertTrue($this->operationService->flushRequest());
        $this->assertTrue($this->operationService->flushMemory());
        $this->assertTrue($this->operationService->flushDatabase());
    }

    #[Test]
    public function it_handles_complex_data_types(): void
    {
        $testCases = [
            'array' => ['key' => 'value', 'nested' => ['data' => 123]],
            'object' => (object) ['property' => 'value'],
            'integer' => 42,
            'float' => 3.14159,
            'boolean_true' => true,
            'boolean_false' => false,
            'null' => null,
        ];

        foreach ($testCases as $type => $value) {
            $key = "complex_data_{$type}";
            $this->assertTrue($this->operationService->put($key, $value));
            $this->assertEquals($value, $this->operationService->get($key));
        }
    }

    #[Test]
    public function it_handles_cache_key_object_with_parameters(): void
    {
        $cacheKey = new CacheKey('user_data', 'users', [
            'user_id' => 123,
            'include_profile' => true,
        ]);

        $userData = [
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $this->assertTrue($this->operationService->put($cacheKey, $userData));
        $this->assertEquals($userData, $this->operationService->get($cacheKey));
    }

    #[Test]
    public function it_handles_empty_and_special_values(): void
    {
        $testCases = [
            'empty_string' => '',
            'zero' => 0,
            'empty_array' => [],
            'false' => false,
        ];

        foreach ($testCases as $type => $value) {
            $key = "special_value_{$type}";
            $this->assertTrue($this->operationService->put($key, $value));
            $this->assertEquals($value, $this->operationService->get($key));
        }
    }
}
