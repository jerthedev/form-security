<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpamPatternsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $patterns = [
            // Common spam keywords
            [
                'name' => 'Generic Spam Keywords',
                'description' => 'Common spam keywords and phrases',
                'pattern_type' => 'keyword',
                'pattern' => 'viagra|cialis|casino|lottery|winner|congratulations|urgent|act now|limited time|free money|make money fast|work from home|guaranteed|no risk|click here|buy now|order now',
                'pattern_config' => json_encode(['min_matches' => 1]),
                'case_sensitive' => false,
                'whole_word_only' => true,
                'target_fields' => json_encode(['message', 'comment', 'description', 'content']),
                'scope' => 'global',
                'risk_score' => 75,
                'action' => 'block',
                'is_active' => true,
                'priority' => 10,
                'categories' => json_encode(['commercial_spam', 'pharmaceutical']),
                'source' => 'manual',
                'version' => '1.0',
            ],

            // Suspicious email patterns
            [
                'name' => 'Suspicious Email Patterns',
                'description' => 'Email addresses with suspicious patterns',
                'pattern_type' => 'email_pattern',
                'pattern' => '^[a-z0-9]{20,}@(gmail|yahoo|hotmail|outlook)\.com$',
                'pattern_config' => json_encode(['check_disposable' => true]),
                'case_sensitive' => false,
                'target_fields' => json_encode(['email']),
                'scope' => 'global',
                'risk_score' => 40,
                'action' => 'flag',
                'is_active' => true,
                'priority' => 20,
                'categories' => json_encode(['suspicious_email']),
                'source' => 'manual',
                'version' => '1.0',
            ],

            // URL spam patterns
            [
                'name' => 'Suspicious URLs',
                'description' => 'URLs with suspicious characteristics',
                'pattern_type' => 'url_pattern',
                'pattern' => '(bit\.ly|tinyurl|t\.co|goo\.gl|short\.link|[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})',
                'pattern_config' => json_encode(['check_redirects' => true, 'max_redirects' => 3]),
                'case_sensitive' => false,
                'target_fields' => json_encode(['message', 'comment', 'website', 'url']),
                'scope' => 'global',
                'risk_score' => 60,
                'action' => 'flag',
                'is_active' => true,
                'priority' => 15,
                'categories' => json_encode(['url_spam', 'phishing']),
                'source' => 'manual',
                'version' => '1.0',
            ],

            // Excessive content length
            [
                'name' => 'Excessive Content Length',
                'description' => 'Messages that are unusually long',
                'pattern_type' => 'content_length',
                'pattern' => '5000',
                'pattern_config' => json_encode(['operator' => 'greater_than', 'field_type' => 'text']),
                'target_fields' => json_encode(['message', 'comment', 'description']),
                'scope' => 'global',
                'risk_score' => 30,
                'action' => 'flag',
                'is_active' => true,
                'priority' => 50,
                'categories' => json_encode(['content_spam']),
                'source' => 'manual',
                'version' => '1.0',
            ],

            // Rapid submission pattern
            [
                'name' => 'Rapid Submissions',
                'description' => 'Multiple submissions in short time period',
                'pattern_type' => 'submission_rate',
                'pattern' => '5',
                'pattern_config' => json_encode([
                    'time_window' => 300, // 5 minutes
                    'operator' => 'greater_than',
                    'track_by' => 'ip_address',
                ]),
                'scope' => 'global',
                'risk_score' => 80,
                'action' => 'block',
                'is_active' => true,
                'priority' => 5,
                'categories' => json_encode(['rate_limiting', 'bot_behavior']),
                'source' => 'manual',
                'version' => '1.0',
            ],

            // Honeypot field detection
            [
                'name' => 'Honeypot Field Filled',
                'description' => 'Hidden honeypot field was filled by bot',
                'pattern_type' => 'behavioral',
                'pattern' => 'honeypot_filled',
                'pattern_config' => json_encode(['honeypot_fields' => ['website', 'url', 'homepage']]),
                'target_fields' => json_encode(['website', 'url', 'homepage']),
                'scope' => 'global',
                'risk_score' => 95,
                'action' => 'block',
                'is_active' => true,
                'priority' => 1,
                'categories' => json_encode(['bot_detection', 'honeypot']),
                'source' => 'manual',
                'version' => '1.0',
            ],

            // Profanity filter
            [
                'name' => 'Profanity Filter',
                'description' => 'Common profanity and offensive language',
                'pattern_type' => 'keyword',
                'pattern' => 'damn|hell|stupid|idiot|moron|jerk|asshole|bitch|bastard|shit|fuck|crap',
                'pattern_config' => json_encode(['severity' => 'moderate']),
                'case_sensitive' => false,
                'whole_word_only' => true,
                'target_fields' => json_encode(['message', 'comment', 'name', 'subject']),
                'scope' => 'global',
                'risk_score' => 25,
                'action' => 'flag',
                'is_active' => false, // Disabled by default - admin can enable
                'priority' => 100,
                'categories' => json_encode(['profanity', 'content_moderation']),
                'source' => 'manual',
                'version' => '1.0',
            ],
        ];

        foreach ($patterns as $pattern) {
            DB::table('spam_patterns')->insert(array_merge($pattern, [
                'created_at' => now(),
                'updated_at' => now(),
                'last_updated_at' => now(),
            ]));
        }
    }
}
