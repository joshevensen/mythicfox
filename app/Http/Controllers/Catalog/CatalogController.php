<?php

/**
 * Entry point for /catalog: sends the operator to the first product or shows the upload prompt.
 *
 * @context Inertia controller for the operator-facing catalog browser. The catalog is a drill-down
 *          (product, then sets or cards), so this route only picks a starting product.
 *
 * @gotchas
 * - "First" means first by products.name, not by display label, matching Settings and the top nav.
 * - With no products it renders Catalog/Empty, which prompts for a TCGPlayer PricingCustomExport upload.
 *
 * @dependencies
 * - CatalogProductController — the redirect target (catalog.products.show)
 */

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(): RedirectResponse|Response
    {
        $firstProductId = Product::query()->orderBy('name')->value('id');

        if ($firstProductId !== null) {
            return redirect()->route('catalog.products.show', $firstProductId);
        }

        return Inertia::render('Catalog/Empty');
    }
}
