<?php

namespace App\Services\Catalog\Sync;

use App\Models\Product;
use App\Models\Set;

interface CatalogSyncSource
{
    public function syncSets(Product $product): int;

    public function syncCardsForSet(Set $set): int;
}
