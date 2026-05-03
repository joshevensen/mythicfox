<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateProductPricingRulesRequest;
use App\Http\Requests\Settings\UpdateSetPricingRulesRequest;
use App\Models\CardSet;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PricingRulesController extends Controller
{
    public function index(): Response
    {
        $products = Product::query()
            ->with(['sets' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'base_price' => $product->base_price,
                'high_price' => $product->high_price,
                'market_offset' => $product->market_offset,
                'high_offset' => $product->high_offset,
                'sets' => $product->sets->map(fn (CardSet $set) => [
                    'id' => $set->id,
                    'name' => $set->name,
                    'base_price' => $set->base_price,
                    'high_price' => $set->high_price,
                    'market_offset' => $set->market_offset,
                    'high_offset' => $set->high_offset,
                    'overridden' => $set->base_price !== null
                        || $set->high_price !== null
                        || $set->market_offset !== null
                        || $set->high_offset !== null,
                ])->all(),
                'sets_count' => $product->sets->count(),
            ])->all();

        return Inertia::render('Settings', [
            'products' => $products,
        ]);
    }

    public function updateProduct(UpdateProductPricingRulesRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return back()->with('toast', [
            'kind' => 'success',
            'message' => "{$product->name} pricing rules saved.",
        ]);
    }

    public function updateSet(UpdateSetPricingRulesRequest $request, CardSet $set): RedirectResponse
    {
        $set->update($request->validated());

        return back()->with('toast', [
            'kind' => 'success',
            'message' => "{$set->name} pricing rules saved.",
        ]);
    }
}
