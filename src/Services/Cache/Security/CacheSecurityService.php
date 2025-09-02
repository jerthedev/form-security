<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Services\Cache\Security;

use Illuminate\Cache\CacheManager as LaravelCacheManager;
use JTD\FormSecurity\Contracts\Cache\CacheSecurityServiceInterface;
use JTD\FormSecurity\Services\Cache\Traits\CacheConfigurationTrait;
use JTD\FormSecurity\Services\Cache\Traits\CacheUtilitiesTrait;

/**
 * CacheSecurityService
 */
class CacheSecurityService implements CacheSecurityServiceInterface
{
    use CacheConfigurationTrait;
    use CacheUtilitiesTrait;

    private array $stats = [];

    public function __construct(
        private LaravelCacheManager $cacheManager
    ) {
        $this->initializeRepositories();
    }

    /**
     * Enable security features for cache operations
     */
    public function enableSecurity(array $securityConfig = []): void
    {
        $defaultConfig = [
            'encryption_enabled' => true,
            'access_control_enabled' => true,
            'audit_logging_enabled' => true,
            'cache_poisoning_protection' => true,
            'max_value_size' => 10 * 1024 * 1024, // 10MB
            'audit_log_destination' => 'file',
            'audit_log_file' => storage_path('logs/cache_audit.log'),
            'rate_limits' => [
                'default' => [
                    'get' => ['max_requests' => 1000, 'window' => 60],
                    'put' => ['max_requests' => 100, 'window' => 60],
                    'delete' => ['max_requests' => 50, 'window' => 60],
                ],
            ],
        ];

        $this->configuration['security'] = array_merge($defaultConfig, $securityConfig);

        $this->auditLog('security_enabled', [
            'config' => array_keys($securityConfig),
        ]);
    }

    /**
     * Get security status and configuration
     */
    public function getSecurityStatus(): array
    {
        return [
            'encryption_enabled' => $this->configuration['security']['encryption_enabled'] ?? false,
            'access_control_enabled' => $this->configuration['security']['access_control_enabled'] ?? false,
            'audit_logging_enabled' => $this->configuration['security']['audit_logging_enabled'] ?? false,
            'cache_poisoning_protection' => $this->configuration['security']['cache_poisoning_protection'] ?? false,
            'max_value_size' => $this->configuration['security']['max_value_size'] ?? 0,
            'audit_log_destination' => $this->configuration['security']['audit_log_destination'] ?? 'none',
            'rate_limits_configured' => ! empty($this->configuration['security']['rate_limits']),
        ];
    }

    /**
     * Disable security features
     */
    public function disableSecurity(): void
    {
        $this->configuration['security']['enabled'] = false;
        $this->configuration['security']['data_encryption_enabled'] = false;
        $this->configuration['security']['access_control_enabled'] = false;
        $this->configuration['security']['audit_logging_enabled'] = false;
        $this->configuration['security']['cache_poisoning_protection'] = false;

        $this->auditLog('security_disabled', [
            'timestamp' => time(),
        ]);
    }

    /**
     * Log security audit events
     */
    private function auditLog(string $event, array $data = []): void
    {
        if ($this->configuration['security']['audit_logging_enabled'] ?? false) {
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'event' => $event,
                'data' => $data,
            ];

            // Write to audit log file instead of error_log to avoid PHPUnit risky test warnings
            $logFile = $this->configuration['security']['audit_log_file'] ?? storage_path('logs/cache_audit.log');
            $logDir = dirname($logFile);

            if (! is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $logMessage = 'Cache Security Audit: '.json_encode($logEntry).PHP_EOL;
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }
}
