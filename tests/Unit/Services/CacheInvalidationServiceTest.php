<?php

declare(strict_types=1);

/**
 * Test File: CacheInvalidationServiceTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-003-multi-level-caching-system
 * SPRINT: Sprint-004-caching-cli-integration
 * TICKET: 1014-multi-level-caching-system
 *
 * Description: Tests for the CacheInvalidationService
 * including dependency tracking and event-driven invalidation.
 */

namespace JTD\FormSecurity\Tests\Unit\Services;

use Illuminate\Events\Dispatcher;
use JTD\FormSecurity\Contracts\CacheManagerInterface;
use JTD\FormSecurity\Events\CacheInvalidated;
use JTD\FormSecurity\Services\CacheInvalidationService;
use JTD\FormSecurity\Tests\TestCase;
use JTD\FormSecurity\ValueObjects\CacheKey;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-004')]
#[Group('ticket-1014')]
#[Group('caching')]
#[Group('invalidation')]
#[Group('unit')]
class CacheInvalidationServiceTest extends TestCase
{
    private CacheInvalidationService $invalidationService;

    private CacheManagerInterface $cacheManager;

    private Dispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheManager = $this->createMock(CacheManagerInterface::class);
        $this->eventDispatcher = $this->createMock(Dispatcher::class);

        $this->invalidationService = new CacheInvalidationService(
            $this->cacheManager,
            $this->eventDispatcher
        );
    }

    #[Test]
    public function it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(CacheInvalidationService::class, $this->invalidationService);
    }

    #[Test]
    public function it_can_invalidate_cache_by_key(): void
    {
        $key = CacheKey::make('test_key');

        $this->cacheManager->expects($this->once())
            ->method('forget')
            ->with($key, null)
            ->willReturn(true);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CacheInvalidated::class));

        $result = $this->invalidationService->invalidate($key);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_can_invalidate_cache_by_tags(): void
    {
        $tags = ['test_tag', 'another_tag'];

        $this->cacheManager->expects($this->once())
            ->method('invalidateByTags')
            ->with($tags, null)
            ->willReturn(true);

        $result = $this->invalidationService->invalidateByTags($tags);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_can_invalidate_cache_by_namespace(): void
    {
        $namespace = 'test_namespace';
        $expectedPattern = "form_security:{$namespace}:*";

        $this->cacheManager->expects($this->once())
            ->method('invalidateByPattern')
            ->with($expectedPattern, null)
            ->willReturn(true);

        $result = $this->invalidationService->invalidateByNamespace($namespace);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_can_invalidate_cache_for_model(): void
    {
        $modelClass = 'App\\Models\\TestModel';
        $modelId = 123;

        $this->cacheManager->expects($this->exactly(2))
            ->method('forget')
            ->willReturn(true);

        $result = $this->invalidationService->invalidateForModel($modelClass, $modelId);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_tracks_invalidation_statistics(): void
    {
        $key = CacheKey::make('test_key');

        $this->cacheManager->method('forget')->willReturn(true);
        $this->eventDispatcher->method('dispatch');

        // Initial stats should be zero
        $initialStats = $this->invalidationService->getStats();
        $this->assertEquals(0, $initialStats['invalidations']);

        // Perform invalidation
        $this->invalidationService->invalidate($key);

        // Stats should be updated
        $updatedStats = $this->invalidationService->getStats();
        $this->assertEquals(1, $updatedStats['invalidations']);
    }

    #[Test]
    public function it_can_add_and_remove_dependencies(): void
    {
        $source = 'source_namespace';
        $dependent = 'dependent_namespace';

        // Add dependency
        $this->invalidationService->addDependency($source, $dependent);

        $dependencies = $this->invalidationService->getDependencies();
        $this->assertArrayHasKey($source, $dependencies);
        $this->assertContains($dependent, $dependencies[$source]);

        // Remove dependency
        $this->invalidationService->removeDependency($source, $dependent);

        $dependencies = $this->invalidationService->getDependencies();
        $this->assertNotContains($dependent, $dependencies[$source] ?? []);
    }

    #[Test]
    public function it_can_reset_statistics(): void
    {
        $key = CacheKey::make('test_key');

        $this->cacheManager->method('forget')->willReturn(true);
        $this->eventDispatcher->method('dispatch');

        // Perform some invalidations
        $this->invalidationService->invalidate($key);

        $stats = $this->invalidationService->getStats();
        $this->assertGreaterThan(0, $stats['invalidations']);

        // Reset stats
        $this->invalidationService->resetStats();

        $resetStats = $this->invalidationService->getStats();
        $this->assertEquals(0, $resetStats['invalidations']);
        $this->assertEquals(0, $resetStats['cascade_invalidations']);
        $this->assertEquals(0, $resetStats['dependency_invalidations']);
    }

    #[Test]
    public function it_has_predefined_dependencies(): void
    {
        $dependencies = $this->invalidationService->getDependencies();

        $this->assertIsArray($dependencies);
        $this->assertArrayHasKey('configuration', $dependencies);
        $this->assertArrayHasKey('spam_patterns', $dependencies);
        $this->assertArrayHasKey('ip_reputation', $dependencies);
    }

    #[Test]
    public function it_handles_string_keys_by_converting_to_cache_key(): void
    {
        $stringKey = 'test_string_key';

        $this->cacheManager->expects($this->once())
            ->method('forget')
            ->with($this->callback(function ($key) use ($stringKey) {
                return $key instanceof CacheKey && $key->key === $stringKey;
            }), null)
            ->willReturn(true);

        $this->eventDispatcher->method('dispatch');

        $result = $this->invalidationService->invalidate($stringKey);

        $this->assertTrue($result);
    }

    // Advanced Cache Invalidation Tests
    #[Test]
    public function it_handles_cascade_invalidation_with_dependencies(): void
    {
        $sourceKey = 'source_key';
        $dependentKey1 = 'dependent_key_1';
        $dependentKey2 = 'dependent_key_2';

        // Set up dependencies using namespace (CacheKey::make() uses 'default' namespace)
        $this->invalidationService->addDependency('default', $dependentKey1);
        $this->invalidationService->addDependency('default', $dependentKey2);

        // Expect invalidation of source key (forget) plus cascade invalidations (invalidateByPattern)
        $this->cacheManager->expects($this->once())
            ->method('forget')
            ->willReturn(true);

        $this->cacheManager->expects($this->exactly(2))
            ->method('invalidateByPattern')
            ->willReturn(true);

        $this->eventDispatcher->method('dispatch');

        $result = $this->invalidationService->invalidate($sourceKey);
        $this->assertTrue($result);

        // Verify cascade invalidation stats
        $stats = $this->invalidationService->getStats();
        $this->assertGreaterThan(0, $stats['cascade_invalidations']);
    }

    #[Test]
    public function it_handles_dependency_tracking_correctly(): void
    {
        $source1 = 'source_1';
        $source2 = 'source_2';
        $dependent1 = 'dependent_1';
        $dependent2 = 'dependent_2';

        // Create complex dependency graph
        $this->invalidationService->addDependency($source1, $dependent1);
        $this->invalidationService->addDependency($source1, $dependent2);
        $this->invalidationService->addDependency($source2, $dependent1);

        $dependencies = $this->invalidationService->getDependencies();

        // Verify dependency structure
        $this->assertArrayHasKey($source1, $dependencies);
        $this->assertArrayHasKey($source2, $dependencies);
        $this->assertContains($dependent1, $dependencies[$source1]);
        $this->assertContains($dependent2, $dependencies[$source1]);
        $this->assertContains($dependent1, $dependencies[$source2]);
        $this->assertNotContains($dependent2, $dependencies[$source2]);
    }

    #[Test]
    public function it_handles_circular_dependency_prevention(): void
    {
        $key1 = 'circular_key_1';
        $key2 = 'circular_key_2';

        // Create potential circular dependency
        $this->invalidationService->addDependency($key1, $key2);
        $this->invalidationService->addDependency($key2, $key1);

        // Should not cause infinite loop during invalidation
        $this->cacheManager->method('forget')->willReturn(true);
        $this->eventDispatcher->method('dispatch');

        $result = $this->invalidationService->invalidate($key1);
        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_invalidation_failures_gracefully(): void
    {
        $key = CacheKey::make('failing_key');

        // Simulate cache manager failure
        $this->cacheManager->expects($this->once())
            ->method('forget')
            ->with($key, null)
            ->willReturn(false);

        // Event should not be dispatched on failure
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $this->invalidationService->invalidate($key);
        $this->assertFalse($result);

        // Stats should not be updated on failure
        $stats = $this->invalidationService->getStats();
        $this->assertEquals(0, $stats['invalidations']);
    }

    #[Test]
    public function it_supports_selective_level_invalidation(): void
    {
        $key = CacheKey::make('selective_key');
        $levels = ['memory', 'database']; // Skip request level

        $this->cacheManager->expects($this->once())
            ->method('forget')
            ->with($key, $levels)
            ->willReturn(true);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($key, $levels) {
                return $event instanceof CacheInvalidated
                    && $event->key->toString() === $key->toString()
                    && $event->levels === $levels;
            }));

        $result = $this->invalidationService->invalidate($key, $levels);
        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_tag_based_invalidation_with_multiple_tags(): void
    {
        $tags = ['user', 'profile', 'settings'];
        $levels = null; // All levels

        $this->cacheManager->expects($this->once())
            ->method('invalidateByTags')
            ->with($tags, $levels)
            ->willReturn(true);

        $result = $this->invalidationService->invalidateByTags($tags, $levels);
        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_namespace_dependency_invalidation(): void
    {
        $namespace = 'user_data';
        $dependentNamespace = 'user_cache';

        // Set up namespace dependency
        $this->invalidationService->addDependency($namespace, $dependentNamespace);

        // Expect pattern-based invalidation for both namespaces
        $this->cacheManager->expects($this->exactly(2))
            ->method('invalidateByPattern')
            ->willReturn(true);

        $result = $this->invalidationService->invalidateByNamespace($namespace);
        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_model_invalidation_with_relationships(): void
    {
        $modelClass = 'App\\Models\\User';
        $modelId = 123;

        // Should invalidate specific model and collection caches
        $this->cacheManager->expects($this->exactly(2))
            ->method('forget')
            ->willReturnOnConsecutiveCalls(true, true);

        $result = $this->invalidationService->invalidateForModel($modelClass, $modelId);
        $this->assertTrue($result);
    }

    #[Test]
    public function it_tracks_different_types_of_invalidations(): void
    {
        $key = CacheKey::make('stats_test_key');
        $tags = ['test_tag'];
        $namespace = 'test_namespace';

        $this->cacheManager->method('forget')->willReturn(true);
        $this->cacheManager->method('invalidateByTags')->willReturn(true);
        $this->cacheManager->method('invalidateByPattern')->willReturn(true);
        $this->eventDispatcher->method('dispatch');

        // Perform different types of invalidations
        $this->invalidationService->invalidate($key);
        $this->invalidationService->invalidateByTags($tags);
        $this->invalidationService->invalidateByNamespace($namespace);

        $stats = $this->invalidationService->getStats();

        $this->assertGreaterThan(0, $stats['invalidations']);
        $this->assertArrayHasKey('cascade_invalidations', $stats);
        $this->assertArrayHasKey('dependency_invalidations', $stats);
    }

    #[Test]
    public function it_handles_bulk_dependency_operations(): void
    {
        $source = 'bulk_source';
        $dependents = ['dep1', 'dep2', 'dep3', 'dep4', 'dep5'];

        // Add multiple dependencies
        foreach ($dependents as $dependent) {
            $this->invalidationService->addDependency($source, $dependent);
        }

        $dependencies = $this->invalidationService->getDependencies();
        $this->assertCount(count($dependents), $dependencies[$source]);

        // Remove some dependencies
        $this->invalidationService->removeDependency($source, 'dep2');
        $this->invalidationService->removeDependency($source, 'dep4');

        $updatedDependencies = $this->invalidationService->getDependencies();
        $this->assertCount(3, $updatedDependencies[$source]);
        $this->assertNotContains('dep2', $updatedDependencies[$source]);
        $this->assertNotContains('dep4', $updatedDependencies[$source]);
    }

    #[Test]
    public function it_handles_event_dispatching_with_correct_payload(): void
    {
        $key = CacheKey::make('event_test_key', 'test_namespace', ['tag1', 'tag2']);
        $levels = ['memory', 'database'];

        $this->cacheManager->method('forget')->willReturn(true);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($key, $levels) {
                if (! $event instanceof CacheInvalidated) {
                    return false;
                }

                return $event->key->toString() === $key->toString()
                    && $event->levels === $levels
                    && $event->key->namespace === 'test_namespace'
                    && $event->key->tags === ['tag1', 'tag2'];
            }));

        $result = $this->invalidationService->invalidate($key, $levels);
        $this->assertTrue($result);
    }
}
