<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JTD\FormSecurity\Contracts\SpamDetectionContract;

/**
 * Spam detection validation rule for Laravel forms.
 *
 * This rule can be used with Laravel's validator to automatically
 * check form fields for spam content.
 */
class SpamDetectionRule implements ValidationRule
{
    /**
     * Create a new spam detection rule instance.
     *
     * @param  array<string, mixed>  $options  Rule configuration options
     */
    public function __construct(
        protected array $options = []
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return; // Only validate string values
        }

        $spamDetector = app(SpamDetectionContract::class);
        $threshold = $this->options['threshold'] ?? 0.7;

        $score = $spamDetector->calculateSpamScore($value);

        if ($score >= $threshold) {
            $fail("The {$attribute} field contains content that appears to be spam.");
        }
    }
}
