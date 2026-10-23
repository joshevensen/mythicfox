<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Set;
use Inertia\Inertia;
use Inertia\Response;

class ProductsController extends Controller
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
                'display_name' => $product->display_name,
                'display_label' => $product->display_label,
                'base_price' => $product->base_price,
                'high_price' => $product->high_price,
                'market_offset' => $product->market_offset,
                'high_offset' => $product->high_offset,
                'sets' => $product->sets->map(fn (Set $set) => [
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

        return Inertia::render('settings/Products', [
            'products' => $products,
        ]);
    }
}
