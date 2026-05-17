<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('PATCH updates quantity for a single inventory row', function () {
    $product = Product::factory()->magic()->create();
    $set = CardSet::factory()->forProduct($product)->create();
    $card = Card::factory()->state(['set_id' => $set->id])->nearMint()->create();

    $inv = Inventory::factory()->state(['card_id' => $card->id, 'quantity' => 5])->create();

    $this->patchJson(route('inventory.update', $inv), ['quantity' => 7])
        ->assertOk()
        ->assertJsonPath('inventory.id', $inv->id)
        ->assertJsonPath('inventory.quantity', 7);

    expect($inv->fresh()->quantity)->toBe(7);
});

test('PATCH with empty override_price clears it to null', function () {
    $product = Product::factory()->magic()->create();
    $set = CardSet::factory()->forProduct($product)->create();
    $card = Card::factory()->state(['set_id' => $set->id])->nearMint()->create();

    $inv = Inventory::factory()
        ->state(['card_id' => $card->id])
        ->withOverride(500)
        ->create();

    $this->patchJson(route('inventory.update', $inv), ['override_price' => null])
        ->assertOk()
        ->assertJsonPath('inventory.override_price', null);

    expect($inv->fresh()->override_price)->toBeNull();
});

test('PATCH sets override_price to a numeric value', function () {
    $product = Product::factory()->magic()->create();
    $set = CardSet::factory()->forProduct($product)->create();
    $card = Card::factory()->state(['set_id' => $set->id])->nearMint()->create();

    $inv = Inventory::factory()->state(['card_id' => $card->id])->create();

    $this->patchJson(route('inventory.update', $inv), ['override_price' => 1234])
        ->assertOk()
        ->assertJsonPath('inventory.override_price', 1234);

    expect($inv->fresh()->override_price)->toBe(1234);
});

test('PATCH rejects negative quantity', function () {
    $product = Product::factory()->magic()->create();
    $set = CardSet::factory()->forProduct($product)->create();
    $card = Card::factory()->state(['set_id' => $set->id])->nearMint()->create();

    $inv = Inventory::factory()->state(['card_id' => $card->id])->create();

    $this->patchJson(route('inventory.update', $inv), ['quantity' => -1])
        ->assertUnprocessable();
});

test('DELETE soft-removes the row (qty=0, override=null) without hard-deleting', function () {
    $product = Product::factory()->magic()->create();
    $set = CardSet::factory()->forProduct($product)->create();
    $card = Card::factory()->state(['set_id' => $set->id])->nearMint()->create();

    $inv = Inventory::factory()
        ->state(['card_id' => $card->id, 'quantity' => 5])
        ->withOverride(500)
        ->create();

    $this->deleteJson(route('inventory.destroy', $inv))
        ->assertOk()
        ->assertJsonPath('inventory.quantity', 0)
        ->assertJsonPath('inventory.override_price', null);

    $fresh = Inventory::find($inv->id);

    expect($fresh)->not->toBeNull()
        ->and($fresh->quantity)->toBe(0)
        ->and($fresh->override_price)->toBeNull();
});

test('PATCH does not require both quantity and override_price', function () {
    $product = Product::factory()->magic()->create();
    $set = CardSet::factory()->forProduct($product)->create();
    $card = Card::factory()->state(['set_id' => $set->id])->nearMint()->create();

    $inv = Inventory::factory()
        ->state(['card_id' => $card->id, 'quantity' => 3])
        ->withOverride(500)
        ->create();

    // Sending only override_price preserves quantity.
    $this->patchJson(route('inventory.update', $inv), ['override_price' => 999])
        ->assertOk();

    $fresh = $inv->fresh();
    expect($fresh->quantity)->toBe(3);
    expect($fresh->override_price)->toBe(999);
});
