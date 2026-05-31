<?php

use App\Models\Card;
use App\Models\Printing;
use App\Models\Product;
use App\Models\Set;
use App\Services\Catalog\Sync\FleshAndBloodSource;
use Illuminate\Support\Facades\Http;

function fabSets(): array
{
    return [['id' => 'WTR', 'name' => 'Welcome to Rathe']];
}

function fabWtrCards(): array
{
    return [
        [
            'unique_id' => 'card-uuid-1',
            'name' => 'Rhinar, Reckless Rampage',
            'printings' => [
                [
                    'unique_id' => 'printing-uuid-1',
                    'id' => 'WTR001',
                    'set_id' => 'WTR',
                    'rarity' => 'M',
                    'foiling' => 'S',
                    'image_url' => 'https://storage.fab.io/WTR001.png',
                    'tcgplayer_product_id' => '551001',
                ],
                [
                    'unique_id' => 'printing-uuid-2',
                    'id' => 'WTR001',
                    'set_id' => 'WTR',
                    'rarity' => 'M',
                    'foiling' => 'R',
                    'image_url' => 'https://storage.fab.io/WTR001-R.png',
                    'tcgplayer_product_id' => '',
                ],
            ],
        ],
    ];
}

function fakeFab(array $cards = [], ?array $sets = null): void
{
    Http::fake([
        '*sets.json*' => Http::response($sets ?? fabSets()),
        '*WTR.json*' => Http::response($cards !== [] ? $cards : fabWtrCards()),
    ]);
}

test('syncSets upserts fab sets', function () {
    fakeFab();
    $product = Product::factory()->create(['name' => 'Flesh & Blood TCG']);

    $count = (new FleshAndBloodSource)->syncSets($product);

    expect($count)->toBe(1);
    expect(Set::where('name', 'Welcome to Rathe')->where('product_id', $product->id)->exists())->toBeTrue();
});

test('syncSets is idempotent', function () {
    fakeFab();
    $product = Product::factory()->create(['name' => 'Flesh & Blood TCG']);
    $source = new FleshAndBloodSource;

    $source->syncSets($product);
    $source->syncSets($product);

    expect(Set::where('product_id', $product->id)->count())->toBe(1);
});

test('syncCardsForSet maps card name, number, rarity, and foiling to finish', function () {
    fakeFab();
    $product = Product::factory()->create(['name' => 'Flesh & Blood TCG']);
    $set = Set::factory()->create(['product_id' => $product->id, 'name' => 'Welcome to Rathe']);

    (new FleshAndBloodSource)->syncCardsForSet($set);

    $card = Card::where('name', 'Rhinar, Reckless Rampage')->firstOrFail();
    expect($card->number)->toBe('WTR001');
    expect($card->rarity)->toBe('M');

    expect(Printing::where('card_id', $card->id)->count())->toBe(2);

    $standard = Printing::where('card_id', $card->id)->where('finish', 'non-foil')->first();
    expect($standard)->not->toBeNull();
    expect($standard->tcgplayer_id)->toBe(551001);

    $rainbow = Printing::where('card_id', $card->id)->where('finish', 'rainbow-foil')->first();
    expect($rainbow)->not->toBeNull();
    expect($rainbow->tcgplayer_id)->toBeNull();
});

test('fab printing unique_id is stored in other_ids', function () {
    fakeFab();
    $product = Product::factory()->create(['name' => 'Flesh & Blood TCG']);
    $set = Set::factory()->create(['product_id' => $product->id, 'name' => 'Welcome to Rathe']);

    (new FleshAndBloodSource)->syncCardsForSet($set);

    $printing = Printing::where('finish', 'non-foil')
        ->whereHas('card', fn ($q) => $q->where('name', 'Rhinar, Reckless Rampage'))
        ->first();

    expect($printing->other_ids)->toBe(['fab_id' => 'printing-uuid-1']);
});

test('printings from other sets are skipped', function () {
    Http::fake([
        '*sets.json*' => Http::response([
            ['id' => 'WTR', 'name' => 'Welcome to Rathe'],
        ]),
        '*WTR.json*' => Http::response([
            [
                'name' => 'Reprint Card',
                'printings' => [
                    ['unique_id' => 'wtr-x', 'id' => 'WTR999', 'set_id' => 'WTR', 'rarity' => 'C', 'foiling' => 'S', 'image_url' => '', 'tcgplayer_product_id' => ''],
                    ['unique_id' => 'ele-y', 'id' => 'ELE999', 'set_id' => 'ELE', 'rarity' => 'C', 'foiling' => 'S', 'image_url' => '', 'tcgplayer_product_id' => ''],
                ],
            ],
        ]),
    ]);

    $product = Product::factory()->create(['name' => 'Flesh & Blood TCG']);
    $set = Set::factory()->create(['product_id' => $product->id, 'name' => 'Welcome to Rathe']);

    (new FleshAndBloodSource)->syncCardsForSet($set);

    expect(Card::where('set_id', $set->id)->count())->toBe(1);
    expect(Card::where('number', 'WTR999')->exists())->toBeTrue();
    expect(Card::where('number', 'ELE999')->exists())->toBeFalse();
});
