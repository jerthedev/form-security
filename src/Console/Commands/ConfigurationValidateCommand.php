<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Console\Commands;

use Illuminate\Console\Command;
use JTD\FormSecurity\Contracts\ConfigurationManagerInterface;
use JTD\FormSecurity\Contracts\ConfigurationValidatorInterface;

/**
 * Configuration validation command.
 *
 * This command validates the current configuration against schema
 * and business rules, providing detailed feedback on any issues.
 */
class ConfigurationValidateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'form-security:config:validate 
                            {--key=* : Validate specific configuration keys}
                            {--strict : Use strict validation mode}
                            {--fix : Attempt to fix validation issues automatically}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate FormSecurity configuration';

    /**
     * Create a new command instance.
     *
     * @param  ConfigurationManagerInterface  $configManager  Configuration manager
     * @param  ConfigurationValidatorInterface  $validator  Configuration validator
     */
    public function __construct(
        protected ConfigurationManagerInterface $configManager,
        protected ConfigurationValidatorInterface $validator
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int Command exit code
     */
    public function handle(): int
    {
        $this->info('Validating FormSecurity configuration...');
        $this->newLine();

        $keys = $this->option('key');
        $strict = $this->option('strict');
        $fix = $this->option('fix');

        try {
            if (! empty($keys)) {
                return $this->validateSpecificKeys($keys, $strict, $fix);
            } else {
                return $this->validateAllConfiguration($strict, $fix);
            }
        } catch (\Exception $e) {
            $this->error('Validation failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Validate specific configuration keys.
     *
     * @param  array<string>  $keys  Configuration keys to validate
     * @param  bool  $strict  Use strict validation
     * @param  bool  $fix  Attempt to fix issues
     * @return int Command exit code
     */
    protected function validateSpecificKeys(array $keys, bool $strict, bool $fix): int
    {
        $hasErrors = false;

        foreach ($keys as $key) {
            $this->info("Validating key: {$key}");

            $value = $this->configManager->get($key);
            $result = $this->validator->validateValue($key, $value, [
                'strict' => $strict,
            ]);

            if ($result['valid']) {
                $this->info("✓ {$key}: Valid");
            } else {
                $hasErrors = true;
                $this->error("✗ {$key}: Invalid");

                foreach ($result['errors'] as $error) {
                    $this->line("  • {$error}");
                }

                if ($fix) {
                    $this->attemptFix($key, $result['errors']);
                }
            }

            $this->newLine();
        }

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Validate all configuration.
     *
     * @param  bool  $strict  Use strict validation
     * @param  bool  $fix  Attempt to fix issues
     * @return int Command exit code
     */
    protected function validateAllConfiguration(bool $strict, bool $fix): int
    {
        // Get all configuration
        $config = $this->configManager->exportConfiguration();

        // Validate configuration
        $result = $this->validator->validateConfiguration($config, [
            'strict' => $strict,
        ]);

        // Display summary
        $this->displayValidationSummary($result['summary']);
        $this->newLine();

        if ($result['valid']) {
            $this->info('✓ All configuration is valid!');

            return Command::SUCCESS;
        }

        // Display errors
        $this->error('✗ Configuration validation failed:');
        $this->newLine();

        foreach ($result['errors'] as $key => $errors) {
            if (is_array($errors)) {
                $this->error("Key: {$key}");
                foreach ($errors as $error) {
                    $this->line("  • {$error}");
                }
            } else {
                $this->error("General: {$errors}");
            }
            $this->newLine();
        }

        // Attempt fixes if requested
        if ($fix) {
            $this->info('Attempting to fix configuration issues...');
            $this->attemptFixAll($result['errors']);
        }

        // Display recommendations
        $this->displayRecommendations($result);

        return Command::FAILURE;
    }

    /**
     * Display validation summary.
     *
     * @param  array<string, mixed>  $summary  Validation summary
     */
    protected function displayValidationSummary(array $summary): void
    {
        $this->info('Validation Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Keys', $summary['total_keys']],
                ['Valid Keys', $summary['valid_keys']],
                ['Invalid Keys', $summary['invalid_keys']],
                ['Total Errors', $summary['error_count']],
            ]
        );
    }

    /**
     * Attempt to fix a specific configuration key.
     *
     * @param  string  $key  Configuration key
     * @param  array<string>  $errors  Validation errors
     */
    protected function attemptFix(string $key, array $errors): void
    {
        $this->info("Attempting to fix {$key}...");

        // Get current value
        $currentValue = $this->configManager->get($key);

        // Attempt common fixes based on error patterns
        $fixedValue = $this->suggestFix($key, $currentValue, $errors);

        if ($fixedValue !== $currentValue) {
            if ($this->confirm("Change {$key} from '{$currentValue}' to '{$fixedValue}'?")) {
                $this->configManager->set($key, $fixedValue);
                $this->info("✓ Fixed {$key}");
            }
        } else {
            $this->warn("No automatic fix available for {$key}");
        }
    }

    /**
     * Attempt to fix all configuration issues.
     *
     * @param  array<string, mixed>  $errors  All validation errors
     */
    protected function attemptFixAll(array $errors): void
    {
        foreach ($errors as $key => $keyErrors) {
            if (is_array($keyErrors)) {
                $this->attemptFix($key, $keyErrors);
            }
        }
    }

    /**
     * Suggest a fix for a configuration value.
     *
     * @param  string  $key  Configuration key
     * @param  mixed  $currentValue  Current value
     * @param  array<string>  $errors  Validation errors
     * @return mixed Suggested fixed value
     */
    protected function suggestFix(string $key, mixed $currentValue, array $errors): mixed
    {
        foreach ($errors as $error) {
            // Fix type issues
            if (str_contains($error, 'must be of type boolean')) {
                return filter_var($currentValue, FILTER_VALIDATE_BOOLEAN);
            }

            if (str_contains($error, 'must be of type integer')) {
                return (int) $currentValue;
            }

            if (str_contains($error, 'must be of type float')) {
                return (float) $currentValue;
            }

            // Fix range issues
            if (str_contains($error, 'must be between 0.0 and 1.0')) {
                $value = (float) $currentValue;

                return max(0.0, min(1.0, $value));
            }

            // Fix required issues
            if (str_contains($error, 'is required')) {
                return $this->getDefaultValue($key);
            }
        }

        return $currentValue;
    }

    /**
     * Get default value for a configuration key.
     *
     * @param  string  $key  Configuration key
     * @return mixed Default value
     */
    protected function getDefaultValue(string $key): mixed
    {
        $defaults = [
            'enabled' => true,
            'spam_threshold' => 0.7,
            'features.spam_detection' => true,
            'features.csrf_protection' => true,
            'features.logging' => true,
            'features.caching' => true,
            'rate_limit.max_attempts' => 10,
            'rate_limit.window_minutes' => 60,
            'performance.cache_ttl' => 3600,
        ];

        return $defaults[$key] ?? null;
    }

    /**
     * Display configuration recommendations.
     *
     * @param  array<string, mixed>  $validationResult  Validation result
     */
    protected function displayRecommendations(array $validationResult): void
    {
        $this->info('Recommendations:');

        $recommendations = [];

        // Check for common issues and provide recommendations
        if (isset($validationResult['errors']['features.ai_analysis'])) {
            $recommendations[] = 'Consider enabling spam_detection before enabling ai_analysis';
        }

        if (isset($validationResult['errors']['spam_threshold'])) {
            $recommendations[] = 'Spam threshold should be between 0.0 (permissive) and 1.0 (strict)';
        }

        if (isset($validationResult['errors']['performance.cache_ttl'])) {
            $recommendations[] = 'Cache TTL should be between 60 seconds and 24 hours for optimal performance';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Review the configuration documentation for best practices';
            $recommendations[] = 'Consider running: php artisan form-security:config:optimize';
        }

        foreach ($recommendations as $recommendation) {
            $this->line("• {$recommendation}");
        }
    }
}
