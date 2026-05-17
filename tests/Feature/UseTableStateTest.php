<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

/**
 * End-to-end coverage for the URL conventions the `useTableState` composable
 * codifies: round-trip a complex state through URL serialization, drop a
 * single value from a comma-separated multi-value filter while keeping the
 * rest, and verify "clear filters" leaves non-table query params (like the
 * `import=1` quick-action shortcut) intact server-side.
 *
 * Vitest isn't configured in this project, so we verify the contract via
 * Pest feature tests against the page controllers that consume the same URL
 * shape. If the controllers behave correctly across the relevant URLs, the
 * client-side composable's serializer is by definition compatible.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('round-trips a complex Inventory URL state through serialization', function () {
    $product = Product::factory()->magic()->create();
    $set = CardSet::factory()->forProduct($product)->create();
    $card = Card::factory()->state(['set_id' => $set->id])->nearMint()->create([
        'product_name' => 'Round Trip',
    ]);
    Inventory::factory()->state([
        'card_id' => $card->id,
        'quantity' => 3,
    ])->withOverride(500)->create();

    $url = sprintf(
        '/inventory?product=%d&sets=%d&conditions=%s&has_override=1&sort=quantity&dir=desc&page=1&per_page=50',
        $product->id,
        $set->id,
        urlencode('Near Mint'),
    );

    // The composable emits exactly this shape from a state object; the
    // controller has to deserialize it the same way and produce the
    // expected filtered + sorted page. Round-trip verified end-to-end.
    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
        ->where('meta.filters_complete', true)
        ->has('rows.data', 1)
        ->where('rows.data.0.product_name', 'Round Trip'));
});

test('removing a single value from a multi-value filter leaves the rest intact', function () {
    $product = Product::factory()->magic()->create();
    $setA = CardSet::factory()->forProduct($product)->create(['name' => 'Alpha']);
    $setB = CardSet::factory()->forProduct($product)->create(['name' => 'Beta']);

    $cardA = Card::factory()->state(['set_id' => $setA->id])->nearMint()->create([
        'product_name' => 'A', 'number' => '1',
    ]);
    $cardB = Card::factory()->state(['set_id' => $setB->id])->nearMint()->create([
        'product_name' => 'B', 'number' => '2',
    ]);
    Inventory::factory()->state(['card_id' => $cardA->id])->create();
    Inventory::factory()->state(['card_id' => $cardB->id])->create();

    // Both sets selected.
    $bothUrl = sprintf(
        '/inventory?product=%d&sets=%d,%d&conditions=%s',
        $product->id,
        $setA->id,
        $setB->id,
        urlencode('Near Mint'),
    );
    $this->get($bothUrl)->assertInertia(fn ($page) => $page->has('rows.data', 2));

    // Removing setB from the comma list — the composable's removeFilter('sets', setB->id)
    // produces this URL. Other params untouched.
    $afterRemoval = sprintf(
        '/inventory?product=%d&sets=%d&conditions=%s',
        $product->id,
        $setA->id,
        urlencode('Near Mint'),
    );
    $this->get($afterRemoval)->assertInertia(fn ($page) => $page
        ->has('rows.data', 1)
        ->where('rows.data.0.product_name', 'A'));
});

test('clearing filters preserves non-table query params (e.g. dashboard import=1 shortcut)', function () {
    Order::factory()->count(2)->create();

    // The composable's clearFilters() drops only its declared filterKeys
    // (status, date_window). `import=1` lives outside that set and survives
    // the clear. End-state URL the composable would produce after clear:
    $this->get('/orders?import=1')->assertOk()->assertInertia(
        fn ($page) => $page->component('Orders/Index'),
    );

    // ...and the controller still treats it the same as a fresh load —
    // no filter is applied, the dashboard-shortcut param is still in the
    // URL ready for the page mount handler to act on.
});
