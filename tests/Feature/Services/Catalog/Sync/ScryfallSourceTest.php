<?php

use App\Models\Card;
use App\Models\Printing;
use App\Models\Product;
use App\Models\Set;
use App\Services\Catalog\Sync\ScryfallSource;
use Illuminate\Support\Facades\Http;

function scryfallSetsResponse(): array
{
    return [
        'data' => [
            [
                'code' => 'woe',
                'name' => 'Wilds of Eldraine',
                'set_type' => 'expansion',
            ],
        ],
    ];
}

function scryfallCardsResponse(bool $hasMore = false, ?string $nextPage = null): array
{
    return [
        'data' => [
            [
                'name' => 'Agatha\'s Soul Cauldron',
                'collector_number' => '234',
                'rarity' => 'mythic',
                'finishes' => ['nonfoil', 'foil'],
                'image_uris' => ['normal' => 'https://cards.scryfall.io/normal/woe/234.jpg'],
                'tcgplayer_id' => 512345,
            ],
        ],
        'has_more' => $hasMore,
        'next_page' => $nextPage,
    ];
}

function fakeScryfall(?array $sets = null, ?array $cards = null): void
{
    Http::fake([
        '*api.scryfall.com/sets*' => Http::response($sets ?? scryfallSetsResponse()),
        '*api.scryfall.com/cards/search*' => Http::response($cards ?? scryfallCardsResponse()),
    ]);
}

test('syncSets upserts sets for included set types', function () {
    fakeScryfall();
    $product = Product::factory()->create(['name' => 'Magic']);
    $source = new ScryfallSource;

    $count = $source->syncSets($product);

    expect($count)->toBe(1);
    expect(Set::where('name', 'Wilds of Eldraine')->where('product_id', $product->id)->exists())->toBeTrue();
});

test('syncSets is idempotent — re-running does not duplicate sets', function () {
    fakeScryfall();
    $product = Product::factory()->create(['name' => 'Magic']);
    $source = new ScryfallSource;

    $source->syncSets($product);
    $source->syncSets($product);

    expect(Set::where('product_id', $product->id)->count())->toBe(1);
});

test('syncSets skips non-expansion set types', function () {
    fakeScryfall(sets: [
        'data' => [
            ['code' => 'woe', 'name' => 'Wilds of Eldraine', 'set_type' => 'expansion'],
            ['code' => 'sld', 'name' => 'Secret Lair Drop', 'set_type' => 'secret_lair'],
        ],
    ]);

    $product = Product::factory()->create(['name' => 'Magic']);
    $source = new ScryfallSource;

    $count = $source->syncSets($product);

    expect($count)->toBe(1);
    expect(Set::where('name', 'Secret Lair Drop')->exists())->toBeFalse();
});

test('syncCardsForSet creates canonical cards and printings per finish', function () {
    fakeScryfall();
    $product = Product::factory()->create(['name' => 'Magic']);
    $set = Set::factory()->create(['product_id' => $product->id, 'name' => 'Wilds of Eldraine']);
    $source = new ScryfallSource;

    $source->syncCardsForSet($set);

    expect(Card::where('set_id', $set->id)->count())->toBe(1);

    $card = Card::where('name', "Agatha's Soul Cauldron")->firstOrFail();
    expect($card->number)->toBe('234');
    expect($card->rarity)->toBe('mythic');

    expect(Printing::where('card_id', $card->id)->count())->toBe(2);

    $nonFoil = Printing::where('card_id', $card->id)->where('finish', 'non-foil')->first();
    expect($nonFoil)->not->toBeNull();
    expect($nonFoil->image_url)->toBe('https://cards.scryfall.io/normal/woe/234.jpg');

    $foil = Printing::where('card_id', $card->id)->where('finish', 'foil')->first();
    expect($foil)->not->toBeNull();
});

test('syncCardsForSet is idempotent — re-running does not duplicate cards', function () {
    fakeScryfall();
    $product = Product::factory()->create(['name' => 'Magic']);
    $set = Set::factory()->create(['product_id' => $product->id, 'name' => 'Wilds of Eldraine']);
    $source = new ScryfallSource;

    $source->syncCardsForSet($set);
    $source->syncCardsForSet($set);

    expect(Card::where('set_id', $set->id)->count())->toBe(1);
    expect(Printing::whereHas('card', fn ($q) => $q->where('set_id', $set->id))->count())->toBe(2);
});

test('tcgplayer_id is null when card has multiple finishes', function () {
    fakeScryfall();
    $product = Product::factory()->create(['name' => 'Magic']);
    $set = Set::factory()->create(['product_id' => $product->id, 'name' => 'Wilds of Eldraine']);
    $source = new ScryfallSource;

    $source->syncCardsForSet($set);

    $printings = Printing::whereHas('card', fn ($q) => $q->where('name', "Agatha's Soul Cauldron"))->get();
    foreach ($printings as $printing) {
        expect($printing->tcgplayer_id)->toBeNull();
    }
});
