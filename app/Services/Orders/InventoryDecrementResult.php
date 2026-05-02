<?php

namespace App\Services\Orders;

class InventoryDecrementResult
{
    public function __construct(
        public int $decremented = 0,
        public int $unmatched = 0,
        public int $unmatchedNoInventory = 0,
    ) {}
}
