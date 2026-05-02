<?php

namespace App\Console\Commands;

use App\Services\Catalog\InventoryRecomputeService;
use App\Services\Catalog\PricingExporter;
use Illuminate\Console\Command;

class ExportPricingCommand extends Command
{
    protected $signature = 'catalog:export-pricing';

    protected $description = 'Recompute every inventory price, write a MyPricing-format CSV, and update last_exported_price baselines';

    public function handle(): int
    {
        $recompute = (new InventoryRecomputeService)->recompute();
        $this->info(sprintf(
            'Recompute: %d rows processed, %d priced, %d skipped (no market price).',
            $recompute->rowsProcessed,
            $recompute->rowsWithResult,
            $recompute->rowsNullResult,
        ));

        $export = (new PricingExporter)->export();
        $this->info(sprintf(
            'Export: %d rows written. File: %s',
            $export->rowsWritten,
            $export->file->file_path,
        ));

        return self::SUCCESS;
    }
}
