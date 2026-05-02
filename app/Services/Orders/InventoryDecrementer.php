<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Decrements catalog inventory for newly-created order_items. The match key is
 * the snapshot fields on order_items (product_line, set_name, product_name,
 * number, condition) joined to products → sets → cards. Decrement is floored
 * at zero via Postgres GREATEST(0, ...) so concurrent imports can't go
 * negative. Canceled orders are skipped entirely per
 * docs/order-schema.md#5-decrement-inventory.
 *
 * Idempotency at the order level: 20-008 only invokes decrement() for
 * newly-inserted line items, never for existing ones, so re-imports never
 * double-decrement.
 */
class InventoryDecrementer
{
    /**
     * @param  Collection<int, OrderItem>  $newItems
     */
    public function decrement(Order $order, Collection $newItems): InventoryDecrementResult
    {
        $result = new InventoryDecrementResult;

        if ($order->tcgplayer_status === 'Canceled') {
            return $result;
        }

        foreach ($newItems as $item) {
            $cardId = DB::table('cards')
                ->join('sets', 'sets.id', '=', 'cards.set_id')
                ->join('products', 'products.id', '=', 'sets.product_id')
                ->where('products.name', $item->product_line)
                ->where('sets.name', $item->set_name)
                ->where('cards.product_name', $item->product_name)
                ->where('cards.number', $item->number)
                ->where('cards.condition', $item->condition)
                ->value('cards.id');

            if ($cardId === null) {
                $result->unmatched++;
                Log::warning('InventoryDecrementer: no card match', [
                    'order' => $order->tcgplayer_order_number,
                    'snapshot' => [
                        'product_line' => $item->product_line,
                        'set_name' => $item->set_name,
                        'product_name' => $item->product_name,
                        'number' => $item->number,
                        'condition' => $item->condition,
                    ],
                ]);

                continue;
            }

            $affected = DB::update(
                'UPDATE inventory SET quantity = GREATEST(0, quantity - ?), updated_at = NOW() WHERE card_id = ?',
                [$item->quantity, $cardId]
            );

            if ($affected === 0) {
                $result->unmatchedNoInventory++;
                Log::warning('InventoryDecrementer: no inventory row for card', [
                    'order' => $order->tcgplayer_order_number,
                    'card_id' => $cardId,
                ]);

                continue;
            }

            $result->decremented++;
        }

        return $result;
    }
}
