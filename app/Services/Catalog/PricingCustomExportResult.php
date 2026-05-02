<?php

namespace App\Services\Catalog;

use App\Models\File;

class PricingCustomExportResult
{
    public function __construct(
        public readonly File $file,
        public readonly int $rowsProcessed,
        public readonly int $productsTouched,
    ) {}
}
