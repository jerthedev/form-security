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
        Schema::create('pattern_matches', function (Blueprint $table) {
            $table->id();

            // Core relationship tracking
            $table->foreignId('submission_id')
                ->constrained('blocked_submissions')
                ->onDelete('cascade')
                ->index();
            $table->foreignId('pattern_id')
                ->constrained('spam_patterns')
                ->onDelete('cascade')
                ->index();

            // Match scoring and analysis
            $table->integer('match_score')->default(0)->index(); // 0-100 match confidence
            $table->decimal('confidence_level', 5, 4)->default(1.0000); // Statistical confidence
            $table->json('match_context')->nullable(); // Context about what matched
            $table->text('matched_content')->nullable(); // The actual content that matched
            $table->json('match_positions')->nullable(); // Position information for highlighted matches

            // Processing and performance metrics
            $table->integer('processing_time_ms')->default(0); // Time taken to match this pattern
            $table->enum('match_type', [
                'exact',
                'partial',
                'fuzzy',
                'regex',
                'keyword',
                'behavioral',
                'threshold',
            ])->default('exact')->index();

            // Analytics and debugging
            $table->json('debug_info')->nullable(); // Debug information for troubleshooting
            $table->boolean('is_false_positive')->default(false)->index(); // Manual flag for false positives
            $table->text('false_positive_reason')->nullable(); // Reason for false positive flag

            // Timestamps
            $table->timestamp('matched_at')->index(); // When the match occurred
            $table->timestamps();

            // Performance indexes for common queries
            $table->index(['submission_id', 'match_score', 'matched_at'], 'idx_pattern_matches_submission_score_time');
            $table->index(['pattern_id', 'match_score', 'matched_at'], 'idx_pattern_matches_pattern_score_time');
            $table->index(['match_type', 'match_score', 'matched_at'], 'idx_pattern_matches_type_score_time');
            $table->index(['is_false_positive', 'matched_at'], 'idx_pattern_matches_false_positive_time');

            // Composite indexes for analytics
            $table->index(['matched_at', 'match_score', 'confidence_level'], 'idx_pattern_matches_time_score_confidence');
            $table->index(['submission_id', 'pattern_id', 'matched_at'], 'idx_pattern_matches_submission_pattern_time');

            // Performance covering indexes
            $table->index(['match_score', 'match_type', 'matched_at', 'confidence_level'], 'idx_pattern_matches_performance_covering');
            $table->index(['pattern_id', 'matched_at', 'match_score', 'processing_time_ms'], 'idx_pattern_matches_pattern_performance_covering');

            // Analytics covering index for reporting
            $table->index(['matched_at', 'is_false_positive', 'match_score', 'match_type'], 'idx_pattern_matches_analytics_covering');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pattern_matches');
    }
};
