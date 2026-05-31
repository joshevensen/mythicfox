<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCardsRequest;
use App\Models\Card;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AddCardsController extends Controller
{
    /**
     * The 11 TCGPlayer condition strings observed across imports.
     * Source of truth: docs/catalog-schema.md §Condition vocabulary.
     */
    public const CONDITIONS = [
        'Near Mint',
        'Lightly Played',
        'Moderately Played',
        'Damaged',
        'Near Mint Foil',
        'Lightly Played Foil',
        'Near Mint Holofoil',
        'Near Mint Cold Foil',
        'Lightly Played Cold Foil',
        'Near Mint Unlimited Edition Normal',
        'Near Mint Unlimited Edition Rainbow Foil',
    ];

    public function show(Request $request): Response
    {
        $products = Product::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();

        $productId = $request->query('product_id');
        $setId = $request->query('set_id');
        $condition = $request->query('condition');

        $sets = $productId
            ? Set::query()
                ->where('product_id', $productId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all()
            : [];

        $cards = ($productId && $setId && $condition)
            ? Card::query()
                ->where('set_id', $setId)
                ->where('condition', $condition)
                ->orderBy('product_name')
                ->get(['id', 'product_name', 'number'])
                ->map(fn (Card $c) => [
                    'id' => $c->id,
                    'name' => $c->product_name,
                    'number' => $c->number,
                ])
                ->all()
            : [];

        return Inertia::render('AddCards', [
            'products' => $products,
            'sets' => $sets,
            'cards' => $cards,
            'conditions' => self::CONDITIONS,
            'scope' => [
                'product_id' => $productId !== null ? (int) $productId : null,
                'set_id' => $setId !== null ? (int) $setId : null,
                'condition' => $condition,
            ],
        ]);
    }

    public function store(AddCardsRequest $request): RedirectResponse
    {
        $entries = collect($request->validated('entries'))
            ->filter(fn (array $entry) => (int) $entry['qty'] > 0)
            ->values();

        $totalAdded = 0;

        DB::transaction(function () use ($entries, &$totalAdded) {
            foreach ($entries as $entry) {
                $cardId = (int) $entry['card_id'];
                $qty = (int) $entry['qty'];

                $inventory = Inventory::firstOrCreate(
                    ['card_id' => $cardId],
                    ['quantity' => 0],
                );

                $inventory->forceFill([
                    'quantity' => $inventory->quantity + $qty,
                ])->save();

                $totalAdded += $qty;
            }
        });

        $set = Set::query()->find($request->validated('set_id'));
        $condition = $request->validated('condition');

        return back()->with('toast', [
            'kind' => 'success',
            'message' => "Added {$totalAdded} cards to {$set?->name} ({$condition}).",
            'count' => $totalAdded,
        ]);
    }
}
