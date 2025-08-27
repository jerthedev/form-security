<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Console\Commands;

use Illuminate\Console\Command;
use JTD\FormSecurity\Services\FeatureToggleService;

/**
 * Feature toggle management command.
 *
 * This command provides management capabilities for feature toggles
 * including listing, enabling, disabling, and status checking.
 */
class FeatureToggleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'form-security:features 
                            {action : Action to perform (list|enable|disable|status|toggle)}
                            {feature? : Feature name (required for enable/disable/status/toggle)}
                            {--all : Apply action to all features}
                            {--force : Force action without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage FormSecurity feature toggles';

    /**
     * Create a new command instance.
     *
     * @param  FeatureToggleService  $featureToggle  Feature toggle service
     */
    public function __construct(
        protected FeatureToggleService $featureToggle
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
        $action = $this->argument('action');
        $feature = $this->argument('feature');
        $all = $this->option('all');
        $force = $this->option('force');

        try {
            return match ($action) {
                'list' => $this->listFeatures(),
                'enable' => $this->enableFeature($feature, $all, $force),
                'disable' => $this->disableFeature($feature, $all, $force),
                'status' => $this->showFeatureStatus($feature),
                'toggle' => $this->toggleFeature($feature, $force),
                default => $this->handleInvalidAction($action),
            };
        } catch (\Exception $e) {
            $this->error('Command failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * List all available features.
     *
     * @return int Command exit code
     */
    protected function listFeatures(): int
    {
        $this->info('FormSecurity Features:');
        $this->newLine();

        $features = $this->featureToggle->getFeatureStatus();

        if (empty($features)) {
            $this->warn('No features found.');

            return Command::SUCCESS;
        }

        $tableData = [];
        foreach ($features as $name => $status) {
            $tableData[] = [
                $name,
                $status['enabled'] ? '✓ Enabled' : '✗ Disabled',
                $status['dependencies_met'] ? '✓' : '✗',
                count($status['dependencies']),
                count($status['dependent_features']),
                $status['has_fallback'] ? '✓' : '✗',
            ];
        }

        $this->table(
            ['Feature', 'Status', 'Deps Met', 'Dependencies', 'Dependents', 'Fallback'],
            $tableData
        );

        $this->newLine();
        $this->info('Legend:');
        $this->line('• Deps Met: Whether all dependencies are satisfied');
        $this->line('• Dependencies: Number of features this feature depends on');
        $this->line('• Dependents: Number of features that depend on this feature');
        $this->line('• Fallback: Whether a fallback strategy is available');

        return Command::SUCCESS;
    }

    /**
     * Enable a feature.
     *
     * @param  string|null  $feature  Feature name
     * @param  bool  $all  Enable all features
     * @param  bool  $force  Force without confirmation
     * @return int Command exit code
     */
    protected function enableFeature(?string $feature, bool $all, bool $force): int
    {
        if ($all) {
            return $this->enableAllFeatures($force);
        }

        if (! $feature) {
            $this->error('Feature name is required when not using --all option');

            return Command::FAILURE;
        }

        $this->info("Enabling feature: {$feature}");

        // Check current status
        $status = $this->featureToggle->getFeatureStatus($feature);
        if (empty($status)) {
            $this->error("Feature '{$feature}' not found");

            return Command::FAILURE;
        }

        $featureStatus = $status[$feature];

        if ($featureStatus['enabled']) {
            $this->info("Feature '{$feature}' is already enabled");

            return Command::SUCCESS;
        }

        // Check dependencies
        if (! $featureStatus['dependencies_met']) {
            $this->warn("Feature '{$feature}' has unmet dependencies:");
            foreach ($featureStatus['dependencies'] as $dependency) {
                $this->line("  • {$dependency}");
            }

            if (! $force && ! $this->confirm('Enable dependencies automatically?')) {
                return Command::FAILURE;
            }

            // Enable dependencies first
            foreach ($featureStatus['dependencies'] as $dependency) {
                $this->info("Enabling dependency: {$dependency}");
                $this->featureToggle->enable($dependency);
            }
        }

        // Enable the feature
        if ($this->featureToggle->enable($feature)) {
            $this->info("✓ Feature '{$feature}' enabled successfully");

            return Command::SUCCESS;
        } else {
            $this->error("✗ Failed to enable feature '{$feature}'");

            return Command::FAILURE;
        }
    }

    /**
     * Disable a feature.
     *
     * @param  string|null  $feature  Feature name
     * @param  bool  $all  Disable all features
     * @param  bool  $force  Force without confirmation
     * @return int Command exit code
     */
    protected function disableFeature(?string $feature, bool $all, bool $force): int
    {
        if ($all) {
            return $this->disableAllFeatures($force);
        }

        if (! $feature) {
            $this->error('Feature name is required when not using --all option');

            return Command::FAILURE;
        }

        $this->info("Disabling feature: {$feature}");

        // Check current status
        $status = $this->featureToggle->getFeatureStatus($feature);
        if (empty($status)) {
            $this->error("Feature '{$feature}' not found");

            return Command::FAILURE;
        }

        $featureStatus = $status[$feature];

        if (! $featureStatus['enabled']) {
            $this->info("Feature '{$feature}' is already disabled");

            return Command::SUCCESS;
        }

        // Check dependent features
        if (! empty($featureStatus['dependent_features'])) {
            $this->warn("Feature '{$feature}' has dependent features:");
            foreach ($featureStatus['dependent_features'] as $dependent) {
                $this->line("  • {$dependent}");
            }

            if (! $force && ! $this->confirm('This will also disable dependent features. Continue?')) {
                return Command::FAILURE;
            }
        }

        // Disable the feature
        if ($this->featureToggle->disable($feature)) {
            $this->info("✓ Feature '{$feature}' disabled successfully");

            return Command::SUCCESS;
        } else {
            $this->error("✗ Failed to disable feature '{$feature}'");

            return Command::FAILURE;
        }
    }

    /**
     * Show feature status.
     *
     * @param  string|null  $feature  Feature name
     * @return int Command exit code
     */
    protected function showFeatureStatus(?string $feature): int
    {
        if (! $feature) {
            return $this->listFeatures();
        }

        $status = $this->featureToggle->getFeatureStatus($feature);
        if (empty($status)) {
            $this->error("Feature '{$feature}' not found");

            return Command::FAILURE;
        }

        $featureStatus = $status[$feature];

        $this->info("Feature Status: {$feature}");
        $this->newLine();

        $this->table(
            ['Property', 'Value'],
            [
                ['Enabled', $featureStatus['enabled'] ? 'Yes' : 'No'],
                ['Dependencies Met', $featureStatus['dependencies_met'] ? 'Yes' : 'No'],
                ['Has Fallback', $featureStatus['has_fallback'] ? 'Yes' : 'No'],
                ['Safe Default', $featureStatus['safe_default'] ? 'Yes' : 'No'],
                ['Dependencies', implode(', ', $featureStatus['dependencies']) ?: 'None'],
                ['Dependent Features', implode(', ', $featureStatus['dependent_features']) ?: 'None'],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Toggle a feature.
     *
     * @param  string|null  $feature  Feature name
     * @param  bool  $force  Force without confirmation
     * @return int Command exit code
     */
    protected function toggleFeature(?string $feature, bool $force): int
    {
        if (! $feature) {
            $this->error('Feature name is required for toggle action');

            return Command::FAILURE;
        }

        $status = $this->featureToggle->getFeatureStatus($feature);
        if (empty($status)) {
            $this->error("Feature '{$feature}' not found");

            return Command::FAILURE;
        }

        $featureStatus = $status[$feature];
        $currentlyEnabled = $featureStatus['enabled'];

        if ($currentlyEnabled) {
            return $this->disableFeature($feature, false, $force);
        } else {
            return $this->enableFeature($feature, false, $force);
        }
    }

    /**
     * Enable all features.
     *
     * @param  bool  $force  Force without confirmation
     * @return int Command exit code
     */
    protected function enableAllFeatures(bool $force): int
    {
        if (! $force && ! $this->confirm('Enable all features?')) {
            return Command::FAILURE;
        }

        $features = $this->featureToggle->getFeatureStatus();
        $enabled = 0;
        $failed = 0;

        foreach ($features as $name => $status) {
            if (! $status['enabled']) {
                if ($this->featureToggle->enable($name)) {
                    $this->info("✓ Enabled: {$name}");
                    $enabled++;
                } else {
                    $this->error("✗ Failed: {$name}");
                    $failed++;
                }
            }
        }

        $this->info("Enabled {$enabled} features, {$failed} failed");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Disable all features.
     *
     * @param  bool  $force  Force without confirmation
     * @return int Command exit code
     */
    protected function disableAllFeatures(bool $force): int
    {
        if (! $force && ! $this->confirm('Disable all features? This may affect security!')) {
            return Command::FAILURE;
        }

        $features = $this->featureToggle->getFeatureStatus();
        $disabled = 0;
        $failed = 0;

        foreach ($features as $name => $status) {
            if ($status['enabled']) {
                if ($this->featureToggle->disable($name)) {
                    $this->info("✓ Disabled: {$name}");
                    $disabled++;
                } else {
                    $this->error("✗ Failed: {$name}");
                    $failed++;
                }
            }
        }

        $this->info("Disabled {$disabled} features, {$failed} failed");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Handle invalid action.
     *
     * @param  string  $action  Invalid action
     * @return int Command exit code
     */
    protected function handleInvalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->info('Available actions: list, enable, disable, status, toggle');

        return Command::FAILURE;
    }
}
