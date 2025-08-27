<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JTD\FormSecurity\Contracts\ConfigurationManagerInterface;

/**
 * Configuration publishing command.
 *
 * This command publishes configuration files and manages configuration
 * publishing operations for the FormSecurity package.
 */
class ConfigurationPublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'form-security:config:publish 
                            {--force : Overwrite existing configuration files}
                            {--tag=* : Publish specific configuration tags}
                            {--all : Publish all configuration files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish FormSecurity configuration files';

    /**
     * Configuration files to publish.
     *
     * @var array<string, array<string, string>>
     */
    protected array $configFiles = [
        'main' => [
            'source' => 'config/form-security.php',
            'destination' => 'config/form-security.php',
            'description' => 'Main FormSecurity configuration',
        ],
        'cache' => [
            'source' => 'config/form-security-cache.php',
            'destination' => 'config/form-security-cache.php',
            'description' => 'Cache configuration for FormSecurity',
        ],
        'patterns' => [
            'source' => 'config/form-security-patterns.php',
            'destination' => 'config/form-security-patterns.php',
            'description' => 'Spam pattern configuration',
        ],
    ];

    /**
     * Create a new command instance.
     *
     * @param  ConfigurationManagerInterface  $configManager  Configuration manager
     */
    public function __construct(
        protected ConfigurationManagerInterface $configManager
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
        $this->info('Publishing FormSecurity configuration files...');

        $tags = $this->option('tag');
        $force = $this->option('force');
        $all = $this->option('all');

        try {
            if ($all || empty($tags)) {
                // Publish all configuration files
                $this->publishAllConfigurations($force);
            } else {
                // Publish specific tagged configurations
                $this->publishTaggedConfigurations($tags, $force);
            }

            $this->info('Configuration files published successfully!');
            $this->newLine();
            $this->displayPostPublishInstructions();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to publish configuration files: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Publish all configuration files.
     *
     * @param  bool  $force  Whether to overwrite existing files
     */
    protected function publishAllConfigurations(bool $force): void
    {
        foreach ($this->configFiles as $tag => $config) {
            $this->publishConfiguration($tag, $config, $force);
        }
    }

    /**
     * Publish specific tagged configurations.
     *
     * @param  array<string>  $tags  Configuration tags to publish
     * @param  bool  $force  Whether to overwrite existing files
     */
    protected function publishTaggedConfigurations(array $tags, bool $force): void
    {
        foreach ($tags as $tag) {
            if (! isset($this->configFiles[$tag])) {
                $this->warn("Unknown configuration tag: {$tag}");

                continue;
            }

            $config = $this->configFiles[$tag];
            $this->publishConfiguration($tag, $config, $force);
        }
    }

    /**
     * Publish a single configuration file.
     *
     * @param  string  $tag  Configuration tag
     * @param  array<string, string>  $config  Configuration details
     * @param  bool  $force  Whether to overwrite existing files
     */
    protected function publishConfiguration(string $tag, array $config, bool $force): void
    {
        $sourcePath = $this->getPackageConfigPath($config['source']);
        $destinationPath = config_path(basename($config['destination']));

        // Check if source file exists
        if (! File::exists($sourcePath)) {
            $this->warn("Source configuration file not found: {$sourcePath}");

            return;
        }

        // Check if destination exists and force is not set
        if (File::exists($destinationPath) && ! $force) {
            if (! $this->confirm("Configuration file {$config['destination']} already exists. Overwrite?")) {
                $this->info("Skipped: {$config['description']}");

                return;
            }
        }

        // Copy the configuration file
        File::copy($sourcePath, $destinationPath);

        $this->info("Published: {$config['description']} → {$destinationPath}");
    }

    /**
     * Get the package configuration file path.
     *
     * @param  string  $relativePath  Relative path to configuration file
     * @return string Full path to package configuration file
     */
    protected function getPackageConfigPath(string $relativePath): string
    {
        return __DIR__.'/../../../'.$relativePath;
    }

    /**
     * Display post-publish instructions.
     */
    protected function displayPostPublishInstructions(): void
    {
        $this->info('Next steps:');
        $this->line('1. Review the published configuration files in config/');
        $this->line('2. Update environment variables in your .env file');
        $this->line('3. Run: php artisan form-security:config:validate');
        $this->line('4. Run: php artisan form-security:config:cache');

        $this->newLine();
        $this->info('Available commands:');
        $this->line('• form-security:config:show     - Display current configuration');
        $this->line('• form-security:config:validate - Validate configuration');
        $this->line('• form-security:config:cache    - Cache configuration for performance');
        $this->line('• form-security:config:clear    - Clear configuration cache');
        $this->line('• form-security:features:list   - List available features');
        $this->line('• form-security:features:toggle - Toggle feature on/off');
    }
}
