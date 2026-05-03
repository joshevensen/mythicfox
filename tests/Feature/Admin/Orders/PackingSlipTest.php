<?php

use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('per-order packing-slip route returns 200 for an existing order', function () {
    $order = Order::factory()->create([
        'tcgplayer_order_number' => 'TEST-ORDER-AAA',
    ]);

    $this->get(route('orders.packing-slip.show', $order))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Orders/PackingSlip')
                ->has('orders', 1)
                ->where('orders.0.tcgplayer_order_number', 'TEST-ORDER-AAA')
        );
});

test('per-order packing-slip route returns 404 for an unknown order number', function () {
    $this->get('/orders/NONEXISTENT-ORDER/packing-slip')->assertNotFound();
});

test('bulk packing-slip route returns 200 with valid IDs', function () {
    $a = Order::factory()->create(['tcgplayer_order_number' => 'BULK-001']);
    $b = Order::factory()->create(['tcgplayer_order_number' => 'BULK-002']);

    $this->get(route('orders.packing-slip.bulk', ['ids' => "{$a->tcgplayer_order_number},{$b->tcgplayer_order_number}"]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Orders/PackingSlip')
                ->has('orders', 2)
        );
});

test('bulk packing-slip route returns 404 when any ID is missing', function () {
    Order::factory()->create(['tcgplayer_order_number' => 'BULK-001']);

    $this->get(route('orders.packing-slip.bulk', ['ids' => 'BULK-001,DOES-NOT-EXIST']))
        ->assertNotFound();
});

test('bulk packing-slip route returns 404 when no IDs are given', function () {
    $this->get(route('orders.packing-slip.bulk'))->assertNotFound();
});

test('bulk packing-slip route preserves the order in which IDs were requested', function () {
    Order::factory()->create(['tcgplayer_order_number' => 'BULK-A']);
    Order::factory()->create(['tcgplayer_order_number' => 'BULK-B']);
    Order::factory()->create(['tcgplayer_order_number' => 'BULK-C']);

    $this->get(route('orders.packing-slip.bulk', ['ids' => 'BULK-C,BULK-A,BULK-B']))
        ->assertInertia(
            fn ($page) => $page
                ->where('orders.0.tcgplayer_order_number', 'BULK-C')
                ->where('orders.1.tcgplayer_order_number', 'BULK-A')
                ->where('orders.2.tcgplayer_order_number', 'BULK-B')
        );
});

test('orders index page renders per-row print URLs with the correct order number', function () {
    $order = Order::factory()->create([
        'tcgplayer_order_number' => 'RENDER-LOOK-ME-UP',
        'order_date' => now()->subDays(2),
    ]);

    $response = $this->get(route('orders.index'));

    $response->assertOk();
    $response->assertSee($order->tcgplayer_order_number);

    $source = file_get_contents(resource_path('js/pages/Orders/Index.vue'));
    expect($source)
        ->toContain('printSlipUrl(row.tcgplayer_order_number)')
        ->toContain('tcgplayerUrl(row.tcgplayer_order_number)')
        ->toContain('https://sellerportal.tcgplayer.com/orders/');
});

test('bulk action bar wires the bulk-print handler in the Vue source', function () {
    $source = file_get_contents(resource_path('js/pages/Orders/Index.vue'));

    expect($source)
        ->toContain('@click="onBulkPrint(selectedKeys)"')
        ->toContain('Print packing slips')
        ->toContain('packingSlipRoutes.bulk.url')
        ->toContain('BULK_PRINT_HARD_CAP')
        ->toContain('BULK_PRINT_CONFIRM_THRESHOLD');
});

test('per-order packing-slip stub view explains that phase 70 will fill it in', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.packing-slip.show', $order))->assertInertia(
        fn ($page) => $page->where(
            'placeholder_message',
            'Packing slip rendering is implemented in phase 70.',
        )
    );
});

test('routes use Wayfinder typed helpers via /orders/{order}/packing-slip and /orders/print', function () {
    expect(route('orders.packing-slip.show', ['order' => 'ABC123']))
        ->toContain('/orders/ABC123/packing-slip');
    expect(route('orders.packing-slip.bulk', ['ids' => 'ABC,DEF']))
        ->toContain('/orders/print');
});
