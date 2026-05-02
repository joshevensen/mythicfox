<?php

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;

function freshOrder(): Order
{
    return Order::create([
        'tcgplayer_order_number' => '623394E9-EEEEEE-FFFFF',
        'tcgplayer_status' => 'Completed - Paid',
        'buyer_name' => 'Test Buyer',
        'order_date' => Carbon::parse('2026-02-15'),
        'product_amount' => 1500,
        'shipping_amount' => 199,
        'total_amount' => 1699,
        'buyer_paid' => true,
        'imported_at' => Carbon::now(),
    ]);
}

test('OrderItem belongs to Order and Order has many items', function () {
    $order = freshOrder();

    $itemA = OrderItem::create([
        'order_id' => $order->id,
        'product_line' => 'Magic',
        'set_name' => 'Wilds of Eldraine',
        'product_name' => 'Edgewall Innkeeper',
        'number' => '97/204',
        'rarity' => 'R',
        'condition' => 'Near Mint',
        'quantity' => 2,
        'unit_price' => 250,
        'total_price' => 500,
        'tcgplayer_sku_id' => 5012345,
    ]);

    $itemB = OrderItem::create([
        'order_id' => $order->id,
        'product_line' => 'Magic',
        'set_name' => 'Wilds of Eldraine',
        'product_name' => 'Bulk Common',
        'number' => '12/204',
        'rarity' => 'C',
        'condition' => 'Lightly Played',
        'quantity' => 4,
        'unit_price' => null,
        'total_price' => null,
        'tcgplayer_sku_id' => null,
    ]);

    expect($order->items()->count())->toBe(2);
    expect($order->items()->pluck('id')->all())->toContain($itemA->id, $itemB->id);
    expect($itemA->order->id)->toBe($order->id);
});

test('unit_price and total_price accept null', function () {
    $order = freshOrder();

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_line' => 'Lorcana TCG',
        'set_name' => 'The First Chapter',
        'product_name' => 'Mickey Mouse',
        'number' => '100',
        'rarity' => 'Legendary',
        'condition' => 'Near Mint',
        'quantity' => 1,
        'unit_price' => null,
        'total_price' => null,
    ]);

    expect($item->refresh()->unit_price)->toBeNull();
    expect($item->refresh()->total_price)->toBeNull();
});

test('compound condition strings round-trip verbatim', function () {
    $order = freshOrder();

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_line' => 'Flesh & Blood TCG',
        'set_name' => 'Welcome to Rathe, Unlimited',
        'product_name' => 'Boltyn',
        'number' => 'WTR001',
        'rarity' => 'Majestic',
        'condition' => 'Near Mint Unlimited Edition Rainbow Foil',
        'quantity' => 1,
    ]);

    expect($item->refresh()->condition)->toBe('Near Mint Unlimited Edition Rainbow Foil');
});

test('integer casts apply to numeric fields', function () {
    $order = freshOrder();

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_line' => 'Magic',
        'set_name' => 'Test',
        'product_name' => 'Test',
        'number' => '1',
        'rarity' => 'C',
        'condition' => 'Near Mint',
        'quantity' => 3,
        'unit_price' => 100,
        'total_price' => 300,
        'tcgplayer_sku_id' => 999999,
    ]);

    $reloaded = $item->fresh();
    expect($reloaded->quantity)->toBeInt()->toBe(3);
    expect($reloaded->unit_price)->toBeInt()->toBe(100);
    expect($reloaded->total_price)->toBeInt()->toBe(300);
    expect($reloaded->tcgplayer_sku_id)->toBeInt()->toBe(999999);
});
