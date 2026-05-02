<?php

use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

test('Order::create round-trips with all fields populated', function () {
    $order = Order::create([
        'tcgplayer_order_number' => '623394E9-23CAFE-565FC',
        'tcgplayer_status' => 'Completed - Paid',
        'buyer_firstname' => 'Thomas',
        'buyer_lastname' => 'Birch',
        'buyer_name' => 'Thomas Birch',
        'address1' => '34 Horton Heights Drive',
        'address2' => null,
        'city' => 'Newfield',
        'state' => 'NY',
        'postal_code' => '14867',
        'country' => 'US',
        'order_date' => Carbon::parse('2025-11-14'),
        'shipping_method' => 'Standard (7-10 days)',
        'item_count' => 1,
        'product_weight' => '0.07',
        'product_amount' => 690,
        'shipping_amount' => 199,
        'total_amount' => 889,
        'buyer_paid' => true,
        'tracking_number' => null,
        'carrier' => null,
        'imported_at' => Carbon::now(),
    ]);

    $reloaded = $order->fresh();

    expect($reloaded->tcgplayer_order_number)->toBe('623394E9-23CAFE-565FC');
    expect($reloaded->buyer_paid)->toBeTrue();
    expect($reloaded->product_amount)->toBe(690);
    expect($reloaded->total_amount)->toBe(889);
    expect($reloaded->state)->toBe('NY');
    expect($reloaded->order_date->toDateString())->toBe('2025-11-14');
    expect((float) $reloaded->product_weight)->toBe(0.07);
});

test('tcgplayer_order_number unique constraint is enforced', function () {
    Order::create([
        'tcgplayer_order_number' => '623394E9-AAAAAA-BBBBB',
        'tcgplayer_status' => 'Completed - Paid',
        'buyer_name' => 'Test Buyer',
        'order_date' => Carbon::parse('2026-01-01'),
        'product_amount' => 100,
        'shipping_amount' => 0,
        'total_amount' => 100,
        'buyer_paid' => true,
        'imported_at' => Carbon::now(),
    ]);

    expect(fn () => Order::create([
        'tcgplayer_order_number' => '623394E9-AAAAAA-BBBBB',
        'tcgplayer_status' => 'Canceled',
        'buyer_name' => 'Other Buyer',
        'order_date' => Carbon::parse('2026-01-02'),
        'product_amount' => 200,
        'shipping_amount' => 0,
        'total_amount' => 200,
        'buyer_paid' => false,
        'imported_at' => Carbon::now(),
    ]))->toThrow(QueryException::class);
});
