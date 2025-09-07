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
        Schema::create('spam_scores', function (Blueprint $table) {
            $table->id();

            // Core submission identification
            $table->string('submission_hash', 64)->unique()->index(); // SHA256 hash for privacy
            $table->foreignId('submission_id')
                ->nullable()
                ->constrained('blocked_submissions')
                ->onDelete('set null')
                ->index();

            // Scoring information
            $table->integer('total_score')->default(0)->index(); // Final calculated spam score
            $table->json('component_scores'); // Breakdown by detection method/pattern
            $table->integer('threshold_used')->default(50)->index(); // Threshold that was applied
            $table->enum('final_action', [
                'allow',
                'flag',
                'block',
                'captcha',
                'delay',
                'honeypot',
            ])->default('allow')->index();

            // Detection metadata
            $table->json('detection_methods')->nullable(); // Which detection methods were used
            $table->json('pattern_matches')->nullable(); // Summary of pattern matches
            $table->json('risk_factors')->nullable(); // Risk factors that contributed to score
            $table->text('detection_reason')->nullable(); // Human-readable reason for the action

            // Performance and processing metrics
            $table->integer('processing_time_ms')->default(0); // Total processing time
            $table->integer('patterns_checked')->default(0); // Number of patterns evaluated
            $table->integer('patterns_matched')->default(0); // Number of patterns that matched
            $table->decimal('confidence_level', 5, 4)->default(1.0000); // Overall confidence in the score

            // Form and context information
            $table->string('form_identifier', 255)->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('user_agent_hash', 64)->nullable()->index(); // Hashed for privacy
            $table->json('form_field_analysis')->nullable(); // Analysis of individual form fields

            // Geographic and network context
            $table->string('country_code', 2)->nullable()->index();
            $table->json('geolocation_factors')->nullable(); // Geolocation risk factors
            $table->json('ip_reputation_factors')->nullable(); // IP reputation analysis

            // Machine learning and AI analysis
            $table->json('ai_analysis')->nullable(); // AI/ML analysis results if available
            $table->decimal('ml_confidence', 5, 4)->nullable(); // ML model confidence score
            $table->string('model_version', 50)->nullable(); // Version of ML model used

            // Analytics and learning
            $table->boolean('is_training_data')->default(false)->index(); // Used for ML training
            $table->boolean('is_verified')->default(false)->index(); // Human verification status
            $table->enum('verification_result', [
                'correct_positive',
                'false_positive',
                'correct_negative',
                'false_negative',
                'unknown',
            ])->nullable()->index();
            $table->text('verification_notes')->nullable();

            // Timestamps and tracking
            $table->timestamp('detected_at')->index(); // When the detection occurred
            $table->timestamp('verified_at')->nullable(); // When human verification occurred
            $table->string('verified_by', 255)->nullable(); // Who verified the result
            $table->timestamps();

            // Performance indexes for high-volume queries
            $table->index(['total_score', 'detected_at'], 'idx_spam_scores_score_time');
            $table->index(['final_action', 'detected_at'], 'idx_spam_scores_action_time');
            $table->index(['threshold_used', 'total_score', 'detected_at'], 'idx_spam_scores_threshold_score_time');
            $table->index(['form_identifier', 'detected_at'], 'idx_spam_scores_form_time');

            // Analytics indexes
            $table->index(['detected_at', 'total_score', 'final_action'], 'idx_spam_scores_analytics_time_score_action');
            $table->index(['country_code', 'detected_at', 'total_score'], 'idx_spam_scores_country_time_score');
            $table->index(['is_verified', 'verification_result', 'detected_at'], 'idx_spam_scores_verification_time');

            // Performance covering indexes
            $table->index(['detected_at', 'total_score', 'confidence_level', 'processing_time_ms'], 'idx_spam_scores_performance_covering');
            $table->index(['form_identifier', 'detected_at', 'final_action', 'total_score'], 'idx_spam_scores_form_analytics_covering');

            // Machine learning indexes
            $table->index(['is_training_data', 'verification_result', 'detected_at'], 'idx_spam_scores_ml_training_covering');
            $table->index(['ml_confidence', 'model_version', 'detected_at'], 'idx_spam_scores_ml_performance_covering');

            // Research and analysis indexes
            $table->index(['patterns_checked', 'patterns_matched', 'processing_time_ms'], 'idx_spam_scores_pattern_efficiency');
            $table->index(['total_score', 'confidence_level', 'verification_result'], 'idx_spam_scores_accuracy_analysis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spam_scores');
    }
};
