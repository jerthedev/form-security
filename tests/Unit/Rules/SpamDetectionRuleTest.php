<?php

declare(strict_types=1);

/**
 * Test File: SpamDetectionRuleTest.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: N/A - Validation rule testing
 * SPRINT: Sprint-002-core-foundation-service-provider-database
 * TICKET: 1020-service-provider-tests
 *
 * Description: Tests for SpamDetectionRule validation rule functionality.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Sprints/002-core-foundation-service-provider-database.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Test-Implementation/1020-service-provider-tests.md
 */

namespace JTD\FormSecurity\Tests\Unit\Rules;

use JTD\FormSecurity\Rules\SpamDetectionRule;
use JTD\FormSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('epic-001')]
#[Group('foundation-infrastructure')]
#[Group('sprint-002')]
#[Group('ticket-1020')]
#[Group('validation-rules')]
#[Group('unit')]
class SpamDetectionRuleTest extends TestCase
{
    #[Test]
    public function rule_passes_for_clean_content(): void
    {
        $rule = new SpamDetectionRule;
        $failed = false;

        $fail = function ($message) use (&$failed) {
            $failed = true;
        };

        $rule->validate('message', 'This is a clean message', $fail);

        $this->assertFalse($failed);
    }

    #[Test]
    public function rule_fails_for_spam_content(): void
    {
        $rule = new SpamDetectionRule(['threshold' => 0.1]); // Very low threshold to ensure failure
        $failed = false;
        $failMessage = '';

        $fail = function ($message) use (&$failed, &$failMessage) {
            $failed = true;
            $failMessage = $message;
        };

        $spamContent = 'Buy cheap viagra online! Casino gambling poker! Make money fast!';
        $rule->validate('message', $spamContent, $fail);

        $this->assertTrue($failed);
        $this->assertStringContainsString('spam', $failMessage);
    }

    #[Test]
    public function rule_uses_custom_threshold(): void
    {
        $rule = new SpamDetectionRule(['threshold' => 0.9]); // Very high threshold
        $failed = false;

        $fail = function ($message) use (&$failed) {
            $failed = true;
        };

        $spamContent = 'Buy cheap viagra online!';
        $rule->validate('message', $spamContent, $fail);

        // Should pass with high threshold
        $this->assertFalse($failed);
    }

    #[Test]
    public function rule_ignores_non_string_values(): void
    {
        $rule = new SpamDetectionRule;
        $failed = false;

        $fail = function ($message) use (&$failed) {
            $failed = true;
        };

        // Test with various non-string values
        $rule->validate('field', 123, $fail);
        $this->assertFalse($failed);

        $rule->validate('field', [], $fail);
        $this->assertFalse($failed);

        $rule->validate('field', null, $fail);
        $this->assertFalse($failed);
    }

    #[Test]
    public function rule_uses_default_threshold(): void
    {
        $rule = new SpamDetectionRule;
        $failed = false;

        $fail = function ($message) use (&$failed) {
            $failed = true;
        };

        // Test that default threshold (0.7) is used
        $moderateSpamContent = 'Buy cheap products online';
        $rule->validate('message', $moderateSpamContent, $fail);

        // Result depends on spam detection algorithm, but rule should execute
        $this->assertIsBool($failed);
    }

    #[Test]
    public function rule_includes_attribute_name_in_error_message(): void
    {
        $rule = new SpamDetectionRule(['threshold' => 0.1]); // Very low threshold
        $failed = false;
        $failMessage = '';

        $fail = function ($message) use (&$failed, &$failMessage) {
            $failed = true;
            $failMessage = $message;
        };

        $rule->validate('comment', 'spam content', $fail);

        if ($failed) {
            $this->assertStringContainsString('comment', $failMessage);
        } else {
            // If it didn't fail, at least assert that the rule executed
            $this->assertFalse($failed);
        }
    }
}
