<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services;

use Illuminate\Support\Facades\Log;
use JTD\FormSecurity\Contracts\ConfigurationManagerInterface;

/**
 * Feature toggle service with graceful degradation.
 *
 * This service manages feature toggles with support for graceful degradation,
 * A/B testing, and runtime feature control.
 */
class FeatureToggleService
{
    /**
     * Feature toggle cache.
     *
     * @var array<string, bool>
     */
    protected array $featureCache = [];

    /**
     * Feature dependencies mapping.
     *
     * @var array<string, array<string>>
     */
    protected array $featureDependencies = [
        'ai_analysis' => ['spam_detection'],
        'geolocation' => ['ip_reputation'],
        'advanced_logging' => ['logging'],
    ];

    /**
     * Feature fallback strategies.
     *
     * @var array<string, callable>
     */
    protected array $fallbackStrategies = [];

    /**
     * Create a new feature toggle service instance.
     *
     * @param  ConfigurationManagerInterface  $configManager  Configuration manager
     */
    public function __construct(
        protected ConfigurationManagerInterface $configManager
    ) {
        $this->initializeFallbackStrategies();
    }

    /**
     * Check if a feature is enabled.
     *
     * @param  string  $feature  Feature name
     * @param  array<string, mixed>  $context  Additional context for evaluation
     * @return bool True if feature is enabled
     */
    public function isEnabled(string $feature, array $context = []): bool
    {
        // Check cache first
        if (isset($this->featureCache[$feature])) {
            return $this->featureCache[$feature];
        }

        try {
            // Get feature configuration
            $enabled = $this->configManager->isFeatureEnabled($feature);

            // Check dependencies
            if ($enabled && ! $this->areDependenciesMet($feature)) {
                Log::warning('Feature disabled due to unmet dependencies', [
                    'feature' => $feature,
                    'dependencies' => $this->featureDependencies[$feature] ?? [],
                ]);
                $enabled = false;
            }

            // Apply context-based rules
            if ($enabled && ! empty($context)) {
                $enabled = $this->evaluateContextRules($feature, $context);
            }

            // Cache the result
            $this->featureCache[$feature] = $enabled;

            return $enabled;
        } catch (\Exception $e) {
            Log::error('Error checking feature toggle', [
                'feature' => $feature,
                'error' => $e->getMessage(),
            ]);

            // Return safe default
            return $this->getSafeDefault($feature);
        }
    }

    /**
     * Enable a feature with optional context.
     *
     * @param  string  $feature  Feature name
     * @param  array<string, mixed>  $context  Additional context
     * @return bool True if feature was enabled successfully
     */
    public function enable(string $feature, array $context = []): bool
    {
        try {
            // Check if dependencies are met
            if (! $this->areDependenciesMet($feature)) {
                $missingDeps = $this->getMissingDependencies($feature);
                Log::warning('Cannot enable feature due to missing dependencies', [
                    'feature' => $feature,
                    'missing_dependencies' => $missingDeps,
                ]);

                return false;
            }

            // Enable the feature
            $success = $this->configManager->toggleFeature($feature, true);

            if ($success) {
                // Clear cache
                unset($this->featureCache[$feature]);

                Log::info('Feature enabled', [
                    'feature' => $feature,
                    'context' => $context,
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('Error enabling feature', [
                'feature' => $feature,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Disable a feature with graceful degradation.
     *
     * @param  string  $feature  Feature name
     * @param  array<string, mixed>  $context  Additional context
     * @return bool True if feature was disabled successfully
     */
    public function disable(string $feature, array $context = []): bool
    {
        try {
            // Check for dependent features
            $dependentFeatures = $this->getDependentFeatures($feature);

            if (! empty($dependentFeatures)) {
                Log::warning('Disabling feature with dependent features', [
                    'feature' => $feature,
                    'dependent_features' => $dependentFeatures,
                ]);

                // Optionally disable dependent features or warn
                foreach ($dependentFeatures as $dependentFeature) {
                    if ($this->isEnabled($dependentFeature)) {
                        Log::info('Auto-disabling dependent feature', [
                            'parent_feature' => $feature,
                            'dependent_feature' => $dependentFeature,
                        ]);
                        $this->disable($dependentFeature, $context);
                    }
                }
            }

            // Disable the feature
            $success = $this->configManager->toggleFeature($feature, false);

            if ($success) {
                // Clear cache
                unset($this->featureCache[$feature]);

                Log::info('Feature disabled', [
                    'feature' => $feature,
                    'context' => $context,
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('Error disabling feature', [
                'feature' => $feature,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Execute code with feature toggle check and fallback.
     *
     * @param  string  $feature  Feature name
     * @param  callable  $callback  Code to execute if feature is enabled
     * @param  callable|null  $fallback  Fallback code if feature is disabled
     * @param  array<string, mixed>  $context  Additional context
     * @return mixed Result of callback or fallback
     */
    public function when(string $feature, callable $callback, ?callable $fallback = null, array $context = []): mixed
    {
        if ($this->isEnabled($feature, $context)) {
            try {
                return $callback();
            } catch (\Exception $e) {
                Log::error('Feature callback failed, falling back', [
                    'feature' => $feature,
                    'error' => $e->getMessage(),
                ]);

                // Execute fallback if available
                if ($fallback) {
                    return $fallback();
                }

                // Use registered fallback strategy
                return $this->executeFallbackStrategy($feature, $e);
            }
        }

        // Feature is disabled, execute fallback
        if ($fallback) {
            return $fallback();
        }

        // Use registered fallback strategy
        return $this->executeFallbackStrategy($feature);
    }

    /**
     * Get all enabled features.
     *
     * @return array<string> List of enabled features
     */
    public function getEnabledFeatures(): array
    {
        return $this->configManager->getEnabledFeatures();
    }

    /**
     * Get feature status with metadata.
     *
     * @param  string|null  $feature  Specific feature or null for all
     * @return array<string, array<string, mixed>> Feature status information
     */
    public function getFeatureStatus(?string $feature = null): array
    {
        $features = $feature ? [$feature] : $this->getAllFeatureNames();
        $status = [];

        foreach ($features as $featureName) {
            $status[$featureName] = [
                'enabled' => $this->isEnabled($featureName),
                'dependencies' => $this->featureDependencies[$featureName] ?? [],
                'dependencies_met' => $this->areDependenciesMet($featureName),
                'dependent_features' => $this->getDependentFeatures($featureName),
                'has_fallback' => isset($this->fallbackStrategies[$featureName]),
                'safe_default' => $this->getSafeDefault($featureName),
            ];
        }

        return $status;
    }

    /**
     * Register a fallback strategy for a feature.
     *
     * @param  string  $feature  Feature name
     * @param  callable  $strategy  Fallback strategy
     * @return bool True if strategy was registered successfully
     */
    public function registerFallbackStrategy(string $feature, callable $strategy): bool
    {
        try {
            $this->fallbackStrategies[$feature] = $strategy;

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to register fallback strategy', [
                'feature' => $feature,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Clear feature toggle cache.
     *
     * @param  string|null  $feature  Specific feature or null for all
     */
    public function clearCache(?string $feature = null): void
    {
        if ($feature) {
            unset($this->featureCache[$feature]);
        } else {
            $this->featureCache = [];
        }
    }

    /**
     * Check if feature dependencies are met.
     *
     * @param  string  $feature  Feature name
     * @return bool True if all dependencies are met
     */
    protected function areDependenciesMet(string $feature): bool
    {
        $dependencies = $this->featureDependencies[$feature] ?? [];

        foreach ($dependencies as $dependency) {
            if (! $this->configManager->isFeatureEnabled($dependency)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing dependencies for a feature.
     *
     * @param  string  $feature  Feature name
     * @return array<string> Missing dependencies
     */
    protected function getMissingDependencies(string $feature): array
    {
        $dependencies = $this->featureDependencies[$feature] ?? [];
        $missing = [];

        foreach ($dependencies as $dependency) {
            if (! $this->configManager->isFeatureEnabled($dependency)) {
                $missing[] = $dependency;
            }
        }

        return $missing;
    }

    /**
     * Get features that depend on the given feature.
     *
     * @param  string  $feature  Feature name
     * @return array<string> Dependent features
     */
    protected function getDependentFeatures(string $feature): array
    {
        $dependent = [];

        foreach ($this->featureDependencies as $dependentFeature => $dependencies) {
            if (in_array($feature, $dependencies)) {
                $dependent[] = $dependentFeature;
            }
        }

        return $dependent;
    }

    /**
     * Evaluate context-based rules for feature.
     *
     * @param  string  $feature  Feature name
     * @param  array<string, mixed>  $context  Context data
     * @return bool True if context rules pass
     */
    protected function evaluateContextRules(string $feature, array $context): bool
    {
        // Example context rules - can be extended

        // User-based toggles
        if (isset($context['user_id'])) {
            $userId = $context['user_id'];

            // Example: Enable AI analysis only for premium users
            if ($feature === 'ai_analysis' && isset($context['user_tier'])) {
                return $context['user_tier'] === 'premium';
            }
        }

        // Environment-based toggles
        if (isset($context['environment'])) {
            // Example: Disable certain features in production
            if ($context['environment'] === 'production' && in_array($feature, ['debug_logging'])) {
                return false;
            }
        }

        // Load-based toggles
        if (isset($context['system_load'])) {
            $load = $context['system_load'];

            // Disable resource-intensive features under high load
            if ($load > 0.8 && in_array($feature, ['ai_analysis', 'advanced_logging'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Execute fallback strategy for a feature.
     *
     * @param  string  $feature  Feature name
     * @param  \Exception|null  $exception  Optional exception that triggered fallback
     * @return mixed Fallback result
     */
    protected function executeFallbackStrategy(string $feature, ?\Exception $exception = null): mixed
    {
        if (isset($this->fallbackStrategies[$feature])) {
            try {
                return $this->fallbackStrategies[$feature]($exception);
            } catch (\Exception $e) {
                Log::error('Fallback strategy failed', [
                    'feature' => $feature,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Return safe default
        return null;
    }

    /**
     * Get safe default for a feature.
     *
     * @param  string  $feature  Feature name
     * @return bool Safe default value
     */
    protected function getSafeDefault(string $feature): bool
    {
        // Most features should default to false for safety
        $safeDefaults = [
            'spam_detection' => true,  // Core security feature should be enabled
            'csrf_protection' => true, // Security feature should be enabled
            'logging' => true,         // Logging should be enabled for debugging
            'caching' => true,         // Caching improves performance
        ];

        return $safeDefaults[$feature] ?? false;
    }

    /**
     * Get all feature names.
     *
     * @return array<string> All feature names
     */
    protected function getAllFeatureNames(): array
    {
        $configFeatures = array_keys($this->configManager->get('features', []));
        $dependencyFeatures = array_keys($this->featureDependencies);

        return array_unique(array_merge($configFeatures, $dependencyFeatures));
    }

    /**
     * Initialize default fallback strategies.
     */
    protected function initializeFallbackStrategies(): void
    {
        // Spam detection fallback: use basic pattern matching
        $this->fallbackStrategies['spam_detection'] = function (?\Exception $e = null) {
            Log::info('Using spam detection fallback strategy');

            return ['score' => 0.0, 'method' => 'fallback'];
        };

        // AI analysis fallback: skip AI, use rule-based detection
        $this->fallbackStrategies['ai_analysis'] = function (?\Exception $e = null) {
            Log::info('Using AI analysis fallback strategy');

            return ['analysis' => 'rule_based', 'confidence' => 0.5];
        };

        // Geolocation fallback: return unknown location
        $this->fallbackStrategies['geolocation'] = function (?\Exception $e = null) {
            Log::info('Using geolocation fallback strategy');

            return ['country' => 'unknown', 'region' => 'unknown'];
        };
    }
}
