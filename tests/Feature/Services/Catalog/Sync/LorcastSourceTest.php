<?php

use App\Models\Card;
use App\Models\Printing;
use App\Models\Product;
use App\Models\Set;
use App\Services\Catalog\Sync\LorcastSource;
use Illuminate\Support\Facades\Http;

function lorcastSets(): array
{
    return ['results' => [['id' => 'TFC', 'name' => 'The First Chapter']]];
}

function lorcastCards(): array
{
    return [
        'results' => [
            [
                'id' => 'lorcast-001',
                'name' => 'Elsa - Spirit of Winter',
                'collector_number' => '001',
                'rarity' => 'Legendary',
                'foil' => true,
                'non_foil' => true,
                'image_uris' => [
                    'digital' => ['normal' => 'https://api.lorcast.com/images/001.jpg'],
                ],
            ],
        ],
    ];
}

function fakeLorcast(): void
{
    Http::fake([
        '*api.lorcast.com/v0/sets/*/cards*' => Http::response(lorcastCards()),
        '*api.lorcast.com/v0/sets' => Http::response(lorcastSets()),
    ]);
}

test('syncSets upserts lorcana sets', function () {
    fakeLorcast();
    $product = Product::factory()->create(['name' => 'Lorcana TCG']);

    $count = (new LorcastSource)->syncSets($product);

    expect($count)->toBe(1);
    expect(Set::where('name', 'The First Chapter')->where('product_id', $product->id)->exists())->toBeTrue();
});

test('syncSets is idempotent', function () {
    fakeLorcast();
    $product = Product::factory()->create(['name' => 'Lorcana TCG']);
    $source = new LorcastSource;

    $source->syncSets($product);
    $source->syncSets($product);

    expect(Set::where('product_id', $product->id)->count())->toBe(1);
});

test('syncCardsForSet maps card name, number, rarity, and finishes', function () {
    fakeLorcast();
    $product = Product::factory()->create(['name' => 'Lorcana TCG']);
    $set = Set::factory()->create(['product_id' => $product->id, 'name' => 'The First Chapter']);

    (new LorcastSource)->syncCardsForSet($set);

    $card = Card::where('name', 'Elsa - Spirit of Winter')->firstOrFail();
    expect($card->number)->toBe('001');
    expect($card->rarity)->toBe('Legendary');

    expect(Printing::where('card_id', $card->id)->count())->toBe(2);

    $foil = Printing::where('card_id', $card->id)->where('finish', 'foil')->first();
    expect($foil)->not->toBeNull();
    expect($foil->image_url)->toBe('https://api.lorcast.com/images/001.jpg');

    $nonFoil = Printing::where('card_id', $card->id)->where('finish', 'non-foil')->first();
    expect($nonFoil)->not->toBeNull();
});

test('lorcast_id is stored in other_ids', function () {
    fakeLorcast();
    $product = Product::factory()->create(['name' => 'Lorcana TCG']);
    $set = Set::factory()->create(['product_id' => $product->id, 'name' => 'The First Chapter']);

    (new LorcastSource)->syncCardsForSet($set);

    $printing = Printing::whereHas('card', fn ($q) => $q->where('name', 'Elsa - Spirit of Winter'))->first();
    expect($printing->other_ids)->toBe(['lorcast_id' => 'lorcast-001']);
});
