<?php

declare(strict_types=1);

namespace JTD\FormSecurity\ValueObjects;

use JTD\FormSecurity\Enums\ConfigurationType;

/**
 * Configuration schema value object for validation rules.
 *
 * This value object defines the schema for configuration validation,
 * including type constraints, validation rules, and default values.
 */
readonly class ConfigurationSchema
{
    /**
     * Create a new configuration schema instance.
     *
     * @param  string  $key  Configuration key
     * @param  ConfigurationType  $type  Expected type
     * @param  bool  $required  Whether the configuration is required
     * @param  mixed  $default  Default value
     * @param  array<string>  $validationRules  Validation rules
     * @param  array<mixed>  $allowedValues  Allowed values (for enum-like validation)
     * @param  array<string, mixed>  $constraints  Additional constraints
     * @param  string|null  $description  Schema description
     * @param  array<string>  $tags  Schema tags
     * @param  bool  $sensitive  Whether the value is sensitive
     * @param  bool  $cacheable  Whether the value can be cached
     */
    public function __construct(
        public string $key,
        public ConfigurationType $type = ConfigurationType::MIXED,
        public bool $required = false,
        public mixed $default = null,
        public array $validationRules = [],
        public array $allowedValues = [],
        public array $constraints = [],
        public ?string $description = null,
        public array $tags = [],
        public bool $sensitive = false,
        public bool $cacheable = true
    ) {}

    /**
     * Create a schema from array definition.
     *
     * @param  string  $key  Configuration key
     * @param  array<string, mixed>  $definition  Schema definition
     * @return self New schema instance
     */
    public static function fromArray(string $key, array $definition): self
    {
        return new self(
            key: $key,
            type: ConfigurationType::tryFrom($definition['type'] ?? 'mixed') ?? ConfigurationType::MIXED,
            required: $definition['required'] ?? false,
            default: $definition['default'] ?? null,
            validationRules: $definition['rules'] ?? [],
            allowedValues: $definition['allowed_values'] ?? [],
            constraints: $definition['constraints'] ?? [],
            description: $definition['description'] ?? null,
            tags: $definition['tags'] ?? [],
            sensitive: $definition['sensitive'] ?? false,
            cacheable: $definition['cacheable'] ?? true
        );
    }

    /**
     * Validate a value against this schema.
     *
     * @param  mixed  $value  Value to validate
     * @return array<string, mixed> Validation result
     */
    public function validate(mixed $value): array
    {
        $errors = [];

        // Check if required value is missing
        if ($this->required && ($value === null || $value === '')) {
            $errors[] = "Configuration '{$this->key}' is required";

            return ['valid' => false, 'errors' => $errors];
        }

        // Skip validation if value is null and not required
        if ($value === null && ! $this->required) {
            return ['valid' => true, 'errors' => []];
        }

        // Type validation
        if (! $this->validateType($value)) {
            $errors[] = "Configuration '{$this->key}' must be of type {$this->type->value}";
        }

        // Allowed values validation
        if (! empty($this->allowedValues) && ! in_array($value, $this->allowedValues, true)) {
            $allowedStr = implode(', ', $this->allowedValues);
            $errors[] = "Configuration '{$this->key}' must be one of: {$allowedStr}";
        }

        // Constraint validation
        foreach ($this->constraints as $constraint => $constraintValue) {
            if (! $this->validateConstraint($value, $constraint, $constraintValue)) {
                $errors[] = "Configuration '{$this->key}' violates constraint '{$constraint}'";
            }
        }

        // Custom validation rules
        foreach ($this->validationRules as $rule) {
            if (is_callable($rule)) {
                $result = $rule($value);
                if ($result !== true) {
                    $errors[] = is_string($result) ? $result : "Configuration '{$this->key}' failed custom validation";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate value type.
     *
     * @param  mixed  $value  Value to validate
     * @return bool True if type is valid
     */
    private function validateType(mixed $value): bool
    {
        return match ($this->type) {
            ConfigurationType::BOOLEAN => is_bool($value),
            ConfigurationType::INTEGER => is_int($value),
            ConfigurationType::FLOAT => is_float($value) || is_int($value),
            ConfigurationType::STRING => is_string($value),
            ConfigurationType::ARRAY => is_array($value),
            ConfigurationType::OBJECT => is_object($value),
            ConfigurationType::EMAIL => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            ConfigurationType::URL => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            ConfigurationType::IP_ADDRESS => is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false,
            ConfigurationType::JSON => is_string($value) && json_decode($value) !== null,
            ConfigurationType::REGEX => is_string($value) && @preg_match($value, '') !== false,
            ConfigurationType::DATETIME => is_string($value) && strtotime($value) !== false,
            ConfigurationType::FILE_PATH => is_string($value) && (file_exists($value) || is_dir(dirname($value))),
            ConfigurationType::MIXED, ConfigurationType::ENCRYPTED, ConfigurationType::ENUM => true,
        };
    }

    /**
     * Validate constraint.
     *
     * @param  mixed  $value  Value to validate
     * @param  string  $constraint  Constraint name
     * @param  mixed  $constraintValue  Constraint value
     * @return bool True if constraint is satisfied
     */
    private function validateConstraint(mixed $value, string $constraint, mixed $constraintValue): bool
    {
        return match ($constraint) {
            'min' => is_numeric($value) && $value >= $constraintValue,
            'max' => is_numeric($value) && $value <= $constraintValue,
            'min_length' => is_string($value) && strlen($value) >= $constraintValue,
            'max_length' => is_string($value) && strlen($value) <= $constraintValue,
            'pattern' => is_string($value) && preg_match($constraintValue, $value) === 1,
            'not_empty' => ! empty($value),
            'positive' => is_numeric($value) && $value > 0,
            'non_negative' => is_numeric($value) && $value >= 0,
            default => true,
        };
    }

    /**
     * Get the default value for this schema.
     *
     * @return mixed Default value
     */
    public function getDefaultValue(): mixed
    {
        return $this->default ?? $this->type->getDefaultValue();
    }

    /**
     * Check if the schema allows caching.
     *
     * @return bool True if caching is allowed
     */
    public function allowsCaching(): bool
    {
        return $this->cacheable && ! $this->sensitive;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed> Array representation
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'required' => $this->required,
            'default' => $this->default,
            'validation_rules' => $this->validationRules,
            'allowed_values' => $this->allowedValues,
            'constraints' => $this->constraints,
            'description' => $this->description,
            'tags' => $this->tags,
            'sensitive' => $this->sensitive,
            'cacheable' => $this->cacheable,
        ];
    }

    /**
     * Create a builder for fluent schema creation.
     *
     * @param  string  $key  Configuration key
     * @return ConfigurationSchemaBuilder Schema builder
     */
    public static function builder(string $key): ConfigurationSchemaBuilder
    {
        return new ConfigurationSchemaBuilder($key);
    }
}

/**
 * Builder class for fluent configuration schema creation.
 */
class ConfigurationSchemaBuilder
{
    private ConfigurationType $type = ConfigurationType::MIXED;

    private bool $required = false;

    private mixed $default = null;

    /** @var array<string, mixed> */
    private array $validationRules = [];

    /** @var array<mixed> */
    private array $allowedValues = [];

    /** @var array<string, mixed> */
    private array $constraints = [];

    private ?string $description = null;

    /** @var array<string> */
    private array $tags = [];

    private bool $sensitive = false;

    private bool $cacheable = true;

    public function __construct(private readonly string $key) {}

    public function type(ConfigurationType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function default(mixed $default): self
    {
        $this->default = $default;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function rules(array $rules): self
    {
        $this->validationRules = $rules;

        return $this;
    }

    /**
     * @param  array<mixed>  $values
     */
    public function allowedValues(array $values): self
    {
        $this->allowedValues = $values;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $constraints
     */
    public function constraints(array $constraints): self
    {
        $this->constraints = $constraints;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  array<string>  $tags
     */
    public function tags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    public function sensitive(bool $sensitive = true): self
    {
        $this->sensitive = $sensitive;

        return $this;
    }

    public function cacheable(bool $cacheable = true): self
    {
        $this->cacheable = $cacheable;

        return $this;
    }

    public function build(): ConfigurationSchema
    {
        return new ConfigurationSchema(
            key: $this->key,
            type: $this->type,
            required: $this->required,
            default: $this->default,
            validationRules: $this->validationRules,
            allowedValues: $this->allowedValues,
            constraints: $this->constraints,
            description: $this->description,
            tags: $this->tags,
            sensitive: $this->sensitive,
            cacheable: $this->cacheable
        );
    }
}
