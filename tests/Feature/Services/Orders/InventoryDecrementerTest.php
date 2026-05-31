<?php

use App\Models\Card;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Set;
use App\Services\Orders\InventoryDecrementer;
use App\Services\Orders\InventoryDecrementResult;
use Illuminate\Support\Carbon;

function buildSnapshotItem(Order $order, array $overrides = []): OrderItem
{
    return OrderItem::create(array_merge([
        'order_id' => $order->id,
        'product_line' => 'Magic',
        'set_name' => 'Test Set',
        'product_name' => 'Test Card',
        'number' => '1',
        'rarity' => 'C',
        'condition' => 'Near Mint',
        'quantity' => 1,
    ], $overrides));
}

function buildOrder(string $status = 'Completed - Paid'): Order
{
    return Order::create([
        'tcgplayer_order_number' => '623394E9-'.fake()->bothify('??????').'-'.fake()->bothify('?????'),
        'tcgplayer_status' => $status,
        'buyer_name' => 'Test',
        'order_date' => Carbon::parse('2026-01-01'),
        'product_amount' => 100,
        'shipping_amount' => 0,
        'total_amount' => 100,
        'buyer_paid' => true,
        'imported_at' => Carbon::now(),
    ]);
}

function seedCatalog(): array
{
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create(['name' => 'Test Set']);
    $card = Card::factory()->create([
        'set_id' => $set->id,
        'product_name' => 'Test Card',
        'number' => '1',
        'rarity' => 'C',
        'condition' => 'Near Mint',
    ]);
    $inventory = Inventory::factory()->create([
        'card_id' => $card->id,
        'quantity' => 10,
    ]);

    return compact('product', 'set', 'card', 'inventory');
}

test('decrements matched inventory and reports counts', function () {
    ['inventory' => $inventory] = seedCatalog();
    $order = buildOrder();
    $item = buildSnapshotItem($order, ['quantity' => 3]);

    $result = (new InventoryDecrementer)->decrement($order, collect([$item]));

    expect($result->decremented)->toBe(1);
    expect($result->totalUnmatched())->toBe(0);
    expect($inventory->refresh()->quantity)->toBe(7);
});

test('floors at zero when ordered quantity exceeds inventory', function () {
    ['inventory' => $inventory] = seedCatalog();
    $order = buildOrder();
    $item = buildSnapshotItem($order, ['quantity' => 100]);

    (new InventoryDecrementer)->decrement($order, collect([$item]));

    expect($inventory->refresh()->quantity)->toBe(0);
});

test('canceled order produces zero decrements regardless of items', function () {
    ['inventory' => $inventory] = seedCatalog();
    $order = buildOrder('Canceled');
    $item = buildSnapshotItem($order, ['quantity' => 5]);

    $result = (new InventoryDecrementer)->decrement($order, collect([$item]));

    expect($result->decremented)->toBe(0);
    expect($inventory->refresh()->quantity)->toBe(10);
});

test('unknown product (no catalog match) increments unmatched counter, others decrement', function () {
    ['inventory' => $inventory] = seedCatalog();
    $order = buildOrder();
    $matched = buildSnapshotItem($order, ['quantity' => 2]);
    $unknown = buildSnapshotItem($order, ['product_name' => 'Nonexistent', 'quantity' => 5]);

    $result = (new InventoryDecrementer)->decrement($order, collect([$matched, $unknown]));

    expect($result->decremented)->toBe(1);
    expect($result->unmatched)->toBe(1);
    expect($inventory->refresh()->quantity)->toBe(8);
});

test('card with no inventory row increments the no-inventory counter', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create(['name' => 'Test Set']);
    Card::factory()->create([
        'set_id' => $set->id,
        'product_name' => 'Test Card',
        'number' => '1',
        'condition' => 'Near Mint',
    ]); // no Inventory row

    $order = buildOrder();
    $item = buildSnapshotItem($order, ['quantity' => 2]);

    $result = (new InventoryDecrementer)->decrement($order, collect([$item]));

    expect($result->decremented)->toBe(0);
    expect($result->unmatchedNoInventory)->toBe(1);
    expect($result->totalUnmatched())->toBe(1);
});

test('totalUnmatched aggregates both unmatched buckets', function () {
    $result = new InventoryDecrementResult(
        decremented: 5,
        unmatched: 2,
        unmatchedNoInventory: 3,
    );

    expect($result->totalUnmatched())->toBe(5);
});
