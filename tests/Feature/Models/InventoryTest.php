<?php

use App\Models\Card;
use App\Models\Inventory;
use Illuminate\Database\QueryException;

test('factory creates a row attached to a card with null pricing fields', function () {
    $inventory = Inventory::factory()->create();

    expect($inventory->card)->toBeInstanceOf(Card::class);
    expect($inventory->quantity)->toBeGreaterThan(0);
    expect($inventory->calculated_price)->toBeNull();
    expect($inventory->override_price)->toBeNull();
    expect($inventory->last_exported_price)->toBeNull();
});

test('card_id uniqueness is enforced', function () {
    $card = Card::factory()->create();
    Inventory::factory()->create(['card_id' => $card->id]);

    expect(fn () => Inventory::factory()->create(['card_id' => $card->id]))
        ->toThrow(QueryException::class);
});

test('quantity cannot be negative', function () {
    expect(fn () => Inventory::factory()->create(['quantity' => -1]))
        ->toThrow(QueryException::class);
});

test('effective_price returns override_price when set', function () {
    $inventory = Inventory::factory()->create([
        'calculated_price' => 100,
        'override_price' => 250,
    ]);

    expect($inventory->effective_price)->toBe(250);
});

test('effective_price returns calculated_price when override is null', function () {
    $inventory = Inventory::factory()->create([
        'calculated_price' => 100,
        'override_price' => null,
    ]);

    expect($inventory->effective_price)->toBe(100);
});

test('effective_price returns null when both are null', function () {
    $inventory = Inventory::factory()->create([
        'calculated_price' => null,
        'override_price' => null,
    ]);

    expect($inventory->effective_price)->toBeNull();
});

test('card hasOne inventory relation resolves', function () {
    $card = Card::factory()->create();
    $inventory = Inventory::factory()->create(['card_id' => $card->id]);

    expect($card->inventory->id)->toBe($inventory->id);
});
