<?php

use App\Models\File;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

// ── Single-order route ────────────────────────────────────────

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

test('unauthenticated request redirects to login', function () {
    auth()->logout();

    $order = Order::factory()->create();

    $this->get(route('orders.packing-slip.show', $order))->assertRedirect(route('login'));
});

// ── Full order data in props ──────────────────────────────────

test('packing slip props include order number, buyer name, formatted total, and order date', function () {
    $order = Order::factory()->create([
        'tcgplayer_order_number' => 'PROP-CHECK-001',
        'buyer_name' => 'Alice Buyer',
        'total_amount' => 2345, // $23.45
        'order_date' => '2025-04-28',
    ]);

    $this->get(route('orders.packing-slip.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('orders.0.tcgplayer_order_number', 'PROP-CHECK-001')
            ->where('orders.0.buyer_name', 'Alice Buyer')
            ->where('orders.0.total_amount_formatted', '$23.45')
            ->where('orders.0.order_date', 'Apr 28, 2025')
        );
});

test('packing slip props include return address from config', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.packing-slip.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('returnAddress')
            ->where('returnAddress.name', config('brand.return_address.name'))
            ->where('returnAddress.line1', config('brand.return_address.line1'))
            ->where('returnAddress.line2', config('brand.return_address.line2'))
        );
});

test('packing slip props include recipient shipping address fields', function () {
    $order = Order::factory()->create([
        'tcgplayer_order_number' => 'ADDR-CHECK',
        'address1' => '123 Main St',
        'city' => 'Austin',
        'state' => 'TX',
        'postal_code' => '78701',
    ]);

    $this->get(route('orders.packing-slip.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('orders.0.address1', '123 Main St')
            ->where('orders.0.city', 'Austin')
            ->where('orders.0.state', 'TX')
            ->where('orders.0.postal_code', '78701')
        );
});

// ── Item rendering ────────────────────────────────────────────

test('order with 1 line item has 1 item in props', function () {
    $order = Order::factory()
        ->has(OrderItem::factory()->count(1), 'items')
        ->create();

    $this->get(route('orders.packing-slip.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.0.items', 1)
        );
});

test('order with 20 line items has 20 items in props (single-sheet)', function () {
    $order = Order::factory()
        ->has(OrderItem::factory()->count(20), 'items')
        ->create();

    $this->get(route('orders.packing-slip.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.0.items', 20)
        );
});

test('item props include product_line, product_name, set_name, condition, quantity', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_line' => 'Magic',
        'product_name' => 'Black Lotus',
        'set_name' => 'Alpha',
        'condition' => 'NM',
        'quantity' => 1,
    ]);

    $this->get(route('orders.packing-slip.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('orders.0.items.0.product_line', 'Magic')
            ->where('orders.0.items.0.product_name', 'Black Lotus')
            ->where('orders.0.items.0.set_name', 'Alpha')
            ->where('orders.0.items.0.condition', 'NM')
            ->where('orders.0.items.0.quantity', 1)
        );
});

// ── No files row created ──────────────────────────────────────

test('rendering a packing slip does not create a files row', function () {
    $order = Order::factory()->create();
    $before = File::count();

    $this->get(route('orders.packing-slip.show', $order))->assertOk();

    expect(File::count())->toBe($before);
});

// ── Bulk route ────────────────────────────────────────────────

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

// ── Orders index integration ──────────────────────────────────

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
        ->toContain('onBulkPrint(selectedKeys, selectAllMatching)')
        ->toContain('Print packing slips')
        ->toContain('packingSlipRoutes.bulk.url')
        ->toContain('BULK_PRINT_HARD_CAP')
        ->toContain('BULK_PRINT_CONFIRM_THRESHOLD');
});

test('routes use Wayfinder typed helpers via /orders/{order}/packing-slip and /orders/print', function () {
    expect(route('orders.packing-slip.show', ['order' => 'ABC123']))
        ->toContain('/orders/ABC123/packing-slip');
    expect(route('orders.packing-slip.bulk', ['ids' => 'ABC,DEF']))
        ->toContain('/orders/print');
});

// ── Bulk select-all ───────────────────────────────────────────

test('bulk endpoint with select_all=1 resolves to all orders matching the current filters', function () {
    Order::factory()->create([
        'tcgplayer_order_number' => 'KEEP-A',
        'tcgplayer_status' => 'Completed - Paid',
        'order_date' => Carbon::now()->subDays(5),
    ]);
    Order::factory()->create([
        'tcgplayer_order_number' => 'KEEP-B',
        'tcgplayer_status' => 'Completed - Paid',
        'order_date' => Carbon::now()->subDays(2),
    ]);
    Order::factory()->canceled()->create([
        'tcgplayer_order_number' => 'EXCLUDED',
        'tcgplayer_status' => 'Canceled',
        'order_date' => Carbon::now()->subDays(3),
    ]);

    $this->get(route('orders.packing-slip.bulk', [
        'select_all' => '1',
        'status' => 'Completed - Paid',
    ]))->assertOk()->assertInertia(
        fn ($page) => $page
            ->component('Orders/PackingSlip')
            ->has('orders', 2)
    );
});

test('bulk endpoint with select_all=1 honors the date_window filter signature', function () {
    Order::factory()->create([
        'tcgplayer_order_number' => 'IN-WINDOW',
        'order_date' => Carbon::now()->subDays(5),
    ]);
    Order::factory()->create([
        'tcgplayer_order_number' => 'OUT-OF-WINDOW',
        'order_date' => Carbon::now()->subDays(45),
    ]);

    $this->get(route('orders.packing-slip.bulk', [
        'select_all' => '1',
        'date_window' => '30',
    ]))->assertInertia(
        fn ($page) => $page
            ->has('orders', 1)
            ->where('orders.0.tcgplayer_order_number', 'IN-WINDOW')
    );
});

test('bulk endpoint with select_all=1 applies the same 90-day default as the orders index', function () {
    Order::factory()->create([
        'tcgplayer_order_number' => 'WITHIN-WINDOW',
        'order_date' => Carbon::now()->subDays(10),
    ]);
    Order::factory()->create([
        'tcgplayer_order_number' => 'BEYOND-WINDOW',
        'order_date' => Carbon::now()->subDays(180),
    ]);

    $this->get(route('orders.packing-slip.bulk', ['select_all' => '1']))
        ->assertInertia(
            fn ($page) => $page
                ->has('orders', 1)
                ->where('orders.0.tcgplayer_order_number', 'WITHIN-WINDOW')
        );
});

test('bulk endpoint with select_all=1 returns 404 when no orders match the filters', function () {
    $this->get(route('orders.packing-slip.bulk', [
        'select_all' => '1',
        'status' => 'Canceled',
    ]))->assertNotFound();
});

test('bulk endpoint with select_all=1 returns 413 when matching count exceeds the 100-order cap', function () {
    Order::factory()->count(101)->create([
        'tcgplayer_status' => 'Completed - Paid',
        'order_date' => Carbon::now()->subDays(2),
    ]);

    $this->get(route('orders.packing-slip.bulk', [
        'select_all' => '1',
        'status' => 'Completed - Paid',
    ]))->assertStatus(413);
});

test('bulk endpoint without ids and without select_all returns 404', function () {
    $this->get(route('orders.packing-slip.bulk'))->assertNotFound();
});

test('orders index page wires the select-all-matching flag through the bulk-actions slot', function () {
    $source = file_get_contents(resource_path('js/pages/Orders/Index.vue'));

    expect($source)
        ->toContain(
            '#bulk-actions="{ selectedKeys, selectAllMatching }"',
        )
        ->toContain('onBulkPrint(selectedKeys, selectAllMatching)')
        ->toContain('buildSelectAllUrl')
        ->toContain("select_all: '1'")
        ->toContain('FILTER_SIGNATURE_KEYS');
});
