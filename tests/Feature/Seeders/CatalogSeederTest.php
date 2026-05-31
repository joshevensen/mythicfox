<?php

use App\Models\Card;
use App\Models\Printing;
use App\Models\Product;
use App\Models\Set;
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
    expect(Set::count())->toBe(6);
    expect(Card::count())->toBe(120);
    expect(Printing::count())->toBe(120);

    $orphanCards = Card::whereNotIn('set_id', Set::pluck('id'))->count();
    expect($orphanCards)->toBe(0);

    $orphanPrintings = Printing::whereNotIn('card_id', Card::pluck('id'))->count();
    expect($orphanPrintings)->toBe(0);
});

test('DemoCatalogSeeder produces game-specific rarity vocabulary', function () {
    $this->seed(DemoCatalogSeeder::class);

    $magicSet = Set::whereHas('product', fn ($q) => $q->where('name', 'Magic'))->firstOrFail();
    $magicRarities = Card::where('set_id', $magicSet->id)->pluck('rarity')->unique();
    expect($magicRarities)->toContain('R');

    $fabSet = Set::whereHas('product', fn ($q) => $q->where('name', 'Flesh & Blood TCG'))->firstOrFail();
    $fabRarities = Card::where('set_id', $fabSet->id)->pluck('rarity')->unique();
    expect($fabRarities)->toContain('Majestic');
});

test('DemoCatalogSeeder produces finish and pricing edge cases', function () {
    $this->seed(DemoCatalogSeeder::class);

    expect(Printing::where('finish', 'foil')->exists())->toBeTrue();
    expect(Printing::where('finish', 'non-foil')->exists())->toBeTrue();
    expect(Printing::whereNull('market_price')->exists())->toBeTrue();
    expect(Printing::whereNull('low_price')->exists())->toBeTrue();
});

test('factory states are wired up', function () {
    $magic = Product::factory()->magic()->create();
    expect($magic->name)->toBe('Magic');

    $lorcana = Product::factory()->lorcana()->create();
    expect($lorcana->name)->toBe('Lorcana TCG');

    $fab = Product::factory()->fleshAndBlood()->create();
    expect($fab->name)->toBe('Flesh & Blood TCG');

    $set = Set::factory()->forProduct($magic)->create();
    expect($set->product_id)->toBe($magic->id);

    $foil = Printing::factory()->foil()->create();
    expect($foil->finish)->toBe('foil');

    $custom = Printing::factory()->withPricing(1234, 999)->create();
    expect($custom->market_price)->toBe(1234);
    expect($custom->low_price)->toBe(999);
});
