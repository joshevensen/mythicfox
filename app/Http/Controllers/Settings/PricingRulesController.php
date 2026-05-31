<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateProductPricingRulesRequest;
use App\Http\Requests\Settings\UpdateSetPricingRulesRequest;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Http\RedirectResponse;

class PricingRulesController extends Controller
{
    public function updateProduct(UpdateProductPricingRulesRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return back()->with('toast', [
            'kind' => 'success',
            'message' => "{$product->name} pricing rules saved.",
        ]);
    }

    public function updateSet(UpdateSetPricingRulesRequest $request, Set $set): RedirectResponse
    {
        $set->update($request->validated());

        return back()->with('toast', [
            'kind' => 'success',
            'message' => "{$set->name} pricing rules saved.",
        ]);
    }
}
