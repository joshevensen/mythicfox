<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogUpserter;
use App\Services\Catalog\PricingCustomExportImporter;
use Illuminate\Console\Command;

class ImportPricingCustomExportCommand extends Command
{
    protected $signature = 'catalog:import-pricing-custom-export {path}';

    protected $description = 'Import a TCGPlayer PricingCustomExport CSV (catalog seed + market-price refresh)';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_readable($path)) {
            $this->error("Cannot read CSV at [{$path}]");

            return self::FAILURE;
        }

        $importer = new PricingCustomExportImporter(new CatalogUpserter);
        $result = $importer->import($path);

        $this->info(sprintf(
            'Imported %d rows, touched %d product(s). File: %s',
            $result->rowsProcessed,
            $result->productsTouched,
            $result->file->file_path,
        ));

        return self::SUCCESS;
    }
}
