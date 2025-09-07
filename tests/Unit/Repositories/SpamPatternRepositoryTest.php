<?php

/**
 * Test File: SpamPatternRepositoryTest.php
 *
 * EPIC: EPIC-002-core-spam-detection-engine
 * SPEC: SPEC-004-pattern-based-spam-detection
 * SPRINT: Sprint-007-Epic-002-Foundation-Setup
 * TICKET: 2011-spam-pattern-model-repository
 *
 * Description: Comprehensive unit tests for the SpamPatternRepository including
 * CRUD operations, caching behavior, pattern management, and performance optimization.
 *
 * @see docs/Planning/Epics/EPIC-002-core-spam-detection-engine.md
 * @see docs/Planning/Specs/Core-Detection-Engine/SPEC-004-pattern-based-spam-detection.md
 * @see docs/Planning/Tickets/Core-Spam-Detection-Engine/Implementation/2011-spam-pattern-model-repository.md
 */

namespace JTD\FormSecurity\Tests\Unit\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use JTD\FormSecurity\Contracts\CacheManagerInterface;
use JTD\FormSecurity\Enums\PatternAction;
use JTD\FormSecurity\Enums\PatternType;
use JTD\FormSecurity\Models\SpamPattern;
use JTD\FormSecurity\Repositories\SpamPatternRepository;
use JTD\FormSecurity\Tests\TestCase;
use JTD\FormSecurity\ValueObjects\CacheKey;
use JTD\FormSecurity\ValueObjects\PatternEffectiveness;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('sprint-007')]
#[Group('epic-002')]
#[Group('repositories')]
#[Group('spam-detection')]
#[Group('ticket-2011')]
class SpamPatternRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private SpamPatternRepository $repository;

    private CacheManagerInterface $mockCacheManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockCacheManager = Mockery::mock(CacheManagerInterface::class);
        $this->repository = new SpamPatternRepository($this->mockCacheManager);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // Basic CRUD Operations Tests

    #[Test]
    public function find_returns_pattern_from_cache(): void
    {
        $pattern = SpamPattern::factory()->create();

        $this->mockCacheManager
            ->shouldReceive('remember')
            ->once()
            ->with(
                Mockery::on(fn ($key) => $key instanceof CacheKey && $key->key === "spam_pattern:{$pattern->id}"),
                Mockery::type('closure'),
                3600
            )
            ->andReturn($pattern);

        $result = $this->repository->find($pattern->id);

        $this->assertInstanceOf(SpamPattern::class, $result);
        $this->assertEquals($pattern->id, $result->id);
    }

    #[Test]
    public function find_returns_null_for_nonexistent_pattern(): void
    {
        $this->mockCacheManager
            ->shouldReceive('remember')
            ->once()
            ->andReturn(null);

        $result = $this->repository->find(999);

        $this->assertNull($result);
    }

    #[Test]
    public function find_by_name_returns_correct_pattern(): void
    {
        $pattern = SpamPattern::factory()->create(['name' => 'Test Pattern']);

        $this->mockCacheManager
            ->shouldReceive('remember')
            ->once()
            ->with(
                Mockery::on(fn ($key) => $key instanceof CacheKey && $key->key === 'spam_pattern_name:Test Pattern'),
                Mockery::type('closure'),
                3600
            )
            ->andReturnUsing(function ($key, $callback, $ttl) {
                return $callback();
            });

        $result = $this->repository->findByName('Test Pattern');

        $this->assertInstanceOf(SpamPattern::class, $result);
        $this->assertEquals('Test Pattern', $result->name);
    }

    #[Test]
    public function get_active_patterns_returns_cached_collection(): void
    {
        $patterns = SpamPattern::factory()->count(3)->create(['is_active' => true]);
        SpamPattern::factory()->create(['is_active' => false]); // Inactive pattern

        $this->mockCacheManager
            ->shouldReceive('remember')
            ->once()
            ->with(
                Mockery::on(fn ($key) => $key instanceof CacheKey && $key->key === 'active_patterns'),
                Mockery::type('closure'),
                3600
            )
            ->andReturnUsing(function ($key, $callback, $ttl) {
                return $callback();
            });

        $result = $this->repository->getActivePatterns();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
        $this->assertTrue($result->every(fn ($pattern) => $pattern->is_active));
    }

    #[Test]
    public function create_invalidates_cache_and_logs_creation(): void
    {
        Log::spy();

        $this->mockCacheManager
            ->shouldReceive('flush')
            ->once()
            ->with(['spam_patterns']);

        $patternData = [
            'name' => 'New Pattern',
            'pattern_type' => PatternType::KEYWORD->value,
            'pattern' => 'test',
            'action' => PatternAction::FLAG->value,
        ];

        $result = $this->repository->create($patternData);

        $this->assertInstanceOf(SpamPattern::class, $result);
        $this->assertEquals('New Pattern', $result->name);
        $this->assertDatabaseHas('spam_patterns', ['name' => 'New Pattern']);

        Log::shouldHaveReceived('info')
            ->atLeast()->times(1)
            ->withAnyArgs();
    }

    #[Test]
    public function update_invalidates_cache_and_logs_update(): void
    {
        Log::spy();

        $pattern = SpamPattern::factory()->create(['name' => 'Original Name']);

        $this->mockCacheManager
            ->shouldReceive('forget')
            ->once()
            ->with(Mockery::on(fn ($key) => $key instanceof CacheKey && $key->key === "spam_pattern:{$pattern->id}"));

        $result = $this->repository->update($pattern->id, ['name' => 'Updated Name']);

        $this->assertTrue($result);
        $this->assertDatabaseHas('spam_patterns', [
            'id' => $pattern->id,
            'name' => 'Updated Name',
        ]);

        Log::shouldHaveReceived('info')
            ->atLeast()->times(1)
            ->withAnyArgs();
    }

    #[Test]
    public function update_returns_false_for_nonexistent_pattern(): void
    {
        $result = $this->repository->update(999, ['name' => 'Updated Name']);

        $this->assertFalse($result);
    }

    #[Test]
    public function delete_invalidates_cache_and_logs_deletion(): void
    {
        Log::spy();

        $pattern = SpamPattern::factory()->create();

        $this->mockCacheManager
            ->shouldReceive('forget')
            ->once()
            ->with(Mockery::on(fn ($key) => $key instanceof CacheKey && $key->key === "spam_pattern:{$pattern->id}"));

        $result = $this->repository->delete($pattern->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('spam_patterns', ['id' => $pattern->id]);

        Log::shouldHaveReceived('info')
            ->atLeast()->times(1)
            ->withAnyArgs();
    }

    // Pattern Management Tests

    #[Test]
    public function get_high_priority_patterns_filters_correctly(): void
    {
        SpamPattern::factory()->create(['priority' => 9, 'is_active' => true]);
        SpamPattern::factory()->create(['priority' => 6, 'is_active' => true]);
        SpamPattern::factory()->create(['priority' => 8, 'is_active' => true]);

        $this->mockCacheManager
            ->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $callback, $ttl) {
                return $callback();
            });

        $result = $this->repository->getHighPriorityPatterns(8);

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($pattern) => $pattern->priority >= 8));
    }

    #[Test]
    public function get_high_accuracy_patterns_filters_correctly(): void
    {
        SpamPattern::factory()->create(['accuracy_rate' => 0.95, 'is_active' => true]);
        SpamPattern::factory()->create(['accuracy_rate' => 0.85, 'is_active' => true]);
        SpamPattern::factory()->create(['accuracy_rate' => 0.92, 'is_active' => true]);

        $this->mockCacheManager
            ->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $callback, $ttl) {
                return $callback();
            });

        $result = $this->repository->getHighAccuracyPatterns(0.9);

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($pattern) => $pattern->accuracy_rate >= 0.9));
    }

    #[Test]
    public function get_patterns_by_type_returns_correct_patterns(): void
    {
        SpamPattern::factory()->create(['pattern_type' => PatternType::REGEX, 'is_active' => true]);
        SpamPattern::factory()->create(['pattern_type' => PatternType::KEYWORD, 'is_active' => true]);
        SpamPattern::factory()->create(['pattern_type' => PatternType::REGEX, 'is_active' => true]);

        $this->mockCacheManager
            ->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $callback, $ttl) {
                return $callback();
            });

        $result = $this->repository->getPatternsByType(PatternType::REGEX);

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($pattern) => $pattern->pattern_type === PatternType::REGEX));
    }

    #[Test]
    public function get_patterns_for_field_returns_correct_patterns(): void
    {
        SpamPattern::factory()->create([
            'target_fields' => ['email', 'message'],
            'is_active' => true,
        ]);
        SpamPattern::factory()->create([
            'target_fields' => ['subject'],
            'is_active' => true,
        ]);
        SpamPattern::factory()->create([
            'target_fields' => ['message', 'body'],
            'is_active' => true,
        ]);

        $this->mockCacheManager
            ->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $callback, $ttl) {
                return $callback();
            });

        $result = $this->repository->getPatternsForField('message');

        $this->assertCount(2, $result);
    }

    // Performance and Analytics Tests

    #[Test]
    public function get_pattern_effectiveness_returns_value_object(): void
    {
        $pattern = SpamPattern::factory()->create([
            'match_count' => 100,
            'false_positive_count' => 5,
            'accuracy_rate' => 0.95,
            'processing_time_ms' => 10,
            'priority' => 8,
        ]);

        $this->mockCacheManager
            ->shouldReceive('remember')
            ->once()
            ->andReturn($pattern);

        $result = $this->repository->getPatternEffectiveness($pattern->id);

        $this->assertInstanceOf(PatternEffectiveness::class, $result);
        $this->assertEquals(100, $result->totalMatches);
        $this->assertEquals(5, $result->falsePositives);
        $this->assertEquals(0.95, $result->accuracyRate);
    }

    #[Test]
    public function get_patterns_needing_optimization_returns_poor_performers(): void
    {
        // Good pattern
        SpamPattern::factory()->create([
            'accuracy_rate' => 0.9,
            'processing_time_ms' => 10,
            'match_count' => 50,
            'false_positive_count' => 2,
            'is_active' => true,
        ]);

        // Poor accuracy pattern
        SpamPattern::factory()->create([
            'accuracy_rate' => 0.6,
            'processing_time_ms' => 10,
            'match_count' => 50,
            'false_positive_count' => 20,
            'is_active' => true,
        ]);

        // Slow pattern
        SpamPattern::factory()->create([
            'accuracy_rate' => 0.9,
            'processing_time_ms' => 50,
            'match_count' => 50,
            'false_positive_count' => 2,
            'is_active' => true,
        ]);

        $result = $this->repository->getPatternsNeedingOptimization();

        $this->assertCount(2, $result);
    }

    #[Test]
    public function update_pattern_statistics_records_match_correctly(): void
    {
        $pattern = SpamPattern::factory()->create([
            'match_count' => 10,
            'false_positive_count' => 1,
        ]);

        $this->mockCacheManager
            ->shouldReceive('forget')
            ->once()
            ->with(Mockery::on(fn ($key) => $key instanceof CacheKey && $key->key === "spam_pattern:{$pattern->id}"));

        $result = $this->repository->updatePatternStatistics($pattern->id, false, 15);

        $this->assertTrue($result);

        $updatedPattern = SpamPattern::find($pattern->id);
        $this->assertEquals(11, $updatedPattern->match_count);
        $this->assertEquals(1, $updatedPattern->false_positive_count);
        $this->assertNotNull($updatedPattern->last_matched);
    }

    #[Test]
    public function reset_pattern_statistics_clears_data(): void
    {
        $pattern = SpamPattern::factory()->create([
            'match_count' => 100,
            'false_positive_count' => 10,
            'accuracy_rate' => 0.9,
            'processing_time_ms' => 15,
            'last_matched' => now(),
        ]);

        $this->mockCacheManager
            ->shouldReceive('forget')
            ->once();

        $result = $this->repository->resetPatternStatistics($pattern->id);

        $this->assertTrue($result);

        $resetPattern = SpamPattern::find($pattern->id);
        $this->assertEquals(0, $resetPattern->match_count);
        $this->assertEquals(0, $resetPattern->false_positive_count);
        $this->assertEquals(1.0, (float) $resetPattern->accuracy_rate);
        $this->assertNull($resetPattern->last_matched);
    }

    // Bulk Operations Tests

    #[Test]
    public function activate_patterns_updates_multiple_patterns(): void
    {
        Log::spy();

        $patterns = SpamPattern::factory()->count(3)->create(['is_active' => false]);
        $patternIds = $patterns->pluck('id')->toArray();

        $this->mockCacheManager
            ->shouldReceive('flush')
            ->once()
            ->with(['spam_patterns']);

        $result = $this->repository->activatePatterns($patternIds);

        $this->assertEquals(3, $result);

        foreach ($patterns as $pattern) {
            $this->assertDatabaseHas('spam_patterns', [
                'id' => $pattern->id,
                'is_active' => true,
            ]);
        }

        Log::shouldHaveReceived('info')
            ->atLeast()->times(1)
            ->withAnyArgs();
    }

    #[Test]
    public function deactivate_patterns_updates_multiple_patterns(): void
    {
        Log::spy();

        $patterns = SpamPattern::factory()->count(2)->create(['is_active' => true]);
        $patternIds = $patterns->pluck('id')->toArray();

        $this->mockCacheManager
            ->shouldReceive('flush')
            ->once()
            ->with(['spam_patterns']);

        $result = $this->repository->deactivatePatterns($patternIds);

        $this->assertEquals(2, $result);

        foreach ($patterns as $pattern) {
            $this->assertDatabaseHas('spam_patterns', [
                'id' => $pattern->id,
                'is_active' => false,
            ]);
        }

        Log::shouldHaveReceived('info')
            ->atLeast()->times(1)
            ->withAnyArgs();
    }

    #[Test]
    public function update_pattern_priorities_updates_multiple_patterns(): void
    {
        $pattern1 = SpamPattern::factory()->create(['priority' => 5]);
        $pattern2 = SpamPattern::factory()->create(['priority' => 3]);

        $priorityMap = [
            $pattern1->id => 8,
            $pattern2->id => 7,
        ];

        $this->mockCacheManager
            ->shouldReceive('flush')
            ->once();

        $result = $this->repository->updatePatternPriorities($priorityMap);

        $this->assertEquals(2, $result);

        $this->assertDatabaseHas('spam_patterns', [
            'id' => $pattern1->id,
            'priority' => 8,
        ]);
        $this->assertDatabaseHas('spam_patterns', [
            'id' => $pattern2->id,
            'priority' => 7,
        ]);
    }

    // Import/Export Tests

    #[Test]
    public function import_patterns_creates_new_patterns(): void
    {
        Log::spy();

        $this->mockCacheManager
            ->shouldReceive('flush')
            ->once();

        $patterns = [
            [
                'name' => 'Imported Pattern 1',
                'pattern_type' => PatternType::KEYWORD->value,
                'pattern' => 'spam',
                'action' => PatternAction::BLOCK->value,
            ],
            [
                'name' => 'Imported Pattern 2',
                'pattern_type' => PatternType::REGEX->value,
                'pattern' => '/casino/',
                'action' => PatternAction::FLAG->value,
            ],
        ];

        $result = $this->repository->importPatterns($patterns);

        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEmpty($result['errors']);

        $this->assertDatabaseHas('spam_patterns', ['name' => 'Imported Pattern 1']);
        $this->assertDatabaseHas('spam_patterns', ['name' => 'Imported Pattern 2']);

        Log::shouldHaveReceived('info')
            ->atLeast()->times(1) // Allow multiple log calls
            ->withAnyArgs();
    }

    #[Test]
    public function import_patterns_updates_existing_patterns(): void
    {
        $existingPattern = SpamPattern::factory()->create([
            'name' => 'Existing Pattern',
            'pattern' => 'old pattern',
        ]);

        $this->mockCacheManager
            ->shouldReceive('flush')
            ->once();

        $patterns = [
            [
                'name' => 'Existing Pattern',
                'pattern' => 'updated pattern',
            ],
        ];

        $result = $this->repository->importPatterns($patterns);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['updated']);

        $this->assertDatabaseHas('spam_patterns', [
            'id' => $existingPattern->id,
            'name' => 'Existing Pattern',
            'pattern' => 'updated pattern',
        ]);
    }

    #[Test]
    public function export_patterns_returns_all_patterns_when_no_ids(): void
    {
        SpamPattern::factory()->count(3)->create();

        $result = $this->repository->exportPatterns();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    #[Test]
    public function export_patterns_returns_specific_patterns_when_ids_provided(): void
    {
        $pattern1 = SpamPattern::factory()->create();
        $pattern2 = SpamPattern::factory()->create();
        $pattern3 = SpamPattern::factory()->create();

        $result = $this->repository->exportPatterns([$pattern1->id, $pattern3->id]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    // Cache Management Tests

    #[Test]
    public function warm_pattern_cache_preloads_frequently_accessed_data(): void
    {
        Log::spy();

        $this->mockCacheManager
            ->shouldReceive('remember')
            ->times(4) // active, high priority, high accuracy, fast processing
            ->andReturn(new Collection);

        $result = $this->repository->warmPatternCache();

        $this->assertTrue($result);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Pattern cache warmed successfully');
    }

    #[Test]
    public function invalidate_pattern_cache_clears_specific_pattern(): void
    {
        $pattern = SpamPattern::factory()->create();

        $this->mockCacheManager
            ->shouldReceive('forget')
            ->once()
            ->with(Mockery::on(fn ($key) => $key instanceof CacheKey && $key->key === "spam_pattern:{$pattern->id}"));

        $result = $this->repository->invalidatePatternCache($pattern->id);

        $this->assertTrue($result);
    }

    #[Test]
    public function invalidate_pattern_cache_clears_all_when_no_id(): void
    {
        $this->mockCacheManager
            ->shouldReceive('flush')
            ->once()
            ->with(['spam_patterns']);

        $result = $this->repository->invalidatePatternCache();

        $this->assertTrue($result);
    }

    // Validation Tests

    #[Test]
    public function validate_pattern_syntax_validates_regex(): void
    {
        $validResult = $this->repository->validatePatternSyntax('/valid.*regex/', PatternType::REGEX);
        $invalidResult = $this->repository->validatePatternSyntax('/invalid[regex/', PatternType::REGEX);

        $this->assertTrue($validResult['valid']);
        $this->assertEmpty($validResult['errors']);

        $this->assertFalse($invalidResult['valid']);
        $this->assertNotEmpty($invalidResult['errors']);
    }

    #[Test]
    public function validate_pattern_syntax_validates_keyword(): void
    {
        $validResult = $this->repository->validatePatternSyntax('spam', PatternType::KEYWORD);
        $invalidResult = $this->repository->validatePatternSyntax('   ', PatternType::KEYWORD);

        $this->assertTrue($validResult['valid']);
        $this->assertFalse($invalidResult['valid']);
        $this->assertContains('Keyword cannot be empty', $invalidResult['errors']);
    }

    #[Test]
    public function test_pattern_returns_result_array(): void
    {
        $pattern = SpamPattern::factory()->create([
            'pattern_type' => PatternType::KEYWORD,
            'pattern' => 'spam',
        ]);

        $this->mockCacheManager
            ->shouldReceive('remember')
            ->once()
            ->andReturn($pattern);

        $result = $this->repository->testPattern($pattern->id, 'This content contains spam');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('matches', $result);
        $this->assertArrayHasKey('processing_time_ms', $result);
        $this->assertTrue($result['matches']);
    }

    #[Test]
    public function test_pattern_returns_error_for_nonexistent_pattern(): void
    {
        $this->mockCacheManager
            ->shouldReceive('remember')
            ->once()
            ->andReturn(null);

        $result = $this->repository->testPattern(999, 'test content');

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Pattern not found', $result['error']);
    }

    // Analytics Tests

    #[Test]
    public function get_top_performing_patterns_returns_best_patterns(): void
    {
        SpamPattern::factory()->create([
            'accuracy_rate' => 0.95,
            'is_active' => true,
        ]);
        SpamPattern::factory()->create([
            'accuracy_rate' => 0.85,
            'is_active' => true,
        ]);
        SpamPattern::factory()->create([
            'accuracy_rate' => 0.92,
            'is_active' => true,
        ]);

        $result = $this->repository->getTopPerformingPatterns(2);

        $this->assertCount(2, $result);
        $this->assertEquals(0.95, (float) $result->first()->accuracy_rate);
    }

    #[Test]
    public function generate_pattern_analytics_report_returns_comprehensive_data(): void
    {
        SpamPattern::factory()->count(5)->create(['is_active' => true]);
        SpamPattern::factory()->count(2)->create(['is_active' => false]);

        $result = $this->repository->generatePatternAnalyticsReport();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('performance', $result);
        $this->assertArrayHasKey('generated_at', $result);

        $this->assertEquals(7, $result['summary']['total_patterns']);
        $this->assertEquals(5, $result['summary']['active_patterns']);
    }
}
