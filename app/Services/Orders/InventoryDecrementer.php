<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Skeleton inventory decrementer. The full implementation lands in 20-009;
 * this class exists in 20-008 so the importer's call site is wired and tests
 * can pass with a no-op decrementer (catalog state is left untouched in
 * 20-008's tests, which assert orders/order_items behavior only).
 */
class InventoryDecrementer
{
    public function decrement(Order $order, Collection $newItems): InventoryDecrementResult
    {
        // 20-009 replaces this with the real catalog-match-and-decrement logic.
        return new InventoryDecrementResult;
    }
}
