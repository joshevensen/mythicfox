<?php

namespace App\Services\Catalog;

class InventoryRecomputeResult
{
    /**
     * @param  list<array{product:string, age_days:?int, inventory_rows:int}>  $stale
     */
    public function __construct(
        public readonly int $rowsProcessed,
        public readonly int $rowsWithResult,
        public readonly int $rowsNullResult,
        public readonly array $stale,
    ) {}
}
