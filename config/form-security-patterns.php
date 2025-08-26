<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | FormSecurity Spam Patterns Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains spam detection patterns and rules for the JTD
    | FormSecurity package. These patterns are used to identify potential
    | spam content in form submissions.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Spam Detection Patterns
    |--------------------------------------------------------------------------
    |
    | Regular expressions used to detect spam content. Each pattern has
    | a weight that contributes to the overall spam score.
    |
    */

    'spam' => [
        // Pharmaceutical spam
        '/\b(viagra|cialis|levitra|pharmacy|prescription|pills?)\b/i' => 0.8,
        '/\b(buy\s+(cheap|discount|online)\s+(viagra|cialis|pills?))\b/i' => 0.9,

        // Casino and gambling
        '/\b(casino|gambling|poker|blackjack|roulette|slots?)\b/i' => 0.7,
        '/\b(win\s+(money|cash|big)|jackpot|lottery)\b/i' => 0.6,

        // Financial spam
        '/\b(loan|credit|mortgage|debt|refinance)\b/i' => 0.6,
        '/\b(make\s+money|earn\s+\$|guaranteed\s+income)\b/i' => 0.7,
        '/\b(investment|trading|forex|cryptocurrency)\b/i' => 0.5,

        // SEO and marketing spam
        '/\b(seo|backlink|link\s+building|page\s+rank)\b/i' => 0.8,
        '/\b(increase\s+(traffic|ranking|visitors))\b/i' => 0.6,
        '/\b(submit\s+your\s+site|directory\s+submission)\b/i' => 0.7,

        // Replica and counterfeit goods
        '/\b(replica|fake|counterfeit|knockoff)\b/i' => 0.8,
        '/\b(designer\s+(bags|watches|shoes)|luxury\s+replica)\b/i' => 0.9,

        // Adult content
        '/\b(adult|porn|xxx|sex|dating|escort)\b/i' => 0.7,
        '/\b(meet\s+(singles|women|men)|hookup)\b/i' => 0.6,

        // Weight loss and health
        '/\b(weight\s+loss|diet\s+pills?|lose\s+weight)\b/i' => 0.6,
        '/\b(miracle\s+(cure|treatment)|amazing\s+results?)\b/i' => 0.7,

        // Generic spam indicators
        '/\b(click\s+here|visit\s+now|act\s+now|limited\s+time)\b/i' => 0.5,
        '/\b(free\s+(trial|sample|offer)|no\s+obligation)\b/i' => 0.4,
        '/\b(congratulations|you\s+(won|have\s+been\s+selected))\b/i' => 0.8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Suspicious Patterns
    |--------------------------------------------------------------------------
    |
    | Patterns that indicate suspicious but not necessarily spam content.
    | These have lower weights but still contribute to the spam score.
    |
    */

    'suspicious' => [
        // Excessive punctuation
        '/[!]{3,}/' => 0.2,
        '/[?]{3,}/' => 0.2,
        '/[.]{4,}/' => 0.3,

        // Excessive capitalization
        '/[A-Z]{10,}/' => 0.3,
        '/\b[A-Z]{5,}\b/' => 0.2,

        // Suspicious URLs
        '/bit\.ly|tinyurl|t\.co|goo\.gl/' => 0.4,
        '/\b\d+\.\d+\.\d+\.\d+\b/' => 0.3, // IP addresses in content

        // Suspicious email patterns
        '/\b[a-z0-9]{20,}@[a-z0-9]+\.[a-z]{2,}\b/i' => 0.4,
        '/\bnoreply@|no-reply@/i' => 0.2,

        // Suspicious formatting
        '/\s{5,}/' => 0.2, // Excessive whitespace
        '/\n{5,}/' => 0.3, // Excessive line breaks
    ],

    /*
    |--------------------------------------------------------------------------
    | Language-Specific Patterns
    |--------------------------------------------------------------------------
    |
    | Patterns for detecting spam in different languages.
    |
    */

    'languages' => [
        'russian' => [
            '/[а-я]{50,}/ui' => 0.6, // Long Russian text might be spam
        ],
        'chinese' => [
            '/[\x{4e00}-\x{9fff}]{30,}/u' => 0.6, // Long Chinese text
        ],
        'arabic' => [
            '/[\x{0600}-\x{06ff}]{30,}/u' => 0.6, // Long Arabic text
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Whitelist Patterns
    |--------------------------------------------------------------------------
    |
    | Patterns that should reduce spam score or be ignored entirely.
    | These help prevent false positives.
    |
    */

    'whitelist' => [
        // Common legitimate phrases
        '/\b(thank\s+you|please|help|support|question)\b/i' => -0.1,
        '/\b(contact\s+(us|me)|more\s+information)\b/i' => -0.1,

        // Business-related terms that might trigger false positives
        '/\b(business\s+(loan|credit)|legitimate\s+business)\b/i' => -0.2,
        '/\b(medical\s+(prescription|treatment))\b/i' => -0.2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Analysis Rules
    |--------------------------------------------------------------------------
    |
    | Rules for analyzing content structure and characteristics.
    |
    */

    'content_rules' => [
        'min_words' => 3, // Minimum word count
        'max_words' => 1000, // Maximum word count
        'max_links' => 3, // Maximum number of links
        'max_emails' => 2, // Maximum number of email addresses
        'max_phones' => 2, // Maximum number of phone numbers
        'max_caps_ratio' => 0.5, // Maximum ratio of capital letters
        'max_number_ratio' => 0.3, // Maximum ratio of numbers
        'max_special_chars_ratio' => 0.2, // Maximum ratio of special characters
    ],

    /*
    |--------------------------------------------------------------------------
    | Field-Specific Rules
    |--------------------------------------------------------------------------
    |
    | Rules that apply to specific form fields.
    |
    */

    'field_rules' => [
        'name' => [
            'min_length' => 2,
            'max_length' => 50,
            'allow_numbers' => false,
            'allow_special_chars' => false,
        ],
        'email' => [
            'validate_format' => true,
            'check_disposable' => true,
            'max_length' => 254,
        ],
        'phone' => [
            'validate_format' => true,
            'min_length' => 10,
            'max_length' => 15,
        ],
        'message' => [
            'min_length' => 10,
            'max_length' => 5000,
            'check_spam_patterns' => true,
        ],
        'url' => [
            'validate_format' => true,
            'check_reputation' => true,
            'max_length' => 2048,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pattern Updates
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic pattern updates and learning.
    |
    */

    'updates' => [
        'auto_update' => env('FORM_SECURITY_AUTO_UPDATE_PATTERNS', false),
        'update_frequency' => env('FORM_SECURITY_PATTERN_UPDATE_FREQUENCY', 'weekly'),
        'learning_enabled' => env('FORM_SECURITY_PATTERN_LEARNING', false),
        'min_confidence' => env('FORM_SECURITY_PATTERN_MIN_CONFIDENCE', 0.8),
    ],
];
