<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\File;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
});

function seedInventoryRow(int $market = 250, ?int $low = 225, ?int $override = null, ?int $lastExported = null): Inventory
{
    $product = Product::factory()->create(['name' => fake()->unique()->word()]);
    $set = CardSet::factory()->create(['product_id' => $product->id]);
    $card = Card::factory()->create([
        'set_id' => $set->id,
        'market_price' => $market,
        'low_price' => $low,
    ]);

    return Inventory::factory()->create([
        'card_id' => $card->id,
        'quantity' => 4,
        'calculated_price' => null,
        'override_price' => $override,
        'last_exported_price' => $lastExported,
    ]);
}

test('POST recompute runs the algorithm and updates calculated_price', function () {
    $inv = seedInventoryRow(market: 250, low: 225);

    expect($inv->calculated_price)->toBeNull();

    $this->postJson(route('inventory.export.recompute'))
        ->assertOk()
        ->assertJsonPath('rows_processed', 1)
        ->assertJsonPath('rows_with_result', 1);

    expect($inv->fresh()->calculated_price)->not->toBeNull();
});

test('GET preview returns only changed rows when show_all is off', function () {
    // Row A: current effective ≠ last_exported → changed.
    $changed = seedInventoryRow(market: 250, low: 225, override: 199, lastExported: 999);
    // Row B: matches the last export → not changed.
    $unchanged = seedInventoryRow(market: 250, low: 225, override: 500, lastExported: 500);

    $response = $this->getJson(route('inventory.export.preview'))->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($changed->id);
    expect($ids)->not->toContain($unchanged->id);
});

test('GET preview returns all rows when show_all is on', function () {
    $changed = seedInventoryRow(override: 199, lastExported: 999);
    $unchanged = seedInventoryRow(override: 500, lastExported: 500);

    $response = $this->getJson(route('inventory.export.preview', ['show_all' => '1']))
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($changed->id, $unchanged->id);
});

test('first_export flag is true when no row has last_exported_price', function () {
    seedInventoryRow();

    $this->getJson(route('inventory.export.preview'))
        ->assertOk()
        ->assertJsonPath('meta.first_export', true);
});

test('first_export flag flips to false once any row has been exported', function () {
    seedInventoryRow();
    seedInventoryRow(lastExported: 200);

    $this->getJson(route('inventory.export.preview'))
        ->assertOk()
        ->assertJsonPath('meta.first_export', false);
});

test('POST download writes a files row, returns CSV with MyPricing headers, and updates last_exported_price', function () {
    $inv = seedInventoryRow(market: 250, low: 225, override: 199);

    $response = $this->post(route('inventory.export.download'));
    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $body = $response->streamedContent();
    $firstLine = strtok($body, "\n");

    expect($firstLine)->toContain('TCGplayer Id')
        ->and($firstLine)->toContain('TCG Marketplace Price');

    expect(File::query()->where('type', 'export')->count())->toBe(1);
    expect($inv->fresh()->last_exported_price)->toBe(199);
});

test('cancelling without download leaves last_exported_price unchanged but calculated_price updated', function () {
    $inv = seedInventoryRow(market: 250, low: 225, lastExported: 999);

    $beforeCalculated = $inv->calculated_price;
    $beforeLastExported = $inv->last_exported_price;

    // Recompute (always happens, even if user cancels next).
    $this->postJson(route('inventory.export.recompute'))->assertOk();

    $fresh = $inv->fresh();

    expect($fresh->calculated_price)->not->toBe($beforeCalculated);
    expect($fresh->last_exported_price)->toBe($beforeLastExported);

    // Re-running preview still shows the same diff against the old baseline.
    $this->getJson(route('inventory.export.preview'))
        ->assertOk()
        ->assertJsonPath('meta.changed_count', 1);
});

test('download failure leaves last_exported_price unchanged', function () {
    $inv = seedInventoryRow(market: 250, low: 225, lastExported: 100);

    Storage::shouldReceive('put')->andReturn(false);

    // The PricingExporter throws on Storage::put() failure; the controller
    // surfaces it as a 500 (or wherever Laravel's exception handler routes
    // it). The key invariant is that last_exported_price is not touched.
    $this->withoutExceptionHandling();

    try {
        $this->post(route('inventory.export.download'));
        expect(true)->toBeFalse('Expected the download to fail.');
    } catch (RuntimeException) {
        // expected
    }

    expect($inv->fresh()->last_exported_price)->toBe(100);
});
