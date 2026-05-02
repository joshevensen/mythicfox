<?php

namespace App\Services\Catalog;

use App\Models\File;

class MyPricingResult
{
    /**
     * @param  list<array{tcgplayer_id:int, local_quantity:int, csv_quantity:int, local_effective_price:?int, csv_marketplace_price:?int}>  $discrepancies
     * @param  list<int>  $missingLocally  tcgplayer_id values present in CSV but not in cards
     * @param  list<int>  $localOnly  tcgplayer_id values in inventory but not in CSV
     */
    public function __construct(
        public readonly File $file,
        public readonly MyPricingImportMode $mode,
        public readonly int $rowsProcessed,
        public readonly int $inventoryRowsWritten,
        public readonly array $discrepancies,
        public readonly array $missingLocally,
        public readonly array $localOnly,
    ) {}
}
