<?php

declare(strict_types=1);

/**
 * Test File: RequestLevelCacheRepositoryTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: Code Coverage Gap
 *
 * Description: Unit tests for RequestLevelCacheRepository testing all
 * implemented methods for request-level caching functionality.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-003-multi-level-caching-system.md
 * @see docs/Planning/Sprints/004-caching-cli-integration.md
 */

namespace JTD\FormSecurity\Tests\Unit\Services\Cache\Support;

use JTD\FormSecurity\Services\Cache\Support\RequestLevelCacheRepository;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('unit')]
#[Group('request-cache')]
class RequestLevelCacheRepositoryTest extends TestCase
{
    private RequestLevelCacheRepository $repository;
    private array $requestCache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestCache = [];
        $this->repository = new RequestLevelCacheRepository($this->requestCache);
    }

    #[Test]
    public function it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(RequestLevelCacheRepository::class, $this->repository);
    }

    #[Test]
    public function it_can_store_and_retrieve_values(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $this->assertTrue($this->repository->put($key, $value));
        $this->assertEquals($value, $this->repository->get($key));
    }

    #[Test]
    public function it_returns_default_value_for_missing_keys(): void
    {
        $default = 'default_value';
        $this->assertEquals($default, $this->repository->get('missing_key', $default));
        $this->assertNull($this->repository->get('missing_key'));
    }

    #[Test]
    public function it_can_store_values_forever(): void
    {
        $key = 'forever_key';
        $value = 'forever_value';

        $this->assertTrue($this->repository->forever($key, $value));
        $this->assertEquals($value, $this->repository->get($key));
        $this->assertTrue($this->repository->has($key));
    }

    #[Test]
    public function it_can_forget_values(): void
    {
        $key = 'forget_key';
        $value = 'forget_value';

        $this->repository->put($key, $value);
        $this->assertTrue($this->repository->has($key));

        $this->assertTrue($this->repository->forget($key));
        $this->assertFalse($this->repository->has($key));
        $this->assertNull($this->repository->get($key));
    }

    #[Test]
    public function it_can_flush_all_values(): void
    {
        $this->repository->put('key1', 'value1');
        $this->repository->put('key2', 'value2');
        $this->repository->put('key3', 'value3');

        $this->assertTrue($this->repository->has('key1'));
        $this->assertTrue($this->repository->has('key2'));
        $this->assertTrue($this->repository->has('key3'));

        $this->assertTrue($this->repository->flush());

        $this->assertFalse($this->repository->has('key1'));
        $this->assertFalse($this->repository->has('key2'));
        $this->assertFalse($this->repository->has('key3'));
    }

    #[Test]
    public function it_can_check_if_key_exists(): void
    {
        $key = 'exists_key';
        $value = 'exists_value';

        $this->assertFalse($this->repository->has($key));
        $this->repository->put($key, $value);
        $this->assertTrue($this->repository->has($key));
    }

    #[Test]
    public function it_can_add_values_only_if_key_does_not_exist(): void
    {
        $key = 'add_key';
        $value1 = 'first_value';
        $value2 = 'second_value';

        // First add should succeed
        $this->assertTrue($this->repository->add($key, $value1));
        $this->assertEquals($value1, $this->repository->get($key));

        // Second add should fail (key already exists)
        $this->assertFalse($this->repository->add($key, $value2));
        $this->assertEquals($value1, $this->repository->get($key)); // Value unchanged
    }

    #[Test]
    public function it_can_increment_numeric_values(): void
    {
        $key = 'increment_key';

        // Increment non-existent key should create it with the increment value
        $result = $this->repository->increment($key, 5);
        $this->assertEquals(5, $result);
        $this->assertEquals(5, $this->repository->get($key));

        // Increment existing key
        $result = $this->repository->increment($key, 3);
        $this->assertEquals(8, $result);
        $this->assertEquals(8, $this->repository->get($key));

        // Default increment by 1
        $result = $this->repository->increment($key);
        $this->assertEquals(9, $result);
        $this->assertEquals(9, $this->repository->get($key));
    }

    #[Test]
    public function it_can_decrement_numeric_values(): void
    {
        $key = 'decrement_key';

        // Set initial value
        $this->repository->put($key, 10);

        // Decrement by specific amount
        $result = $this->repository->decrement($key, 3);
        $this->assertEquals(7, $result);
        $this->assertEquals(7, $this->repository->get($key));

        // Default decrement by 1
        $result = $this->repository->decrement($key);
        $this->assertEquals(6, $result);
        $this->assertEquals(6, $this->repository->get($key));
    }

    #[Test]
    public function it_can_get_multiple_values(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        foreach ($data as $key => $value) {
            $this->repository->put($key, $value);
        }

        $keys = ['key1', 'key2', 'key3', 'missing_key'];
        $result = $this->repository->many($keys);

        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('value2', $result['key2']);
        $this->assertEquals('value3', $result['key3']);
        $this->assertNull($result['missing_key']);
    }

    #[Test]
    public function it_can_put_multiple_values(): void
    {
        $data = [
            'multi_key1' => 'multi_value1',
            'multi_key2' => 'multi_value2',
            'multi_key3' => 'multi_value3'
        ];

        $this->assertTrue($this->repository->putMany($data));

        foreach ($data as $key => $expectedValue) {
            $this->assertEquals($expectedValue, $this->repository->get($key));
        }
    }

    #[Test]
    public function it_provides_cache_prefix(): void
    {
        $prefix = $this->repository->getPrefix();
        $this->assertIsString($prefix);
        $this->assertEquals('request_cache:', $prefix);
    }

    #[Test]
    public function it_can_get_all_cached_data(): void
    {
        $data = [
            'all_key1' => 'all_value1',
            'all_key2' => 'all_value2'
        ];

        foreach ($data as $key => $value) {
            $this->repository->put($key, $value);
        }

        $all = $this->repository->all();
        $this->assertIsArray($all);
        $this->assertEquals('all_value1', $all['all_key1']);
        $this->assertEquals('all_value2', $all['all_key2']);
    }

    #[Test]
    public function it_provides_cache_size_information(): void
    {
        $this->repository->put('size_key1', 'size_value1');
        $this->repository->put('size_key2', 'size_value2');

        $size = $this->repository->size();
        $this->assertIsArray($size);
        $this->assertArrayHasKey('keys', $size);
        $this->assertArrayHasKey('memory_usage', $size);
        $this->assertEquals(2, $size['keys']);
        $this->assertIsInt($size['memory_usage']);
        $this->assertGreaterThan(0, $size['memory_usage']);
    }

    #[Test]
    public function it_handles_ttl_expiration(): void
    {
        $key = 'ttl_key';
        $value = 'ttl_value';

        // Store with 1 second TTL
        $this->repository->put($key, $value, 1);
        $this->assertTrue($this->repository->has($key));
        $this->assertEquals($value, $this->repository->get($key));

        // Sleep for 2 seconds to ensure expiration
        sleep(2);

        // Key should be expired and not accessible
        $this->assertFalse($this->repository->has($key));
        $this->assertNull($this->repository->get($key));
    }

    #[Test]
    public function it_handles_zero_and_negative_ttl(): void
    {
        $key = 'zero_ttl_key';
        $value = 'zero_ttl_value';

        // Zero TTL should store without expiration
        $this->repository->put($key, $value, 0);
        $this->assertTrue($this->repository->has($key));

        // Negative TTL should store without expiration
        $this->repository->put($key, $value, -1);
        $this->assertTrue($this->repository->has($key));
    }

    #[Test]
    public function it_handles_non_numeric_increment_gracefully(): void
    {
        $key = 'non_numeric_key';
        $value = 'string_value';

        $this->repository->put($key, $value);
        
        // Increment should return false for non-numeric values
        $result = $this->repository->increment($key);
        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        $this->repository->flush();
        parent::tearDown();
    }
}
