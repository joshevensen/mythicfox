<?php

use App\Catalog\Queries\SearchPrintingsQuery;
use App\Models\Card;
use App\Models\Printing;
use App\Models\Product;
use App\Models\Set;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * @param  list<string>  $finishes
 */
function searchCard(string $name, ?Set $set = null, string $number = '1', array $finishes = ['non-foil']): Card
{
    $card = Card::factory()->create([
        'set_id' => ($set ?? Set::factory()->create())->id,
        'name' => $name,
        'number' => $number,
    ]);

    foreach ($finishes as $finish) {
        Printing::factory()->create(['card_id' => $card->id, 'finish' => $finish]);
    }

    return $card;
}

/**
 * @return list<string>
 */
function searchNames(string $term): array
{
    return collect(test()->getJson(route('catalog.search', ['q' => $term]))->assertOk()->json('data'))
        ->pluck('name')
        ->all();
}

test('guests cannot search', function () {
    auth()->logout();

    $this->getJson(route('catalog.search', ['q' => 'bolt']))->assertUnauthorized();
    $this->get(route('catalog.search', ['q' => 'bolt']))->assertRedirect(route('login'));
});

test('q is required and must be 2 to 100 characters', function (?string $q) {
    $this->getJson(route('catalog.search', $q === null ? [] : ['q' => $q]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
})->with([
    'missing' => [null],
    'one character' => ['a'],
    'padded one character' => [' a '],
    'too long' => [str_repeat('a', 101)],
]);

test('search path does not collide with the product route', function () {
    $this->getJson('/catalog/search?q=bo')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('matches card names case-insensitively by substring', function () {
    searchCard('Lightning Bolt');
    searchCard('Boltwing');
    searchCard('Ancestral Recall');

    expect(searchNames('BOLT'))->toEqualCanonicalizing(['Boltwing', 'Lightning Bolt']);
});

test('names starting with the query rank first, then alphabetically', function () {
    searchCard('Lightning Bolt');
    searchCard('Boltwing');
    searchCard('Bolt Hound');

    expect(searchNames('bolt'))->toBe(['Bolt Hound', 'Boltwing', 'Lightning Bolt']);
});

test('returns one row per printing ordered by set, number and finish', function () {
    $product = Product::factory()->magic()->create();
    $alpha = Set::factory()->forProduct($product)->create(['name' => 'Alpha']);
    $beta = Set::factory()->forProduct($product)->create(['name' => 'Beta']);

    searchCard('Sol Ring', $beta, '1', ['etched', 'foil', 'non-foil']);
    searchCard('Sol Ring', $alpha, '1', ['non-foil']);

    $rows = $this->getJson(route('catalog.search', ['q' => 'sol ring']))->assertOk()->json('data');

    expect(collect($rows)->map(fn ($r) => [$r['set_name'], $r['finish']])->all())->toBe([
        ['Alpha', 'non-foil'],
        ['Beta', 'non-foil'],
        ['Beta', 'foil'],
        ['Beta', 'etched'],
    ]);
});

test('searches across all products', function () {
    $magic = Set::factory()->forProduct(Product::factory()->magic()->create())->create();
    $lorcana = Set::factory()->forProduct(Product::factory()->lorcana()->create())->create();

    searchCard('Mickey Flame', $magic);
    searchCard('Mickey Spark', $lorcana);

    expect(searchNames('mickey'))->toEqualCanonicalizing(['Mickey Flame', 'Mickey Spark']);
});

test('rows carry ids, display label, set, number and finish', function () {
    $product = Product::factory()->magic()->withDisplayName('MTG')->create();
    $set = Set::factory()->forProduct($product)->create(['name' => 'Alpha']);
    $card = searchCard('Boltyn', $set, '12', ['foil']);
    $printing = $card->printings()->firstOrFail();

    $rows = $this->getJson(route('catalog.search', ['q' => 'boltyn']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->json('data');

    expect($rows[0])->toHaveKeys([
        'printing_id', 'card_id', 'product_id', 'name', 'product_label', 'set_name', 'number', 'finish',
    ])->and(array_keys($rows[0]))->toHaveCount(8)
        ->and($rows[0]['printing_id'])->toBe($printing->id)
        ->and($rows[0]['card_id'])->toBe($card->id)
        ->and($rows[0]['product_id'])->toBe($product->id)
        ->and($rows[0]['name'])->toBe('Boltyn')
        ->and($rows[0]['product_label'])->toBe('MTG')
        ->and($rows[0]['set_name'])->toBe('Alpha')
        ->and($rows[0]['number'])->toBe('12')
        ->and($rows[0]['finish'])->toBe('foil');
});

test('product label falls back to the product name without a display name', function () {
    $product = Product::factory()->magic()->create(['display_name' => null]);
    searchCard('Fallback Card', Set::factory()->forProduct($product)->create());

    $rows = $this->getJson(route('catalog.search', ['q' => 'fallback']))->assertOk()->json('data');

    expect($rows[0]['product_label'])->toBe($product->name);
});

test('escapes LIKE wildcards in the query', function () {
    searchCard('100% Pure');
    searchCard('1000 Pure');
    searchCard('a_b');
    searchCard('axb');
    searchCard('back\\slash');
    searchCard('backslash');

    expect(searchNames('0%'))->toBe(['100% Pure'])
        ->and(searchNames('a_b'))->toBe(['a_b'])
        ->and(searchNames('k\\s'))->toBe(['back\\slash']);
});

test('caps results', function () {
    $set = Set::factory()->create();

    foreach (range(1, 25) as $i) {
        searchCard('Capped Card '.$i, $set, (string) $i);
    }

    $this->getJson(route('catalog.search', ['q' => 'capped']))
        ->assertOk()
        ->assertJsonCount(SearchPrintingsQuery::LIMIT, 'data');
});

test('card search component implements the combobox pattern', function () {
    $source = file_get_contents(resource_path('js/components/catalog/CatalogCardSearch.vue'));

    expect($source)
        ->toContain('role="combobox"')
        ->toContain('aria-expanded')
        ->toContain('aria-activedescendant')
        ->toContain('role="listbox"')
        ->toContain('role="option"')
        ->toContain('ArrowDown')
        ->toContain('ArrowUp')
        ->toContain('Enter')
        ->toContain('Escape')
        ->toContain('onClickOutside')
        ->toContain('router.visit')
        ->toContain('@/routes/catalog/products/cards')
        ->toContain('@/routes/catalog')
        ->toContain('No cards found')
        ->toContain('data-test="card-search-input"')
        ->toContain('data-test="card-search-option"')
        ->toContain('useCardSearch')
        ->not->toContain("'/catalog/");
});
