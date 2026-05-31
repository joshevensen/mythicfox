<?php

use App\Models\Card;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Set;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('unauthenticated visit redirects to login', function () {
    auth()->logout();

    $this->get(route('add-cards'))->assertRedirect(route('login'));
});

test('authenticated visit returns 200 and renders the placeholder when no scope is selected', function () {
    $this->get(route('add-cards'))->assertOk()->assertInertia(
        fn ($page) => $page
            ->component('AddCards')
            ->where('scope.product_id', null)
            ->where('scope.set_id', null)
            ->where('scope.condition', null)
            ->has('products')
            ->has('cards', 0)
    );
});

test('scoped query returns the alphabetical card list for a (set, condition) pair', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();
    Card::factory()->state(['set_id' => $set->id])->nearMint()->create([
        'product_name' => 'Beta Card',
        'number' => '002',
    ]);
    Card::factory()->state(['set_id' => $set->id])->nearMint()->create([
        'product_name' => 'Alpha Card',
        'number' => '001',
    ]);
    // Different condition — should be excluded.
    Card::factory()->state(['set_id' => $set->id])->nearMintFoil()->create([
        'product_name' => 'Foil Card',
    ]);

    $this->get(route('add-cards', [
        'product_id' => $product->id,
        'set_id' => $set->id,
        'condition' => 'Near Mint',
    ]))->assertInertia(
        fn ($page) => $page
            ->has('cards', 2)
            ->where('cards.0.name', 'Alpha Card')
            ->where('cards.1.name', 'Beta Card')
    );
});

test('save endpoint additively increments existing inventory rows', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();
    $cardWithStock = Card::factory()->state(['set_id' => $set->id])->nearMint()->create();
    Inventory::factory()->create([
        'card_id' => $cardWithStock->id,
        'quantity' => 5,
    ]);
    $cardNew = Card::factory()->state(['set_id' => $set->id])->nearMint()->create();

    $this->post(route('add-cards.store'), [
        'product_id' => $product->id,
        'set_id' => $set->id,
        'condition' => 'Near Mint',
        'entries' => [
            ['card_id' => $cardWithStock->id, 'qty' => 3],
            ['card_id' => $cardNew->id, 'qty' => 2],
            ['card_id' => Card::factory()->state(['set_id' => $set->id])->nearMint()->create()->id, 'qty' => 0],
        ],
    ])->assertRedirect();

    expect(Inventory::where('card_id', $cardWithStock->id)->value('quantity'))->toBe(8);
    expect(Inventory::where('card_id', $cardNew->id)->value('quantity'))->toBe(2);
});

test('save endpoint rejects negative qty values with 422', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();
    $card = Card::factory()->state(['set_id' => $set->id])->nearMint()->create();

    $this->post(route('add-cards.store'), [
        'product_id' => $product->id,
        'set_id' => $set->id,
        'condition' => 'Near Mint',
        'entries' => [
            ['card_id' => $card->id, 'qty' => -2],
        ],
    ])->assertSessionHasErrors('entries.0.qty');
});

test('save response flash includes the total count saved (used by the success toast)', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();
    $card1 = Card::factory()->state(['set_id' => $set->id])->nearMint()->create();
    $card2 = Card::factory()->state(['set_id' => $set->id])->nearMint()->create();

    $response = $this->post(route('add-cards.store'), [
        'product_id' => $product->id,
        'set_id' => $set->id,
        'condition' => 'Near Mint',
        'entries' => [
            ['card_id' => $card1->id, 'qty' => 3],
            ['card_id' => $card2->id, 'qty' => 4],
        ],
    ]);

    $response->assertSessionHas('toast', fn (array $toast) => ($toast['count'] ?? null) === 7);
});

test('zero-qty entries are skipped silently and create no inventory rows', function () {
    $product = Product::factory()->magic()->create();
    $set = Set::factory()->forProduct($product)->create();
    $card = Card::factory()->state(['set_id' => $set->id])->nearMint()->create();

    $this->post(route('add-cards.store'), [
        'product_id' => $product->id,
        'set_id' => $set->id,
        'condition' => 'Near Mint',
        'entries' => [
            ['card_id' => $card->id, 'qty' => 0],
        ],
    ])->assertRedirect();

    expect(Inventory::where('card_id', $card->id)->exists())->toBeFalse();
});
