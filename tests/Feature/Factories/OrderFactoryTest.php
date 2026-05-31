<?php

use App\Models\Card;
use App\Models\File;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Set;

test('Order factory produces a fully populated row with seller-id-prefixed order number', function () {
    config(['services.tcgplayer.seller_id' => '623394e9']);

    $order = Order::factory()->create();

    expect($order->tcgplayer_order_number)->toStartWith('623394E9-');
    expect($order->tcgplayer_status)->toBe('Completed - Paid');
    expect($order->buyer_name)->toContain($order->buyer_firstname);
    expect($order->buyer_paid)->toBeTrue();
    expect($order->total_amount)->toBe($order->product_amount + $order->shipping_amount);
});

test('canceled() state nulls all ShippingExport-only fields', function () {
    $order = Order::factory()->canceled()->create();

    expect($order->tcgplayer_status)->toBe('Canceled');
    expect($order->address1)->toBeNull();
    expect($order->state)->toBeNull();
    expect($order->item_count)->toBeNull();
    expect($order->product_weight)->toBeNull();
    expect($order->shipping_method)->toBeNull();
    expect($order->tracking_number)->toBeNull();
    expect($order->carrier)->toBeNull();
});

test('canceled() state round-trips cleanly through model casts', function () {
    $order = Order::factory()->canceled()->create();
    $reloaded = $order->fresh();

    expect($reloaded->tcgplayer_status)->toBe('Canceled');
    expect($reloaded->buyer_paid)->toBeBool();
    expect($reloaded->order_date->toDateString())->toMatch('/\d{4}-\d{2}-\d{2}/');
});

test('shipped() state populates tracking_number and carrier', function () {
    $order = Order::factory()->shipped()->create();

    expect($order->tracking_number)->toBeString()->not->toBeEmpty();
    expect($order->carrier)->toBe('USPS');
});

test('OrderItemFactory produces line items consistent with the snapshot fields', function () {
    $item = OrderItem::factory()->create();

    expect($item->product_line)->toBeIn(['Magic', 'Lorcana', 'Flesh and Blood']);
    expect($item->total_price)->toBe($item->unit_price * $item->quantity);
    expect($item->tcgplayer_sku_id)->toBeInt();
});

test('OrderItemFactory::withoutPrice nulls unit_price and total_price', function () {
    $item = OrderItem::factory()->withoutPrice()->create();

    expect($item->unit_price)->toBeNull();
    expect($item->total_price)->toBeNull();
});

test('OrderItemFactory::forCard copies catalog fields into the snapshot', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create(['name' => 'Wilds of Eldraine']);
    $card = Card::factory()->create([
        'set_id' => $set->id,
        'name' => 'Edgewall Innkeeper',
        'number' => '97/204',
        'rarity' => 'R',
    ]);

    $item = OrderItem::factory()->forCard($card)->create();

    expect($item->product_line)->toBe('Magic');
    expect($item->set_name)->toBe('Wilds of Eldraine');
    expect($item->product_name)->toBe('Edgewall Innkeeper');
    expect($item->number)->toBe('97/204');
    expect($item->rarity)->toBe('R');
    expect($item->condition)->toBe('Near Mint');
});

test('FileFactory produces a row matching the path convention', function () {
    $file = File::factory()->create();

    expect($file->type)->toBe('import');
    expect($file->file_path)->toMatch('#^imports/orders/\d{4}/\d{2}/[0-9A-Z]{26}-#');
    expect($file->uploaded_at)->not->toBeNull();
});

test('Order::factory()->has(OrderItem::factory()->count(3)) wires items via afterCreating', function () {
    $order = Order::factory()->has(OrderItem::factory()->count(3), 'items')->create();

    expect($order->items()->count())->toBe(3);
});
