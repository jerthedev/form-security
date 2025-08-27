<?php

namespace JTD\FormSecurity\Console\Commands;

use Illuminate\Console\Command;
use JTD\FormSecurity\Services\GeoLite2ImportService;
use Exception;

class ImportGeoLite2Command extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'form-security:import-geolite2 
                            {--locations= : Path to GeoLite2 locations CSV file}
                            {--blocks= : Path to GeoLite2 IPv4 blocks CSV file}
                            {--chunk-size=1000 : Number of records to process per chunk}
                            {--memory-limit=128 : Memory limit in MB}
                            {--clear : Clear existing data before import}';

    /**
     * The console command description.
     */
    protected $description = 'Import GeoLite2 database files into the application';

    /**
     * Execute the console command.
     */
    public function handle(GeoLite2ImportService $importService): int
    {
        $locationsPath = $this->option('locations');
        $blocksPath = $this->option('blocks');
        $chunkSize = (int) $this->option('chunk-size');
        $memoryLimit = (int) $this->option('memory-limit');
        $clear = $this->option('clear');

        if (!$locationsPath && !$blocksPath) {
            $this->error('Please specify at least one file to import using --locations or --blocks');
            return 1;
        }

        // Configure import service
        $importService->setChunkSize($chunkSize)->setMemoryLimit($memoryLimit);

        // Clear existing data if requested
        if ($clear) {
            if ($this->confirm('This will delete all existing GeoLite2 data. Continue?')) {
                $this->info('Clearing existing GeoLite2 data...');
                $importService->clearData();
                $this->info('Data cleared successfully.');
            } else {
                $this->info('Import cancelled.');
                return 0;
            }
        }

        $totalStartTime = microtime(true);

        try {
            // Import locations if specified
            if ($locationsPath) {
                $this->info("Importing GeoLite2 locations from: {$locationsPath}");
                $this->importWithProgress($importService, 'importLocations', $locationsPath);
            }

            // Import IPv4 blocks if specified
            if ($blocksPath) {
                $this->info("Importing GeoLite2 IPv4 blocks from: {$blocksPath}");
                $this->importWithProgress($importService, 'importIPv4Blocks', $blocksPath);
            }

            $totalDuration = microtime(true) - $totalStartTime;
            
            // Show final statistics
            $stats = $importService->getImportStats();
            $this->info('Import completed successfully!');
            $this->table(['Metric', 'Value'], [
                ['Total Duration', number_format($totalDuration, 2) . ' seconds'],
                ['Locations Count', number_format($stats['locations_count'])],
                ['IPv4 Blocks Count', number_format($stats['ipv4_blocks_count'])],
                ['Last Updated', $stats['last_updated']],
            ]);

            return 0;

        } catch (Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Import with progress bar
     */
    protected function importWithProgress(GeoLite2ImportService $importService, string $method, string $filePath): void
    {
        $progressBar = null;
        $lastProcessed = 0;

        $progressCallback = function (array $stats) use (&$progressBar, &$lastProcessed) {
            if (!$progressBar) {
                $progressBar = $this->output->createProgressBar();
                $progressBar->setFormat('debug');
            }

            $newProcessed = $stats['processed'] - $lastProcessed;
            if ($newProcessed > 0) {
                $progressBar->advance($newProcessed);
                $lastProcessed = $stats['processed'];
            }
        };

        $stats = $importService->$method($filePath, $progressCallback);

        if ($progressBar) {
            $progressBar->finish();
            $this->newLine();
        }

        // Display import statistics
        $this->table(['Metric', 'Value'], [
            ['Processed', number_format($stats['processed'])],
            ['Imported', number_format($stats['imported'])],
            ['Skipped', number_format($stats['skipped'])],
            ['Errors', number_format($stats['errors'])],
            ['Duration', number_format($stats['duration'], 2) . ' seconds'],
            ['Rate', number_format($stats['processed'] / max($stats['duration'], 0.001), 0) . ' records/sec'],
        ]);
    }
}
