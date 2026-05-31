<?php

use App\Models\Card;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Set;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('bulk clear-overrides clears override_price on the supplied IDs', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    $cards = Card::factory()
        ->count(3)
        ->state(['set_id' => $set->id])
        ->nearMint()
        ->state(new Sequence(
            ['product_name' => 'A', 'number' => '1'],
            ['product_name' => 'B', 'number' => '2'],
            ['product_name' => 'C', 'number' => '3'],
        ))
        ->create();

    $ids = $cards
        ->map(fn (Card $c) => Inventory::factory()
            ->state(['card_id' => $c->id])
            ->withOverride(500)
            ->create()->id)
        ->all();

    $this->postJson(route('inventory.bulk.clear-overrides'), ['ids' => $ids])
        ->assertOk()
        ->assertJsonPath('updated', 3)
        ->assertJsonPath('override_count', 0);

    expect(Inventory::whereNotNull('override_price')->count())->toBe(0);
});

test('bulk mark-out-of-stock zeroes quantity but preserves override_price', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();

    $a = Card::factory()->state(['set_id' => $set->id])->nearMint()->create([
        'product_name' => 'A', 'number' => '1',
    ]);
    $b = Card::factory()->state(['set_id' => $set->id])->nearMint()->create([
        'product_name' => 'B', 'number' => '2',
    ]);

    $invA = Inventory::factory()
        ->state(['card_id' => $a->id, 'quantity' => 5])
        ->withOverride(500)
        ->create();
    $invB = Inventory::factory()
        ->state(['card_id' => $b->id, 'quantity' => 9])
        ->create();

    $this->postJson(route('inventory.bulk.mark-out-of-stock'), [
        'ids' => [$invA->id, $invB->id],
    ])->assertOk()->assertJsonPath('updated', 2);

    expect($invA->fresh()->quantity)->toBe(0)
        ->and($invA->fresh()->override_price)->toBe(500)
        ->and($invB->fresh()->quantity)->toBe(0);
});

test('bulk endpoint rejects an empty selection', function () {
    $this->postJson(route('inventory.bulk.clear-overrides'), [])
        ->assertStatus(422);
});

test('bulk endpoint rejects more than 1000 IDs at the validation layer', function () {
    $tooMany = range(1, 1001);

    $this->postJson(route('inventory.bulk.clear-overrides'), ['ids' => $tooMany])
        ->assertStatus(422);
});

test('bulk select-all uses filter signature and only mutates matching rows', function () {
    $product = Product::factory()->magic()->create();
    $setA = Set::factory()->forProduct($product)->create(['name' => 'Alpha']);
    $setB = Set::factory()->forProduct($product)->create(['name' => 'Beta']);

    $cardA = Card::factory()->state(['set_id' => $setA->id])->nearMint()->create([
        'product_name' => 'A', 'number' => '1',
    ]);
    $cardB = Card::factory()->state(['set_id' => $setB->id])->nearMint()->create([
        'product_name' => 'B', 'number' => '2',
    ]);

    $invA = Inventory::factory()->state(['card_id' => $cardA->id])->withOverride(500)->create();
    $invB = Inventory::factory()->state(['card_id' => $cardB->id])->withOverride(700)->create();

    // Filter to set A only.
    $this->postJson(route('inventory.bulk.clear-overrides'), [
        'select_all' => true,
        'product' => (string) $product->id,
        'sets' => (string) $setA->id,
        'conditions' => 'Near Mint',
    ])->assertOk()->assertJsonPath('updated', 1);

    expect($invA->fresh()->override_price)->toBeNull()
        ->and($invB->fresh()->override_price)->toBe(700);
});

test('bulk select-all without complete required filters is rejected', function () {
    $this->postJson(route('inventory.bulk.clear-overrides'), [
        'select_all' => true,
    ])->assertStatus(422);
});
