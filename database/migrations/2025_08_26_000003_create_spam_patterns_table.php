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
        Schema::create('spam_patterns', function (Blueprint $table) {
            $table->id();

            // Pattern identification
            $table->string('name', 255)->index();
            $table->text('description')->nullable();
            $table->enum('pattern_type', [
                'regex',
                'keyword',
                'phrase',
                'email_pattern',
                'url_pattern',
                'behavioral',
                'content_length',
                'submission_rate',
            ])->index();

            // Pattern definition
            $table->text('pattern'); // The actual pattern (regex, keywords, etc.)
            $table->json('pattern_config')->nullable(); // Additional pattern configuration
            $table->boolean('case_sensitive')->default(false);
            $table->boolean('whole_word_only')->default(false);

            // Targeting and scope
            $table->json('target_fields')->nullable(); // Which form fields to check
            $table->json('target_forms')->nullable(); // Which forms this applies to
            $table->enum('scope', [
                'global',
                'form_specific',
                'field_specific',
                'conditional',
            ])->default('global')->index();

            // Scoring and actions
            $table->integer('risk_score')->default(10)->index(); // 1-100 risk contribution
            $table->enum('action', [
                'block',
                'flag',
                'score_only',
                'honeypot',
                'delay',
                'captcha',
            ])->default('flag')->index();
            $table->json('action_config')->nullable(); // Action-specific configuration

            // Performance and matching
            $table->integer('match_count')->default(0)->index(); // How many times matched
            $table->integer('false_positive_count')->default(0);
            $table->decimal('accuracy_rate', 5, 4)->default(1.0000)->index(); // Success rate
            $table->integer('processing_time_ms')->default(0); // Average processing time

            // Status and management
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_learning')->default(false); // Machine learning pattern
            $table->integer('priority')->default(100)->index(); // Processing priority (lower = higher priority)
            $table->timestamp('last_matched')->nullable()->index();

            // Categorization
            $table->json('categories')->nullable(); // Spam categories (phishing, malware, etc.)
            $table->json('languages')->nullable(); // Target languages
            $table->json('regions')->nullable(); // Geographic targeting

            // Version control and updates
            $table->string('version', 20)->default('1.0');
            $table->string('source', 100)->default('manual'); // manual, imported, learned
            $table->timestamp('last_updated_at')->nullable();
            $table->string('updated_by', 255)->nullable();

            // Metadata
            $table->json('metadata')->nullable(); // Additional flexible data
            $table->timestamps();

            // Performance indexes
            $table->index(['is_active', 'priority']);
            $table->index(['pattern_type', 'is_active']);
            $table->index(['risk_score', 'is_active']);
            $table->index(['action', 'is_active']);
            $table->index(['scope', 'is_active']);

            // Analytics indexes
            $table->index(['match_count', 'updated_at']);
            $table->index(['accuracy_rate', 'match_count']);
            $table->index(['last_matched', 'is_active']);

            // Composite indexes for pattern matching
            $table->index(['pattern_type', 'scope', 'is_active']);
            $table->index(['priority', 'is_active', 'pattern_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spam_patterns');
    }
};
