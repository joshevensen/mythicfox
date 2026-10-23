<?php

/**
 * Serves the read-only catalog detail page for one card.
 *
 * @context Inertia controller rendering Catalog/Card for one card: its import data, image and printings.
 *
 * @gotchas
 * - The route uses scoped bindings through Product::cards() (HasManyThrough via sets), so a card from another product 404s before reaching this controller.
 * - The image is the first non-null printing image_url by finish priority (non-foil → foil → etched).
 * - Printings reuse BrowseCardsQuery::variantsFor so the shape matches the table row expand.
 *
 * @dependencies
 * - BrowseCardsQuery — printings and image lookup
 * - CatalogProductPayload — product label and pricing staleness
 */

namespace App\Http\Controllers\Catalog;

use App\Catalog\CatalogProductPayload;
use App\Catalog\Queries\BrowseCardsQuery;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class CatalogCardController extends Controller
{
    public function __construct(private readonly BrowseCardsQuery $browse) {}

    public function show(Product $product, Card $card): Response
    {
        $set = $card->set;

        return Inertia::render('Catalog/Card', [
            'product' => CatalogProductPayload::for($product),
            'set' => ['id' => (int) $set->id, 'name' => (string) $set->name],
            'card' => [
                'id' => (int) $card->id,
                'name' => (string) $card->name,
                'number' => (string) $card->number,
                'rarity' => (string) $card->rarity,
                'image_url' => $this->browse->imageUrlFor((int) $card->id),
            ],
            'heading' => $product->display_label.' › '.$set->name.' › '.$card->name,
            'printings' => $this->browse->variantsFor((int) $card->id),
        ]);
    }
}
