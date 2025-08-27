<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Configuration validation failed event.
 *
 * This event is fired when configuration validation fails,
 * enabling error tracking and notification systems.
 */
class ConfigurationValidationFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new configuration validation failed event instance.
     *
     * @param  string  $key  Configuration key that failed validation
     * @param  mixed  $value  Value that failed validation
     * @param  array<string, mixed>  $errors  Validation errors
     * @param  array<string, mixed>  $context  Additional context
     * @param  string|null  $userId  User who attempted the change
     * @param  \DateTimeImmutable  $timestamp  When the validation failed
     */
    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly array $errors,
        public readonly array $context = [],
        public readonly ?string $userId = null,
        public readonly \DateTimeImmutable $timestamp = new \DateTimeImmutable
    ) {}

    /**
     * Get the primary error message.
     *
     * @return string Primary error message
     */
    public function getPrimaryError(): string
    {
        return $this->errors[0] ?? 'Unknown validation error';
    }

    /**
     * Get all error messages.
     *
     * @return array<string> All error messages
     */
    public function getAllErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if validation failed due to type mismatch.
     *
     * @return bool True if type validation failed
     */
    public function isTypeError(): bool
    {
        return str_contains($this->getPrimaryError(), 'must be of type');
    }

    /**
     * Check if validation failed due to missing required value.
     *
     * @return bool True if required validation failed
     */
    public function isRequiredError(): bool
    {
        return str_contains($this->getPrimaryError(), 'is required');
    }

    /**
     * Check if validation failed due to constraint violation.
     *
     * @return bool True if constraint validation failed
     */
    public function isConstraintError(): bool
    {
        return str_contains($this->getPrimaryError(), 'violates constraint');
    }

    /**
     * Get event summary for logging.
     *
     * @return array<string, mixed> Event summary
     */
    public function getSummary(): array
    {
        return [
            'key' => $this->key,
            'value' => is_scalar($this->value) ? $this->value : gettype($this->value),
            'error_count' => count($this->errors),
            'primary_error' => $this->getPrimaryError(),
            'is_type_error' => $this->isTypeError(),
            'is_required_error' => $this->isRequiredError(),
            'is_constraint_error' => $this->isConstraintError(),
            'user_id' => $this->userId,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'context' => $this->context,
        ];
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed> Array representation
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'errors' => $this->errors,
            'context' => $this->context,
            'user_id' => $this->userId,
            'timestamp' => $this->timestamp->format('c'),
        ];
    }
}
