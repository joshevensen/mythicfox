<?php

namespace App\Services\Catalog;

use App\Models\File;

class PricingExportResult
{
    public function __construct(
        public readonly File $file,
        public readonly int $rowsWritten,
    ) {}
}
