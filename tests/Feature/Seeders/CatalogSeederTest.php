<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\Inventory;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoCatalogSeeder;

test('CatalogSeeder seeds the three canonical products', function () {
    $this->seed(CatalogSeeder::class);

    $names = Product::pluck('name')->all();
    expect($names)->toContain('Magic');
    expect($names)->toContain('Lorcana TCG');
    expect($names)->toContain('Flesh & Blood TCG');
    expect(Product::count())->toBe(3);
});

test('CatalogSeeder is idempotent', function () {
    $this->seed(CatalogSeeder::class);
    $this->seed(CatalogSeeder::class);

    expect(Product::count())->toBe(3);
});

test('DemoCatalogSeeder produces expected row counts and resolves all FKs', function () {
    $this->seed(DemoCatalogSeeder::class);

    expect(Product::count())->toBe(3);
    expect(CardSet::count())->toBe(6);
    expect(Card::count())->toBe(120); // 3 products × 2 sets × 20 cards
    expect(Inventory::count())->toBe(30); // ~10 per product

    $orphanCards = Card::whereNotIn('set_id', CardSet::pluck('id'))->count();
    expect($orphanCards)->toBe(0);

    $orphanInventory = Inventory::whereNotIn('card_id', Card::pluck('id'))->count();
    expect($orphanInventory)->toBe(0);
});

test('DemoCatalogSeeder produces game-specific rarity vocabulary', function () {
    $this->seed(DemoCatalogSeeder::class);

    $magicSet = CardSet::whereHas('product', fn ($q) => $q->where('name', 'Magic'))->firstOrFail();
    $magicRarities = Card::where('set_id', $magicSet->id)->pluck('rarity')->unique();
    expect($magicRarities)->toContain('R');

    $fabSet = CardSet::whereHas('product', fn ($q) => $q->where('name', 'Flesh & Blood TCG'))->firstOrFail();
    $fabRarities = Card::where('set_id', $fabSet->id)->pluck('rarity')->unique();
    expect($fabRarities)->toContain('Majestic');
});

test('DemoCatalogSeeder produces edge-case inventory rows', function () {
    $this->seed(DemoCatalogSeeder::class);

    expect(Inventory::where('quantity', 0)->exists())->toBeTrue();
    expect(Inventory::whereNotNull('override_price')->exists())->toBeTrue();
    expect(Inventory::whereNull('calculated_price')->exists())->toBeTrue();
});

test('factory states are wired up', function () {
    $magic = Product::factory()->magic()->create();
    expect($magic->name)->toBe('Magic');

    $lorcana = Product::factory()->lorcana()->create();
    expect($lorcana->name)->toBe('Lorcana TCG');

    $fab = Product::factory()->fleshAndBlood()->create();
    expect($fab->name)->toBe('Flesh & Blood TCG');

    $set = CardSet::factory()->forProduct($magic)->create();
    expect($set->product_id)->toBe($magic->id);

    $foil = Card::factory()->nearMintFoil()->create();
    expect($foil->condition)->toBe('Near Mint Foil');

    $custom = Card::factory()->withMarketAndLow(1234, 999)->create();
    expect($custom->market_price)->toBe(1234);
    expect($custom->low_price)->toBe(999);

    $override = Inventory::factory()->withOverride(500)->create();
    expect($override->override_price)->toBe(500);

    $calculated = Inventory::factory()->withCalculated(750)->create();
    expect($calculated->calculated_price)->toBe(750);

    $exported = Inventory::factory()->lastExported(800)->create();
    expect($exported->last_exported_price)->toBe(800);
});
