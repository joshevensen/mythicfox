<?php

use App\Models\Card;
use App\Models\Printing;
use App\Models\Set;
use Illuminate\Database\QueryException;

test('factory creates a card attached to a set', function () {
    $card = Card::factory()->create();

    expect($card->set)->toBeInstanceOf(Set::class);
    expect($card->name)->toBeString();
});

test('canonical card uniqueness is enforced per set name and number', function () {
    $set = Set::factory()->create();
    Card::factory()->create(['set_id' => $set->id, 'name' => 'Black Lotus', 'number' => '1']);

    expect(fn () => Card::factory()->create(['set_id' => $set->id, 'name' => 'Black Lotus', 'number' => '1']))
        ->toThrow(QueryException::class);
});

test('set hasMany cards relation resolves', function () {
    $set = Set::factory()->create();
    Card::factory()->count(3)->create(['set_id' => $set->id]);

    expect($set->cards()->count())->toBe(3);
});

test('card hasMany printings relation resolves', function () {
    $card = Card::factory()->create();
    Printing::factory()->create(['card_id' => $card->id, 'finish' => 'non-foil']);
    Printing::factory()->foil()->create(['card_id' => $card->id]);

    expect($card->printings()->count())->toBe(2);
});
