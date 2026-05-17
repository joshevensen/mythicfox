<?php

namespace App\Services\Catalog;

use App\Models\File;

class PricingCustomExportResult
{
    /**
     * @param  list<int>  $productIds
     */
    public function __construct(
        public readonly File $file,
        public readonly int $rowsProcessed,
        public readonly int $productsTouched,
        public readonly array $productIds = [],
    ) {}
}
