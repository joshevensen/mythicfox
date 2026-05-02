<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogUpserter;
use App\Services\Catalog\MyPricingImporter;
use App\Services\Catalog\MyPricingImportMode;
use Illuminate\Console\Command;

class ImportMyPricingCommand extends Command
{
    protected $signature = 'catalog:import-mypricing {path} {--mode=reconcile} {--force}';

    protected $description = 'Import a TCGPlayer MyPricing CSV in bootstrap (one-shot inventory seed) or reconciliation (read-only drift report) mode';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $modeArg = (string) $this->option('mode');

        $mode = MyPricingImportMode::tryFrom($modeArg);
        if ($mode === null) {
            $this->error("Unknown mode [{$modeArg}]. Use 'bootstrap' or 'reconcile'.");

            return self::FAILURE;
        }

        if (! is_readable($path)) {
            $this->error("Cannot read CSV at [{$path}]");

            return self::FAILURE;
        }

        $importer = new MyPricingImporter(new CatalogUpserter);

        try {
            $result = $importer->import($path, $mode, force: (bool) $this->option('force'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($mode === MyPricingImportMode::Bootstrap) {
            $this->info(sprintf(
                'Bootstrap: %d rows processed, %d inventory rows written. File: %s',
                $result->rowsProcessed,
                $result->inventoryRowsWritten,
                $result->file->file_path,
            ));
        } else {
            $this->info(sprintf(
                'Reconcile: %d rows processed, %d discrepancies, %d missing locally, %d local-only.',
                $result->rowsProcessed,
                count($result->discrepancies),
                count($result->missingLocally),
                count($result->localOnly),
            ));
        }

        return self::SUCCESS;
    }
}
